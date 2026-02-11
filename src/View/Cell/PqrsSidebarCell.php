<?php
declare(strict_types=1);

namespace App\View\Cell;

use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use App\Model\Enum\PqrsStatus;
use Cake\View\Cell;

/**
 * PQRS Sidebar Cell
 *
 * Displays sidebar navigation for PQRS management with counts
 */
class PqrsSidebarCell extends Cell
{
    use LocatorAwareTrait;
    /**
     * Display method
     *
     * @param string $currentView Current active view
     * @param string|null $userRole User role (admin, agent, servicio_cliente, compras, requester)
     * @param int|null $userId Current user ID
     * @return void
     */
    public function display(string $currentView = 'todos_sin_resolver', ?string $userRole = null, ?int $userId = null): void
    {
        $pqrsTable = $this->fetchTable('Pqrs');
        $usersTable = $this->fetchTable('Users');

        // Get current user object for profile image
        $currentUser = null;
        if ($userId) {
            $currentUser = $usersTable->get($userId);
        }

        // Optimize counts with a single query using GROUP BY
        $statusCounts = $pqrsTable->find()
            ->select(['status', 'count' => $pqrsTable->find()->func()->count('*')])
            ->group(['status'])
            ->all()
            ->combine('status', 'count')
            ->toArray();

        // Calculate counts from grouped results
        $counts = [
            'sin_asignar' => $pqrsTable->find()
                ->where(['assignee_id IS' => null, 'status NOT IN' => PqrsStatus::resolvedValues()])
                ->count(),
            'todos_sin_resolver' => ($statusCounts['nuevo'] ?? 0) + ($statusCounts['en_revision'] ?? 0) + ($statusCounts['en_proceso'] ?? 0),
            'nuevas' => $statusCounts['nuevo'] ?? 0,
            'en_revision' => $statusCounts['en_revision'] ?? 0,
            'en_proceso' => $statusCounts['en_proceso'] ?? 0,
            'resueltas' => $statusCounts['resuelto'] ?? 0,
            'cerradas' => $statusCounts['cerrado'] ?? 0,
        ];

        // Add "mis_pqrs" count for agents, servicio_cliente, compras and admin
        if (($userRole === 'agent' || $userRole === 'servicio_cliente' || $userRole === 'compras' || $userRole === 'admin') && $userId) {
            $counts['mis_pqrs'] = $pqrsTable->find()
                ->where(['assignee_id' => $userId, 'status NOT IN' => PqrsStatus::resolvedValues()])
                ->count();
        }

        // Get counts by type with GROUP BY
        $typeCountsRaw = $pqrsTable->find()
            ->select(['type', 'count' => $pqrsTable->find()->func()->count('*')])
            ->where(['status NOT IN' => PqrsStatus::resolvedValues()])
            ->group(['type'])
            ->all()
            ->combine('type', 'count')
            ->toArray();

        $typeCounts = [
            'peticion' => $typeCountsRaw['peticion'] ?? 0,
            'queja' => $typeCountsRaw['queja'] ?? 0,
            'reclamo' => $typeCountsRaw['reclamo'] ?? 0,
            'sugerencia' => $typeCountsRaw['sugerencia'] ?? 0,
        ];

        $this->set('counts', $counts);
        $this->set('typeCounts', $typeCounts);
        $this->set('view', $currentView);
        $this->set('userRole', $userRole);
        $this->set('currentUser', $currentUser);
    }
}
