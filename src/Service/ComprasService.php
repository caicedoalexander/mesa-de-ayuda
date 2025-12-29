<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\Compra;
use App\Model\Entity\Ticket;
use App\Service\Traits\TicketSystemTrait;
use App\Service\Traits\EntityConversionTrait;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;

class ComprasService
{
    use LocatorAwareTrait;
    use TicketSystemTrait;
    use \App\Service\Traits\NotificationDispatcherTrait;
    use \App\Service\Traits\GenericAttachmentTrait;
    use EntityConversionTrait;

    private EmailService $emailService;
    private WhatsappService $whatsappService;
    private ?array $systemConfig;

    public function __construct(?array $systemConfig = null)
    {
        $this->systemConfig = $systemConfig;
        $this->emailService = new EmailService($systemConfig);
        $this->whatsappService = new WhatsappService($systemConfig);
    }

    /**
     * Convert compra to ticket (full workflow)
     *
     * This method handles the complete conversion workflow:
     * 1. Creates the ticket from compra
     * 2. Marks compra as converted
     * 3. Copies all data (comments, attachments)
     *
     * @param Compra $compra Source compra
     * @param int $userId User performing the conversion
     * @param \App\Service\TicketService $ticketService Injected TicketService
     * @return \App\Model\Entity\Ticket|null Created ticket or null on failure
     */
    public function convertToTicket(
        Compra $compra,
        int $userId,
        \App\Service\TicketService $ticketService
    ): ?\App\Model\Entity\Ticket {
        try {
            // Create ticket from compra
            $ticket = $ticketService->createFromCompra($compra, ['user_id' => $userId]);

            if (!$ticket) {
                Log::error('Failed to create ticket from compra', ['compra_id' => $compra->id]);
                return null;
            }

            // Mark compra as converted using trait method
            $this->markAsConverted('compra', $compra, 'ticket', $ticket, $userId);

            // Copy all data (comments and attachments)
            $ticketService->copyCompraData($compra, $ticket);

            Log::info('Compra converted to Ticket successfully', [
                'compra_id' => $compra->id,
                'compra_number' => $compra->compra_number,
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
            ]);

            return $ticket;

        } catch (\Exception $e) {
            Log::error('Error in convertToTicket: ' . $e->getMessage(), [
                'compra_id' => $compra->id,
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Crea una compra desde un ticket existente
     * Workflow: Ticket → Convertir → Compra
     */
    public function createFromTicket(Ticket $ticket, array $data = []): ?Compra
    {
        $comprasTable = $this->fetchTable('Compras');

        try {
            $compraNumber = $comprasTable->generateCompraNumber();
            $slaDate = $this->calculateSLA();

            $compra = $comprasTable->newEntity([
                'compra_number' => $compraNumber,
                'original_ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'status' => 'nuevo',
                'priority' => $ticket->priority,
                'requester_id' => $ticket->requester_id,
                'assignee_id' => $data['assignee_id'] ?? null,
                'channel' => $ticket->channel ?? 'email',
                'email_to' => $ticket->email_to,  // Copy email recipients
                'email_cc' => $ticket->email_cc,  // Copy CC recipients (managers, etc.)
                'sla_due_date' => $slaDate,
            ]);

            if ($comprasTable->save($compra)) {
                $this->logHistory(
                    'ComprasHistory',
                    'compra_id',
                    $compra->id,
                    'created',
                    null,
                    'nuevo',
                    $data['user_id'] ?? null,
                    "Compra creada desde ticket {$ticket->ticket_number}"
                );

                // Send creation notifications (Email + WhatsApp)
                $this->dispatchCreationNotifications('compra', $compra);

                return $compra;
            }

            Log::error('Error al guardar compra', ['ticket_id' => $ticket->id]);
            return null;

        } catch (\Exception $e) {
            Log::error('Error en createFromTicket: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Copia datos del ticket a la compra
     *
     * REFACTORED: Now uses EntityConversionTrait for generic copying logic
     *
     * @param Ticket $ticket Source ticket
     * @param Compra $compra Destination compra
     * @return bool Success status
     */
    public function copyTicketData(Ticket $ticket, Compra $compra): bool
    {
        try {
            // Load ticket with associations
            $ticketsTable = $this->fetchTable('Tickets');
            $ticket = $ticketsTable->get($ticket->id, [
                'contain' => ['TicketComments', 'Attachments']
            ]);

            // Copy comments using trait
            $commentsCopied = $this->copyComments('ticket', $ticket, 'compra', $compra);

            // Copy attachments using trait
            $attachmentsCopied = $this->copyAttachments(
                'ticket',
                $ticket,
                'compra',
                $compra,
                $compra->compra_number
            );

            Log::info('Copied ticket data to compra', [
                'ticket_id' => $ticket->id,
                'compra_id' => $compra->id,
                'comments_copied' => $commentsCopied,
                'attachments_copied' => $attachmentsCopied,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error copiando datos: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
                'compra_id' => $compra->id,
                'exception' => $e,
            ]);
            return false;
        }
    }

    /**
     * Calcula fecha de vencimiento de SLA
     * SLA de Compras: created + 3 días
     */
    public function calculateSLA(?Compra $compra = null): DateTime
    {
        $createdDate = $compra ? $compra->created : new DateTime();
        return $createdDate->modify('+3 days');
    }

    /**
     * Verifica si el SLA está vencido
     */
    public function isSLABreached(Compra $compra): bool
    {
        if (in_array($compra->status, ['completado', 'rechazado'])) {
            return false;
        }

        $now = new DateTime();
        return $compra->sla_due_date && $now > $compra->sla_due_date;
    }

    /**
     * Obtiene compras con SLA vencido
     */
    public function getBreachedSLACompras(): array
    {
        $comprasTable = $this->fetchTable('Compras');

        return $comprasTable->find()
            ->where([
                'sla_due_date <' => new DateTime(),
                'status NOT IN' => ['completado', 'rechazado']
            ])
            ->contain(['Requesters', 'Assignees'])
            ->order(['sla_due_date' => 'ASC'])
            ->toArray();
    }

    /**
     * Note: addComment() method is provided by TicketSystemTrait
     *
     * This service uses the trait's addComment() method directly.
     * When calling from external code, use:
     *
     * $comprasService->addComment(
     *     $compraId,
     *     $userId,
     *     $body,
     *     $type,              // 'public' or 'internal'
     *     $isSystem,          // true for system comments
     *     $sendNotifications, // false by default
     *     'compra',           // entityType (REQUIRED: 'ticket', 'pqrs', or 'compra')
     *     $emailTo,           // optional array
     *     $emailCc            // optional array
     * );
     */

    /**
     * Save uploaded file for compra (using GenericAttachmentTrait)
     *
     * @param Compra $compra Compra entity
     * @param \Psr\Http\Message\UploadedFileInterface $file Uploaded file
     * @param int|null $commentId Comment ID
     * @param int|null $userId User ID
     * @return \App\Model\Entity\ComprasAttachment|null
     */
    public function saveUploadedFile(
        Compra $compra,
        \Psr\Http\Message\UploadedFileInterface $file,
        ?int $commentId = null,
        ?int $userId = null
    ): ?\App\Model\Entity\ComprasAttachment {
        $result = $this->saveGenericUploadedFile('compra', $compra, $file, $commentId, $userId);
        assert($result instanceof \App\Model\Entity\ComprasAttachment || $result === null);
        return $result;
    }
}
