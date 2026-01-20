<?php
declare(strict_types=1);

namespace App\Controller\Traits;

use Cake\Http\Response;
use Cake\Log\Log;

/**
 * EntityViewTrait
 *
 * Handles view/detail and history operations for Tickets, PQRS, and Compras.
 * Extracted from TicketSystemControllerTrait for SRP compliance.
 *
 * Responsibilities:
 * - Entity detail view with associations
 * - AJAX history loading
 * - Permission checks
 * - View data normalization
 *
 * Required controller properties:
 * - $this->Authentication (CakePHP Authentication component)
 */
trait EntityViewTrait
{
    // EntityConfigTrait is included via TicketSystemControllerTrait facade
    // Methods used: getEntityComponents(), getSingleEntityVariable(), getStatusConfig(),
    //               getPriorityConfig(), getResolvedStatuses(), isEntityLocked(),
    //               getEntityMetadata(), getDefaultAgentsRoleFilter(), getHistoryTable()

    /**
     * View single entity with all related data
     *
     * @param string $entityType Entity type ('ticket', 'pqrs', 'compra')
     * @param int $id Entity id
     * @param array $config Configuration options
     * @return \Cake\Http\Response|null Redirect response if permission denied, null otherwise
     */
    protected function viewEntity(string $entityType, int $id, array $config = []): ?Response
    {
        $components = $this->getEntityComponents($entityType);
        $tableName = $components['tableName'];
        $variableName = $this->getSingleEntityVariable($entityType);

        // Get contain configuration
        $contain = $config['contain'] ?? $this->getDefaultViewContain($entityType, $config['lazyLoadHistory'] ?? false);

        // Load entity with associations
        $entity = $this->fetchTable($tableName)->get($id, compact('contain'));

        // Permission check
        if (isset($config['permissionCheck']) && is_callable($config['permissionCheck'])) {
            $permissionResult = $config['permissionCheck']($entity);
            if ($permissionResult !== null) {
                return $permissionResult;
            }
        }

        // Get agents for assignment dropdown
        $agentsRoleFilter = $config['agentsRoleFilter'] ?? $this->getDefaultAgentsRoleFilter($entityType);
        $agents = $this->fetchTable('Users')
            ->find('list')
            ->where(['role IN' => $agentsRoleFilter, 'is_active' => true])
            ->toArray();

        // Prepare view variables
        $viewVars = [
            $variableName => $entity,
            'agents' => $agents,
        ];

        // Add additional view vars from config
        if (isset($config['additionalViewVars'])) {
            $viewVars = array_merge($viewVars, $config['additionalViewVars']);
        }

        // Run beforeSet callback if provided
        if (isset($config['beforeSet']) && is_callable($config['beforeSet'])) {
            $viewVars = $config['beforeSet']($entity, $viewVars);
        }

        // Auto-inject normalized data for view templates
        $allStatuses = $this->getStatusConfig($entityType);
        $selectableStatuses = array_filter($allStatuses, function($key) {
            return $key !== 'convertido';
        }, ARRAY_FILTER_USE_KEY);

        $viewVars = array_merge($viewVars, [
            'entityType' => $entityType,
            'entityMetadata' => $this->getEntityMetadata($entityType, $entity),
            'statuses' => $selectableStatuses,
            'priorities' => $this->getPriorityConfig($entityType),
            'resolvedStatuses' => $this->getResolvedStatuses($entityType),
            'isLocked' => $this->isEntityLocked($entityType, $entity),
        ]);

        $this->set($viewVars);

        return null;
    }

    /**
     * AJAX endpoint for lazy loading entity history
     *
     * @param string $entityType Entity type ('ticket', 'pqrs', 'compra')
     * @param int $id Entity id
     * @return void Sets JSON response
     */
    protected function historyEntity(string $entityType, int $id): void
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName('Json');

