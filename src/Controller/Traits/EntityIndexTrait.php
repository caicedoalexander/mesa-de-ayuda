<?php
declare(strict_types=1);

namespace App\Controller\Traits;

/**
 * EntityIndexTrait
 *
 * Handles index/listing operations for Tickets, PQRS, and Compras.
 * Extracted from TicketSystemControllerTrait for SRP compliance.
 *
 * Responsibilities:
 * - Entity listing with filters
 * - Query building and pagination
 * - Filter data preparation for views
 * - Role-based filtering
 *
 * Required controller properties:
 * - $this->Authentication (CakePHP Authentication component)
 * - $this->paginate() method
 */
trait EntityIndexTrait
{
    // EntityConfigTrait is included via TicketSystemControllerTrait facade
    // Methods used: getEntityComponents(), getEntityVariable(), getDefaultUsersRoleFilter(),
    //               getStatusConfig(), getPriorityConfig()

    /**
     * Generic index method for listing entities with filters, sorting, and pagination
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @param array $config Configuration options
     * @return void Sets view variables
     */
    protected function indexEntity(string $entityType, array $config = []): void
    {
        $defaults = [
            'defaultView' => 'todos_sin_resolver',
            'defaultSort' => 'created',
            'defaultDirection' => 'desc',
            'paginationLimit' => 10,
            'contain' => null,
            'validSortFields' => null,
            'filterParams' => [],
            'usersRoleFilter' => null,
            'additionalViewVars' => [],
            'beforeQuery' => null,
            'specialRedirects' => null,
        ];
        $config = array_merge($defaults, $config);

        $user = $this->Authentication->getIdentity();
        $userRole = $user ? $user->get('role') : null;

        // Handle special redirects
        if (is_callable($config['specialRedirects'])) {
            $redirect = $config['specialRedirects']($this->request, $user, $userRole);
            if ($redirect !== null) {
                return;
            }
        }

        // Get filter parameters
        $filters = $this->extractFilterParams($config);
        $sortField = $this->request->getQuery('sort', $config['defaultSort']);
        $sortDirection = $this->request->getQuery('direction', $config['defaultDirection']);

        // Get table and metadata
        $components = $this->getEntityComponents($entityType);
        $table = $components['table'];
        $tableAlias = $table->getAlias();
        $entityVariable = $this->getEntityVariable($entityType);

        // Build query
        $query = $table->find('withFilters', [
            'view' => $filters['view'],
            'filters' => $filters['queryFilters'],
            'user' => $user
        ]);

        // Apply contain
        $contain = $config['contain'] ?? $this->getDefaultContain($entityType);
        $query->contain($contain);

        // Apply sorting
        $query = $this->applySorting(
            $query,
            $entityType,
            $tableAlias,
            $sortField,
            $sortDirection,
            $filters['view'],
            $config
        );

        // Apply role-based filters
        $this->applyRoleBasedFilters($query, $entityType, $user, $userRole, $tableAlias);

        // Custom modifications
        if (is_callable($config['beforeQuery'])) {
            $config['beforeQuery']($query, $user, $userRole);
        }

        // Paginate
        $entities = $this->paginate($query, [
            'limit' => $config['paginationLimit'],
        ]);

        // Get filter data for view
        $filterData = $this->getFilterDataForView($entityType, $config);

        // Set view variables
        $viewVars = [
            $entityVariable => $entities,
            'view' => $filters['view'],
            'filters' => $filters['viewFilters'],
        ];

        $viewVars = array_merge($viewVars, $filterData, $config['additionalViewVars']);

        $this->set($viewVars);
    }

    /**
     * Extract filter parameters from request
     *
     * @param array $config Configuration
     * @return array Filter data
     */
    private function extractFilterParams(array $config): array
    {
        $view = $this->request->getQuery('view', $config['defaultView']);
        $search = $this->request->getQuery('search');
        $filterStatus = $this->request->getQuery('filter_status');
        $filterPriority = $this->request->getQuery('filter_priority');
        $filterAssignee = $this->request->getQuery('filter_assignee');
        $filterDateFrom = $this->request->getQuery('filter_date_from');
        $filterDateTo = $this->request->getQuery('filter_date_to');

        // Additional entity-specific filters
        $additionalFilters = [];
        foreach ($config['filterParams'] as $paramName => $queryKey) {
            $additionalFilters[$paramName] = $this->request->getQuery($queryKey);
        }

        $queryFilters = array_merge([
            'search' => $search,
            'status' => $filterStatus,
            'priority' => $filterPriority,
            'assignee_id' => $filterAssignee,
            'date_from' => $filterDateFrom,
            'date_to' => $filterDateTo,
        ], $additionalFilters);

        $viewFilters = compact(
            'search',
            'filterStatus',
            'filterPriority',
            'filterAssignee',
            'filterDateFrom',
            'filterDateTo'
        );

        // Add entity-specific filter vars
        foreach ($config['filterParams'] as $paramName => $queryKey) {
            $filterVarName = 'filter' . ucfirst($paramName);
            $viewFilters[$filterVarName] = $this->request->getQuery($queryKey);
        }

        return [
            'view' => $view,
            'queryFilters' => $queryFilters,
            'viewFilters' => $viewFilters,
        ];
    }

