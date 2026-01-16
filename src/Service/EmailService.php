<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Log\Log;
use Cake\Core\Configure;

/**
 * Email Service (Facade)
 *
 * Public API for sending email notifications for Tickets, PQRS, and Compras.
 * Delegates to EmailTemplateService and GenericEmailService for implementation.
 *
 * Architecture:
 * - EmailTemplateService: Template loading/rendering
 * - GenericEmailService: Low-level email sending via Gmail API
 * - EmailService (this): Business logic facade for notifications
 *
 * Refactored from 1,139 lines God Object to focused facade pattern.
 */
class EmailService
{
    use LocatorAwareTrait;

    private GenericEmailService $emailSender;
    private EmailTemplateService $templateService;
    private \App\Service\Renderer\NotificationRenderer $renderer;
    private ?array $systemConfig;

    /**
     * Constructor
     *
     * @param array|null $systemConfig Optional system configuration to avoid redundant DB queries
     * @param GenericEmailService|null $emailSender Optional email sender (for DI/testing)
     * @param EmailTemplateService|null $templateService Optional template service (for DI/testing)
     */
    public function __construct(
        ?array $systemConfig = null,
        ?GenericEmailService $emailSender = null,
        ?EmailTemplateService $templateService = null
    ) {
        $this->systemConfig = $systemConfig;
        $this->templateService = $templateService ?? new EmailTemplateService();
        $this->emailSender = $emailSender ?? new GenericEmailService($systemConfig, $this->templateService);
        $this->renderer = new \App\Service\Renderer\NotificationRenderer();
    }

    // ===================================================================
    // TICKET NOTIFICATIONS
    // ===================================================================

    /**
     * Send new ticket notification to requester
     *
     * Also notifies recipients in email_to and email_cc if ticket was created from email.
     * Excludes requester and system email to avoid duplicates and loops.
     *
     * @param EntityInterface $ticket Ticket entity
     * @return bool Success status
     */
    public function sendNewTicketNotification($ticket): bool
    {
        // Load ticket with associations
        $ticketsTable = $this->fetchTable('Tickets');
        $ticket = $ticketsTable->get($ticket->id, contain: ['Requesters']);

        // Get system email to exclude from notifications
        $systemEmail = strtolower($this->systemConfig['gmail_user_email'] ?? $this->getSystemEmail());

        // Extract additional recipients from ticket's email_to and email_cc
        [$additionalTo, $additionalCc] = $this->parseTicketRecipients($ticket, $systemEmail);

        // Send using generic service
        return $this->emailSender->sendTemplateEmail(
            'ticket',
            'nuevo_ticket',
            $ticket,
            [],
            [],
            $additionalTo,
            $additionalCc
        );
    }

    /**
     * Send status change notification
     *
     * @param EntityInterface $ticket Ticket entity
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @return bool Success status
     */
    public function sendStatusChangeNotification($ticket, string $oldStatus, string $newStatus): bool
    {
        // Load ticket with associations
        $ticketsTable = $this->fetchTable('Tickets');
        $ticket = $ticketsTable->get($ticket->id, contain: ['Requesters', 'Assignees']);

        $assigneeName = $ticket->assignee ? $ticket->assignee->name : 'No asignado';

        return $this->emailSender->sendTemplateEmail('ticket', 'ticket_estado', $ticket, [
            'status_change_section' => $this->renderer->renderStatusChangeHtml($oldStatus, $newStatus, $assigneeName),
        ]);
    }

