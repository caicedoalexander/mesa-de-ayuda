<?php
declare(strict_types=1);

namespace App\Service\Traits;

use App\Service\EmailService;
use App\Service\WhatsappService;
use Cake\Datasource\EntityInterface;
use Cake\Log\Log;

/**
 * NotificationDispatcherTrait
 *
 * REFACTORED (ARCH-016): Services now obtained via abstract method instead of assumed properties.
 *
 * Centralizes notification dispatch logic with clear rules:
 * - WhatsApp: ONLY on entity creation
 * - Email: Creation, status changes, comments
 *
 * BEFORE: Assumed $this->emailService and $this->whatsappService existed (hidden dependencies)
 * AFTER: Services obtained via getNotificationServices() abstract method
 *
 * Benefits:
 * - Testable: Services can be mocks
 * - Explicit: No hidden dependencies
 * - Flexible: Services don't have to be named specific properties
 * - SOLID compliant: Dependency Inversion Principle respected
 *
 * Required in using class:
 * - Implement getNotificationServices() method returning ['email' => EmailService, 'whatsapp' => WhatsappService]
 *
 * @package App\Service\Traits
 */
trait NotificationDispatcherTrait
{
    /**
     * Get notification services from using class
     *
     * Must be implemented by using class to provide service instances.
     * This method allows each service to provide its own EmailService/WhatsappService
     * instances without the trait assuming property names.
     *
     * @return array{email: EmailService, whatsapp: WhatsappService}
     */
    abstract protected function getNotificationServices(): array;

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
        $services = $this->getNotificationServices();
        $methods = $this->getNotificationMethods($entityType, 'creation');

        // Send Email
        if ($sendEmail && !empty($methods['email'])) {
            try {
                $services['email']->{$methods['email']}($entity);
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
                $services['whatsapp']->{$methods['whatsapp']}($entity);
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
        $services = $this->getNotificationServices();
        $methods = $this->getNotificationMethods($entityType, $notificationType);

        // Email ONLY (WhatsApp never sent for updates)
        if (empty($methods['email'])) {
            Log::warning("No email method found for {$entityType} {$notificationType}");
            return;
        }

        try {
            switch ($notificationType) {
                case 'status_change':
                    $services['email']->{$methods['email']}(
                        $entity,
                        $context['old_status'] ?? '',
                        $context['new_status'] ?? ''
                    );
                    break;

                case 'comment':
                    $services['email']->{$methods['email']}(
                        $entity,
                        $context['comment'] ?? null,
                        $context['additional_to'] ?? [],
                        $context['additional_cc'] ?? []
                    );
                    break;

                case 'response':
                    $services['email']->{$methods['email']}(
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