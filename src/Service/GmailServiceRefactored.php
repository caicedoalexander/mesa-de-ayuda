<?php
declare(strict_types=1);

namespace App\Service;

use Google\Service\Gmail;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use App\Utility\SettingsEncryptionTrait;
use App\Service\Gmail\GmailClientFactory;
use App\Service\Gmail\GmailMessageParser;
use App\Service\Gmail\GmailEmailComposer;

/**
 * Gmail Service (Refactored)
 *
 * Facade that coordinates Gmail API operations through specialized components.
 * This is a refactored version following Single Responsibility Principle.
 *
 * Resolves: ARCH-001 (GmailService SRP violation)
 *
 * Components:
 * - GmailClientFactory: OAuth2 authentication and client management
 * - GmailMessageParser: Message parsing and attachment extraction
 * - GmailEmailComposer: Email composition and sending
 *
 * This class maintains backward compatibility with the original GmailService API.
 */
class GmailServiceRefactored
{
    use LocatorAwareTrait;
    use SettingsEncryptionTrait;

    private GmailClientFactory $clientFactory;
    private ?GmailMessageParser $messageParser = null;
    private ?GmailEmailComposer $emailComposer = null;
    private array $config;

    /**
     * Load Gmail configuration from database
     *
     * @return array Configuration array with 'client_secret_path' and 'refresh_token'
     */
    public static function loadConfigFromDatabase(): array
    {
        $instance = new self([]);

        $settingsTable = $instance->fetchTable('SystemSettings');
        $settings = $settingsTable->find()
            ->where(['setting_key IN' => ['gmail_refresh_token', 'gmail_client_secret_path']])
            ->all();

        $config = [];
        foreach ($settings as $setting) {
            $key = str_replace('gmail_', '', $setting->setting_key);
            $config[$key] = $instance->shouldEncrypt($setting->setting_key)
                ? $instance->decryptSetting($setting->setting_value, $setting->setting_key)
                : $setting->setting_value;
        }

        return $config;
    }

    /**
     * Constructor
     *
     * @param array $config Configuration array
     * @param GmailClientFactory|null $clientFactory Optional factory for testing
     */
    public function __construct(array $config = [], ?GmailClientFactory $clientFactory = null)
    {
        $this->config = $config;
        $this->clientFactory = $clientFactory ?? new GmailClientFactory($config);
    }

    /**
     * Get Gmail service instance
     *
     * @return Gmail
     */
    public function getService(): Gmail
    {
        return $this->clientFactory->getGmailService();
    }

    /**
     * Get message parser (lazy loaded)
     *
     * @return GmailMessageParser
     */
    private function getMessageParser(): GmailMessageParser
    {
        if ($this->messageParser === null) {
            $this->messageParser = new GmailMessageParser($this->getService());
        }

        return $this->messageParser;
    }

    /**
     * Get email composer (lazy loaded)
     *
     * @return GmailEmailComposer
     */
    private function getEmailComposer(): GmailEmailComposer
    {
        if ($this->emailComposer === null) {
            $this->emailComposer = new GmailEmailComposer($this->getService());
        }

        return $this->emailComposer;
    }

    /**
     * Get authorization URL for OAuth2 flow
     *
     * @return string
     */
    public function getAuthUrl(): string
    {
        return $this->clientFactory->getAuthUrl();
    }

    /**
     * Exchange authorization code for access token
     *
     * @param string $code Authorization code
     * @return array Token data
     */
    public function authenticate(string $code): array
    {
        return $this->clientFactory->authenticate($code);
    }

    /**
     * Get messages from Gmail inbox
     *
     * @param string $query Gmail search query
     * @param int $maxResults Maximum number of messages
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
     * Delegates to GmailMessageParser
     *
     * @param string $messageId Gmail message ID
     * @return array Parsed message data
     */
    public function parseMessage(string $messageId): array
    {
        return $this->getMessageParser()->parseMessage($messageId);
    }

    /**
     * Download attachment from Gmail
     *
     * Delegates to GmailMessageParser
     *
     * @param string $messageId Gmail message ID
     * @param string $attachmentId Gmail attachment ID
     * @return string Binary content
     */
    public function downloadAttachment(string $messageId, string $attachmentId): string
    {
        return $this->getMessageParser()->downloadAttachment($messageId, $attachmentId);
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
     * Detect if email is an auto-reply
     *
     * Delegates to GmailMessageParser
     *
     * @param array $headers Array of header objects
     * @return bool True if auto-reply detected
     */
    public function isAutoReply(array $headers): bool
    {
        return $this->getMessageParser()->isAutoReply($headers);
    }

    /**
     * Detect if email is a system notification response
     *
     * Delegates to GmailMessageParser
     *
     * @param array $headers Array of header objects
     * @return bool True if system notification detected
     */
    public function isSystemNotification(array $headers): bool
    {
        return $this->getMessageParser()->isSystemNotification($headers);
    }

    /**
     * Send email via Gmail API
     *
     * Delegates to GmailEmailComposer
     *
     * @param string|array $to Recipient(s)
     * @param string $subject Subject
     * @param string $htmlBody HTML body
     * @param array $attachments Array of file paths
     * @param array $options Additional options
     * @return bool Success status
     */
    public function sendEmail($to, string $subject, string $htmlBody, array $attachments = [], array $options = []): bool
    {
        return $this->getEmailComposer()->sendEmail($to, $subject, $htmlBody, $attachments, $options);
    }

    /**
     * Extract email address from string
     *
     * @param string $emailString Email string
     * @return string Email address
     */
    public function extractEmailAddress(string $emailString): string
    {
        return $this->getMessageParser()->extractEmailAddress($emailString);
    }

    /**
     * Extract name from email header
     *
     * @param string $emailString Email string
     * @return string Name
     */
    public function extractName(string $emailString): string
    {
        // Match "Name <email>" pattern
        if (preg_match('/^(.+?)\s*</', $emailString, $matches)) {
            return trim($matches[1], '" ');
        }

        return $this->extractEmailAddress($emailString);
    }
}
