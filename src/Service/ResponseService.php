<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\Ticket;
use App\Model\Entity\Pqr;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Response Service
 *
 * Handles the unified logic for adding comments, uploading files,
 * changing statuses, and sending notifications for both Tickets and PQRS.
 */
class ResponseService
{
    use LocatorAwareTrait;
    use \App\Service\Traits\NotificationDispatcherTrait;

    private ?TicketService $ticketService = null;
    private ?PqrsService $pqrsService = null;
    private ?ComprasService $comprasService = null;
    private ?EmailService $emailService = null;
    private ?WhatsappService $whatsappService = null;
    private ?array $systemConfig;

    /**
     * Constructor
     *
     * Services are lazy-loaded on first use to avoid creating unnecessary instances.
     *
     * @param array|null $systemConfig Optional system configuration to avoid redundant DB queries
     */
    public function __construct(?array $systemConfig = null)
    {
        $this->systemConfig = $systemConfig;
    }

    private function getTicketService(): TicketService
    {
        return $this->ticketService ??= new TicketService($this->systemConfig);
    }

    private function getPqrsService(): PqrsService
    {
        return $this->pqrsService ??= new PqrsService($this->systemConfig);
    }

    private function getComprasService(): ComprasService
    {
        return $this->comprasService ??= new ComprasService($this->systemConfig);
    }

    private function getEmailService(): EmailService
    {
        return $this->emailService ??= new EmailService($this->systemConfig);
    }

    private function getWhatsappService(): WhatsappService
    {
        return $this->whatsappService ??= new WhatsappService($this->systemConfig);
    }

    /**
     * Get the appropriate entity service for the given type
     *
     * @param string $type 'ticket', 'pqrs', 'compra'
     * @return TicketService|PqrsService|ComprasService
     */
    private function getServiceForType(string $type): TicketService|PqrsService|ComprasService
    {
        return match ($type) {
            'ticket' => $this->getTicketService(),
            'pqrs' => $this->getPqrsService(),
            'compra' => $this->getComprasService(),
        };
    }

    /**
     * Process a response (comment + status change + files + notifications)
     *
     * @param string $type 'ticket' or 'pqrs'
     * @param int $entityId The ID of the ticket or PQRS
     * @param int $userId The ID of the user making the response
     * @param array $data Request data (comment_body, comment_type, status, email_to, email_cc)
     * @param array $files Uploaded files
     * @return array Result with 'success' (bool), 'message' (string), and 'entity' (mixed)
     */
    public function processResponse(string $type, int $entityId, int $userId, array $data, array $files): array
    {
        $commentBody = $data['comment_body'] ?? $data['body'] ?? '';
        $commentType = $data['comment_type'] ?? 'public';
        $newStatus = $data['status'] ?? null;

        // Extract additional recipients (To and CC) from request data
        // Frontend sends these as JSON strings, so we need to decode them
        $emailTo = $this->decodeEmailRecipients($data['email_to'] ?? null);
        $emailCc = $this->decodeEmailRecipients($data['email_cc'] ?? null);

        // DEBUG: Log recipients for troubleshooting
        Log::debug('Response email recipients', [
            'raw_email_to' => $data['email_to'] ?? null,
            'raw_email_cc' => $data['email_cc'] ?? null,
            'decoded_email_to' => $emailTo,
            'decoded_email_cc' => $emailCc,
        ]);

        $hasComment = !empty(trim($commentBody));

        // Get current entity to check status change
        if ($type === 'ticket') {
            $entity = $this->fetchTable('Tickets')->get($entityId);
            assert($entity instanceof Ticket);
        } elseif ($type === 'compra') {
            $entity = $this->fetchTable('Compras')->get($entityId);
            assert($entity instanceof \App\Model\Entity\Compra);
        } else {
            $entity = $this->fetchTable('Pqrs')->get($entityId);
            assert($entity instanceof Pqr);
        }

        $oldStatus = $entity->status;
        $hasStatusChange = $newStatus && $newStatus !== $oldStatus;

        if (!$hasComment && !$hasStatusChange) {
            return [
                'success' => false,
                'message' => 'Debes escribir un comentario o cambiar el estado.',
                'entity' => $entity
            ];
        }

        $comment = null;
        $uploadedCount = 0;

        // 1. Add Comment
        if ($hasComment) {
            $service = $this->getServiceForType($type);
            $comment = $service->addComment(
                $entityId,
                $userId,
                $commentBody,
                $type,         // entityType
                $commentType,
                false,         // isSystem
                $emailTo,      // email_to
                $emailCc       // email_cc
            );

            if (!$comment) {
                return [
                    'success' => false,
                    'message' => 'Error al agregar el comentario.',
                    'entity' => $entity
                ];
            }

            // 2. Handle File Uploads (unified signature across all services)
            if (!empty($files['attachments'])) {
                foreach ($files['attachments'] as $file) {
                    if ($file->getError() === UPLOAD_ERR_OK) {
                        $result = $service->saveUploadedFile($entity, $file, $comment->id, $userId);

                        if ($result) {
                            $uploadedCount++;
                        }
                    }
                }
            }
        }

        // 3. Change Status
        if ($hasStatusChange) {
            $this->getServiceForType($type)->changeStatus($entity, $newStatus, $userId, null, false);
            // Refresh entity to get new status
            $entity->status = $newStatus;
        }

        // 4. Send Notifications
        $this->sendNotifications($type, $entity, $comment, $oldStatus, $newStatus, $hasComment, $commentType, $hasStatusChange, $emailTo, $emailCc);

        // Build success message
        $successMessage = '';
        if ($hasComment && $hasStatusChange) {
            $successMessage = 'Comentario agregado y estado actualizado exitosamente.';
        } elseif ($hasComment) {
            $successMessage = 'Comentario agregado exitosamente.';
        } elseif ($hasStatusChange) {
            $successMessage = 'Estado actualizado exitosamente.';
        }

        if ($uploadedCount > 0) {
            $successMessage .= " ({$uploadedCount} archivo(s) adjunto(s))";
        }

        return [
            'success' => true,
            'message' => $successMessage,
            'entity' => $entity
        ];
    }

