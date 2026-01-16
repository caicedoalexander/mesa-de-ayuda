<?php
declare(strict_types=1);

namespace App\Service;

use App\Service\Traits\GenericAttachmentTrait;
use App\Utility\SettingsEncryptionTrait;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Log\Log;

/**
 * Generic Email Service
 *
 * Responsible for sending emails via Gmail API for all entity types (Tickets, PQRS, Compras).
 * Single Responsibility: Email sending infrastructure (not business logic).
 *
 * Usage:
 *   $service = new GenericEmailService($systemConfig, $emailTemplateService, $renderer);
 *   $service->sendTemplateEmail('ticket', 'nuevo_ticket', $ticket, ['extra' => 'value']);
 */
class GenericEmailService
{
    use LocatorAwareTrait;
    use SettingsEncryptionTrait;
    use GenericAttachmentTrait;

    private EmailTemplateService $templateService;
    private \App\Service\Renderer\NotificationRenderer $renderer;
    private ?array $systemConfig;
    private ?GmailService $gmailService = null;

    /**
     * Constructor
     *
     * @param array|null $systemConfig Optional system configuration to avoid redundant DB queries
     * @param EmailTemplateService|null $templateService Optional template service (for DI)
     * @param \App\Service\Renderer\NotificationRenderer|null $renderer Optional renderer (for DI)
     */
    public function __construct(
        ?array $systemConfig = null,
        ?EmailTemplateService $templateService = null,
        ?\App\Service\Renderer\NotificationRenderer $renderer = null
    ) {
        $this->systemConfig = $systemConfig;
        $this->templateService = $templateService ?? new EmailTemplateService();
        $this->renderer = $renderer ?? new \App\Service\Renderer\NotificationRenderer();
    }

