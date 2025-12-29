<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\Compra;
use App\Model\Entity\Ticket;
use App\Service\Traits\TicketSystemTrait;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;

class ComprasService
{
    use LocatorAwareTrait;
    use TicketSystemTrait {
        TicketSystemTrait::addComment as protected traitAddComment;
    }
    use \App\Service\Traits\NotificationDispatcherTrait;
    use \App\Service\Traits\GenericAttachmentTrait;

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
     */
    public function copyTicketData(Ticket $ticket, Compra $compra): bool
    {
        try {
            $ticketsTable = $this->fetchTable('Tickets');
            $comprasCommentsTable = $this->fetchTable('ComprasComments');
            $comprasAttachmentsTable = $this->fetchTable('ComprasAttachments');

            $ticket = $ticketsTable->get($ticket->id, [
                'contain' => ['TicketComments', 'Attachments']
            ]);

            // Copiar comentarios
            foreach ($ticket->ticket_comments as $comment) {
                $newComment = $comprasCommentsTable->newEntity([
                    'compra_id' => $compra->id,
                    'user_id' => $comment->user_id,
                    'comment_type' => $comment->comment_type,
                    'body' => $comment->body,
                    'is_system_comment' => $comment->is_system_comment,
                    'sent_as_email' => false,
                ]);
                $comprasCommentsTable->save($newComment);
            }

            // Copiar attachments
            foreach ($ticket->attachments as $attachment) {
                $oldPath = WWW_ROOT . $attachment->file_path;
                $newDir = 'uploads' . DS . 'compras' . DS . $compra->compra_number . DS;
                $newPath = WWW_ROOT . $newDir;

                if (!file_exists($newPath)) {
                    mkdir($newPath, 0755, true);
                }

                $newFilePath = $newPath . $attachment->filename;
                if (file_exists($oldPath)) {
                    copy($oldPath, $newFilePath);
                }

                $newAttachment = $comprasAttachmentsTable->newEntity([
                    'compra_id' => $compra->id,
                    'compras_comment_id' => null,
                    'filename' => $attachment->filename,
                    'original_filename' => $attachment->original_filename,
                    'file_path' => $newDir . $attachment->filename,
                    'mime_type' => $attachment->mime_type,
                    'file_size' => $attachment->file_size,
                    'is_inline' => $attachment->is_inline,
                    'content_id' => $attachment->content_id,
                    'uploaded_by_user_id' => $attachment->uploaded_by,  // Corrected: tickets use 'uploaded_by'
                ]);
                $comprasAttachmentsTable->save($newAttachment);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error copiando datos: ' . $e->getMessage());
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
     * Agrega comentario a compra
     *
     * Follows TicketSystemTrait patterns but for Compras entity type
     *
     * @param int $compraId Compra ID
     * @param int|null $userId User ID
     * @param string $body Comment body
     * @param string $type Comment type ('public' or 'internal')
     * @param bool $isSystem Is system comment
     * @param bool $sendNotifications Send notifications (Email ONLY, no WhatsApp)
     * @param array|null $emailTo Array of TO recipients [{'name': '...', 'email': '...'}]
     * @param array|null $emailCc Array of CC recipients [{'name': '...', 'email': '...'}]
     * @return \Cake\Datasource\EntityInterface|null
     */
    public function addComment(
        int $compraId,
        ?int $userId,
        string $body,
        string $type = 'public',
        bool $isSystem = false,
        bool $sendNotifications = false,  // FIXED: Align with trait default
        ?array $emailTo = null,
        ?array $emailCc = null
    ): ?\Cake\Datasource\EntityInterface {
        $comprasCommentsTable = $this->fetchTable('ComprasComments');
        $comprasTable = $this->fetchTable('Compras');

        try {
            $compra = $comprasTable->get($compraId);

            // No sanitization (as per project standards)
            $sanitizedBody = $body;

            $data = [
                'compra_id' => $compraId,
                'user_id' => $userId,
                'comment_type' => $type,
                'body' => $sanitizedBody,
                'is_system_comment' => $isSystem,
                'sent_as_email' => false,  // Not used for compras
            ];

            // Add email recipients if provided (only for public comments)
            if ($type === 'public' && !$isSystem) {
                if (is_array($emailTo) && count($emailTo) > 0) {
                    $data['email_to'] = json_encode($emailTo);
                }
                if (is_array($emailCc) && count($emailCc) > 0) {
                    $data['email_cc'] = json_encode($emailCc);
                }
            }

            $comment = $comprasCommentsTable->newEntity($data);

            if (!$comprasCommentsTable->save($comment)) {
                Log::error('Failed to add compra comment', ['errors' => $comment->getErrors()]);
                return null;
            }

            // Update first_response_at
            if (!$isSystem && !$compra->first_response_at && $userId) {
                $compra->first_response_at = new DateTime();
                $comprasTable->save($compra);
            }

            // Send notifications ONLY if requested (Email ONLY, no WhatsApp)
            if ($sendNotifications && $type === 'public' && !$isSystem) {
                $this->dispatchUpdateNotifications('compra', $compra, 'comment', [
                    'comment' => $comment,
                ]);
            }

            return $comment;

        } catch (\Exception $e) {
            Log::error('Error agregando comentario: ' . $e->getMessage());
            return null;
        }
    }

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