    /**
     * Send notifications based on changes
     *
     * NOTE: WhatsApp notifications are ONLY sent on entity creation (not here).
     * Only email notifications are sent for comments and status changes.
     *
     * Notification Logic:
     * - Comment + Status Change → 'response' (unified notification)
     * - Comment only → 'comment' (independent notification)
     * - Status Change only → 'status_change' (independent notification)
     */
    private function sendNotifications(
        string $type,
        $entity,
        $comment,
        string $oldStatus,
        ?string $newStatus,
        bool $hasComment,
        string $commentType,
        bool $hasStatusChange,
        array $emailTo = [],
        array $emailCc = []
    ): void {
        // Ensure notification services are loaded (required by NotificationDispatcherTrait)
        $this->emailService = $this->getEmailService();
        $this->whatsappService = $this->getWhatsappService();

        $hasPublicComment = $hasComment && $commentType === 'public';

        // Case 1: Comment + Status Change → Unified 'response' notification
        if ($hasPublicComment && $hasStatusChange && $comment) {
            $this->dispatchUpdateNotifications($type, $entity, 'response', [
                'comment' => $comment,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'additional_to' => $emailTo,
                'additional_cc' => $emailCc,
            ]);
        }
        // Case 2: Comment only → Independent 'comment' notification
        elseif ($hasPublicComment && $comment) {
            $this->dispatchUpdateNotifications($type, $entity, 'comment', [
                'comment' => $comment,
                'additional_to' => $emailTo,
                'additional_cc' => $emailCc,
            ]);
        }
        // Case 3: Status Change only → Independent 'status_change' notification
        elseif ($hasStatusChange) {
            $this->dispatchUpdateNotifications($type, $entity, 'status_change', [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
        }
    }

    /**
     * Decode email recipients from JSON string or array
     *
     * @param mixed $data Email recipients as JSON string or array
     * @return array Decoded recipients array
     */
    private function decodeEmailRecipients($data): array
    {
        if (empty($data)) {
            return [];
        }

        // Handle JSON string
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // Handle array (already decoded)
        if (is_array($data)) {
            return $data;
        }

        return [];
    }
}
