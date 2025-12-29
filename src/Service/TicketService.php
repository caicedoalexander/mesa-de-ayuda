<?php
declare(strict_types=1);

namespace App\Service;

use App\Utility\SettingsEncryptionTrait;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Log\Log;
use Cake\I18n\FrozenTime;
use HTMLPurifier;
use HTMLPurifier_Config;
use App\Service\Traits\EntityConversionTrait;

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
    use SettingsEncryptionTrait;
    use \App\Service\Traits\TicketSystemTrait;
    use \App\Service\Traits\NotificationDispatcherTrait;
    use \App\Service\Traits\GenericAttachmentTrait;
    use EntityConversionTrait;

    private EmailService $emailService;
    private WhatsappService $whatsappService;
    private ?N8nService $n8nService = null;
    private ?array $systemConfig = null;

    /**
     * Constructor
     *
     * @param array|null $systemConfig Optional system configuration to avoid redundant DB queries
     */
    public function __construct(?array $systemConfig = null)
    {
        $this->emailService = new EmailService($systemConfig);
        $this->whatsappService = new WhatsappService($systemConfig);
        $this->systemConfig = $systemConfig;
        // N8nService NOT initialized here - loaded lazily only when needed
    }

    /**
     * Get N8nService instance (lazy loading)
     *
     * @return N8nService
     */
    private function getN8nService(): N8nService
    {
        if ($this->n8nService === null) {
            $this->n8nService = new N8nService($this->systemConfig);
        }
        return $this->n8nService;
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

        // No sanitization as requested
        $description = $emailData['body_html'] ?: $emailData['body_text'];

        // Generate ticket number
        $ticketNumber = $ticketsTable->generateTicketNumber();

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
        assert($ticket instanceof \App\Model\Entity\Ticket);

        // Set email recipients directly (bypass marshalling to avoid validation issues)
        $ticket->email_to = !empty($emailData['email_to']) ? $emailData['email_to'] : null;
        $ticket->email_cc = !empty($emailData['email_cc']) ? $emailData['email_cc'] : null;

        if (!$ticketsTable->save($ticket)) {
            Log::error('Failed to save ticket', ['errors' => $ticket->getErrors()]);
            return null;
        }

        // Process attachments
        if (!empty($emailData['attachments'])) {
            $this->processEmailAttachments($ticket, $emailData['attachments'], $user->id);
        }

        // Send creation notifications (Email + WhatsApp)
        $this->dispatchCreationNotifications('ticket', $ticket);

        // Send n8n webhook for AI tag assignment (lazy loaded only when creating tickets)
        try {
            $this->getN8nService()->sendTicketCreatedWebhook($ticket);
        } catch (\Exception $e) {
            Log::warning('n8n webhook failed (non-blocking): ' . $e->getMessage());
            // Don't block ticket creation if webhook fails
        }

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

        // Split name into first and last name
        $nameParts = explode(' ', $name);
        $firstName = array_shift($nameParts);
        $lastName = implode(' ', $nameParts);

        if (empty($lastName)) {
            $lastName = $firstName; // Fallback if no last name
        }

        // Create new user with role 'requester' and null password
        $user = $usersTable->newEntity([
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => 'requester',
            'password' => null,
            'is_active' => true,
        ]);
        assert($user instanceof \App\Model\Entity\User);

        if ($usersTable->save($user)) {
            Log::info('Auto-created user from email', ['email' => $email, 'name' => $name]);
            return $user;
        }

        Log::error('Failed to create user', ['email' => $email, 'errors' => $user->getErrors()]);
        return null;
    }

    /**
     * Process email attachments (now using GenericAttachmentTrait)
     *
     * @param \Cake\Datasource\EntityInterface $ticket Ticket entity
     * @param array $attachments Array of attachment data
     * @param int $userId User ID who uploaded
     * @return void
     */
    private function processEmailAttachments(\Cake\Datasource\EntityInterface $ticket, array $attachments, int $userId): void
    {
        assert($ticket instanceof \App\Model\Entity\Ticket);
        $gmailService = new GmailService(GmailService::loadConfigFromDatabase());

        foreach ($attachments as $attachmentData) {
            try {
                // PERFORMANCE FIX: Reduced sleep from 1000ms to 200ms
                // Gmail API allows 250 requests/second, 200ms = 5 requests/second is safe
                // Previous: 10 files = 10 seconds, Now: 10 files = 2 seconds (80% faster)
                usleep(200000);

                // Download attachment from Gmail
                $content = $gmailService->downloadAttachment(
                    $ticket->gmail_message_id,
                    $attachmentData['attachment_id']
                );

                // Save attachment using GenericAttachmentTrait
                $this->saveAttachmentFromBinary(
                    'ticket',
                    $ticket,
                    $attachmentData['filename'],
                    $content,
                    $attachmentData['mime_type'],
                    null,  // comment_id
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
     * Save uploaded file (using GenericAttachmentTrait for form uploads)
     *
     * This method provides a consistent interface for ResponseService while leveraging
     * the robust security validation from GenericAttachmentTrait.
     *
     * @param int $ticketId Ticket ID
     * @param int|null $commentId Comment ID
     * @param \Psr\Http\Message\UploadedFileInterface $file Uploaded file
     * @param int $userId User ID
     * @return \App\Model\Entity\Attachment|null
     */
    public function saveUploadedFile(
        int $ticketId,
        ?int $commentId,
        \Psr\Http\Message\UploadedFileInterface $file,
        int $userId
    ): ?\App\Model\Entity\Attachment {
        $ticketsTable = $this->fetchTable('Tickets');
        $ticket = $ticketsTable->get($ticketId);
        assert($ticket instanceof \App\Model\Entity\Ticket);

        $result = $this->saveGenericUploadedFile('ticket', $ticket, $file, $commentId, $userId);
        assert($result instanceof \App\Model\Entity\Attachment || $result === null);
        return $result;
    }

    /**
     * Convert ticket to compra (full workflow)
     *
     * This method handles the complete conversion workflow:
     * 1. Creates the compra from ticket
     * 2. Marks ticket as converted
     * 3. Copies all data (comments, attachments)
     *
     * @param \App\Model\Entity\Ticket $ticket Source ticket
     * @param int $userId User performing the conversion
     * @param \App\Service\ComprasService $comprasService Injected ComprasService
     * @return \App\Model\Entity\Compra|null Created compra or null on failure
     */
    public function convertToCompra(
        \App\Model\Entity\Ticket $ticket,
        int $userId,
        \App\Service\ComprasService $comprasService
    ): ?\App\Model\Entity\Compra {
        try {
            // Create compra from ticket
            $compra = $comprasService->createFromTicket($ticket, ['user_id' => $userId]);

            if (!$compra) {
                Log::error('Failed to create compra from ticket', ['ticket_id' => $ticket->id]);
                return null;
            }

            // Mark ticket as converted using trait method
            $this->markAsConverted('ticket', $ticket, 'compra', $compra, $userId);

            // Copy all data (comments and attachments)
            $comprasService->copyTicketData($ticket, $compra);

            Log::info('Ticket converted to Compra successfully', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'compra_id' => $compra->id,
                'compra_number' => $compra->compra_number,
            ]);

            return $compra;

        } catch (\Exception $e) {
            Log::error('Error in convertToCompra: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Create ticket from compra
     *
     * Workflow: Compra → Convert → Ticket
     *
     * @param \App\Model\Entity\Compra $compra Source compra
     * @param array $data Additional data (assignee_id, user_id)
     * @return \App\Model\Entity\Ticket|null Created ticket or null on failure
     */
    public function createFromCompra(\App\Model\Entity\Compra $compra, array $data = []): ?\App\Model\Entity\Ticket
    {
        $ticketsTable = $this->fetchTable('Tickets');

        try {
            // Generate ticket number
            $year = date('Y');
            $prefix = "TKT-{$year}-";
            $lastTicket = $ticketsTable->find()
                ->select(['ticket_number'])
                ->where(['ticket_number LIKE' => $prefix . '%'])
                ->order(['ticket_number' => 'DESC'])
                ->first();

            if ($lastTicket) {
                $lastNumber = (int)substr($lastTicket->ticket_number, -5);
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 1;
            }
            $ticketNumber = $prefix . str_pad((string)$newNumber, 5, '0', STR_PAD_LEFT);

            // Create ticket from compra data
            $ticket = $ticketsTable->newEntity([
                'ticket_number' => $ticketNumber,
                'subject' => "{$compra->subject}",
                'description' => $compra->description,
                'status' => 'nuevo',
                'priority' => $compra->priority,
                'requester_id' => $compra->requester_id,
                'assignee_id' => $data['assignee_id'] ?? null,
                'channel' => $compra->channel ?? 'email',
                'email_to' => $compra->email_to,
                'email_cc' => $compra->email_cc,
                'source_email' => $compra->requester->email ?? null,
            ]);

            if ($ticketsTable->save($ticket)) {
                // Log creation in ticket history
                $this->logHistory(
                    'TicketHistory',
                    'ticket_id',
                    $ticket->id,
                    'created_from_compra',
                    null,
                    $compra->compra_number,
                    $data['user_id'] ?? null,
                    "Creado desde Compra #{$compra->compra_number}"
                );

                return $ticket;
            }

            Log::error('Error al guardar ticket desde compra', ['compra_id' => $compra->id]);
            return null;

        } catch (\Exception $e) {
            Log::error('Error en createFromCompra: ' . $e->getMessage(), [
                'compra_id' => $compra->id,
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Copy compra data (comments and attachments) to ticket
     *
     * REFACTORED: Now uses EntityConversionTrait for generic copying logic
     *
     * @param \App\Model\Entity\Compra $compra Source compra
     * @param \App\Model\Entity\Ticket $ticket Destination ticket
     * @return bool Success status
     */
    public function copyCompraData(\App\Model\Entity\Compra $compra, \App\Model\Entity\Ticket $ticket): bool
    {
        try {
            // Load compra with associations
            $comprasTable = $this->fetchTable('Compras');
            $compra = $comprasTable->get($compra->id, [
                'contain' => ['ComprasComments', 'ComprasAttachments']
            ]);

            // Copy comments using trait
            $commentsCopied = $this->copyComments('compra', $compra, 'ticket', $ticket);

            // Copy attachments using trait
            $attachmentsCopied = $this->copyAttachments(
                'compra',
                $compra,
                'ticket',
                $ticket,
                $ticket->ticket_number
            );

            Log::info('Copied compra data to ticket', [
                'compra_id' => $compra->id,
                'ticket_id' => $ticket->id,
                'comments_copied' => $commentsCopied,
                'attachments_copied' => $attachmentsCopied,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error copiando datos de compra a ticket: ' . $e->getMessage(), [
                'compra_id' => $compra->id,
                'ticket_id' => $ticket->id,
                'exception' => $e,
            ]);
            return false;
        }
    }
}
