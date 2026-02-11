<?php
declare(strict_types=1);

namespace App\Service\Traits;

use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\Log\Log;

/**
 * EntityAssignmentTrait
 *
 * Handles assignment and conversion operations for Tickets, PQRS, and Compras.
 * Extracted from TicketSystemTrait for SRP compliance.
 *
 * Responsibilities:
 * - Entity assignment to users
 * - Entity type conversion (ticket <-> compra)
 * - Assignment history logging
 *
 * Required traits (via TicketSystemTrait facade):
 * - EntityTypeMapperTrait (getEntityTypeFromSource, getHistoryTableName, etc.)
 * - EntityCommentManagementTrait (addComment, logHistory)
 *
 * Required properties in using class:
 * - fetchTable() method (from LocatorAwareTrait)
 *
 * @package App\Service\Traits
 */
trait EntityAssignmentTrait
{
    // Traits are included via TicketSystemTrait facade
    // Methods used: getEntityTypeFromSource(), getHistoryTableName(), getForeignKeyName(),
    //               getEntityTableName(), addComment(), logHistory()

    /**
     * Assign entity to a user
     *
     * Supports all 3 entity types (Ticket, PQRS, Compra).
     * Handles null/0 as "unassigned".
     * Logs change to history and adds system comment.
     *
     * @param EntityInterface $entity Ticket, PQRS, or Compra entity
     * @param int|null $assigneeId User ID to assign to (null/0 to unassign)
     * @param int|null $userId User making the assignment
     * @return bool Success
     */
    public function assign(
        EntityInterface $entity,
        ?int $assigneeId,
        ?int $userId = null
    ): bool {
        $table = $this->fetchTable($entity->getSource());
        $usersTable = $this->fetchTable('Users');

        $oldAssigneeId = $entity->assignee_id;

        // Convert 0 to null for "unassigned" option
        $entity->assignee_id = ($assigneeId === 0 || $assigneeId === '0') ? null : $assigneeId;

        if (!$table->save($entity)) {
            $errors = $entity->getErrors();
            $entityClass = get_class($entity);

            Log::error("Failed to assign entity - Type: {$entityClass}, ID: {$entity->id}");
            Log::error("Assignment details - New assignee: {$assigneeId}, Old assignee: {$oldAssigneeId}");
            Log::error("Validation errors: " . print_r($errors, true));
            Log::error("Dirty fields: " . print_r($entity->getDirty(), true));

            return false;
        }

        // Get assignee names for history
        $oldAssigneeName = 'Sin asignar';
        if ($oldAssigneeId) {
            $oldUser = $usersTable->get($oldAssigneeId);
            $oldAssigneeName = $oldUser->first_name . ' ' . $oldUser->last_name;
        }

        $newAssigneeName = 'Sin asignar';
        if ($assigneeId) {
            $newUser = $usersTable->get($assigneeId);
            $newAssigneeName = $newUser->first_name . ' ' . $newUser->last_name;
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
            'assignee_id',
            $oldAssigneeName,
            $newAssigneeName,
            $userId,
            "Asignado a {$newAssigneeName}"
        );

        // Add system comment
        $systemComment = "Asignado a {$newAssigneeName}";
        $this->addComment($entity->id, $userId, $systemComment, $entityType, 'internal', true);

        return true;
    }

    /**
     * Mark entity as converted to another entity type
     *
     * Generic method to handle conversion workflow:
     * 1. Update source entity status to 'convertido'
     * 2. Set resolved_at timestamp
     * 3. Save entity
     * 4. Add internal system comment
     * 5. Log to history
     *
     * @param string $sourceType Source entity type ('ticket' or 'compra')
     * @param EntityInterface $sourceEntity Source entity being converted
     * @param string $targetType Target entity type ('ticket' or 'compra')
     * @param EntityInterface $targetEntity Newly created target entity
     * @param int $userId User performing the conversion
     * @return void
     */
    protected function markAsConverted(
        string $sourceType,
        EntityInterface $sourceEntity,
        string $targetType,
        EntityInterface $targetEntity,
        int $userId
    ): void {
        $sourceTableName = $this->getEntityTableName($sourceType);
        $sourceTable = $this->fetchTable($sourceTableName);

        // Update source entity status
        $sourceEntity->status = 'convertido';
        $sourceEntity->resolved_at = new DateTime();
        $sourceTable->save($sourceEntity);

        // Get entity numbers for messages
        $sourceNumber = $this->getEntityNumber($sourceType, $sourceEntity);
        $targetNumber = $this->getEntityNumber($targetType, $targetEntity);

        // Get readable type names
        $sourceTypeName = ucfirst($sourceType);
        $targetTypeName = ucfirst($targetType);

        // Add internal system comment
        $this->addComment(
            $sourceEntity->id,
            $userId,
            "{$sourceTypeName} convertido a {$targetTypeName} #{$targetNumber}",
            $sourceType,
            'internal',
            true
        );

        // Log to history
        $historyTable = $this->getHistoryTableName($sourceType);
        $foreignKey = $this->getForeignKeyName($sourceType);

        $this->logHistory(
            $historyTable,
            $foreignKey,
            $sourceEntity->id,
            "converted_to_{$targetType}",
            null,
            $targetNumber,
            $userId,
            "Convertido a {$targetTypeName} #{$targetNumber}"
        );
    }

    /**
     * Get entity number based on type
     *
     * @param string $entityType Entity type (ticket, pqrs, compra)
     * @param EntityInterface $entity Entity instance
     * @return string Entity number
     */
    private function getEntityNumber(string $entityType, EntityInterface $entity): string
    {
        return match ($entityType) {
            'ticket' => $entity->ticket_number,
            'pqrs' => $entity->pqrs_number,
            'compra' => $entity->compra_number,
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };
    }
}