    /**
     * Apply sorting to query
     *
     * @param \Cake\ORM\Query $query Query object
     * @param string $entityType Entity type
     * @param string $tableAlias Table alias
     * @param string $sortField Sort field
     * @param string $sortDirection Sort direction
     * @param string $view Current view filter
     * @param array $config Configuration
     * @return \Cake\ORM\Query Modified query
     */
    private function applySorting($query, string $entityType, string $tableAlias, string $sortField, string $sortDirection, string $view, array $config)
    {
        $validSortFields = $config['validSortFields'] ?? $this->getValidSortFields($entityType);

        // Special handling for resolved views
        $resolvedViews = ['resueltos', 'resueltas', 'completados'];
        $isResolvedView = in_array($view, $resolvedViews);

        if ($isResolvedView && $this->request->getQuery('sort') === null) {
            $query->orderBy([$tableAlias . '.resolved_at' => 'DESC']);
        } elseif (in_array($sortField, $validSortFields)) {
            $query->orderBy([$tableAlias . '.' . $sortField => strtoupper($sortDirection)]);
        } else {
            $query->orderBy([$tableAlias . '.' . $config['defaultSort'] => 'DESC']);
        }

        return $query;
    }

    /**
     * Apply role-based filters to query
     *
     * @param \Cake\ORM\Query $query Query object
     * @param string $entityType Entity type
     * @param mixed $user Current user
     * @param string|null $userRole User role
     * @param string $tableAlias Table alias
     * @return void Modifies query by reference
     */
    private function applyRoleBasedFilters($query, string $entityType, $user, ?string $userRole, string $tableAlias): void
    {
        if (!$user || !$userRole) {
            return;
        }

        $userId = $user->get('id');

        // Requesters only see their own entities
        if ($userRole === 'requester' && $entityType === 'ticket') {
            $query->where([$tableAlias . '.requester_id' => $userId]);
        }
    }

    /**
     * Get default contain associations based on entity type
     *
     * @param string $entityType Entity type
     * @return array Contain array
     */
    private function getDefaultContain(string $entityType): array
    {
        return match ($entityType) {
            'ticket' => ['Requesters' => ['Organizations'], 'Assignees'],
            'pqrs' => ['Assignees'],
            'compra' => ['Requesters', 'Assignees'],
            default => [],
        };
    }

    /**
     * Get valid sort fields based on entity type
     *
     * @param string $entityType Entity type
     * @return array Valid sort fields
     */
    private function getValidSortFields(string $entityType): array
    {
        $common = ['created', 'modified', 'status', 'priority', 'subject'];

        return match ($entityType) {
            'ticket' => array_merge($common, ['ticket_number']),
            'pqrs' => array_merge($common, ['pqrs_number', 'type']),
            'compra' => array_merge($common, ['compra_number']),
            default => $common,
        };
    }

    /**
     * Get filter data for view (users, statuses, priorities, etc.)
     *
     * @param string $entityType Entity type
     * @param array $config Configuration
     * @return array Filter data
     */
    private function getFilterDataForView(string $entityType, array $config): array
    {
        $data = [];

        // Get users for assignment dropdown
        $usersRoleFilter = $config['usersRoleFilter'] ?? $this->getDefaultUsersRoleFilter($entityType);
        if ($usersRoleFilter !== null) {
            $usersVarName = $this->getUsersVariableName($entityType);
            $data[$usersVarName] = $this->fetchTable('Users')
                ->find('list')
                ->where(['role IN' => $usersRoleFilter, 'is_active' => true])
                ->toArray();
        }

        // Common data
        $data['priorities'] = $this->getPriorityConfig($entityType);
        $data['statuses'] = $this->getStatusConfig($entityType);

        // Entity-specific data
        if ($entityType === 'ticket') {
            $data['organizations'] = $this->fetchTable('Organizations')->find('list')->toArray();
            $data['tags'] = $this->fetchTable('Tags')->find()->toArray();
        } elseif ($entityType === 'pqrs') {
            $data['types'] = [
                'peticion' => 'Petición',
                'queja' => 'Queja',
                'reclamo' => 'Reclamo',
                'sugerencia' => 'Sugerencia',
            ];
        }

        return $data;
    }

    /**
     * Get users variable name for view
     *
     * @param string $entityType Entity type
     * @return string Variable name
     */
    private function getUsersVariableName(string $entityType): string
    {
        return match ($entityType) {
            'ticket' => 'agents',
            'pqrs' => 'users',
            'compra' => 'comprasUsers',
            default => 'users',
        };
    }
}
