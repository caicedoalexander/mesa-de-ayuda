<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Log\Log;
use Cake\I18n\FrozenTime;
use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Ticket Service
 *
 * Handles core ticket business logic:
 * - Creating tickets from email
 * - Managing ticket lifecycle
 * - Status changes
 * - Comments
 */
class TicketService
{
    use LocatorAwareTrait;

    private AttachmentService $attachmentService;
    private EmailService $emailService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->attachmentService = new AttachmentService();
        $this->emailService = new EmailService();
    }

    /**
     * Create ticket from email data
     *
     * @param array $emailData Parsed email data from GmailService
     * @return \App\Model\Entity\Ticket|null Created ticket or null on failure
     */
    public function createFromEmail(array $emailData): ?\App\Model\Entity\Ticket
    {
        $ticketsTable = $this->fetchTable('Tickets');
        $usersTable = $this->fetchTable('Users');

        // Check if ticket already exists
        if (!empty($emailData['gmail_message_id'])) {
            $existing = $ticketsTable->find()
                ->where(['gmail_message_id' => $emailData['gmail_message_id']])
                ->first();

            if ($existing) {
                Log::info('Ticket already exists for Gmail message: ' . $emailData['gmail_message_id']);
                return $existing;
            }
        }

        // Extract email address from From header
        $gmailService = new GmailService();
        $fromEmail = $gmailService->extractEmailAddress($emailData['from']);
        $fromName = $gmailService->extractName($emailData['from']);

        // Find or create user
        $user = $this->findOrCreateUser($fromEmail, $fromName);

        if (!$user) {
            Log::error('Failed to create user for email: ' . $fromEmail);
            return null;
        }

        // Sanitize HTML content
        $description = $this->sanitizeHtml($emailData['body_html'] ?: $emailData['body_text']);

        // Generate ticket number
        $ticketNumber = $this->generateTicketNumber();

        // Ensure subject is not empty
        $subject = trim($emailData['subject'] ?? '');
        if (empty($subject)) {
            $subject = '(Sin asunto)';
        }

        // Create ticket
        $ticket = $ticketsTable->newEntity([
            'ticket_number' => $ticketNumber,
            'gmail_message_id' => $emailData['gmail_message_id'] ?? null,
            'gmail_thread_id' => $emailData['gmail_thread_id'] ?? null,
            'subject' => $subject,
            'description' => $description,
            'status' => 'nuevo',
            'priority' => 'media',
            'requester_id' => $user->id,
            'channel' => 'email',
            'source_email' => $fromEmail,
        ]);

        if (!$ticketsTable->save($ticket)) {
            Log::error('Failed to save ticket', ['errors' => $ticket->getErrors()]);
            return null;
        }

        // Process attachments
        if (!empty($emailData['attachments'])) {
            $this->processEmailAttachments($ticket, $emailData['attachments'], $user->id);
        }

        // Process inline images
        if (!empty($emailData['inline_images'])) {
            $description = $this->processInlineImages($ticket, $emailData['inline_images'], $description, $user->id);

            // Update ticket description with corrected image paths
            $ticket->description = $description;
            $ticketsTable->save($ticket);
        }

        // Send notification to requester
        $this->emailService->sendNewTicketNotification($ticket);

        Log::info('Created ticket from email', [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'from' => $fromEmail,
        ]);

        return $ticket;
    }

    /**
     * Find existing user or create new one
     *
     * @param string $email User email
     * @param string $name User name
     * @return \App\Model\Entity\User|null
     */
    private function findOrCreateUser(string $email, string $name): ?\App\Model\Entity\User
    {
        $usersTable = $this->fetchTable('Users');

        $user = $usersTable->find()
            ->where(['email' => $email])
            ->first();

        if ($user) {
            return $user;
        }

        // Create new user with role 'requester' and null password
        $user = $usersTable->newEntity([
            'email' => $email,
            'name' => $name,
            'role' => 'requester',
            'password' => null,
            'is_active' => true,
        ]);

        if ($usersTable->save($user)) {
            Log::info('Auto-created user from email', ['email' => $email, 'name' => $name]);
            return $user;
        }

        Log::error('Failed to create user', ['email' => $email, 'errors' => $user->getErrors()]);
        return null;
    }

    /**
     * Generate unique ticket number in format TKT-YYYY-NNNNN
     *
     * @return string
     */
    private function generateTicketNumber(): string
    {
        $ticketsTable = $this->fetchTable('Tickets');
        $year = date('Y');
        $prefix = "TKT-{$year}-";

        // Get last ticket number for this year
        $lastTicket = $ticketsTable->find()
            ->where(['ticket_number LIKE' => $prefix . '%'])
            ->orderBy(['id' => 'DESC'])
            ->first();

        if ($lastTicket) {
            // Extract sequence number and increment
            $parts = explode('-', $lastTicket->ticket_number);
            $sequence = (int)$parts[2] + 1;
        } else {
            $sequence = 1;
        }

        return $prefix . str_pad((string)$sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Sanitize HTML content to prevent XSS while preserving email styles
     *
     * For emails from Gmail, we use minimal sanitization to preserve the original
     * appearance while still protecting against the most dangerous XSS attacks.
     *
     * @param string $html Raw HTML content
     * @param bool $isFromEmail Whether this is from an email (less strict) or user input (more strict)
     * @return string Sanitized HTML
     */
    private function sanitizeHtml(string $html, bool $isFromEmail = true): string
    {
        if ($isFromEmail) {
            // For emails: Minimal sanitization - only remove dangerous scripts
            // Remove <script> tags and javascript: protocols
            $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
            $html = preg_replace('/on\w+\s*=\s*["\']?[^"\']*["\']?/i', '', $html); // Remove onxxx event handlers
            $html = preg_replace('/javascript:/i', '', $html); // Remove javascript: protocols

            // Keep everything else: styles, classes, data attributes, etc.
            return $html;
        }

        // For user-generated content: Use HTMLPurifier with strict settings
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,a[href],strong,em,ul,ol,li,br,img[src|alt],h1,h2,h3,h4,blockquote,pre,code,span');
        $config->set('AutoFormat.AutoParagraph', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        $purifier = new HTMLPurifier($config);
        return $purifier->purify($html);
    }

    /**
     * Process email attachments
     *
     * @param \App\Model\Entity\Ticket $ticket Ticket entity
     * @param array $attachments Array of attachment data
     * @param int $userId User ID who uploaded
     * @return void
     */
    private function processEmailAttachments($ticket, array $attachments, int $userId): void
    {
        $gmailService = new GmailService($this->getGmailConfig());

        foreach ($attachments as $attachmentData) {
            try {
                // Download attachment from Gmail
                $content = $gmailService->downloadAttachment(
                    $ticket->gmail_message_id,
                    $attachmentData['attachment_id']
                );

                // Save attachment
                $this->attachmentService->saveAttachment(
                    $ticket->id,
                    null,
                    $attachmentData['filename'],
                    $content,
                    $attachmentData['mime_type'],
                    $userId
                );
            } catch (\Exception $e) {
                Log::error('Failed to process attachment', [
                    'ticket_id' => $ticket->id,
                    'filename' => $attachmentData['filename'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process inline images and replace cid: references with local paths
     *
     * @param \App\Model\Entity\Ticket $ticket Ticket entity
     * @param array $inlineImages Array of inline image data
     * @param string $html HTML content with cid: references
     * @param int $userId User ID who uploaded
     * @return string HTML with corrected image paths
     */
    private function processInlineImages($ticket, array $inlineImages, string $html, int $userId): string
    {
        $gmailService = new GmailService($this->getGmailConfig());

        foreach ($inlineImages as $imageData) {
            try {
                // Download image from Gmail
                $content = $gmailService->downloadAttachment(
                    $ticket->gmail_message_id,
                    $imageData['attachment_id']
                );

                // Check if it's actually an image MIME type
                $mimeType = $imageData['mime_type'];
                $isImage = str_starts_with($mimeType, 'image/');

                if ($isImage) {
                    // Save as inline image
                    $attachment = $this->attachmentService->saveInlineImage(
                        $ticket->id,
                        $imageData['filename'],
                        $content,
                        $mimeType,
                        $imageData['content_id'],
                        $userId
                    );

                    // Replace cid: reference with local path (case-insensitive, multiple formats)
                    if ($attachment) {
                        $contentId = $imageData['content_id'];
                        $localPath = '/uploads/attachments/' . $attachment->file_path;

                        // Replace various cid: formats that Gmail might use
                        $patterns = [
                            'cid:' . $contentId,           // cid:contentid
                            'cid://' . $contentId,         // cid://contentid
                            'CID:' . $contentId,           // CID:contentid (uppercase)
                            '"cid:' . $contentId . '"',    // "cid:contentid"
                            '\'cid:' . $contentId . '\'',  // 'cid:contentid'
                        ];

                        foreach ($patterns as $pattern) {
                            $html = str_ireplace($pattern, $localPath, $html);
                        }

                        // Also replace URL-encoded versions
                        $html = str_ireplace(urlencode('cid:' . $contentId), $localPath, $html);
                    }
                } else {
                    // Not an image, but has content-id - save as regular attachment
                    Log::warning('File marked as inline but is not an image, saving as regular attachment', [
                        'filename' => $imageData['filename'],
                        'mime_type' => $mimeType,
                        'content_id' => $imageData['content_id'],
                    ]);

                    $this->attachmentService->saveAttachment(
                        $ticket->id,
                        null,
                        $imageData['filename'],
                        $content,
                        $mimeType,
                        $userId
                    );
                }
            } catch (\Exception $e) {
                Log::error('Failed to process inline image', [
                    'ticket_id' => $ticket->id,
                    'filename' => $imageData['filename'],
                    'content_id' => $imageData['content_id'] ?? 'N/A',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $html;
    }

    /**
     * Change ticket status
     *
     * @param \App\Model\Entity\Ticket $ticket Ticket entity
     * @param string $newStatus New status (nuevo, abierto, pendiente, resuelto)
     * @param int $userId User making the change
     * @param string|null $comment Optional comment
     * @return bool Success status
     */
    public function changeStatus($ticket, string $newStatus, int $userId, ?string $comment = null): bool
    {
        $ticketsTable = $this->fetchTable('Tickets');
        $ticketHistoryTable = $this->fetchTable('TicketHistory');
        $oldStatus = $ticket->status;

        $ticket->status = $newStatus;

        // Update timestamps based on status
        if ($newStatus === 'resuelto' && empty($ticket->resolved_at)) {
            $ticket->resolved_at = FrozenTime::now();
        }

        if ($newStatus === 'abierto' && empty($ticket->first_response_at)) {
            $ticket->first_response_at = FrozenTime::now();
        }

        if (!$ticketsTable->save($ticket)) {
            Log::error('Failed to change ticket status', ['ticket_id' => $ticket->id, 'errors' => $ticket->getErrors()]);
            return false;
        }

        // Log status change to history
        $ticketHistoryTable->logChange(
            $ticket->id,
            'status',
            $oldStatus,
            $newStatus,
            $userId,
            "Estado cambiado de '{$oldStatus}' a '{$newStatus}'"
        );

        // Create system comment
        $this->addComment(
            $ticket->id,
            $userId,
            "Estado cambiado de <strong>{$oldStatus}</strong> a <strong>{$newStatus}</strong>",
            'internal',
            true
        );

        // Add user comment if provided
        if ($comment) {
            $this->addComment($ticket->id, $userId, $comment, 'public', false);
        }

        // Send notification
        $this->emailService->sendStatusChangeNotification($ticket, $oldStatus, $newStatus);

        Log::info('Ticket status changed', [
            'ticket_id' => $ticket->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        return true;
    }

    /**
     * Add comment to ticket
     *
     * @param int $ticketId Ticket ID
     * @param int $userId User ID
     * @param string $body Comment body (HTML)
     * @param string $type Comment type (public, internal)
     * @param bool $isSystem Is system comment
     * @return \App\Model\Entity\TicketComment|null
     */
    public function addComment(int $ticketId, int $userId, string $body, string $type = 'public', bool $isSystem = false): ?\App\Model\Entity\TicketComment
    {
        $commentsTable = $this->fetchTable('TicketComments');

        // Sanitize HTML if not system comment
        if (!$isSystem) {
            $body = $this->sanitizeHtml($body);
        }

        $comment = $commentsTable->newEntity([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'comment_type' => $type,
            'body' => $body,
            'is_system_comment' => $isSystem,
        ]);

        if ($commentsTable->save($comment)) {
            // Send notification for public comments
            if ($type === 'public' && !$isSystem) {
                $ticket = $this->fetchTable('Tickets')->get($ticketId, contain: ['Requesters']);
                $this->emailService->sendNewCommentNotification($ticket, $comment);
            }

            return $comment;
        }

        Log::error('Failed to add comment', ['ticket_id' => $ticketId, 'errors' => $comment->getErrors()]);
        return null;
    }

    /**
     * Assign ticket to agent
     *
     * @param \App\Model\Entity\Ticket $ticket Ticket entity
     * @param int $agentId Agent user ID
     * @param int $currentUserId User making the assignment
     * @return bool Success status
     */
    public function assignTicket($ticket, int $agentId, int $currentUserId): bool
    {
        $ticketsTable = $this->fetchTable('Tickets');

        $oldAssignee = $ticket->assignee_id;
        $ticket->assignee_id = $agentId;

        if (!$ticketsTable->save($ticket)) {
            return false;
        }

        // Create system comment
        $usersTable = $this->fetchTable('Users');
        $agent = $usersTable->get($agentId);

        $this->addComment(
            $ticket->id,
            $currentUserId,
            "Ticket asignado a <strong>{$agent->name}</strong>",
            'internal',
            true
        );

        Log::info('Ticket assigned', [
            'ticket_id' => $ticket->id,
            'agent_id' => $agentId,
        ]);

        return true;
    }

    /**
     * Get Gmail configuration from system settings
     *
     * @return array
     */
    private function getGmailConfig(): array
    {
        $settingsTable = $this->fetchTable('SystemSettings');
        $settings = $settingsTable->find()
            ->select(['setting_key', 'setting_value'])
            ->toArray();

        $config = [];
        foreach ($settings as $setting) {
            $config[$setting->setting_key] = $setting->setting_value;
        }

        return [
            'refresh_token' => $config['gmail_refresh_token'] ?? null,
            'client_secret_path' => $config['gmail_client_secret_path'] ?? null,
        ];
    }
}