    /**
     * Send email using template (generic for all entity types)
     *
     * This is the main entry point for sending emails for Tickets, PQRS, and Compras.
     *
     * @param string $entityType 'ticket', 'pqrs', 'compra'
     * @param string $templateKey Template key from database (e.g., 'nuevo_ticket')
     * @param EntityInterface $entity Entity instance
     * @param array $extraVariables Additional template variables
     * @param array $attachments Attachment entities (optional)
     * @param array $additionalTo Additional To recipients [['name' => '', 'email' => ''], ...]
     * @param array $additionalCc Additional CC recipients [['name' => '', 'email' => ''], ...]
     * @return bool Success status
     */
    public function sendTemplateEmail(
        string $entityType,
        string $templateKey,
        EntityInterface $entity,
        array $extraVariables = [],
        array $attachments = [],
        array $additionalTo = [],
        array $additionalCc = []
    ): bool {
        try {
            // Load entity with associations
            $entity = $this->loadEntityWithAssociations($entityType, $entity);

            // Get and render template
            $variables = $this->buildTemplateVariables($entityType, $entity, $extraVariables);
            $rendered = $this->templateService->getAndRender($templateKey, $variables, $this->systemConfig);

            if (!$rendered) {
                Log::error("Failed to render email template: {$templateKey}");
                return false;
            }

            // Get recipient email
            $recipientEmail = $this->getRecipientEmail($entityType, $entity);
            if (empty($recipientEmail)) {
                Log::warning("No recipient email for {$entityType}", ['entity_id' => $entity->id]);
                return false;
            }

            // Send email via Gmail API
            return $this->sendEmail(
                $recipientEmail,
                $rendered['subject'],
                $rendered['body'],
                $attachments,
                $additionalTo,
                $additionalCc
            );

        } catch (\Exception $e) {
            Log::error("Failed to send {$entityType} email", [
                'template' => $templateKey,
                'entity_id' => $entity->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send email using Gmail API
     *
     * Low-level method that handles the actual Gmail API call with attachments.
     *
     * @param string $to Primary recipient email
     * @param string $subject Email subject
     * @param string $body HTML body
     * @param array $attachments Array of Attachment entities (optional)
     * @param array $additionalTo Additional To recipients [['name' => '', 'email' => ''], ...]
     * @param array $additionalCc Additional CC recipients [['name' => '', 'email' => ''], ...]
     * @return bool Success status
     */
    public function sendEmail(
        string $to,
        string $subject,
        string $body,
        array $attachments = [],
        array $additionalTo = [],
        array $additionalCc = []
    ): bool {
        try {
            // Get system settings for From header
            $systemTitle = $this->systemConfig['system_title'] ?? 'Sistema de Soporte';
            $fromEmail = $this->systemConfig['gmail_user_email'] ?? 'noreply@localhost';

            // If system config is not provided, load from database
            if ($this->systemConfig === null) {
                $settingsTable = $this->fetchTable('SystemSettings');
                $settings = $settingsTable->find()
                    ->where(['setting_key IN' => ['system_title', 'gmail_user_email']])
                    ->all()
                    ->combine('setting_key', 'setting_value')
                    ->toArray();

                $systemTitle = $settings['system_title'] ?? $systemTitle;
                $fromEmail = $settings['gmail_user_email'] ?? $fromEmail;
            }

            // Build recipients array
            $toRecipients = [$to => $to]; // Primary recipient

            // Add additional To recipients
            foreach ($additionalTo as $recipient) {
                if (!empty($recipient['email'])) {
                    $toRecipients[$recipient['email']] = $recipient['name'] ?? $recipient['email'];
                }
            }

            // Build CC recipients
            $ccRecipients = [];
            foreach ($additionalCc as $recipient) {
                if (!empty($recipient['email'])) {
                    $ccRecipients[$recipient['email']] = $recipient['name'] ?? $recipient['email'];
                }
            }

            // Build attachment file paths using GenericAttachmentTrait
            $attachmentPaths = [];
            foreach ($attachments as $attachment) {
                $filePath = $this->getFullPath($attachment);
                if (file_exists($filePath)) {
                    $attachmentPaths[] = $filePath;
                }
            }

            // Build options for Gmail API
            $options = [
                'from' => [$fromEmail => $systemTitle],
                'headers' => ['X-Mesa-Ayuda-Notification' => 'true'],
            ];

            if (!empty($ccRecipients)) {
                $options['cc'] = $ccRecipients;
            }

            // Send via Gmail API
            $gmailService = $this->getGmailService();
            $result = $gmailService->sendEmail($toRecipients, $subject, $body, $attachmentPaths, $options);

            if ($result) {
                Log::info('Email sent successfully via Gmail API', ['to' => $to, 'subject' => $subject]);
            } else {
                Log::warning('Gmail API returned false for email send', ['to' => $to, 'subject' => $subject]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to send email via Gmail API', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Load entity with proper associations based on entity type
     *
     * @param string $entityType 'ticket', 'pqrs', 'compra'
     * @param EntityInterface $entity Entity instance
     * @return EntityInterface Loaded entity with associations
     */
    private function loadEntityWithAssociations(string $entityType, EntityInterface $entity): EntityInterface
    {
        $config = match ($entityType) {
            'ticket' => [
                'table' => 'Tickets',
                'contain' => ['Requesters', 'Assignees', 'Attachments'],
            ],
            'pqrs' => [
                'table' => 'Pqrs',
                'contain' => ['Assignees', 'PqrsAttachments'],
            ],
            'compra' => [
                'table' => 'Compras',
                'contain' => ['Requesters', 'Assignees', 'ComprasAttachments'],
            ],
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };

        $table = $this->fetchTable($config['table']);
        return $table->get($entity->id, contain: $config['contain']);
    }

    /**
     * Build template variables for entity type
     *
     * @param string $entityType 'ticket', 'pqrs', 'compra'
     * @param EntityInterface $entity Entity instance
     * @param array $extraVariables Additional variables to merge
     * @return array Complete template variables
     */
    private function buildTemplateVariables(
        string $entityType,
        EntityInterface $entity,
        array $extraVariables
    ): array {
        $systemVars = $this->templateService->getSystemVariables($this->systemConfig);

        $entityVars = match ($entityType) {
            'ticket' => [
                'ticket_number' => $entity->ticket_number,
                'subject' => $entity->subject,
                'requester_name' => $entity->requester->name ?? 'N/A',
                'created_date' => $this->renderer->formatDate($entity->created),
                'ticket_url' => $this->renderer->getTicketUrl($entity->id),
            ],
            'pqrs' => [
                'pqrs_number' => $entity->pqrs_number,
                'pqrs_type' => $this->renderer->getTypeLabel($entity->type),
                'subject' => $entity->subject,
                'requester_name' => $entity->requester_name,
                'created_date' => $this->renderer->formatDate($entity->created),
            ],
            'compra' => [
                'compra_number' => $entity->compra_number,
                'subject' => $entity->subject,
                'requester_name' => $entity->requester->name ?? 'N/A',
                'created_date' => $this->renderer->formatDate($entity->created),
                'compra_url' => $this->getCompraUrl($entity->id),
            ],
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };

        return array_merge($systemVars, $entityVars, $extraVariables);
    }

    /**
     * Get recipient email for entity type
     *
     * @param string $entityType 'ticket', 'pqrs', 'compra'
     * @param EntityInterface $entity Entity instance
     * @return string Recipient email address
     */
    private function getRecipientEmail(string $entityType, EntityInterface $entity): string
    {
        return match ($entityType) {
            'ticket' => $entity->requester->email ?? '',
            'pqrs' => $entity->requester_email ?? '',
            'compra' => $entity->requester->email ?? '',
            default => '',
        };
    }

    /**
     * Get or create GmailService instance
     *
     * Lazy-loads GmailService with credentials from system config.
     *
     * @return GmailService
     */
    private function getGmailService(): GmailService
    {
        if ($this->gmailService === null) {
            // Use cached config if available
            if ($this->systemConfig !== null) {
                $config = $this->systemConfig;
            } else {
                // Load from database
                $settingsTable = $this->fetchTable('SystemSettings');
                $config = $settingsTable->find()
                    ->select(['setting_key', 'setting_value'])
                    ->where(['setting_key IN' => ['gmail_refresh_token', 'gmail_client_secret_path']])
                    ->all()
                    ->combine('setting_key', 'setting_value')
                    ->toArray();
            }

            // Decrypt the refresh token if encrypted
            $refreshToken = $config['gmail_refresh_token'] ?? '';
            if (!empty($refreshToken)) {
                $refreshToken = $this->decryptSetting($refreshToken, 'gmail_refresh_token');
            }

            $this->gmailService = new GmailService([
                'refresh_token' => $refreshToken,
                'client_secret_path' => $config['gmail_client_secret_path'] ?? CONFIG . 'google' . DS . 'client_secret.json',
            ]);
        }

        return $this->gmailService;
    }

    /**
     * Get Compra URL
     *
     * @param int $id Compra ID
     * @return string Full URL
     */
    private function getCompraUrl(int $id): string
    {
        $baseUrl = \Cake\Core\Configure::read('App.fullBaseUrl', 'http://localhost:8765');
        return $baseUrl . '/compras/view/' . $id;
    }
}
