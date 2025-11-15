<?php
declare(strict_types=1);

namespace App\View\Cell;

use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\View\Cell;

class TicketsSidebarCell extends Cell
{
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
        $ticketsTable = TableRegistry::getTableLocator()->get('Tickets');

        // Build base query - filter by user role
        $baseQuery = $ticketsTable->find();
        if ($userRole === 'requester' && $userId) {
            $baseQuery->where(['requester_id' => $userId]);
        }

        // If user is compras role, filter to only their assigned tickets
        if ($userRole === 'compras' && $userId) {
            $baseQuery->where(['assignee_id' => $userId]);
        }

        // Calculate counts for each view
        $counts = [
            'sin_asignar' => (clone $baseQuery)
                ->where(['assignee_id IS' => null, 'status !=' => 'resuelto'])
                ->count(),
            'todos_sin_resolver' => (clone $baseQuery)
                ->where(['status !=' => 'resuelto'])
                ->count(),
            'pendientes' => (clone $baseQuery)
                ->where(['status' => 'pendiente'])
                ->count(),
            'nuevos' => (clone $baseQuery)
                ->where(['status' => 'nuevo'])
                ->count(),
            'abiertos' => (clone $baseQuery)
                ->where(['status' => 'abierto'])
                ->count(),
            'resueltos' => (clone $baseQuery)
                ->where(['status' => 'resuelto'])
                ->count(),
        ];

        // Add "mis_tickets" count for agents and compras
        if (($userRole === 'agent' || $userRole === 'compras') && $userId) {
            $counts['mis_tickets'] = $ticketsTable->find()
                ->where(['assignee_id' => $userId, 'status !=' => 'resuelto'])
                ->count();
        }

        $this->set('counts', $counts);
        $this->set('view', $currentView);
        $this->set('userRole', $userRole);
    }
}
