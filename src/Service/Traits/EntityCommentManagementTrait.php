<?php
declare(strict_types=1);

namespace App\Service\Traits;

use Cake\Datasource\EntityInterface;
use Cake\I18n\FrozenTime;
use Cake\Log\Log;

/**
 * EntityCommentManagementTrait
 *
 * Handles comments and history logging for Tickets, PQRS, and Compras.
 * Extracted from TicketSystemTrait for SRP compliance.
 *
 * Responsibilities:
 * - Adding comments (public, internal, system)
 * - Logging changes to history tables
 * - Email recipient tracking for comments
 *
 * Required traits (via TicketSystemTrait facade):
 * - EntityTypeMapperTrait (getCommentsTableName, getEntityTableName, getForeignKeyName)
 *
 * Required properties in using class:
 * - fetchTable() method (from LocatorAwareTrait)
 *
 * @package App\Service\Traits
 */
trait EntityCommentManagementTrait
{
    // Traits are included via TicketSystemTrait facade
    // Methods used: getCommentsTableName(), getEntityTableName(), getForeignKeyName()

    /**
     * Add comment to entity
     *
     * Supports all 3 entity types using string type parameter.
     * Handles email recipients for public comments.
     * Updates first_response_at timestamp if applicable.
     *
     * NOTE: This method does NOT send notifications. Notifications are handled
     * by ResponseService via NotificationDispatcherTrait for proper coordination
     * of comment + status change + file uploads.
     *
     * @param int $entityId Entity ID
     * @param int|null $userId User ID (null for public/anonymous comments)
     * @param string $body Comment body
     * @param string $entityType Entity type: 'ticket', 'pqrs', or 'compra'
     * @param string $type Comment type: 'public' or 'internal'
     * @param bool $isSystem Is this a system-generated comment?
     * @param array|null $emailTo Array of TO recipients [{'name': '...', 'email': '...'}]
     * @param array|null $emailCc Array of CC recipients [{'name': '...', 'email': '...'}]
     * @return EntityInterface|null Created comment or null
     */
    public function addComment(
        int $entityId,
        ?int $userId,
        string $body,
        string $entityType,
        string $type = 'public',
        bool $isSystem = false,
        ?array $emailTo = null,
        ?array $emailCc = null
    ): ?EntityInterface {
        $commentsTableName = $this->getCommentsTableName($entityType);
        $commentsTable = $this->fetchTable($commentsTableName);

        $entityTableName = $this->getEntityTableName($entityType);
        $entityTable = $this->fetchTable($entityTableName);
        $entity = $entityTable->get($entityId);

        // No sanitization as requested by user
        $sanitizedBody = $body;

        $data = [
            'user_id' => $userId,
            'comment_type' => $type,
            'body' => $sanitizedBody,
            'is_system_comment' => $isSystem,
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

        // Set foreign key based on entity type
        $foreignKey = $this->getForeignKeyName($entityType);
        $data[$foreignKey] = $entityId;

        // Add sent_as_email field for PQRS and Compras
        if ($entityType === 'pqrs' || $entityType === 'compra') {
            $data['sent_as_email'] = false;
        }

        $comment = $commentsTable->newEntity($data);

        if (!$commentsTable->save($comment)) {
            Log::error('Failed to add comment', ['errors' => $comment->getErrors()]);
            return null;
        }

        // Update first_response_at if this is the first non-system comment
        if (!$isSystem && !$entity->first_response_at && $userId) {
            $entity->first_response_at = FrozenTime::now();
            $entityTable->save($entity);
        }

        return $comment;
    }

    /**
     * Log change to history table
     *
     * Creates a history entry for tracking field changes.
     * Supports all entity types via table name parameter.
     *
     * @param string $tableName History table name (TicketHistory, PqrsHistory, ComprasHistory)
     * @param string $foreignKey Foreign key column name
     * @param int $entityId Entity ID
     * @param string $fieldName Changed field name
     * @param string|null $oldValue Previous value
     * @param string|null $newValue New value
     * @param int|null $userId User making the change
     * @param string|null $description Human-readable description
     * @return void
     */
    protected function logHistory(
        string $tableName,
        string $foreignKey,
        int $entityId,
        string $fieldName,
        ?string $oldValue,
        ?string $newValue,
        ?int $userId = null,
        ?string $description = null
    ): void {
        $historyTable = $this->fetchTable($tableName);

        // Use logChange method if available in table class
        if (method_exists($historyTable, 'logChange')) {
            $historyTable->logChange($entityId, $fieldName, $oldValue, $newValue, $userId, $description);
        } else {
            // Fallback implementation
            $history = $historyTable->newEntity([
                $foreignKey => $entityId,
                'user_id' => $userId,
                'field_name' => $fieldName,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'description' => $description,
            ]);
            $historyTable->save($history);
        }
    }
}