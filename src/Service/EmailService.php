<?php
declare(strict_types=1);

namespace App\Service;

use App\Service\Traits\GenericAttachmentTrait;
use App\Utility\SettingsEncryptionTrait;
use Cake\Core\Configure;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Log\Log;

/**
 * Email Service
 *
 * Handles all email notifications using templates from database:
 * - New ticket notifications
 * - Status change notifications
 * - New comment notifications
 */
class EmailService
{
    use LocatorAwareTrait;
    use SettingsEncryptionTrait;
    use GenericAttachmentTrait;

    private \App\Service\Renderer\NotificationRenderer $renderer;
    private ?array $systemConfig = null;
    private ?GmailService $gmailService = null;

    /**
     * Constructor
     *
     * @param array|null $systemConfig Optional system configuration to avoid redundant DB queries
     */
    public function __construct(?array $systemConfig = null)
    {
        $this->renderer = new \App\Service\Renderer\NotificationRenderer();
        $this->systemConfig = $systemConfig;
    }

    /**
     * Get system-wide variables for email templates
     *
     * @return array System variables
     */
    private function getSystemVariables(): array
    {
        $systemTitle = 'Sistema de Soporte'; // Default

        // Use cached config if available
        if ($this->systemConfig !== null) {
            $systemTitle = $this->systemConfig['system_title'] ?? $systemTitle;
        } else {
            // Fallback to DB query with cache
            try {
                
                $systemTitle = \Cake\Cache\Cache::remember('system_title', function () {
                    $settingsTable = $this->fetchTable('SystemSettings');
                    $setting = $settingsTable->find()
                        ->where(['setting_key' => 'system_title'])
                        ->first();
                    return $setting ? $setting->setting_value : 'Sistema de Soporte';
                }, '_cake_core_');
            } catch (\Exception $e) {
                Log::error('Failed to load system_title: ' . $e->getMessage());
            }
        }

        return [
            'system_title' => $systemTitle,
            'current_year' => date('Y'),
        ];
    }

    /**
     * Send new ticket notification to requester
     *
     * @param \App\Model\Entity\Ticket $ticket Ticket entity
     * @return bool Success status
     */
    public function sendNewTicketNotification($ticket): bool
    {
        return $this->sendGenericTemplateEmail('ticket', 'nuevo_ticket', $ticket);
    }

    /**
     * Send status change notification
     *
     * @param \App\Model\Entity\Ticket $ticket Ticket entity
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @return bool Success status
     */
    public function sendStatusChangeNotification($ticket, string $oldStatus, string $newStatus): bool
    {
        // Load ticket with associations to get assignee
        $ticketsTable = $this->fetchTable('Tickets');
        $ticket = $ticketsTable->get($ticket->id, contain: ['Requesters', 'Assignees']);

        return $this->sendGenericTemplateEmail('ticket', 'ticket_estado', $ticket, [
            'old_status' => $this->renderer->getStatusLabel($oldStatus),
            'new_status' => $this->renderer->getStatusLabel($newStatus),
            'assignee_name' => $ticket->assignee ? $ticket->assignee->name : 'No asignado',
            'updated_date' => $this->renderer->formatDate($ticket->modified),
        ]);
    }

