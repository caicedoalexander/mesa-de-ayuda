<?php
declare(strict_types=1);

namespace App\Service;

use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use App\Utility\SettingsEncryptionTrait;

/**
 * Gmail Service
 *
 * Handles all Gmail API interactions including:
 * - OAuth2 authentication
 * - Fetching messages
 * - Parsing email content
 * - Downloading attachments
 * - Sending emails
 */
class GmailService
{
    use LocatorAwareTrait;
    use SettingsEncryptionTrait;

    private GoogleClient $client;
    private ?Gmail $service = null;
    private array $config;

    /**
     * Load Gmail configuration from database
     *
     * Centralized method to get Gmail config from system settings with automatic decryption.
     * Used by TicketService, ImportGmailCommand, and any other class needing Gmail access.
     *
     * @return array Configuration array with 'client_secret_path' and 'refresh_token'
     */
    public static function loadConfigFromDatabase(): array
    {
        // Create temporary instance to use traits
        $instance = new self([]);

        $settingsTable = $instance->fetchTable('SystemSettings');
        $settings = $settingsTable->find()
            ->where(['setting_key IN' => ['gmail_refresh_token', 'gmail_client_secret_path']])
            ->all();

        $config = [];
        foreach ($settings as $setting) {
            $key = str_replace('gmail_', '', $setting->setting_key);
            // Decrypt sensitive values using SettingsEncryptionTrait
            $config[$key] = $instance->shouldEncrypt($setting->setting_key)
                ? $instance->decryptSetting($setting->setting_value, $setting->setting_key)
                : $setting->setting_value;
        }

        return $config;
    }

    /**
     * Constructor
     *
     * @param array $config Configuration array with client_secret_path and refresh_token
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->initializeClient();
    }

    /**
     * Initialize Google Client with OAuth2
     *
     * @return void
     */
    private function initializeClient(): void
    {
        $this->client = new GoogleClient();

        // Load client secret from file
        $clientSecretPath = $this->config['client_secret_path'] ?? CONFIG . 'google' . DS . 'client_secret.json';

        if (file_exists($clientSecretPath)) {
            $this->client->setAuthConfig($clientSecretPath);
        } else {
            Log::error('Client secret file not found: ' . $clientSecretPath);
        }

        $this->client->addScope(Gmail::GMAIL_READONLY);
        $this->client->addScope(Gmail::GMAIL_SEND);
        $this->client->addScope(Gmail::GMAIL_MODIFY);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent'); // Force to always get refresh_token

        // Set redirect URI for OAuth2 flow
        if (!empty($this->config['redirect_uri'])) {
            $this->client->setRedirectUri($this->config['redirect_uri']);
        }

        // Set refresh token and fetch access token if available
        if (!empty($this->config['refresh_token'])) {
            try {
                // Exchange refresh token for access token
                $token = $this->client->fetchAccessTokenWithRefreshToken($this->config['refresh_token']);

                if (isset($token['error'])) {
                    Log::error('OAuth token refresh failed', ['error' => $token]);
                    throw new \RuntimeException('Gmail authentication failed: ' . ($token['error_description'] ?? $token['error']));
                }
            } catch (\Exception $e) {
                Log::error('Failed to refresh OAuth token: ' . $e->getMessage());
                throw new \RuntimeException('Gmail authentication failed. Please re-authenticate in Admin Settings.');
            }
        }
    }

    /**
     * Get Gmail service instance
     *
     * @return \Google\Service\Gmail
     */
    private function getService(): Gmail
    {
        if ($this->service === null) {
            $this->service = new Gmail($this->client);
        }

        return $this->service;
    }

