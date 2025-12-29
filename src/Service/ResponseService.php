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

    private TicketService $ticketService;
    private PqrsService $pqrsService;
    private ComprasService $comprasService;
    private EmailService $emailService;
    private WhatsappService $whatsappService;

    /**
     * Constructor
     *
     * @param array|null $systemConfig Optional system configuration to avoid redundant DB queries
     */
    public function __construct(?array $systemConfig = null)
    {
        $this->ticketService = new TicketService($systemConfig);
        $this->pqrsService = new PqrsService($systemConfig);
        $this->comprasService = new ComprasService($systemConfig);
        $this->emailService = new EmailService($systemConfig);
        $this->whatsappService = new WhatsappService($systemConfig);
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
            if ($type === 'ticket') {
                $comment = $this->ticketService->addComment(
                    $entityId,
                    $userId,
                    $commentBody,
                    $commentType,
                    false,     // isSystem
                    'ticket',  // entityType
                    $emailTo,  // email_to
                    $emailCc   // email_cc
                );
            } elseif ($type === 'compra') {
                $comment = $this->comprasService->addComment(
                    $entityId,
                    $userId,
                    $commentBody,
                    $commentType,
                    false,     // isSystem
                    'compra',  // entityType
                    $emailTo,  // email_to
                    $emailCc   // email_cc
                );
            } else {
                $comment = $this->pqrsService->addComment(
                    $entityId,
                    $userId,
                    $commentBody,
                    $commentType,
                    false,     // isSystem
                    'pqrs',    // entityType
                    $emailTo,  // email_to
                    $emailCc   // email_cc
                );
            }

            if (!$comment) {
                return [
                    'success' => false,
                    'message' => 'Error al agregar el comentario.',
                    'entity' => $entity
                ];
            }

            // 2. Handle File Uploads (all services now use GenericAttachmentTrait)
            if (!empty($files['attachments'])) {
                foreach ($files['attachments'] as $file) {
                    if ($file->getError() === UPLOAD_ERR_OK) {
                        $result = false;
                        if ($type === 'ticket') {
                            $result = $this->ticketService->saveUploadedFile(
                                $entityId,
                                $comment->id,
                                $file,
                                $userId
                            );
                        } elseif ($type === 'compra') {
                            assert($entity instanceof \App\Model\Entity\Compra);
                            $result = $this->comprasService->saveUploadedFile(
                                $entity,
                                $file,
                                $comment->id,
                                $userId
                            );
                        } else {
                            assert($entity instanceof Pqr);
                            $result = $this->pqrsService->saveUploadedFile(
                                $entity,
                                $file,
                                $comment->id,
                                $userId
                            );
                        }

                        if ($result) {
                            $uploadedCount++;
                        }
                    }
                }
            }
        }

        // 3. Change Status
        if ($hasStatusChange) {
            if ($type === 'ticket') {
                $this->ticketService->changeStatus($entity, $newStatus, $userId, null, false);
            } elseif ($type === 'compra') {
                $this->comprasService->changeStatus($entity, $newStatus, $userId, null, false);
            } else {
                $this->pqrsService->changeStatus($entity, $newStatus, $userId, null, false);
            }
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
