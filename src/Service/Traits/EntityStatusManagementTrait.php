<?php
declare(strict_types=1);

namespace App\Service\Traits;

use Cake\Datasource\EntityInterface;
use Cake\I18n\FrozenTime;
use Cake\Log\Log;

/**
 * EntityStatusManagementTrait
 *
 * Handles status and priority changes for Tickets, PQRS, and Compras.
 * Extracted from TicketSystemTrait for SRP compliance.
 *
 * Responsibilities:
 * - Status changes with timestamp updates
 * - Priority changes
 * - History logging for status/priority changes
 * - Status change notifications
 *
 * Required traits (via TicketSystemTrait facade):
 * - EntityTypeMapperTrait (getEntityTypeFromSource, getHistoryTableName, etc.)
 * - EntityHistoryTrait (logHistory)
 * - EntityCommentManagementTrait (addComment)
 *
 * Required properties in using class:
 * - emailService (for notifications)
 * - fetchTable() method (from LocatorAwareTrait)
 *
 * @package App\Service\Traits
 */
trait EntityStatusManagementTrait
{
    // Traits are included via TicketSystemTrait facade
    // Methods used: getEntityTypeFromSource(), getHistoryTableName(), getForeignKeyName(),
    //               getStatusChangeNotificationMethod(), logHistory(), addComment()

    /**
     * Change entity status
     *
     * Supports all 3 entity types (Ticket, PQRS, Compra).
     * Updates timestamps (resolved_at, closed_at) based on new status.
     * Logs change to history and adds system comment.
     *
     * @param EntityInterface $entity Ticket, PQRS, or Compra entity
     * @param string $newStatus New status
     * @param int|null $userId User making the change
     * @param string|null $comment Optional comment
     * @param bool $sendNotifications Whether to send notifications
     * @return bool Success
     */
    public function changeStatus(
        EntityInterface $entity,
        string $newStatus,
        ?int $userId = null,
        ?string $comment = null,
        bool $sendNotifications = true
    ): bool {
        $table = $this->fetchTable($entity->getSource());
        $oldStatus = $entity->status;

        if ($oldStatus === $newStatus) {
            return true; // No change needed
        }

        $entity->status = $newStatus;

        // Update timestamps based on status
        $now = FrozenTime::now();
        if ($newStatus === 'resuelto' && !$entity->resolved_at) {
            $entity->resolved_at = $now;
        }
        if ($newStatus === 'cerrado' && isset($entity->closed_at) && !$entity->closed_at) {
            $entity->closed_at = $now;
        }

        if (!$table->save($entity)) {
            Log::error('Failed to change status', ['errors' => $entity->getErrors()]);
            return false;
        }

        // Determine entity type from source
        $entityType = $this->getEntityTypeFromSource($entity->getSource());
        $historyTable = $this->getHistoryTableName($entityType);
        $foreignKey = $this->getForeignKeyName($entityType);

        // Log the change
        $this->logHistory(
            $historyTable,
            $foreignKey,
            $entity->id,
            'status',
            $oldStatus,
            $newStatus,
            $userId,
            "Estado cambiado de '{$oldStatus}' a '{$newStatus}'"
        );

        // Add system comment (always internal)
        if ($comment) {
            $this->addComment($entity->id, $userId, $comment, $entityType, 'internal', true);
        } else {
            $systemComment = "El estado cambió de '{$oldStatus}' a '{$newStatus}'";
            $this->addComment($entity->id, $userId, $systemComment, $entityType, 'internal', true);
        }

        // Send notifications ONLY if requested
        // NOTE: WhatsApp is ONLY sent on entity creation, not status changes
        if ($sendNotifications) {
            $method = $this->getStatusChangeNotificationMethod($entityType);

            // Send Email ONLY (WhatsApp removed - only sent on creation)
            try {
                $this->emailService->$method($entity, $oldStatus, $newStatus);
            } catch (\Exception $e) {
                Log::error('Failed to send status change email notification: ' . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Change entity priority
     *
     * Supports all 3 entity types (Ticket, PQRS, Compra).
     * Logs change to history and adds system comment.
     *
     * @param EntityInterface $entity Ticket, PQRS, or Compra entity
     * @param string $newPriority New priority
     * @param int|null $userId User making the change
     * @return bool Success
     */
    public function changePriority(
        EntityInterface $entity,
        string $newPriority,
        ?int $userId = null
    ): bool {
        $table = $this->fetchTable($entity->getSource());
        $oldPriority = $entity->priority;

        if ($oldPriority === $newPriority) {
            return true;
        }

        $entity->priority = $newPriority;

        if (!$table->save($entity)) {
            Log::error('Failed to change priority', ['errors' => $entity->getErrors()]);
            return false;
        }

        // Determine entity type from source
        $entityType = $this->getEntityTypeFromSource($entity->getSource());
        $historyTable = $this->getHistoryTableName($entityType);
        $foreignKey = $this->getForeignKeyName($entityType);

        // Log the change
        $this->logHistory(
            $historyTable,
            $foreignKey,
            $entity->id,
            'priority',
            $oldPriority,
            $newPriority,
            $userId,
            "Prioridad cambiada de '{$oldPriority}' a '{$newPriority}'"
        );

        // Add system comment
        $systemComment = "Prioridad cambiada de '{$oldPriority}' a '{$newPriority}'";
        $this->addComment($entity->id, $userId, $systemComment, $entityType, 'internal', true);

        return true;
    }
}
