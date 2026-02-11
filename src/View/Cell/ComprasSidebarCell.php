<?php
declare(strict_types=1);

namespace App\View\Cell;

use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use App\Model\Enum\CompraStatus;
use Cake\View\Cell;

/**
 * Compras Sidebar Cell
 *
 * Displays sidebar navigation for purchase order management with counts
 */
class ComprasSidebarCell extends Cell
{
    use LocatorAwareTrait;
    /**
     * Display method
     *
     * @param string $currentView Current active view
     * @param string|null $userRole User role (admin, compras)
     * @param int|null $userId Current user ID
     * @return void
     */
    public function display(string $currentView = 'todos_sin_resolver', ?string $userRole = null, ?int $userId = null): void
    {
        $comprasTable = $this->fetchTable('Compras');
        $usersTable = $this->fetchTable('Users');

        // Get current user object for profile image
        $currentUser = null;
        if ($userId) {
            $currentUser = $usersTable->get($userId);
        }

        // Optimize counts with a single query using GROUP BY
        $statusCounts = $comprasTable->find()
            ->select(['status', 'count' => $comprasTable->find()->func()->count('*')])
            ->group(['status'])
            ->all()
            ->combine('status', 'count')
            ->toArray();

        // Calculate counts from grouped results
        $counts = [
            'sin_asignar' => $comprasTable->find()
                ->where(['assignee_id IS' => null, 'status NOT IN' => CompraStatus::resolvedValues()])
                ->count(),
            'todos_sin_resolver' => ($statusCounts['nuevo'] ?? 0) + ($statusCounts['en_revision'] ?? 0) + ($statusCounts['aprobado'] ?? 0) + ($statusCounts['en_proceso'] ?? 0),
            'nuevos' => $statusCounts['nuevo'] ?? 0,
            'en_revision' => $statusCounts['en_revision'] ?? 0,
            'aprobados' => $statusCounts['aprobado'] ?? 0,
            'en_proceso' => $statusCounts['en_proceso'] ?? 0,
            'completados' => $statusCounts['completado'] ?? 0,
            'rechazados' => $statusCounts['rechazado'] ?? 0,
            'convertidos' => $statusCounts['convertido'] ?? 0,
        ];

        // Add "mis_compras" count for compras and admin
        if (($userRole === 'compras' || $userRole === 'admin') && $userId) {
            $counts['mis_compras'] = $comprasTable->find()
                ->where(['assignee_id' => $userId, 'status NOT IN' => CompraStatus::resolvedValues()])
                ->count();
        }

        // Count SLA breached
        $now = new DateTime();
        $counts['vencidos_sla'] = $comprasTable->find()
            ->where([
                'sla_due_date <' => $now,
                'status NOT IN' => CompraStatus::resolvedValues(),
            ])
            ->count();

        $this->set('counts', $counts);
        $this->set('view', $currentView);
        $this->set('userRole', $userRole);
        $this->set('currentUser', $currentUser);
    }
}