        try {
            $user = $this->Authentication->getIdentity();
            if (!$user) {
                $this->setJsonError('No autenticado', 401);
                return;
            }

            $components = $this->getEntityComponents($entityType);
            $tableName = $components['tableName'];
            $foreignKey = $components['foreignKey'];

            // Get entity to verify permissions
            $entity = $this->fetchTable($tableName)->get($id);

            // Permission check
            $userRole = $user->get('role');
            $userId = $user->get('id');

            if ($userRole === 'requester' && $entity->requester_id !== $userId) {
                $this->setJsonError('No tienes permiso para ver este historial', 403);
                return;
            }

            // Load history
            $historyTable = $this->getHistoryTable($entityType);
            $history = $historyTable
                ->find()
                ->where([$foreignKey => $id])
                ->contain(['Users'])
                ->order([$historyTable->getAlias() . '.created' => 'DESC'])
                ->all();

            // Format for JSON response
            $formattedHistory = $this->formatHistoryForJson($history);

            $this->set('history', $formattedHistory);
            $this->viewBuilder()->setOption('serialize', ['history']);

        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            Log::warning(ucfirst($entityType) . ' not found for history: ' . $id);
            $this->setJsonError(ucfirst($entityType) . ' no encontrado', 404);
        } catch (\Exception $e) {
            Log::error('Error loading ' . $entityType . ' history: ' . $e->getMessage(), [
                $entityType . '_id' => $id,
                'exception' => $e,
            ]);
            $this->setJsonError('Error al cargar el historial', 500);
        }
    }

    /**
     * Get default contain for view method
     *
     * @param string $entityType Entity type
     * @param bool $lazyLoadHistory Whether to lazy load history
     * @return array Contain configuration
     */
    private function getDefaultViewContain(string $entityType, bool $lazyLoadHistory = false): array
    {
        $contain = match ($entityType) {
            'ticket' => [
                'Requesters' => ['Organizations'],
                'Assignees',
                'TicketComments' => ['Users'],
                'Attachments',
                'Tags',
                'TicketFollowers' => ['Users'],
            ],
            'pqrs' => [
                'Assignees',
                'PqrsComments' => [
                    'Users',
                    'PqrsAttachments',
                    'sort' => ['PqrsComments.created' => 'ASC']
                ],
                'PqrsAttachments',
            ],
            'compra' => [
                'Requesters',
                'Assignees',
                'ComprasComments' => ['Users'],
                'ComprasAttachments',
            ],
            default => [],
        };

        // Add history if not lazy loading
        if (!$lazyLoadHistory) {
            $historyAssoc = match ($entityType) {
                'ticket' => 'TicketHistory',
                'pqrs' => 'PqrsHistory',
                'compra' => 'ComprasHistory',
                default => null,
            };

            if ($historyAssoc) {
                $contain[$historyAssoc] = [
                    'Users',
                    'sort' => [$historyAssoc . '.created' => 'DESC']
                ];
            }
        }

        return $contain;
    }

    /**
     * Format history entries for JSON response
     *
     * @param iterable $history History entries
     * @return array Formatted history
     */
    private function formatHistoryForJson(iterable $history): array
    {
        $formatted = [];

        foreach ($history as $entry) {
            $userData = $entry->user
                ? ['id' => $entry->user->id, 'name' => $entry->user->name]
                : ['id' => null, 'name' => 'Sistema'];

            $formatted[] = [
                'id' => $entry->id,
                'field_name' => $entry->field_name,
                'old_value' => $entry->old_value,
                'new_value' => $entry->new_value,
                'description' => $entry->description,
                'created' => $entry->created->format('Y-m-d H:i:s'),
                'user' => $userData,
            ];
        }

        return $formatted;
    }

    /**
     * Set JSON error response
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @return void
     */
    private function setJsonError(string $message, int $statusCode): void
    {
        $this->set('error', $message);
        $this->viewBuilder()->setOption('serialize', ['error']);
        $this->response = $this->response->withStatus($statusCode);
    }
}
