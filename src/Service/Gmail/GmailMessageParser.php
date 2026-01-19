<?php
declare(strict_types=1);

namespace App\Service\Gmail;

use Google\Service\Gmail;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Gmail Message Parser
 *
 * Handles parsing and extraction of Gmail message content.
 * Extracted from GmailService to follow Single Responsibility Principle.
 *
 * Resolves: ARCH-001 (GmailService SRP violation)
 *
 * Responsibilities:
 * - Parse message headers (From, To, CC, Subject, Date)
 * - Extract body content (HTML and plain text)
 * - Extract attachments and inline images
 * - Detect auto-replies and system notifications
 * - Parse email recipients
 */
class GmailMessageParser
{
    use LocatorAwareTrait;

    private Gmail $gmailService;
    private ?string $systemEmail = null;

    /**
     * Constructor
     *
     * @param Gmail $gmailService Gmail service instance
     */
    public function __construct(Gmail $gmailService)
    {
        $this->gmailService = $gmailService;
    }

    /**
     * Parse Gmail message and extract relevant data
     *
     * @param string $messageId Gmail message ID
     * @return array Parsed message data
     */
    public function parseMessage(string $messageId): array
    {
        try {
            $message = $this->gmailService->users_messages->get('me', $messageId, ['format' => 'full']);

            $headers = $message->getPayload()->getHeaders();

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
                'is_auto_reply' => $this->isAutoReply($headers),
                'is_system_notification' => $this->isSystemNotification($headers),
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
     * Download attachment from Gmail
     *
     * @param string $messageId Gmail message ID
     * @param string $attachmentId Gmail attachment ID
     * @return string Binary content of attachment
     */
    public function downloadAttachment(string $messageId, string $attachmentId): string
    {
        try {
            $attachment = $this->gmailService->users_messages_attachments->get('me', $messageId, $attachmentId);

            return base64_decode(strtr($attachment->getData(), '-_', '+/'));
        } catch (\Exception $e) {
            Log::error('Error downloading Gmail attachment: ' . $e->getMessage());
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
     * Detect if email is an auto-reply (out-of-office, auto-responder)
     *
     * @param array $headers Array of header objects from Gmail API
     * @return bool True if auto-reply detected
     */
    public function isAutoReply(array $headers): bool
    {
        // Check Auto-Submitted header
        $autoSubmitted = $this->getHeader($headers, 'Auto-Submitted');
        if (stripos($autoSubmitted, 'auto-replied') !== false || stripos($autoSubmitted, 'auto-generated') !== false) {
            return true;
        }

        // Check X-Autoreply header
        $xAutoreply = $this->getHeader($headers, 'X-Autoreply');
        if (stripos($xAutoreply, 'yes') !== false) {
            return true;
        }

        // Check X-Autorespond header
        $xAutorespond = $this->getHeader($headers, 'X-Autorespond');
        if (stripos($xAutorespond, 'yes') !== false) {
            return true;
        }

        // Check Precedence header
        $precedence = $this->getHeader($headers, 'Precedence');
        if (stripos($precedence, 'bulk') !== false || stripos($precedence, 'list') !== false || stripos($precedence, 'junk') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Detect if email is a response to a system notification
     *
     * @param array $headers Array of header objects from Gmail API
     * @return bool True if system notification response detected
     */
    public function isSystemNotification(array $headers): bool
    {
        // Check 1: Custom Mesa de Ayuda notification header
        $notificationHeader = $this->getHeader($headers, 'X-Mesa-Ayuda-Notification');
        if ($notificationHeader === 'true') {
            return true;
        }

        // Check 2: Sender is system email address
        $from = $this->getHeader($headers, 'From');
        $fromEmail = $this->extractEmailAddress($from);

        $systemEmail = $this->getSystemEmail();
        if (!empty($systemEmail) && strtolower($fromEmail) === strtolower($systemEmail)) {
            return true;
        }

        // Check 3: Subject contains notification patterns
        $subject = $this->getHeader($headers, 'Subject');
        $notificationPatterns = [
            'Re: [Ticket #',
            'Re: [PQRS #',
            'Re: [Compra #',
            'Re: Tu Solicitud',
        ];

        foreach ($notificationPatterns as $pattern) {
            if (stripos($subject, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get header value by name
     *
     * @param array $headers Array of header objects
     * @param string $name Header name
     * @return string Header value or empty string
     */
    public function getHeader(array $headers, string $name): string
    {
        foreach ($headers as $header) {
            if (strtolower($header->getName()) === strtolower($name)) {
                return $header->getValue();
            }
        }

        return '';
    }

    /**
     * Parse recipients string into array of {name, email}
     *
     * @param string $recipientString Comma-separated recipients
     * @return array Array of ['name' => '...', 'email' => '...']
     */
    public function parseRecipients(string $recipientString): array
    {
        if (empty($recipientString)) {
            return [];
        }

        $recipients = [];

        // Split by comma, but handle quoted names that may contain commas
        preg_match_all('/(?:[^,"]|"[^"]*")+/', $recipientString, $matches);

        foreach ($matches[0] as $recipient) {
            $recipient = trim($recipient);
            if (empty($recipient)) {
                continue;
            }

            $email = $this->extractEmailAddress($recipient);
            $name = $this->extractName($recipient);

            if (!empty($email)) {
                $recipients[] = [
                    'name' => $name,
                    'email' => $email,
                ];
            }
        }

        return $recipients;
    }

    /**
     * Extract email address from string
     *
     * @param string $from Full from header value
     * @return string Email address
     */
    public function extractEmailAddress(string $from): string
    {
        // Match email in angle brackets: "Name <email@domain.com>"
        if (preg_match('/<([^>]+)>/', $from, $matches)) {
            return strtolower(trim($matches[1]));
        }

        // Just email address without brackets
        if (filter_var(trim($from), FILTER_VALIDATE_EMAIL)) {
            return strtolower(trim($from));
        }

        return '';
    }

    /**
     * Extract name from email header
     *
     * @param string $from Full from header value
     * @return string Name or empty string
     */
    private function extractName(string $from): string
    {
        // Match "Name <email>"
        if (preg_match('/^([^<]+)</', $from, $matches)) {
            $name = trim($matches[1], " \t\n\r\0\x0B\"");
            return $name;
        }

        return '';
    }

    /**
     * Get system email address from settings
     *
     * @return string System email or empty string
     */
    private function getSystemEmail(): string
    {
        if ($this->systemEmail !== null) {
            return $this->systemEmail;
        }

        try {
            $settingsTable = $this->fetchTable('SystemSettings');
            $setting = $settingsTable->find()
                ->where(['setting_key' => 'gmail_user_email'])
                ->first();

            $this->systemEmail = $setting ? $setting->setting_value : '';
            return $this->systemEmail;
        } catch (\Exception $e) {
            Log::error('Failed to load system email: ' . $e->getMessage());
            return '';
        }
    }
}
