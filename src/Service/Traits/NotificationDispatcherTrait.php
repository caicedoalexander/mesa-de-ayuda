<?php
declare(strict_types=1);

namespace App\Service\Traits;

use Cake\Datasource\EntityInterface;
use Cake\Log\Log;

/**
 * NotificationDispatcherTrait
 *
 * Centralizes notification dispatch logic with clear rules:
 * - WhatsApp: ONLY on entity creation
 * - Email: Creation, status changes, comments
 *
 * Requires using class to have:
 * - emailService property (EmailService instance)
 * - whatsappService property (WhatsappService instance)
 */
trait NotificationDispatcherTrait
{
    /**
     * Dispatch creation notifications (Email + WhatsApp)
     *
     * WhatsApp is ONLY sent for entity creation events
     *
     * @param string $entityType 'ticket', 'pqrs', 'compra'
     * @param EntityInterface $entity Entity instance
     * @param bool $sendEmail Send email notification
     * @param bool $sendWhatsapp Send WhatsApp notification
     * @return void
     */
    public function dispatchCreationNotifications(
        string $entityType,
        EntityInterface $entity,
        bool $sendEmail = true,
        bool $sendWhatsapp = true
    ): void {
        $methods = $this->getNotificationMethods($entityType, 'creation');

        // Send Email
        if ($sendEmail && !empty($methods['email'])) {
            try {
                $this->emailService->{$methods['email']}($entity);
            } catch (\Exception $e) {
                Log::error("Failed to send {$entityType} creation email", [
                    'error' => $e->getMessage(),
                    'entity_id' => $entity->id,
                ]);
            }
        }

        // Send WhatsApp (ONLY for creation)
        if ($sendWhatsapp && !empty($methods['whatsapp'])) {
            try {
                $this->whatsappService->{$methods['whatsapp']}($entity);
            } catch (\Exception $e) {
                Log::error("Failed to send {$entityType} creation WhatsApp", [
                    'error' => $e->getMessage(),
                    'entity_id' => $entity->id,
                ]);
            }
        }
    }

    /**
     * Dispatch update notifications (Email ONLY)
     *
     * WhatsApp is NEVER sent for updates (status changes, comments, responses)
     *
     * @param string $entityType 'ticket', 'pqrs', 'compra'
     * @param EntityInterface $entity Entity instance
     * @param string $notificationType 'status_change', 'comment', 'response'
     * @param array $context Additional context (old_status, new_status, comment, etc.)
     * @return void
     */
    public function dispatchUpdateNotifications(
        string $entityType,
        EntityInterface $entity,
        string $notificationType,
        array $context = []
    ): void {
        $methods = $this->getNotificationMethods($entityType, $notificationType);

        // Email ONLY (WhatsApp never sent for updates)
        if (empty($methods['email'])) {
            Log::warning("No email method found for {$entityType} {$notificationType}");
            return;
        }

        try {
            switch ($notificationType) {
                case 'status_change':
                    $this->emailService->{$methods['email']}(
                        $entity,
                        $context['old_status'] ?? '',
                        $context['new_status'] ?? ''
                    );
                    break;

                case 'comment':
                    $this->emailService->{$methods['email']}(
                        $entity,
                        $context['comment'] ?? null
                    );
                    break;

                case 'response':
                    $this->emailService->{$methods['email']}(
                        $entity,
                        $context['comment'] ?? null,
                        $context['old_status'] ?? '',
                        $context['new_status'] ?? '',
                        $context['additional_to'] ?? [],
                        $context['additional_cc'] ?? []
                    );
                    break;

                default:
                    Log::warning("Unknown notification type: {$notificationType}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to send {$entityType} {$notificationType} email", [
                'error' => $e->getMessage(),
                'entity_id' => $entity->id,
            ]);
        }
    }

    /**
     * Get notification method names for entity type and notification type
     *
     * @param string $entityType 'ticket', 'pqrs', 'compra'
     * @param string $notificationType Notification type
     * @return array Method names ['email' => '...', 'whatsapp' => '...']
     */
    private function getNotificationMethods(
        string $entityType,
        string $notificationType
    ): array {
        $methodMap = [
            'ticket' => [
                'creation' => [
                    'email' => 'sendNewTicketNotification',
                    'whatsapp' => 'sendNewTicketNotification',
                ],
                'status_change' => [
                    'email' => 'sendStatusChangeNotification',
                ],
                'comment' => [
                    'email' => 'sendNewCommentNotification',
                ],
                'response' => [
                    'email' => 'sendTicketResponseNotification',
                ],
            ],
            'pqrs' => [
                'creation' => [
                    'email' => 'sendNewPqrsNotification',
                    'whatsapp' => 'sendNewPqrsNotification',
                ],
                'status_change' => [
                    'email' => 'sendPqrsStatusChangeNotification',
                ],
                'comment' => [
                    'email' => 'sendPqrsNewCommentNotification',
                ],
                'response' => [
                    'email' => 'sendPqrsResponseNotification',
                ],
            ],
            'compra' => [
                'creation' => [
                    'email' => 'sendNewCompraNotification',
                    'whatsapp' => 'sendNewCompraNotification',
                ],
                'status_change' => [
                    'email' => 'sendCompraStatusChangeNotification',
                ],
                'comment' => [
                    'email' => 'sendCompraCommentNotification',
                ],
                'response' => [
                    'email' => 'sendCompraResponseNotification',
                ],
            ],
        ];

        return $methodMap[$entityType][$notificationType] ?? [];
    }
}