    /**
     * Get authorization URL for OAuth2 flow
     *
     * @return string
     */
    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Exchange authorization code for access token
     *
     * @param string $code Authorization code
     * @return array Token data including refresh_token
     */
    public function authenticate(string $code): array
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            Log::error('Gmail authentication error: ' . $token['error']);
            throw new \RuntimeException('Failed to authenticate with Gmail: ' . $token['error']);
        }

        return $token;
    }

    /**
     * Get messages from Gmail inbox
     *
     * @param string $query Gmail search query (e.g., 'is:unread')
     * @param int $maxResults Maximum number of messages to retrieve
     * @return array Array of message IDs
     */
    public function getMessages(string $query = 'is:unread', int $maxResults = 50): array
    {
        try {
            $service = $this->getService();
            $results = $service->users_messages->listUsersMessages('me', [
                'q' => $query,
                'maxResults' => $maxResults,
            ]);

            $messages = $results->getMessages();

            if (empty($messages)) {
                return [];
            }

            $messageIds = [];
            foreach ($messages as $message) {
                $messageIds[] = $message->getId();
            }

            return $messageIds;
        } catch (\Exception $e) {
            Log::error('Error fetching Gmail messages: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Parse Gmail message and extract relevant data
     *
     * @param string $messageId Gmail message ID
     * @return array Parsed message data with keys: from, subject, body_html, body_text, attachments, inline_images
     */
    public function parseMessage(string $messageId): array
    {
        try {
            $service = $this->getService();
            $message = $service->users_messages->get('me', $messageId, ['format' => 'full']);

            $headers = $message->getPayload()->getHeaders();
            $parts = $message->getPayload()->getParts();

            // Parse To and CC recipients
            $toHeader = $this->getHeader($headers, 'To');
            $ccHeader = $this->getHeader($headers, 'Cc');

            $data = [
                'gmail_message_id' => $messageId,
                'gmail_thread_id' => $message->getThreadId(),
                'from' => $this->getHeader($headers, 'From'),
                'to' => $this->getHeader($headers, 'To'),
                'subject' => $this->getHeader($headers, 'Subject'),
                'date' => $this->getHeader($headers, 'Date'),
                'email_to' => $this->parseRecipients($toHeader),
                'email_cc' => $this->parseRecipients($ccHeader),
                'body_html' => '',
                'body_text' => '',
                'attachments' => [],
                'inline_images' => [],
            ];

            // Extract body and attachments
            $this->extractMessageParts($message->getPayload(), $data);

            return $data;
        } catch (\Exception $e) {
            Log::error('Error parsing Gmail message: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract message parts recursively (body, attachments, inline images)
     *
     * @param \Google\Service\Gmail\MessagePart $payload Message payload
     * @param array &$data Reference to data array to populate
     * @return void
     */
    private function extractMessageParts($payload, array &$data): void
    {
        $mimeType = $payload->getMimeType();
        $parts = $payload->getParts();
        $body = $payload->getBody();

        // Handle body content - preserve ALL HTML including styles
        if ($mimeType === 'text/html' && $body->getSize() > 0 && $body->getData() !== null) {
            $htmlContent = base64_decode(strtr($body->getData(), '-_', '+/'));
            $data['body_html'] = empty($data['body_html']) ? $htmlContent : $data['body_html'] . "\n" . $htmlContent;
        } elseif ($mimeType === 'text/plain' && $body->getSize() > 0 && $body->getData() !== null) {
            $textContent = base64_decode(strtr($body->getData(), '-_', '+/'));
            $data['body_text'] = empty($data['body_text']) ? $textContent : $data['body_text'] . "\n" . $textContent;
        }


        // Handle attachments
        $filename = $payload->getFilename();

        if (!empty($filename)) {
            $headers = $payload->getHeaders();
            $contentId = $this->getHeader($headers, 'Content-ID');
            $contentDisposition = $this->getHeader($headers, 'Content-Disposition');
            $attachmentId = $body->getAttachmentId();

            $attachment = [
                'filename' => $filename,
                'mime_type' => $mimeType,
                'attachment_id' => $attachmentId,
                'size' => $body->getSize(),
            ];

            // Check Content-Disposition first (official way to distinguish inline vs attachment)
            $isExplicitAttachment = stripos($contentDisposition, 'attachment') !== false;
            $isExplicitInline = stripos($contentDisposition, 'inline') !== false;

            if ($isExplicitAttachment) {
                // Explicitly marked as attachment - treat as regular attachment
                $data['attachments'][] = $attachment;
            } elseif ($isExplicitInline && !empty($contentId) && stripos($mimeType, 'image/') === 0) {
                // Explicitly inline AND has Content-ID AND is an image - treat as inline image
                $attachment['content_id'] = trim($contentId, '<>');
                $data['inline_images'][] = $attachment;
            } elseif (!empty($contentId) && stripos($mimeType, 'image/') === 0) {
                // Has Content-ID AND is an image (no explicit disposition) - treat as inline image
                $attachment['content_id'] = trim($contentId, '<>');
                $data['inline_images'][] = $attachment;
            } else {
                // Default: treat as regular attachment
                $data['attachments'][] = $attachment;
            }
        }

        // Recursively process parts
        if (!empty($parts)) {
            foreach ($parts as $part) {
                $this->extractMessageParts($part, $data);
            }
        }
    }

    /**
     * Download attachment from Gmail
     *
     * @param string $messageId Gmail message ID
     * @param string $attachmentId Gmail attachment ID
     * @return string Binary content of attachment
     */
    public function downloadAttachment(string $messageId, string $attachmentId): string
    {
        try {
            $service = $this->getService();
            $attachment = $service->users_messages_attachments->get('me', $messageId, $attachmentId);

            return base64_decode(strtr($attachment->getData(), '-_', '+/'));
        } catch (\Exception $e) {
            Log::error('Error downloading Gmail attachment: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mark message as read
     *
     * @param string $messageId Gmail message ID
     * @return bool Success status
     */
    public function markAsRead(string $messageId): bool
    {
        try {
            $service = $this->getService();
            $mods = new \Google\Service\Gmail\ModifyMessageRequest();
            $mods->setRemoveLabelIds(['UNREAD']);

            $service->users_messages->modify('me', $messageId, $mods);

            return true;
        } catch (\Exception $e) {
            Log::error('Error marking Gmail message as read: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email via Gmail
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $htmlBody HTML body content
     * @param array $attachments Array of attachment file paths
     * @return bool Success status
     */
    /**
     * Send email via Gmail API
     *
     * @param string|array $to Recipient email or array of recipients ['email' => 'name', ...]
     * @param string $subject Subject
     * @param string $htmlBody HTML body
     * @param array $attachments Array of file paths
     * @param array $options Additional options: 'from', 'cc', 'bcc', 'replyTo'
     * @return bool Success status
     */
    public function sendEmail($to, string $subject, string $htmlBody, array $attachments = [], array $options = []): bool
    {
        try {
            $service = $this->getService();

            // Create MIME message
            $boundary = uniqid('boundary_');
            $rawMessage = $this->createMimeMessage($to, $subject, $htmlBody, $attachments, $boundary, $options);

            // Base64 encode for Gmail API
            $encodedMessage = base64_encode($rawMessage);
            $encodedMessage = strtr($encodedMessage, '+/', '-_');
            $encodedMessage = rtrim($encodedMessage, '=');

            $message = new Message();
            $message->setRaw($encodedMessage);

            $service->users_messages->send('me', $message);

            return true;
        } catch (\Exception $e) {
            Log::error('Error sending Gmail message: ' . $e->getMessage(), [
                'to' => is_array($to) ? implode(', ', array_keys($to)) : $to,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create MIME message for sending
     *
     * @param string|array $to Recipient(s)
     * @param string $subject Subject
     * @param string $htmlBody HTML body
     * @param array $attachments Attachments (file paths)
     * @param string $boundary MIME boundary
     * @param array $options Additional options (from, cc, bcc, replyTo)
     * @return string MIME message
     */
    private function createMimeMessage($to, string $subject, string $htmlBody, array $attachments, string $boundary, array $options = []): string
    {
        // Build From header
        if (!empty($options['from'])) {
            if (is_array($options['from'])) {
                // ['email' => 'name']
                $fromEmail = array_keys($options['from'])[0];
                $fromName = $options['from'][$fromEmail];
                $message = "From: {$fromName} <{$fromEmail}>\r\n";
            } else {
                $message = "From: {$options['from']}\r\n";
            }
        } else {
            $message = "";
        }

        // Build To header
        if (is_array($to)) {
            $toList = [];
            foreach ($to as $email => $name) {
                if (is_numeric($email)) {
                    // Simple array of emails
                    $toList[] = $name;
                } else {
                    // Associative array ['email' => 'name']
                    $toList[] = "{$name} <{$email}>";
                }
            }
            $message .= "To: " . implode(', ', $toList) . "\r\n";
        } else {
            $message .= "To: {$to}\r\n";
        }

        // Build CC header
        if (!empty($options['cc'])) {
            if (is_array($options['cc'])) {
                $ccList = [];
                foreach ($options['cc'] as $email => $name) {
                    if (is_numeric($email)) {
                        $ccList[] = $name;
                    } else {
                        $ccList[] = "{$name} <{$email}>";
                    }
                }
                $message .= "Cc: " . implode(', ', $ccList) . "\r\n";
            } else {
                $message .= "Cc: {$options['cc']}\r\n";
            }
        }

        // Build BCC header
        if (!empty($options['bcc'])) {
            if (is_array($options['bcc'])) {
                $bccList = [];
                foreach ($options['bcc'] as $email => $name) {
                    if (is_numeric($email)) {
                        $bccList[] = $name;
                    } else {
                        $bccList[] = "{$name} <{$email}>";
                    }
                }
                $message .= "Bcc: " . implode(', ', $bccList) . "\r\n";
            } else {
                $message .= "Bcc: {$options['bcc']}\r\n";
            }
        }

        // Reply-To header
        if (!empty($options['replyTo'])) {
            $message .= "Reply-To: {$options['replyTo']}\r\n";
        }

        $message .= "Subject: {$subject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";

        // HTML body part
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        // Attachments
        foreach ($attachments as $filePath) {
            if (file_exists($filePath)) {
                $fileName = basename($filePath);
                $fileContent = file_get_contents($filePath);
                $mimeType = mime_content_type($filePath);

                $message .= "--{$boundary}\r\n";
                $message .= "Content-Type: {$mimeType}; name=\"{$fileName}\"\r\n";
                $message .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $message .= chunk_split(base64_encode($fileContent)) . "\r\n";
            }
        }

        $message .= "--{$boundary}--";

        return $message;
    }

    /**
     * Get header value from headers array
     *
     * @param array $headers Array of header objects
     * @param string $name Header name to find
     * @return string Header value or empty string
     */
    private function getHeader(array $headers, string $name): string
    {
        foreach ($headers as $header) {
            if (strtolower($header->getName()) === strtolower($name)) {
                return $header->getValue();
            }
        }

        return '';
    }

    /**
     * Extract email address from "Name <email@example.com>" format
     *
     * @param string $emailString Email string
     * @return string Email address
     */
    public function extractEmailAddress(string $emailString): string
    {
        if (preg_match('/<(.+?)>/', $emailString, $matches)) {
            return $matches[1];
        }

        return trim($emailString);
    }

    /**
     * Extract name from "Name <email@example.com>" format
     *
     * @param string $emailString Email string
     * @return string Name or email if no name found
     */
    public function extractName(string $emailString): string
    {
        if (preg_match('/^(.+?)\s*</', $emailString, $matches)) {
            return trim($matches[1], '" ');
        }

        return $this->extractEmailAddress($emailString);
    }

    /**
     * Parse recipients header into structured array
     *
     * Parses email headers like "To" or "Cc" that may contain multiple recipients
     * in format: "Name1 <email1@example.com>, Name2 <email2@example.com>"
     *
     * @param string $recipientsHeader Raw header string with recipients
     * @return array Array of recipients with 'name' and 'email' keys, or empty array
     */
    private function parseRecipients(string $recipientsHeader): array
    {
        if (empty($recipientsHeader)) {
            return [];
        }

        $recipients = [];

        // Split by comma (handling cases where commas might appear in quoted names)
        $parts = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $recipientsHeader);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            $recipients[] = [
                'name' => $this->extractName($part),
                'email' => $this->extractEmailAddress($part),
            ];
        }

        return $recipients;
    }
}
