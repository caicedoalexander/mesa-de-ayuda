<?php
declare(strict_types=1);

namespace App\View\Cell;

use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\View\Cell;

class TicketsSidebarCell extends Cell
{
    use LocatorAwareTrait;
    /**
     * Display method
     *
     * @param string $currentView Current active view
     * @param string|null $userRole User role (admin, agent, requester)
     * @param int|null $userId Current user ID
     * @return void
     */
    public function display(string $currentView = 'todos_sin_resolver', ?string $userRole = null, ?int $userId = null): void
    {
        $ticketsTable = $this->fetchTable('Tickets');
        $usersTable = $this->fetchTable('Users');

        // Get current user object for profile image
        $currentUser = null;
        if ($userId) {
            $currentUser = $usersTable->get($userId);
        }

        // Optimize counts with a single query using GROUP BY
        $baseQuery = $ticketsTable->find();
        if ($userRole === 'requester' && $userId) {
            $baseQuery->where(['requester_id' => $userId]);
        }

        // Get all status counts in a single query
        $statusCounts = (clone $baseQuery)
            ->select(['status', 'count' => $ticketsTable->find()->func()->count('*')])
            ->group(['status'])
            ->all()
            ->combine('status', 'count')
            ->toArray();

        $isAgent = $userRole === 'agent';

        // For agents: count status-specific tickets that are assigned to them
        $agentStatusCounts = [];
        if ($isAgent && $userId) {
            $agentStatusCounts = $ticketsTable->find()
                ->select(['status', 'count' => $ticketsTable->find()->func()->count('*')])
                ->where(['assignee_id' => $userId, 'status IN' => ['nuevo', 'abierto', 'pendiente']])
                ->group(['status'])
                ->all()
                ->combine('status', 'count')
                ->toArray();
        }

        // Calculate counts from grouped results
        // Must match findWithFilters conditions in TicketsTable
        $counts = [
            'sin_asignar' => (clone $baseQuery)
                ->where(['assignee_id IS' => null, 'status NOT IN' => ['resuelto', 'convertido']])
                ->count(),
            'todos_sin_resolver' => ($statusCounts['nuevo'] ?? 0) + ($statusCounts['abierto'] ?? 0) + ($statusCounts['pendiente'] ?? 0),
            'pendientes' => $isAgent ? ($agentStatusCounts['pendiente'] ?? 0) : ($statusCounts['pendiente'] ?? 0),
            'nuevos' => $isAgent ? ($agentStatusCounts['nuevo'] ?? 0) : ($statusCounts['nuevo'] ?? 0),
            'abiertos' => $isAgent ? ($agentStatusCounts['abierto'] ?? 0) : ($statusCounts['abierto'] ?? 0),
            'resueltos' => $statusCounts['resuelto'] ?? 0,
            'convertidos' => $statusCounts['convertido'] ?? 0,
        ];

        // Add "mis_tickets" count for agents
        if ($isAgent && $userId) {
            $counts['mis_tickets'] = $ticketsTable->find()
                ->where(['assignee_id' => $userId, 'status NOT IN' => ['resuelto', 'convertido']])
                ->count();
        }

        $this->set('counts', $counts);
        $this->set('view', $currentView);
        $this->set('userRole', $userRole);
        $this->set('currentUser', $currentUser);
    }
}
