<?php
declare(strict_types=1);

namespace App\Controller\Traits;

use Cake\Http\Response;
use Cake\Log\Log;

/**
 * BulkOperationsTrait
 *
 * Handles bulk operations for Tickets, PQRS, and Compras.
 * Extracted from TicketSystemControllerTrait for SRP compliance.
 *
 * Responsibilities:
 * - Bulk assignment to agents
 * - Bulk priority changes
 * - Bulk tag addition
 * - Bulk deletion
 *
 * Required controller properties:
 * - $this->Authentication (CakePHP Authentication component)
 * - $this->Flash (CakePHP Flash component)
 * - $this->ticketService, $this->pqrsService, $this->comprasService
 */
trait BulkOperationsTrait
{
    // EntityConfigTrait is included via TicketSystemControllerTrait facade
    // Methods used: getEntityComponents(), getHistoryTable(), getTagsTableName()

    /**
     * Bulk assign entities to an agent
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @return Response Redirect response
     */
    protected function bulkAssignEntity(string $entityType): Response
    {
        $this->request->allowMethod(['post']);

        $entityIds = $this->parseBulkEntityIds();
        $agentId = $this->request->getData('agent_id') ?? $this->request->getData('assignee_id');
        $agentId = $this->normalizeAssigneeId($agentId);

        $user = $this->Authentication->getIdentity();
        $userId = $user ? $user->get('id') : 1;

        $components = $this->getEntityComponents($entityType);
        $table = $components['table'];
        $service = $components['service'];
        $entityName = $components['displayName'];

        $successCount = 0;
        $errorCount = 0;

        foreach ($entityIds as $entityId) {
            try {
                $entity = $table->get($entityId);
                $service->assign($entity, $agentId, $userId);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Error in bulk assign {$entityType} {$entityId}: " . $e->getMessage());
            }
        }

        $this->flashBulkResult($successCount, $errorCount, $entityName, 'asignado');

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Bulk change priority of entities
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @return Response Redirect response
     */
    protected function bulkChangeEntityPriority(string $entityType): Response
    {
        $this->request->allowMethod(['post']);

        $entityIds = $this->parseBulkEntityIds();
        $newPriority = $this->request->getData('priority');

        $user = $this->Authentication->getIdentity();
        $userId = $user ? $user->get('id') : 1;

        $components = $this->getEntityComponents($entityType);
        $table = $components['table'];
        $entityName = $components['displayName'];
        $historyTable = $this->getHistoryTable($entityType);

        $successCount = 0;
        $errorCount = 0;

        foreach ($entityIds as $entityId) {
            try {
                $entity = $table->get($entityId);
                $oldPriority = $entity->priority;
                $entity->priority = $newPriority;

                if ($table->save($entity)) {
                    $historyTable->logChange(
                        $entity->id,
                        'priority',
                        $oldPriority,
                        $newPriority,
                        $userId,
                        "Prioridad cambiada de {$oldPriority} a {$newPriority}"
                    );
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Error in bulk priority change {$entityType} {$entityId}: " . $e->getMessage());
            }
        }

        $this->flashBulkResult($successCount, $errorCount, $entityName, 'actualizado');

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Bulk add tag to entities
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @return Response Redirect response
     */
    protected function bulkAddTagEntity(string $entityType): Response
    {
        $this->request->allowMethod(['post']);

        $entityIds = $this->parseBulkEntityIds();
        $tagId = (int) $this->request->getData('tag_id');

        $components = $this->getEntityComponents($entityType);
        $entityName = $components['displayName'];

        $tagsTableName = $this->getTagsTableName($entityType);
        $tagsTable = $this->fetchTable($tagsTableName);
        $foreignKey = $entityType . '_id';

        $successCount = 0;
        $errorCount = 0;

        foreach ($entityIds as $entityId) {
            try {
                $exists = $tagsTable->exists([
                    $foreignKey => $entityId,
                    'tag_id' => $tagId
                ]);

                if (!$exists) {
                    $entityTag = $tagsTable->newEntity([
                        $foreignKey => $entityId,
                        'tag_id' => $tagId
                    ]);

                    if ($tagsTable->save($entityTag)) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                } else {
                    $successCount++; // Already has the tag
                }
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Error in bulk tag add {$entityType} {$entityId}: " . $e->getMessage());
            }
        }

        if ($successCount > 0) {
            $this->Flash->success(__("Etiqueta agregada a {$successCount} {$entityName}(s)."));
        }
        if ($errorCount > 0) {
            $this->Flash->error(__("{$errorCount} {$entityName}(s) no pudieron ser etiquetados."));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Bulk delete entities
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @return Response Redirect response
     */
    protected function bulkDeleteEntity(string $entityType): Response
    {
        $this->request->allowMethod(['post']);

        $entityIds = $this->parseBulkEntityIds();

        $components = $this->getEntityComponents($entityType);
        $table = $components['table'];
        $entityName = $components['displayName'];

        $successCount = 0;
        $errorCount = 0;

        foreach ($entityIds as $entityId) {
            try {
                $entity = $table->get($entityId);

                if ($table->delete($entity)) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Error in bulk delete {$entityType} {$entityId}: " . $e->getMessage());
            }
        }

        $this->flashBulkResult($successCount, $errorCount, $entityName, 'eliminado');

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Parse bulk entity IDs from request
     *
     * @return array<int> Array of entity IDs
     */
    private function parseBulkEntityIds(): array
    {
        $rawIds = $this->request->getData('entity_ids')
            ?? $this->request->getData('ticket_ids')
            ?? '';

        return array_map('intval', explode(',', $rawIds));
    }

    /**
     * Flash bulk operation result messages
     *
     * @param int $successCount Number of successful operations
     * @param int $errorCount Number of failed operations
     * @param string $entityName Entity display name
     * @param string $action Action verb (e.g., 'asignado', 'eliminado')
     * @return void
     */
    private function flashBulkResult(int $successCount, int $errorCount, string $entityName, string $action): void
    {
        if ($successCount > 0) {
            $this->Flash->success(__("{$successCount} {$entityName}(s) {$action}(s) correctamente."));
        }
        if ($errorCount > 0) {
            $this->Flash->error(__("{$errorCount} {$entityName}(s) no pudieron ser {$action}s."));
        }
    }
}