    /**
     * Send new comment notification
     *
     * @param EntityInterface $ticket Ticket entity
     * @param EntityInterface $comment Comment entity
     * @param array $additionalTo Additional To recipients
     * @param array $additionalCc Additional CC recipients
     * @return bool Success status
     */
    public function sendNewCommentNotification($ticket, $comment, array $additionalTo = [], array $additionalCc = []): bool
    {
        try {
            // Load entities with associations
            $ticketsTable = $this->fetchTable('Tickets');
            $ticket = $ticketsTable->get($ticket->id, contain: ['Requesters', 'Assignees', 'Attachments']);

            $commentsTable = $this->fetchTable('TicketComments');
            $comment = $commentsTable->get($comment->id, contain: ['Users']);

            // Get comment attachments (non-inline only)
            $commentAttachments = $this->getCommentAttachments($ticket->attachments ?? [], $comment->id);

            // Get template
            $template = $this->templateService->getTemplate('nuevo_comentario');
            if (!$template) {
                Log::error('Email template not found: nuevo_comentario');
                return false;
            }

            // Build variables
            $variables = array_merge($this->templateService->getSystemVariables($this->systemConfig), [
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'comment_author' => $comment->user->name,
                'comment_body' => $comment->body,
                'attachments_list' => $this->renderer->renderAttachmentsHtml($commentAttachments),
                'ticket_url' => $this->renderer->getTicketUrl($ticket->id),
                'agent_profile_image_url' => $this->getAgentProfileImageUrl($comment->user),
                'agent_name' => $comment->user->name,
            ]);

            // Render template
            $rendered = $this->templateService->renderTemplate($template, $variables);

            // Send email
            return $this->emailSender->sendEmail(
                $ticket->requester->email,
                $rendered['subject'],
                $rendered['body'],
                $commentAttachments,
                $additionalTo,
                $additionalCc
            );
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
     * @param EntityInterface $ticket Ticket entity
     * @param EntityInterface $comment Comment entity
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @param array $additionalTo Additional To recipients
     * @param array $additionalCc Additional CC recipients
     * @return bool Success status
     */
    public function sendTicketResponseNotification(
        $ticket,
        $comment,
        string $oldStatus,
        string $newStatus,
        array $additionalTo = [],
        array $additionalCc = []
    ): bool {
        try {
            // Load entities with associations
            $ticketsTable = $this->fetchTable('Tickets');
            $ticket = $ticketsTable->get($ticket->id, contain: ['Requesters', 'Assignees', 'Attachments']);

            $commentsTable = $this->fetchTable('TicketComments');
            $comment = $commentsTable->get($comment->id, contain: ['Users']);

            // Get comment attachments
            $commentAttachments = $this->getCommentAttachments($ticket->attachments ?? [], $comment->id);

            // Get template
            $template = $this->templateService->getTemplate('ticket_respuesta');
            if (!$template) {
                Log::error('Email template not found: ticket_respuesta');
                return false;
            }

            // Build status change section (only if status actually changed)
            $hasStatusChange = ($oldStatus !== $newStatus);
            $assigneeName = $ticket->assignee ? $ticket->assignee->name : 'No asignado';
            $statusChangeSection = $hasStatusChange
                ? $this->renderer->renderStatusChangeHtml($oldStatus, $newStatus, $assigneeName)
                : '';

            // Build variables
            $variables = [
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'requester_name' => $ticket->requester->name,
                'comment_author' => $comment->user->name,
                'comment_body' => $comment->body,
                'attachments_list' => $this->renderer->renderAttachmentsHtml($commentAttachments),
                'status_change_section' => $statusChangeSection,
                'ticket_url' => $this->renderer->getTicketUrl($ticket->id),
                'system_title' => 'Sistema de Soporte',
                'agent_profile_image_url' => $this->getAgentProfileImageUrl($comment->user),
                'agent_name' => $comment->user->name,
            ];

            // Render template
            $rendered = $this->templateService->renderTemplate($template, $variables);

            // Send email
            return $this->emailSender->sendEmail(
                $ticket->requester->email,
                $rendered['subject'],
                $rendered['body'],
                $commentAttachments,
                $additionalTo,
                $additionalCc
            );
        } catch (\Exception $e) {
            Log::error('Failed to send ticket response notification', [
                'ticket_id' => $ticket->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ===================================================================
    // PQRS NOTIFICATIONS
    // ===================================================================

    /**
     * Send new PQRS notification to requester
     *
     * @param EntityInterface $pqrs PQRS entity
     * @return bool Success status
     */
    public function sendNewPqrsNotification($pqrs): bool
    {
        return $this->emailSender->sendTemplateEmail('pqrs', 'nuevo_pqrs', $pqrs, [
            'system_title' => 'Sistema de Atención al Cliente',
        ]);
    }

    /**
     * Send PQRS status change notification
     *
     * @param EntityInterface $pqrs PQRS entity
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @return bool Success status
     */
    public function sendPqrsStatusChangeNotification($pqrs, string $oldStatus, string $newStatus): bool
    {
        // Load PQRS with assignee association
        $pqrsTable = $this->fetchTable('Pqrs');
        $pqrs = $pqrsTable->get($pqrs->id, contain: ['Assignees']);

        $assigneeName = $pqrs->assignee ? $pqrs->assignee->name : 'No asignado';

        return $this->emailSender->sendTemplateEmail('pqrs', 'pqrs_estado', $pqrs, [
            'status_change_section' => $this->renderer->renderStatusChangeHtml($oldStatus, $newStatus, $assigneeName),
            'system_title' => 'Sistema de Atención al Cliente',
        ]);
    }

    /**
     * Send PQRS new comment notification
     *
     * @param EntityInterface $pqrs PQRS entity
     * @param EntityInterface $comment Comment entity
     * @param array $additionalTo Additional To recipients
     * @param array $additionalCc Additional CC recipients
     * @return bool Success status
     */
    public function sendPqrsNewCommentNotification($pqrs, $comment, array $additionalTo = [], array $additionalCc = []): bool
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

            // Get comment attachments
            $commentAttachments = $this->getCommentAttachments($pqrs->pqrs_attachments ?? [], $comment->id, 'pqrs_comment_id');

            // Get template
            $template = $this->templateService->getTemplate('pqrs_comentario');
            if (!$template) {
                Log::error('Email template not found: pqrs_comentario');
                return false;
            }

            $author = $comment->user ? $comment->user->name : 'Sistema';

            // Build variables
            $variables = [
                'pqrs_number' => $pqrs->pqrs_number,
                'subject' => $pqrs->subject,
                'comment_author' => $author,
                'comment_body' => $comment->body,
                'attachments_list' => $this->renderer->renderAttachmentsHtml($commentAttachments),
                'system_title' => 'Sistema de Atención al Cliente',
                'agent_profile_image_url' => $this->getAgentProfileImageUrl($comment->user),
                'agent_name' => $author,
            ];

            // Render template
            $rendered = $this->templateService->renderTemplate($template, $variables);

            // Send email
            return $this->emailSender->sendEmail(
                $pqrs->requester_email,
                $rendered['subject'],
                $rendered['body'],
                $commentAttachments,
                $additionalTo,
                $additionalCc
            );
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
     * @param EntityInterface $pqrs PQRS entity
     * @param EntityInterface $comment Comment entity
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @param array $additionalTo Additional To recipients
     * @param array $additionalCc Additional CC recipients
     * @return bool Success status
     */
    public function sendPqrsResponseNotification(
        $pqrs,
        $comment,
        string $oldStatus,
        string $newStatus,
        array $additionalTo = [],
        array $additionalCc = []
    ): bool {
        try {
            // Load entities with associations
            $pqrsTable = $this->fetchTable('Pqrs');
            $pqrs = $pqrsTable->get($pqrs->id, contain: ['Assignees', 'PqrsAttachments']);

            $commentsTable = $this->fetchTable('PqrsComments');
            $comment = $commentsTable->get($comment->id, contain: ['Users']);

            // Get comment attachments
            $commentAttachments = $this->getCommentAttachments(
                $pqrs->pqrs_attachments ?? [],
                $comment->id,
                'pqrs_comment_id'
            );

            // Get template
            $template = $this->templateService->getTemplate('pqrs_respuesta');
            if (!$template) {
                Log::error('Email template not found: pqrs_respuesta');
                return false;
            }

            // Build status change section
            $hasStatusChange = ($oldStatus !== $newStatus);
            $assigneeName = $pqrs->assignee ? $pqrs->assignee->name : 'No asignado';
            $statusChangeSection = $hasStatusChange
                ? $this->renderer->renderStatusChangeHtml($oldStatus, $newStatus, $assigneeName)
                : '';

            // Build variables
            $variables = [
                'pqrs_number' => $pqrs->pqrs_number,
                'pqrs_type' => $this->renderer->getTypeLabel($pqrs->type),
                'subject' => $pqrs->subject,
                'requester_name' => $pqrs->requester_name,
                'comment_author' => $comment->user->name,
                'comment_body' => $comment->body,
                'attachments_list' => $this->renderer->renderAttachmentsHtml($commentAttachments),
                'status_change_section' => $statusChangeSection,
                'system_title' => 'Sistema de Atención al Cliente',
                'agent_profile_image_url' => $this->getAgentProfileImageUrl($comment->user),
                'agent_name' => $comment->user->name,
            ];

            // Render template
            $rendered = $this->templateService->renderTemplate($template, $variables);

            // Send email
            return $this->emailSender->sendEmail(
                $pqrs->requester_email,
                $rendered['subject'],
                $rendered['body'],
                $commentAttachments,
                $additionalTo,
                $additionalCc
            );
        } catch (\Exception $e) {
            Log::error('Failed to send PQRS response notification', [
                'pqrs_id' => $pqrs->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ===================================================================
    // COMPRAS NOTIFICATIONS
    // ===================================================================

    /**
     * Send new Compra notification to assigned agent
     *
     * @param EntityInterface $compra Compra entity
     * @return bool Success status
     */
    public function sendNewCompraNotification($compra): bool
    {
        // Load to check assignee
        $comprasTable = $this->fetchTable('Compras');
        $compra = $comprasTable->get($compra->id, contain: ['Assignees']);

        // Skip if no assignee
        if (!$compra->assignee || !$compra->assignee->email) {
            Log::info('No assignee for compra, skipping email notification', ['compra_id' => $compra->id]);
            return true;
        }

        // Build extra variables
        $slaDate = $compra->sla_due_date
            ? $this->renderer->formatDate($compra->sla_due_date)
            : 'No definido';

        return $this->emailSender->sendTemplateEmail('compra', 'nueva_compra', $compra, [
            'assignee_name' => $compra->assignee->name,
            'priority' => ucfirst($compra->priority),
            'sla_due_date' => $slaDate,
        ]);
    }

    /**
     * Send Compra status change notification
     *
     * @param EntityInterface $compra Compra entity
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @return bool Success status
     */
    public function sendCompraStatusChangeNotification($compra, string $oldStatus, string $newStatus): bool
    {
        // Load compra with associations
        $comprasTable = $this->fetchTable('Compras');
        $compra = $comprasTable->get($compra->id, contain: ['Requesters', 'Assignees']);

        $assigneeName = $compra->assignee ? $compra->assignee->name : 'No asignado';

        return $this->emailSender->sendTemplateEmail('compra', 'compra_estado', $compra, [
            'status_change_section' => $this->renderer->renderStatusChangeHtml($oldStatus, $newStatus, $assigneeName),
        ]);
    }

    /**
     * Send Compra new comment notification
     *
     * @param EntityInterface $compra Compra entity
     * @param EntityInterface $comment Comment entity
     * @param array $additionalTo Additional To recipients
     * @param array $additionalCc Additional CC recipients
     * @return bool Success status
     */
    public function sendCompraCommentNotification($compra, $comment, array $additionalTo = [], array $additionalCc = []): bool
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

            // Get comment attachments
            $commentAttachments = $this->getCommentAttachments(
                $compra->compras_attachments ?? [],
                $comment->id,
                'compras_comment_id'
            );

            // Get template
            $template = $this->templateService->getTemplate('compra_comentario');
            if (!$template) {
                Log::error('Email template not found: compra_comentario');
                return false;
            }

            $author = $comment->user ? $comment->user->name : 'Sistema';

            // Build variables
            $variables = array_merge($this->templateService->getSystemVariables($this->systemConfig), [
                'compra_number' => $compra->compra_number,
                'subject' => $compra->subject,
                'requester_name' => $compra->requester->name,
                'comment_author' => $author,
                'comment_body' => $comment->body,
                'attachments_list' => $this->renderer->renderAttachmentsHtml($commentAttachments),
                'compra_url' => $this->getCompraUrl($compra->id),
                'system_title' => 'Sistema de Compras',
                'agent_profile_image_url' => $this->getAgentProfileImageUrl($comment->user),
                'agent_name' => $author,
            ]);

            // Render template
            $rendered = $this->templateService->renderTemplate($template, $variables);

            // Send email
            return $this->emailSender->sendEmail(
                $compra->requester->email,
                $rendered['subject'],
                $rendered['body'],
                $commentAttachments,
                $additionalTo,
                $additionalCc
            );
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
     * @param EntityInterface $compra Compra entity
     * @param EntityInterface $comment Comment entity
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @param array $additionalTo Additional To recipients
     * @param array $additionalCc Additional CC recipients
     * @return bool Success status
     */
    public function sendCompraResponseNotification(
        $compra,
        $comment,
        string $oldStatus,
        string $newStatus,
        array $additionalTo = [],
        array $additionalCc = []
    ): bool {
        try {
            // Load entities with associations
            $comprasTable = $this->fetchTable('Compras');
            $compra = $comprasTable->get($compra->id, contain: ['Requesters', 'Assignees', 'ComprasAttachments']);

            $commentsTable = $this->fetchTable('ComprasComments');
            $comment = $commentsTable->get($comment->id, contain: ['Users']);

            // Get comment attachments
            $commentAttachments = $this->getCommentAttachments(
                $compra->compras_attachments ?? [],
                $comment->id,
                'compra_comment_id'
            );

            // Get template
            $template = $this->templateService->getTemplate('compra_respuesta');
            if (!$template) {
                Log::error('Email template not found: compra_respuesta');
                return false;
            }

            // Build status change section
            $hasStatusChange = ($oldStatus !== $newStatus);
            $assigneeName = $compra->assignee ? $compra->assignee->name : 'No asignado';
            $statusChangeSection = $hasStatusChange
                ? $this->renderer->renderStatusChangeHtml($oldStatus, $newStatus, $assigneeName)
                : '';

            // Build variables
            $variables = [
                'compra_number' => $compra->compra_number,
                'subject' => $compra->subject,
                'requester_name' => $compra->requester->name,
                'comment_author' => $comment->user->name,
                'comment_body' => $comment->body,
                'attachments_list' => $this->renderer->renderAttachmentsHtml($commentAttachments),
                'status_change_section' => $statusChangeSection,
                'compra_url' => $this->getCompraUrl($compra->id),
                'system_title' => 'Sistema de Compras',
                'agent_profile_image_url' => $this->getAgentProfileImageUrl($comment->user),
                'agent_name' => $comment->user->name,
            ];

            // Render template
            $rendered = $this->templateService->renderTemplate($template, $variables);

            // Send email
            return $this->emailSender->sendEmail(
                $compra->requester->email,
                $rendered['subject'],
                $rendered['body'],
                $commentAttachments,
                $additionalTo,
                $additionalCc
            );
        } catch (\Exception $e) {
            Log::error('Failed to send compra response notification', [
                'compra_id' => $compra->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ===================================================================
    // PRIVATE HELPER METHODS
    // ===================================================================

    /**
     * Parse ticket email_to and email_cc recipients, excluding duplicates
     *
     * @param EntityInterface $ticket Ticket entity
     * @param string $systemEmail System email to exclude
     * @return array [additionalTo, additionalCc]
     */
    private function parseTicketRecipients($ticket, string $systemEmail): array
    {
        $additionalTo = [];
        $additionalCc = [];
        $requesterEmail = strtolower($ticket->requester->email);

        // Parse email_to JSON array
        if (!empty($ticket->email_to)) {
            $emailTo = is_string($ticket->email_to) ? json_decode($ticket->email_to, true) : $ticket->email_to;
            if (is_array($emailTo)) {
                foreach ($emailTo as $recipient) {
                    if (!empty($recipient['email'])) {
                        $recipientEmail = strtolower($recipient['email']);
                        // Exclude requester and system email
                        if ($recipientEmail !== $requesterEmail && $recipientEmail !== $systemEmail) {
                            $additionalTo[] = $recipient;
                        }
                    }
                }
            }
        }

        // Parse email_cc JSON array
        if (!empty($ticket->email_cc)) {
            $emailCc = is_string($ticket->email_cc) ? json_decode($ticket->email_cc, true) : $ticket->email_cc;
            if (is_array($emailCc)) {
                foreach ($emailCc as $recipient) {
                    if (!empty($recipient['email'])) {
                        $recipientEmail = strtolower($recipient['email']);
                        // Exclude requester and system email
                        if ($recipientEmail !== $requesterEmail && $recipientEmail !== $systemEmail) {
                            $additionalCc[] = $recipient;
                        }
                    }
                }
            }
        }

        return [$additionalTo, $additionalCc];
    }

    /**
     * Get comment attachments (non-inline only)
     *
     * @param array $allAttachments All attachments from entity
     * @param int $commentId Comment ID to filter by
     * @param string $commentIdField Field name for comment ID (default: 'comment_id')
     * @return array Filtered attachments
     */
    private function getCommentAttachments(array $allAttachments, int $commentId, string $commentIdField = 'comment_id'): array
    {
        $commentAttachments = [];
        foreach ($allAttachments as $attachment) {
            if ($attachment->$commentIdField === $commentId && !$attachment->is_inline) {
                $commentAttachments[] = $attachment;
            }
        }
        return $commentAttachments;
    }

    /**
     * Get agent profile image URL (absolute URL for email)
     *
     * @param object|null $user User entity
     * @return string Absolute profile image URL
     */
    private function getAgentProfileImageUrl($user): string
    {
        $userHelper = new \App\View\Helper\UserHelper($this->getView());
        $relativeUrl = $user && $user->profile_image
            ? $userHelper->profileImage($user->profile_image)
            : $userHelper->defaultAvatar();

        return $this->getAbsoluteUrl($relativeUrl);
    }

    /**
     * Convert relative URL to absolute URL for email
     *
     * @param string $relativeUrl Relative URL
     * @return string Absolute URL
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

        // Get base URL
        $baseUrl = \Cake\Routing\Router::url('/', true);
        $baseUrl = rtrim($baseUrl, '/');

        // Ensure relative URL starts with /
        if (!str_starts_with($relativeUrl, '/')) {
            $relativeUrl = '/' . $relativeUrl;
        }

        return $baseUrl . $relativeUrl;
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
     * Get system email from config or database
     *
     * @return string System email
     */
    private function getSystemEmail(): string
    {
        if ($this->systemConfig !== null && !empty($this->systemConfig['gmail_user_email'])) {
            return $this->systemConfig['gmail_user_email'];
        }

        $settingsTable = $this->fetchTable('SystemSettings');
        $setting = $settingsTable->find()
            ->where(['setting_key' => 'gmail_user_email'])
            ->first();

        return $setting ? $setting->setting_value : '';
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
}
