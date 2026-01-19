<?php
declare(strict_types=1);

namespace App\Model\Table\Traits;

use Cake\ORM\Query\SelectQuery;

/**
 * Filterable Trait
 *
 * Provides generic filtering logic for Tickets, PQRS, and Compras tables.
 * Eliminates ~300 lines of duplicated code across 3 tables.
 *
 * Resolves: MODEL-001 (findWithFilters() code duplication)
 *
 * Usage:
 * 1. Use this trait in your Table class
 * 2. Define getFilterConfig() to return module-specific configuration
 * 3. Call applyGenericFilters() from findWithFilters()
 */
trait FilterableTrait
{
    /**
     * Get filter configuration for this module
     *
     * Must be implemented by each Table class
     *
     * @return array{
     *   tableAlias: string,
     *   numberField: string,
     *   resolvedStatuses: array,
     *   searchFields: array,
     *   viewConfig: array
     * }
     */
    abstract protected function getFilterConfig(): array;

    /**
     * Apply generic filters to query
     *
     * @param SelectQuery $query Query object
     * @param array $filters Filter parameters
     * @param string $view Current view
     * @param mixed $user Current user
     * @return SelectQuery
     */
    protected function applyGenericFilters(
        SelectQuery $query,
        array $filters,
        string $view,
        mixed $user
    ): SelectQuery {
        $config = $this->getFilterConfig();
        $alias = $config['tableAlias'];
        $resolvedStatuses = $config['resolvedStatuses'];
        $searchFields = $config['searchFields'];
        $viewConfig = $config['viewConfig'] ?? [];

        $userId = $user ? $user->get('id') : null;
        $userRole = $user ? $user->get('role') : null;
        $isAgent = $userRole === 'agent';

        // Apply view-based filters (if no search is active)
        if (empty($filters['search'])) {
            $query = $this->applyViewFilter(
                $query,
                $view,
                $alias,
                $resolvedStatuses,
                $userId,
                $isAgent,
                $viewConfig
            );
        }

        // Apply search filter
        if (!empty($filters['search'])) {
            $query = $this->applySearchFilter(
                $query,
                $filters['search'],
                $searchFields,
                $view,
                $alias,
                $resolvedStatuses
            );
        }

        // Apply specific filters (common to all modules)
        $query = $this->applySpecificFilters($query, $filters, $alias);

        return $query;
    }

