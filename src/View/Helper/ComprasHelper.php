<?php
declare(strict_types=1);

namespace App\View\Helper;

use App\Model\Entity\Compra;
use Cake\View\Helper;

/**
 * Compras Helper
 *
 * Encapsulates presentation logic for purchase order views.
 */
class ComprasHelper extends Helper
{
    /**
     * Helpers
     *
     * @var array
     */
    public array $helpers = ['SLA'];
    /**
     * Get badge color for compra status
     *
     * @param string $status Compra status
     * @return string Bootstrap color class
     */
    public function getStatusColor(string $status): string
    {
        $colors = [
            'nuevo' => 'info',
            'en_revision' => 'warning',
            'aprobado' => 'success',
            'en_proceso' => 'primary',
            'completado' => 'success',
            'rechazado' => 'danger',
        ];

        return $colors[$status] ?? 'secondary';
    }

    /**
     * Get label for compra status
     *
     * @param string $status Compra status
     * @return string Human-readable label
     */
    public function getStatusLabel(string $status): string
    {
        $labels = [
            'nuevo' => 'Nuevo',
            'en_revision' => 'En Revisión',
            'aprobado' => 'Aprobado',
            'en_proceso' => 'En Proceso',
            'completado' => 'Completado',
            'rechazado' => 'Rechazado',
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Get badge color for priority
     *
     * @param string $priority Priority level
     * @return string Bootstrap color class
     */
    public function getPriorityColor(string $priority): string
    {
        $colors = [
            'baja' => 'secondary',
            'media' => 'primary',
            'alta' => 'warning',
            'urgente' => 'danger',
        ];

        return $colors[$priority] ?? 'secondary';
    }

    /**
     * Get label for priority
     *
     * @param string $priority Priority level
     * @return string Human-readable label
     */
    public function getPriorityLabel(string $priority): string
    {
        $labels = [
            'baja' => 'Baja',
            'media' => 'Media',
            'alta' => 'Alta',
            'urgente' => 'Urgente',
        ];

        return $labels[$priority] ?? ucfirst($priority);
    }

    /**
     * Render status badge
     *
     * @param string $status Compra status
     * @return string HTML badge
     */
    public function statusBadge(string $status): string
    {
        $color = $this->getStatusColor($status);
        $label = $this->getStatusLabel($status);

        return sprintf(
            '<span style="border-radius: 8px;" class="small px-2 py-1 text-white fw-bold text-uppercase bg-%s">%s</span>',
            h($color),
            h($label)
        );
    }

    /**
     * Render priority badge
     *
     * @param string $priority Priority level
     * @return string HTML badge
     */
    public function priorityBadge(string $priority): string
    {
        $color = $this->getPriorityColor($priority);
        $label = $this->getPriorityLabel($priority);

        return sprintf(
            '<span class="badge bg-%s">%s</span>',
            h($color),
            h($label)
        );
    }

    /**
     * Get view URL for a compra
     *
     * @param \App\Model\Entity\Compra $compra Compra entity
     * @return array URL array for Router
     */
    public function getViewUrl(Compra $compra): array
    {
        return [
            'controller' => 'Compras',
            'action' => 'view',
            $compra->id
        ];
    }

    /**
     * Calculate SLA status (traffic light system)
     *
     * Delegates to SLAHelper for consistent SLA visualization across modules.
     *
     * @param \App\Model\Entity\Compra $compra Compra entity
     * @param string $slaField SLA field name (default: 'resolution_sla_due')
     * @return array SLA data with color, percentage, and status
     */
    public function getSlaStatus(Compra $compra, string $slaField = 'resolution_sla_due'): array
    {
        $closedStatuses = ['completado', 'rechazado'];
        return $this->SLA->getSlaStatus($compra, $slaField, $closedStatuses);
    }

    /**
     * Render SLA badge with traffic light colors
     *
     * Delegates to SLAHelper for consistent visualization.
     *
     * @param \App\Model\Entity\Compra $compra Compra entity
     * @param string $slaField SLA field name (default: 'resolution_sla_due')
     * @param bool $showPercentage Show percentage in badge (default: false)
     * @return string HTML badge
     */
    public function slaBadge(Compra $compra, string $slaField = 'resolution_sla_due', bool $showPercentage = false): string
    {
        $closedStatuses = ['completado', 'rechazado'];
        return $this->SLA->slaBadge($compra, $slaField, $closedStatuses, $showPercentage);
    }

    /**
     * Render simple SLA icon indicator (for index views)
     *
     * Delegates to SLAHelper for consistent visualization.
     *
     * @param \App\Model\Entity\Compra $compra Compra entity
     * @param string $slaField SLA field name (default: 'resolution_sla_due')
     * @return string HTML icon
     */
    public function slaIcon(Compra $compra, string $slaField = 'resolution_sla_due'): string
    {
        $closedStatuses = ['completado', 'rechazado'];
        return $this->SLA->slaIcon($compra, $slaField, $closedStatuses);
    }

    /**
     * Render detailed SLA indicator with date and icon (for detail views)
     *
     * Delegates to SLAHelper for consistent visualization.
     *
     * @param \App\Model\Entity\Compra $compra Compra entity
     * @param string $slaField SLA field name (default: 'resolution_sla_due')
     * @param bool $showProgressBar Show progress bar (default: false)
     * @return string HTML indicator
     */
    public function slaIndicator(Compra $compra, string $slaField = 'resolution_sla_due', bool $showProgressBar = false): string
    {
        $closedStatuses = ['completado', 'rechazado'];
        return $this->SLA->slaIndicator($compra, $slaField, $closedStatuses, $showProgressBar);
    }
}
