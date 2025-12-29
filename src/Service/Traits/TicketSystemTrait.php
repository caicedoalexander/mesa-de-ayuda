<?php
declare(strict_types=1);

namespace App\Service\Traits;

use Cake\Log\Log;
use Cake\I18n\FrozenTime;

/**
 * TicketSystemTrait
 * 
 * Shared logic for Tickets and PQRS management.
 * Requires the using class to have:
 * - fetchTable() method (from LocatorAwareTrait)
 * - emailService property
 * - whatsappService property
 */
trait TicketSystemTrait
{
    /**
     * Change entity status
     *
     * @param \Cake\Datasource\EntityInterface $entity Ticket or PQRS entity
     * @param string $newStatus New status
     * @param int|null $userId User making the change
     * @param string|null $comment Optional comment
     * @param bool $sendNotifications Whether to send notifications
     * @return bool Success
     */
    public function changeStatus(
        \Cake\Datasource\EntityInterface $entity,
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

        // Determine history table and foreign key
        $isPqrs = $entity->getSource() === 'Pqrs';
        $historyTable = $isPqrs ? 'PqrsHistory' : 'TicketHistory';
        $foreignKey = $isPqrs ? 'pqrs_id' : 'ticket_id';

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
            $this->addComment($entity->id, $userId, $comment, 'internal', true, false, $isPqrs);
        } else {
            $systemComment = "El estado cambió de '{$oldStatus}' a '{$newStatus}'";
            $this->addComment($entity->id, $userId, $systemComment, 'internal', true, false, $isPqrs);
        }

        // Send notifications ONLY if requested
        // NOTE: WhatsApp is ONLY sent on entity creation, not status changes
        if ($sendNotifications) {
            $method = $isPqrs ? 'sendPqrsStatusChangeNotification' : 'sendStatusChangeNotification';

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
     * Add comment to entity
     *
     * @param int $entityId Entity ID
     * @param int|null $userId User ID (null for public/anonymous comments)
     * @param string $body Comment body
     * @param string $type 'public' or 'internal'
     * @param bool $isSystem Is this a system-generated comment?
     * @param bool $sendNotifications Whether to send notifications
     * @param bool $isPqrs Whether this is for PQRS (true) or Ticket (false)
     * @param array|null $emailTo Array of TO recipients [{'name': '...', 'email': '...'}]
     * @param array|null $emailCc Array of CC recipients [{'name': '...', 'email': '...'}]
     * @return \Cake\Datasource\EntityInterface|null Created comment or null
     */
    public function addComment(
        int $entityId,
        ?int $userId,
        string $body,
        string $type = 'public',
        bool $isSystem = false,
        bool $sendNotifications = false,
        bool $isPqrs = false,
        ?array $emailTo = null,
        ?array $emailCc = null
    ): ?\Cake\Datasource\EntityInterface {
        $commentsTableName = $isPqrs ? 'PqrsComments' : 'TicketComments';
        $commentsTable = $this->fetchTable($commentsTableName);

        $entityTableName = $isPqrs ? 'Pqrs' : 'Tickets';
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

        if ($isPqrs) {
            $data['pqrs_id'] = $entityId;
            $data['sent_as_email'] = false;
        } else {
            $data['ticket_id'] = $entityId;
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

        // Only send notifications if explicitly requested
        // NOTE: WhatsApp is ONLY sent on entity creation, not comments
        if ($sendNotifications && $type === 'public' && !$isSystem) {
            $method = $isPqrs ? 'sendPqrsNewCommentNotification' : 'sendNewCommentNotification';

            // Email ONLY (WhatsApp removed - only sent on creation)
            try {
                $this->emailService->$method($entity, $comment);
            } catch (\Exception $e) {
                Log::error('Failed to send comment email notification: ' . $e->getMessage());
            }
        }

        return $comment;
    }

    /**
     * Assign entity to a user
     *
     * @param \Cake\Datasource\EntityInterface $entity Ticket or PQRS entity
     * @param int|null $assigneeId User ID to assign to (null to unassign)
     * @param int|null $userId User making the assignment
     * @return bool Success
     */
    public function assign(
        \Cake\Datasource\EntityInterface $entity,
        ?int $assigneeId,
        ?int $userId = null
    ): bool {
        $table = $this->fetchTable($entity->getSource());
        $usersTable = $this->fetchTable('Users');

        $oldAssigneeId = $entity->assignee_id;
        $entity->assignee_id = $assigneeId;

        if (!$table->save($entity)) {
            Log::error('Failed to assign entity', ['errors' => $entity->getErrors()]);
            return false;
        }

        // Get assignee names for history
        $oldAssigneeName = $oldAssigneeId ? $usersTable->get($oldAssigneeId)->name : 'Sin asignar';
        $newAssigneeName = $assigneeId ? $usersTable->get($assigneeId)->name : 'Sin asignar';

        // Determine history table and foreign key
        $isPqrs = $entity->getSource() === 'Pqrs';
        $historyTable = $isPqrs ? 'PqrsHistory' : 'TicketHistory';
        $foreignKey = $isPqrs ? 'pqrs_id' : 'ticket_id';

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
        $this->addComment($entity->id, $userId, $systemComment, 'internal', true, false, $isPqrs);

        return true;
    }

    /**
     * Change entity priority
     *
     * @param \Cake\Datasource\EntityInterface $entity Ticket or PQRS entity
     * @param string $newPriority New priority
     * @param int|null $userId User making the change
     * @return bool Success
     */
    public function changePriority(
        \Cake\Datasource\EntityInterface $entity,
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

        // Determine history table and foreign key
        $isPqrs = $entity->getSource() === 'Pqrs';
        $historyTable = $isPqrs ? 'PqrsHistory' : 'TicketHistory';
        $foreignKey = $isPqrs ? 'pqrs_id' : 'ticket_id';

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
        $this->addComment($entity->id, $userId, $systemComment, 'internal', true, false, $isPqrs);

        return true;
    }

    /**
     * Log change to history
     */
    private function logHistory(
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

        // Assuming both history tables have a logChange method or similar structure
        // If not, we might need to implement it here or rely on the Table method if it exists
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
                'description' => $description
            ]);
            $historyTable->save($history);
        }
    }
}