    /**
     * Apply view-based filter
     *
     * @param SelectQuery $query Query object
     * @param string $view View name
     * @param string $alias Table alias
     * @param array $resolvedStatuses Statuses considered "resolved"
     * @param int|null $userId Current user ID
     * @param bool $isAgent Whether user is an agent
     * @param array $viewConfig Module-specific view configurations
     * @return SelectQuery
     */
    protected function applyViewFilter(
        SelectQuery $query,
        string $view,
        string $alias,
        array $resolvedStatuses,
        ?int $userId,
        bool $isAgent,
        array $viewConfig
    ): SelectQuery {
        // Check for module-specific view handling first
        if (isset($viewConfig[$view])) {
            $conditions = $viewConfig[$view];
            if (is_callable($conditions)) {
                return $conditions($query, $alias, $userId, $isAgent);
            }
            $query->where($conditions);
            return $query;
        }

        // Generic view handling
        switch ($view) {
            case 'sin_asignar':
                $query->where([
                    "{$alias}.assignee_id IS" => null,
                    "{$alias}.status NOT IN" => $resolvedStatuses,
                ]);
                break;

            case 'mis_tickets':
            case 'mis_compras':
            case 'mis_pqrs':
                if ($userId) {
                    $query->where([
                        "{$alias}.assignee_id" => $userId,
                        "{$alias}.status NOT IN" => $resolvedStatuses,
                    ]);
                }
                break;

            case 'creados_por_mi':
                if ($userId) {
                    $query->where([
                        "{$alias}.requester_id" => $userId,
                    ]);
                    // Exclude converted if applicable
                    if (in_array('convertido', $resolvedStatuses)) {
                        $query->where(["{$alias}.status !=" => 'convertido']);
                    }
                }
                break;

            case 'todos_sin_resolver':
                $query->where(["{$alias}.status NOT IN" => $resolvedStatuses]);
                break;

            case 'nuevos':
            case 'nuevas':
                $conditions = ["{$alias}.status" => 'nuevo'];
                if ($isAgent && $userId) {
                    $conditions["{$alias}.assignee_id"] = $userId;
                }
                $query->where($conditions);
                break;

            case 'abiertos':
                $conditions = ["{$alias}.status" => 'abierto'];
                if ($isAgent && $userId) {
                    $conditions["{$alias}.assignee_id"] = $userId;
                }
                $query->where($conditions);
                break;

            case 'pendientes':
                $conditions = ["{$alias}.status" => 'pendiente'];
                if ($isAgent && $userId) {
                    $conditions["{$alias}.assignee_id"] = $userId;
                }
                $query->where($conditions);
                break;

            case 'en_proceso':
                $query->where(["{$alias}.status" => 'en_proceso']);
                break;

            case 'en_revision':
                $query->where(["{$alias}.status" => 'en_revision']);
                break;

            case 'resueltos':
            case 'resueltas':
                $query->where(["{$alias}.status" => 'resuelto']);
                break;

            case 'cerradas':
            case 'cerrados':
                $query->where(["{$alias}.status" => 'cerrado']);
                break;

            case 'completados':
                $query->where(["{$alias}.status" => 'completado']);
                break;

            case 'rechazados':
                $query->where(["{$alias}.status" => 'rechazado']);
                break;

            case 'aprobados':
                $query->where(["{$alias}.status" => 'aprobado']);
                break;

            case 'convertidos':
                $query->where(["{$alias}.status" => 'convertido']);
                break;

            case 'recientes':
                $query->where([
                    "{$alias}.created >=" => date('Y-m-d', strtotime('-7 days')),
                ]);
                if (in_array('convertido', $resolvedStatuses)) {
                    $query->where(["{$alias}.status !=" => 'convertido']);
                }
                break;

            case 'vencidos_sla':
                $query->where([
                    "{$alias}.sla_due_date <" => new \DateTime(),
                    "{$alias}.status NOT IN" => $resolvedStatuses,
                ]);
                break;
        }

        return $query;
    }

    /**
     * Apply search filter
     *
     * @param SelectQuery $query Query object
     * @param string $search Search term
     * @param array $searchFields Fields to search in
     * @param string $view Current view
     * @param string $alias Table alias
     * @param array $resolvedStatuses Resolved statuses
     * @return SelectQuery
     */
    protected function applySearchFilter(
        SelectQuery $query,
        string $search,
        array $searchFields,
        string $view,
        string $alias,
        array $resolvedStatuses
    ): SelectQuery {
        $orConditions = [];

        foreach ($searchFields as $field) {
            $orConditions["{$field} LIKE"] = '%' . $search . '%';
        }

        $query->where(['OR' => $orConditions]);

        // Exclude converted from search unless explicitly viewing convertidos
        if ($view !== 'convertidos' && in_array('convertido', $resolvedStatuses)) {
            $query->where(["{$alias}.status !=" => 'convertido']);
        }

        return $query;
    }

    /**
     * Apply specific filters (common to all modules)
     *
     * @param SelectQuery $query Query object
     * @param array $filters Filter parameters
     * @param string $alias Table alias
     * @return SelectQuery
     */
    protected function applySpecificFilters(
        SelectQuery $query,
        array $filters,
        string $alias
    ): SelectQuery {
        if (!empty($filters['status'])) {
            $query->where(["{$alias}.status" => $filters['status']]);
        }

        if (!empty($filters['priority'])) {
            $query->where(["{$alias}.priority" => $filters['priority']]);
        }

        if (!empty($filters['assignee_id'])) {
            if ($filters['assignee_id'] === 'unassigned') {
                $query->where(["{$alias}.assignee_id IS" => null]);
            } else {
                $query->where(["{$alias}.assignee_id" => $filters['assignee_id']]);
            }
        }

        if (!empty($filters['date_from'])) {
            $query->where(["{$alias}.created >=" => $filters['date_from'] . ' 00:00:00']);
        }

        if (!empty($filters['date_to'])) {
            $query->where(["{$alias}.created <=" => $filters['date_to'] . ' 23:59:59']);
        }

        // Module-specific filters
        if (!empty($filters['type'])) {
            $query->where(["{$alias}.type" => $filters['type']]);
        }

        return $query;
    }
}