    /**
     * Send new comment notification
     *
     * @param \App\Model\Entity\Ticket $ticket Ticket entity
     * @param \App\Model\Entity\TicketComment $comment Comment entity
     * @return bool Success status
     */
    public function sendNewCommentNotification($ticket, $comment): bool
    {
        try {
            // Load entities with associations
            $ticketsTable = $this->fetchTable('Tickets');
            $ticket = $ticketsTable->get($ticket->id, contain: ['Requesters', 'Assignees', 'Attachments']);

            $commentsTable = $this->fetchTable('TicketComments');
            $comment = $commentsTable->get($comment->id, contain: ['Users']);

            // Get comment attachments (non-inline only)
            $commentAttachments = [];
            if (!empty($ticket->attachments)) {
                foreach ($ticket->attachments as $attachment) {
                    if ($attachment->comment_id === $comment->id && !$attachment->is_inline) {
                        $commentAttachments[] = $attachment;
                    }
                }
            }

            // Get template from database
            $template = $this->getTemplate('nuevo_comentario');
            if (!$template) {
                Log::error('Email template not found: nuevo_comentario');
                return false;
            }

            // Get agent profile image URL
            $userHelper = new \App\View\Helper\UserHelper($this->getView());
            $agentProfileImageUrl = $comment->user && $comment->user->profile_image
                ? $userHelper->profileImage($comment->user->profile_image)
                : $userHelper->defaultAvatar();

            // Convert relative URL to absolute URL for email
            $agentProfileImageUrl = $this->getAbsoluteUrl($agentProfileImageUrl);

            // Replace variables in subject and body
            $variables = array_merge($this->getSystemVariables(), [
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'comment_author' => $comment->user->name,
                'comment_body' => $comment->body,
                'attachments_list' => $this->renderer->renderAttachmentsHtml($commentAttachments),
                'ticket_url' => $this->renderer->getTicketUrl($ticket->id),
                'agent_profile_image_url' => $agentProfileImageUrl,
                'agent_name' => $comment->user->name,
            ]);

            $subject = $this->replaceVariables($template->subject, $variables);
            $body = $this->replaceVariables($template->body_html, $variables);

            // Send email to requester with attachments
            return $this->sendEmail($ticket->requester->email, $subject, $body, $commentAttachments);
        } catch (\Exception $e) {
            Log::error('Failed to send new comment notification', [
                'ticket_id' => $ticket->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send unified ticket response notification (comment + status change)
     *
     * This method combines comment and status change into a single email
     * to avoid sending multiple notifications to the requester.
     *
     * @param \App\Model\Entity\Ticket $ticket Ticket entity
     * @param \App\Model\Entity\TicketComment $comment Comment entity
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @param array $additionalTo Additional To recipients [['name' => '', 'email' => ''], ...]
     * @param array $additionalCc Additional CC recipients [['name' => '', 'email' => ''], ...]
     * @return bool Success status
     */
    public function sendTicketResponseNotification($ticket, $comment, string $oldStatus, string $newStatus, array $additionalTo = [], array $additionalCc = []): bool
    {
        try {
            // Load entities with associations
            $ticketsTable = $this->fetchTable('Tickets');
            $ticket = $ticketsTable->get($ticket->id, contain: ['Requesters', 'Assignees', 'Attachments']);

            $commentsTable = $this->fetchTable('TicketComments');
            $comment = $commentsTable->get($comment->id, contain: ['Users']);

            // Get comment attachments (non-inline only)
            $commentAttachments = [];
            if (!empty($ticket->attachments)) {
                foreach ($ticket->attachments as $attachment) {
                    if ($attachment->comment_id === $comment->id && !$attachment->is_inline) {
                        $commentAttachments[] = $attachment;
                    }
                }
            }

            // Get unified response template
            $template = $this->getTemplate('ticket_respuesta');
            if (!$template) {
                Log::error('Email template not found: ticket_respuesta');
                return false;
            }

            // Check if status actually changed
            $hasStatusChange = ($oldStatus !== $newStatus);
            $assigneeName = $ticket->assignee ? $ticket->assignee->name : 'No asignado';

            // Build status change HTML section (only if status changed)
            $statusChangeSection = '';
            if ($hasStatusChange) {
                $statusChangeSection = $this->renderer->renderStatusChangeHtml($oldStatus, $newStatus, $assigneeName);
            }

            // Get agent profile image URL
            $userHelper = new \App\View\Helper\UserHelper($this->getView());
            $agentProfileImageUrl = $comment->user && $comment->user->profile_image
                ? $userHelper->profileImage($comment->user->profile_image)
                : $userHelper->defaultAvatar();

            // Convert relative URL to absolute URL for email
            $agentProfileImageUrl = $this->getAbsoluteUrl($agentProfileImageUrl);

            // Replace variables
            $variables = [
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'requester_name' => $ticket->requester->name,
                'comment_author' => $comment->user->name,
                'comment_body' => $comment->body,
                'attachments_list' => $this->renderer->renderAttachmentsHtml($commentAttachments),
                'status_change_section' => $statusChangeSection,
                'old_status' => $this->renderer->getStatusLabel($oldStatus),
                'old_status_key' => $oldStatus,
                'new_status' => $this->renderer->getStatusLabel($newStatus),
                'new_status_key' => $newStatus,
                'assignee_name' => $assigneeName,
                'updated_date' => $this->renderer->formatDate($ticket->modified),
                'ticket_url' => $this->renderer->getTicketUrl($ticket->id),
                'system_title' => 'Sistema de Soporte',
                'agent_profile_image_url' => $agentProfileImageUrl,
                'agent_name' => $comment->user->name,
            ];

            $subject = $this->replaceVariables($template->subject, $variables);
            $body = $this->replaceVariables($template->body_html, $variables);

            // Send email to requester with attachments and additional recipients
            return $this->sendEmail($ticket->requester->email, $subject, $body, $commentAttachments, $additionalTo, $additionalCc);
        } catch (\Exception $e) {
            Log::error('Failed to send ticket response notification', [
                'ticket_id' => $ticket->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get email template from database
     *
     * @param string $templateKey Template key
     * @return \App\Model\Entity\EmailTemplate|null
     */
    private function getTemplate(string $templateKey): ?\App\Model\Entity\EmailTemplate
    {
        $templatesTable = $this->fetchTable('EmailTemplates');

        return $templatesTable->find()
            ->where([
                'template_key' => $templateKey,
                'is_active' => true,
            ])
            ->first();
    }

    /**
     * Replace variables in template string
     *
     * @param string $template Template string with {{variables}}
     * @param array $variables Associative array of variable name => value
     * @return string Template with replaced variables
     */
    private function replaceVariables(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace("{{" . $key . "}}", (string) $value, $template);
        }

        return $template;
    }

    /**
     * Get or create GmailService instance
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
     * Send email using Gmail API
     *
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body HTML body
     * @param array $attachments Array of Attachment entities (optional)
     * @param array $additionalTo Additional To recipients [['name' => '', 'email' => ''], ...]
     * @param array $additionalCc Additional CC recipients [['name' => '', 'email' => ''], ...]
     * @return bool Success status
     */
    private function sendEmail(string $to, string $subject, string $body, array $attachments = [], array $additionalTo = [], array $additionalCc = []): bool
    {
        try {
            // Get system title for From header
            $systemTitle = 'Sistema de Soporte';
            if ($this->systemConfig !== null) {
                $systemTitle = $this->systemConfig['system_title'] ?? $systemTitle;
            } else {
                $settingsTable = $this->fetchTable('SystemSettings');
                $setting = $settingsTable->find()
                    ->where(['setting_key' => 'system_title'])
                    ->first();
                if ($setting) {
                    $systemTitle = $setting->setting_value;
                }
            }

            // Get Gmail username for From email
            $fromEmail = 'noreply@localhost';
            if ($this->systemConfig !== null && !empty($this->systemConfig['gmail_user_email'])) {
                $fromEmail = $this->systemConfig['gmail_user_email'];
            } else {
                $settingsTable = $this->fetchTable('SystemSettings');
                $setting = $settingsTable->find()
                    ->where(['setting_key' => 'gmail_user_email'])
                    ->first();
                if ($setting) {
                    $fromEmail = $setting->setting_value;
                }
            }

            // Build recipients array for Gmail API
            $toRecipients = [$to => $to]; // Primary recipient

            // Add additional To recipients
            if (!empty($additionalTo)) {
                foreach ($additionalTo as $recipient) {
                    if (!empty($recipient['email'])) {
                        $toRecipients[$recipient['email']] = $recipient['name'] ?? $recipient['email'];
                    }
                }
            }

            // Build CC recipients
            $ccRecipients = [];
            if (!empty($additionalCc)) {
                foreach ($additionalCc as $recipient) {
                    if (!empty($recipient['email'])) {
                        $ccRecipients[$recipient['email']] = $recipient['name'] ?? $recipient['email'];
                    }
                }
            }

            // Build attachment file paths using GenericAttachmentTrait
            $attachmentPaths = [];
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    // Use unified getFullPath() from GenericAttachmentTrait
                    // (works for Ticket, PQRS, and Compra attachments)
                    $filePath = $this->getFullPath($attachment);

                    if (file_exists($filePath)) {
                        $attachmentPaths[] = $filePath;
                    }
                }
            }

            // Build options for Gmail API
            $options = [
                'from' => [$fromEmail => $systemTitle],
            ];

            if (!empty($ccRecipients)) {
                $options['cc'] = $ccRecipients;
            }

            // Send via Gmail API
            $gmailService = $this->getGmailService();
            $result = $gmailService->sendEmail($toRecipients, $subject, $body, $attachmentPaths, $options);

            if ($result) {
                Log::info('Email sent successfully via Gmail API', ['to' => $to, 'subject' => $subject]);
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
     * Send new PQRS notification to requester
     *
     * @param \App\Model\Entity\Pqr $pqrs PQRS entity
     * @return bool Success status
     */
    public function sendNewPqrsNotification($pqrs): bool
    {
        return $this->sendGenericTemplateEmail('pqrs', 'nuevo_pqrs', $pqrs, [
            'system_title' => 'Sistema de Atención al Cliente',
        ]);
    }

    /**
     * Send PQRS status change notification
     *
     * @param \App\Model\Entity\Pqr $pqrs PQRS entity
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @return bool Success status
     */
    public function sendPqrsStatusChangeNotification($pqrs, string $oldStatus, string $newStatus): bool
    {
        // Build assignee info if there's an assignee
        $assigneeInfo = '';
        if (!empty($pqrs->assignee_id)) {
            $pqrsTable = $this->fetchTable('Pqrs');
            $pqrsWithAssignee = $pqrsTable->get($pqrs->id, contain: ['Assignees']);
            if ($pqrsWithAssignee->assignee) {
                $assigneeInfo = "<p><strong>Asignado a:</strong> {$pqrsWithAssignee->assignee->name}</p>";
            }
        }

        return $this->sendGenericTemplateEmail('pqrs', 'pqrs_estado', $pqrs, [
            'old_status' => $this->renderer->getStatusLabel($oldStatus),
            'new_status' => $this->renderer->getStatusLabel($newStatus),
            'assignee_info' => $assigneeInfo,
            'system_title' => 'Mesa de Ayuda',
        ]);
    }

    /**
     * Send PQRS new comment notification
     *
     * @param \App\Model\Entity\Pqr $pqrs PQRS entity
     * @param \App\Model\Entity\PqrsComment $comment Comment entity
     * @return bool Success status
     */
    public function sendPqrsNewCommentNotification($pqrs, $comment): bool
    {
        try {
            // Only send for public comments
            if ($comment->comment_type !== 'public') {
                return true;
            }

            // Load entities with associations
            $pqrsTable = $this->fetchTable('Pqrs');
            $pqrs = $pqrsTable->get($pqrs->id, contain: ['PqrsAttachments']);

            $commentsTable = $this->fetchTable('PqrsComments');
            $comment = $commentsTable->get($comment->id, contain: ['Users']);

            // Get comment attachments (non-inline only)
            $commentAttachments = [];
            if (!empty($pqrs->pqrs_attachments)) {
                foreach ($pqrs->pqrs_attachments as $attachment) {
                    if ($attachment->pqrs_comment_id === $comment->id && !$attachment->is_inline) {
                        $commentAttachments[] = $attachment;
                    }
                }
            }

            // Get template from database
            $template = $this->getTemplate('pqrs_comentario');
            if (!$template) {
                Log::error('Email template not found: pqrs_comentario');
                return false;
            }

            $author = $comment->user ? $comment->user->name : 'Sistema';

            // Get agent profile image URL
            $userHelper = new \App\View\Helper\UserHelper($this->getView());
            $agentProfileImageUrl = $comment->user && $comment->user->profile_image
                ? $userHelper->profileImage($comment->user->profile_image)
                : $userHelper->defaultAvatar();

            // Convert relative URL to absolute URL for email
            $agentProfileImageUrl = $this->getAbsoluteUrl($agentProfileImageUrl);

            // Replace variables in subject
            $subject = str_replace('{{pqrs_number}}', $pqrs->pqrs_number, $template->subject);

            // Replace variables in body
            $body = str_replace([
                '{{pqrs_number}}',
                '{{subject}}',
                '{{comment_author}}',
                '{{comment_body}}',
                '{{attachments_list}}',
                '{{system_title}}',
                '{{agent_profile_image_url}}',
                '{{agent_name}}',
            ], [
                $pqrs->pqrs_number,
                $pqrs->subject,
                $author,
                $comment->body,
                $this->renderer->renderAttachmentsHtml($commentAttachments),
                'Sistema de Atención al Cliente',
                $agentProfileImageUrl,
                $author,
            ], $template->body_html);

            // Send email to requester with attachments
            return $this->sendEmail($pqrs->requester_email, $subject, $body, $commentAttachments);
        } catch (\Exception $e) {
            Log::error('Failed to send PQRS comment notification', [
                'pqrs_id' => $pqrs->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send unified PQRS response notification (comment + status change)
     *
     * @param \App\Model\Entity\Pqr $pqrs PQRS entity
     * @param object $comment Comment entity
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @return bool Success status
     */
    public function sendPqrsResponseNotification($pqrs, $comment, string $oldStatus, string $newStatus, array $additionalTo = [], array $additionalCc = []): bool
    {
        try {
            // Load entities with associations
            $pqrsTable = $this->fetchTable('Pqrs');
            $pqrs = $pqrsTable->get($pqrs->id, contain: ['Assignees', 'PqrsAttachments']);

            $commentsTable = $this->fetchTable('PqrsComments');
            $comment = $commentsTable->get($comment->id, contain: ['Users']);

            // Get comment attachments (non-inline only)
            $commentAttachments = [];
            if (!empty($pqrs->pqrs_attachments)) {
                foreach ($pqrs->pqrs_attachments as $attachment) {
                    if ($attachment->pqrs_comment_id === $comment->id && !$attachment->is_inline) {
                        $commentAttachments[] = $attachment;
                    }
                }
            }

            // Get unified response template
            $template = $this->getTemplate('pqrs_respuesta');
            if (!$template) {
                Log::error('Email template not found: pqrs_respuesta');
                return false;
            }

            // Check if status actually changed
            $hasStatusChange = ($oldStatus !== $newStatus);
            $assigneeName = $pqrs->assignee ? $pqrs->assignee->name : 'No asignado';

            // Build status change HTML section (only if status changed)
            $statusChangeSection = '';
            if ($hasStatusChange) {
                $statusChangeSection = $this->renderer->renderStatusChangeHtml($oldStatus, $newStatus, $assigneeName);
            }

            // Get agent profile image URL
            $userHelper = new \App\View\Helper\UserHelper($this->getView());
            $agentProfileImageUrl = $comment->user && $comment->user->profile_image
                ? $userHelper->profileImage($comment->user->profile_image)
                : $userHelper->defaultAvatar();

            // Convert relative URL to absolute URL for email
            $agentProfileImageUrl = $this->getAbsoluteUrl($agentProfileImageUrl);

            // Replace variables
            $variables = [
                'pqrs_number' => $pqrs->pqrs_number,
                'pqrs_type' => $this->renderer->getTypeLabel($pqrs->type),
                'subject' => $pqrs->subject,
                'requester_name' => $pqrs->requester_name,
                'comment_author' => $comment->user->name,
                'comment_body' => $comment->body,
                'attachments_list' => $this->renderer->renderAttachmentsHtml($commentAttachments),
                'status_change_section' => $statusChangeSection,
                'old_status' => $this->renderer->getStatusLabel($oldStatus),
                'old_status_key' => $oldStatus,
                'new_status' => $this->renderer->getStatusLabel($newStatus),
                'new_status_key' => $newStatus,
                'assignee_name' => $assigneeName,
                'updated_date' => $this->renderer->formatDate($pqrs->modified),
                'system_title' => 'Sistema de Atención al Cliente',
                'agent_profile_image_url' => $agentProfileImageUrl,
                'agent_name' => $comment->user->name,
            ];

            $subject = $this->replaceVariables($template->subject, $variables);
            $body = $this->replaceVariables($template->body_html, $variables);

            // Send email to requester with attachments and additional recipients
            return $this->sendEmail($pqrs->requester_email, $subject, $body, $commentAttachments, $additionalTo, $additionalCc);
        } catch (\Exception $e) {
            Log::error('Failed to send PQRS response notification', [
                'pqrs_id' => $pqrs->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send new Compra notification to assigned agent
     *
     * @param \App\Model\Entity\Compra $compra Compra entity
     * @return bool Success status
     */
    public function sendNewCompraNotification($compra): bool
    {
        // Load to check assignee
        $comprasTable = $this->fetchTable('Compras');
        $compra = $comprasTable->get($compra->id, contain: ['Assignees']);

        // Skip if no assignee
        if (!$compra->assignee || !$compra->assignee->email) {
            Log::info('No assignee for compra, skipping email notification', [
                'compra_id' => $compra->id,
            ]);
            return true;
        }

        // Build extra variables
        $slaDate = $compra->sla_due_date
            ? $this->renderer->formatDate($compra->sla_due_date)
            : 'No definido';

        $extraVars = [
            'assignee_name' => $compra->assignee->name,
            'priority' => ucfirst($compra->priority),
            'sla_due_date' => $slaDate,
        ];

        return $this->sendGenericTemplateEmail('compra', 'nueva_compra', $compra, $extraVars);
    }

    /**
     * Send Compra status change notification
     *
     * @param \App\Model\Entity\Compra $compra Compra entity
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @return bool Success status
     */
    public function sendCompraStatusChangeNotification($compra, string $oldStatus, string $newStatus): bool
    {
        // Load compra with associations to get assignee
        $comprasTable = $this->fetchTable('Compras');
        $compra = $comprasTable->get($compra->id, contain: ['Requesters', 'Assignees']);

        return $this->sendGenericTemplateEmail('compra', 'compra_estado', $compra, [
            'old_status' => $this->renderer->getStatusLabel($oldStatus),
            'new_status' => $this->renderer->getStatusLabel($newStatus),
            'assignee_name' => $compra->assignee ? $compra->assignee->name : 'No asignado',
            'updated_date' => $this->renderer->formatDate($compra->modified),
        ]);
    }

    /**
     * Send Compra new comment notification
     *
     * @param \App\Model\Entity\Compra $compra Compra entity
     * @param \App\Model\Entity\ComprasComment $comment Comment entity
     * @return bool Success status
     */
    public function sendCompraCommentNotification($compra, $comment): bool
    {
        try {
            // Only send for public comments
            if ($comment->comment_type !== 'public') {
                return true;
            }

            // Load entities with associations
            $comprasTable = $this->fetchTable('Compras');
            $compra = $comprasTable->get($compra->id, contain: ['Requesters', 'ComprasAttachments']);

            $commentsTable = $this->fetchTable('ComprasComments');
            $comment = $commentsTable->get($comment->id, contain: ['Users']);

            // Get comment attachments (non-inline only)
            $commentAttachments = [];
            if (!empty($compra->compras_attachments)) {
                foreach ($compra->compras_attachments as $attachment) {
                    if ($attachment->compras_comment_id === $comment->id && !$attachment->is_inline) {
                        $commentAttachments[] = $attachment;
                    }
                }
            }

            // Get template from database
            $template = $this->getTemplate('compra_comentario');
            if (!$template) {
                Log::error('Email template not found: compra_comentario');
                return false;
            }

            $author = $comment->user ? $comment->user->name : 'Sistema';

            // Get agent profile image URL
            $userHelper = new \App\View\Helper\UserHelper($this->getView());
            $agentProfileImageUrl = $comment->user && $comment->user->profile_image
                ? $userHelper->profileImage($comment->user->profile_image)
                : $userHelper->defaultAvatar();

            // Convert relative URL to absolute URL for email
            $agentProfileImageUrl = $this->getAbsoluteUrl($agentProfileImageUrl);

            // Replace variables in subject and body
            $variables = array_merge($this->getSystemVariables(), [
                'compra_number' => $compra->compra_number,
                'subject' => $compra->subject,
                'requester_name' => $compra->requester->name,
                'comment_author' => $author,
                'comment_body' => $comment->body,
                'attachments_list' => $this->renderer->renderAttachmentsHtml($commentAttachments),
                'compra_url' => $this->getCompraUrl($compra->id),
                'system_title' => 'Sistema de Compras',
                'agent_profile_image_url' => $agentProfileImageUrl,
                'agent_name' => $author,
            ]);

            $subject = $this->replaceVariables($template->subject, $variables);
            $body = $this->replaceVariables($template->body_html, $variables);

            // Send email to requester with attachments
            return $this->sendEmail($compra->requester->email, $subject, $body, $commentAttachments);
        } catch (\Exception $e) {
            Log::error('Failed to send compra comment notification', [
                'compra_id' => $compra->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send unified Compra response notification (comment + status change)
     *
     * This method combines comment and status change into a single email
     * to avoid sending multiple notifications to the requester.
     *
     * @param \App\Model\Entity\Compra $compra Compra entity
     * @param \App\Model\Entity\ComprasComment $comment Comment entity
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @return bool Success status
     */
    public function sendCompraResponseNotification($compra, $comment, string $oldStatus, string $newStatus, array $additionalTo = [], array $additionalCc = []): bool
    {
        try {
            // Load entities with associations
            $comprasTable = $this->fetchTable('Compras');
            $compra = $comprasTable->get($compra->id, contain: ['Requesters', 'Assignees', 'ComprasAttachments']);

            $commentsTable = $this->fetchTable('ComprasComments');
            $comment = $commentsTable->get($comment->id, contain: ['Users']);

            // Get comment attachments (non-inline only)
            $commentAttachments = [];
            if (!empty($compra->compras_attachments)) {
                foreach ($compra->compras_attachments as $attachment) {
                    if ($attachment->compra_comment_id === $comment->id && !$attachment->is_inline) {
                        $commentAttachments[] = $attachment;
                    }
                }
            }

            // Get unified response template
            $template = $this->getTemplate('compra_respuesta');
            if (!$template) {
                Log::error('Email template not found: compra_respuesta');
                return false;
            }

            // Check if status actually changed
            $hasStatusChange = ($oldStatus !== $newStatus);
            $assigneeName = $compra->assignee ? $compra->assignee->name : 'No asignado';

            // Build status change HTML section (only if status changed)
            $statusChangeSection = '';
            if ($hasStatusChange) {
                $statusChangeSection = $this->renderer->renderStatusChangeHtml($oldStatus, $newStatus, $assigneeName);
            }

            // Get agent profile image URL
            $userHelper = new \App\View\Helper\UserHelper($this->getView());
            $agentProfileImageUrl = $comment->user && $comment->user->profile_image
                ? $userHelper->profileImage($comment->user->profile_image)
                : $userHelper->defaultAvatar();

            // Convert relative URL to absolute URL for email
            $agentProfileImageUrl = $this->getAbsoluteUrl($agentProfileImageUrl);

            // Replace variables
            $variables = [
                'compra_number' => $compra->compra_number,
                'subject' => $compra->subject,
                'requester_name' => $compra->requester->name,
                'comment_author' => $comment->user->name,
                'comment_body' => $comment->body,
                'attachments_list' => $this->renderer->renderAttachmentsHtml($commentAttachments),
                'status_change_section' => $statusChangeSection,
                'old_status' => $this->renderer->getStatusLabel($oldStatus),
                'old_status_key' => $oldStatus,
                'new_status' => $this->renderer->getStatusLabel($newStatus),
                'new_status_key' => $newStatus,
                'assignee_name' => $assigneeName,
                'updated_date' => $this->renderer->formatDate($compra->modified),
                'compra_url' => $this->getCompraUrl($compra->id),
                'system_title' => 'Sistema de Compras',
                'agent_profile_image_url' => $agentProfileImageUrl,
                'agent_name' => $comment->user->name,
            ];

            $subject = $this->replaceVariables($template->subject, $variables);
            $body = $this->replaceVariables($template->body_html, $variables);

            // Send email to requester with attachments and additional recipients
            return $this->sendEmail($compra->requester->email, $subject, $body, $commentAttachments, $additionalTo, $additionalCc);
        } catch (\Exception $e) {
            Log::error('Failed to send compra response notification', [
                'compra_id' => $compra->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get Compra URL
     *
     * @param int $id Compra ID
     * @return string Full URL
     */
    private function getCompraUrl(int $id): string
    {
        $baseUrl = Configure::read('App.fullBaseUrl', 'http://localhost:8765');
        return $baseUrl . '/compras/view/' . $id;
    }

    /**
     * Get a View instance for helpers
     *
     * @return \Cake\View\View
     */
    private function getView(): \Cake\View\View
    {
        return new \Cake\View\View();
    }

    /**
     * Convert relative URL to absolute URL for email
     *
     * @param string $relativeUrl Relative URL (e.g., /uploads/profile_images/user_1.jpg)
     * @return string Absolute URL (e.g., http://localhost:8765/uploads/profile_images/user_1.jpg)
     */
    private function getAbsoluteUrl(string $relativeUrl): string
    {
        // If it's already an absolute URL or data URI, return as-is
        if (
            str_starts_with($relativeUrl, 'http://') ||
            str_starts_with($relativeUrl, 'https://') ||
            str_starts_with($relativeUrl, 'data:')
        ) {
            return $relativeUrl;
        }

        // Get base URL from request or configuration
        $baseUrl = \Cake\Routing\Router::url('/', true);

        // Remove trailing slash from base URL
        $baseUrl = rtrim($baseUrl, '/');

        // Ensure relative URL starts with /
        if (!str_starts_with($relativeUrl, '/')) {
            $relativeUrl = '/' . $relativeUrl;
        }

        return $baseUrl . $relativeUrl;
    }

    /**
     * Send email using template (generic for all entity types)
     *
     * INTERNAL METHOD: Consolidates duplicate notification logic
     *
     * @param string $entityType 'ticket', 'pqrs', 'compra'
     * @param string $templateKey Template key from database
     * @param \Cake\Datasource\EntityInterface $entity Entity instance
     * @param array $extraVariables Additional template variables
     * @param array $attachments Attachment entities
     * @param array $additionalTo Additional To recipients
     * @param array $additionalCc Additional CC recipients
     * @return bool Success status
     */
    private function sendGenericTemplateEmail(
        string $entityType,
        string $templateKey,
        \Cake\Datasource\EntityInterface $entity,
        array $extraVariables = [],
        array $attachments = [],
        array $additionalTo = [],
        array $additionalCc = []
    ): bool {
        try {
            // Load entity with associations
            $entity = $this->loadEntityWithAssociations($entityType, $entity);

            // Get template
            $template = $this->getTemplate($templateKey);
            if (!$template) {
                Log::error("Email template not found: {$templateKey}");
                return false;
            }

            // Build variables
            $variables = $this->buildTemplateVariables($entityType, $entity, $extraVariables);

            // Replace variables
            $subject = $this->replaceVariables($template->subject, $variables);
            $body = $this->replaceVariables($template->body_html, $variables);

            // Get recipient
            $recipientEmail = $this->getRecipientEmail($entityType, $entity);

            // Send email
            return $this->sendEmail($recipientEmail, $subject, $body, $attachments, $additionalTo, $additionalCc);

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
     * Load entity with proper associations based on entity type
     *
     * @param string $entityType 'ticket', 'pqrs', 'compra'
     * @param \Cake\Datasource\EntityInterface $entity Entity instance
     * @return \Cake\Datasource\EntityInterface Loaded entity
     */
    private function loadEntityWithAssociations(
        string $entityType,
        \Cake\Datasource\EntityInterface $entity
    ): \Cake\Datasource\EntityInterface {
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
        };

        $table = $this->fetchTable($config['table']);
        return $table->get($entity->id, contain: $config['contain']);
    }

    /**
     * Build template variables for entity type
     *
     * @param string $entityType 'ticket', 'pqrs', 'compra'
     * @param \Cake\Datasource\EntityInterface $entity Entity instance
     * @param array $extraVariables Additional variables to merge
     * @return array Template variables
     */
    private function buildTemplateVariables(
        string $entityType,
        \Cake\Datasource\EntityInterface $entity,
        array $extraVariables
    ): array {
        $systemVars = $this->getSystemVariables();

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
        };

        return array_merge($systemVars, $entityVars, $extraVariables);
    }

    /**
     * Get recipient email for entity type
     *
     * @param string $entityType 'ticket', 'pqrs', 'compra'
     * @param \Cake\Datasource\EntityInterface $entity Entity instance
     * @return string Recipient email address
     */
    private function getRecipientEmail(
        string $entityType,
        \Cake\Datasource\EntityInterface $entity
    ): string {
        return match ($entityType) {
            'ticket' => $entity->requester->email ?? '',
            'pqrs' => $entity->requester_email ?? '',
            'compra' => $entity->requester->email ?? '',
        };
    }
}
