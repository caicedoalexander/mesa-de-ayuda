<?php
declare(strict_types=1);

namespace App\View\Helper;

use Cake\View\Helper;

/**
 * PQRS Helper
 *
 * Encapsulates presentation logic for PQRS views.
 */
class PqrsHelper extends Helper
{
    /**
     * Helpers
     *
     * @var array
     */
    public array $helpers = ['SLA'];

    /**
     * Get badge color for PQRS type
     *
     * @param string $type PQRS type
     * @return string Bootstrap color class
     */
    public function getTypeColor(string $type): string
    {
        $colors = [
            'peticion' => 'primary',
            'queja' => 'warning',
            'reclamo' => 'danger',
            'sugerencia' => 'success',
        ];

        return $colors[$type] ?? 'secondary';
    }

    /**
     * Get label for PQRS type
     *
     * @param string $type PQRS type
     * @return string Human-readable label
     */
    public function getTypeLabel(string $type): string
    {
        $labels = [
            'peticion' => 'Petición',
            'queja' => 'Queja',
            'reclamo' => 'Reclamo',
            'sugerencia' => 'Sugerencia',
        ];

        return $labels[$type] ?? ucfirst($type);
    }

    /**
     * Get badge color for PQRS status
     *
     * @param string $status PQRS status
     * @return string Bootstrap color class
     */
    public function getStatusColor(string $status): string
    {
        $colors = [
            'nuevo' => 'warning',
            'en_revision' => 'info',
            'en_proceso' => 'primary',
            'resuelto' => 'success',
            'cerrado' => 'secondary',
        ];

        return $colors[$status] ?? 'secondary';
    }

    /**
     * Get label for PQRS status
     *
     * @param string $status PQRS status
     * @return string Human-readable label
     */
    public function getStatusLabel(string $status): string
    {
        $labels = [
            'nuevo' => 'Nuevo',
            'en_revision' => 'En Revisión',
            'en_proceso' => 'En Proceso',
            'resuelto' => 'Resuelto',
            'cerrado' => 'Cerrado',
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Render type badge
     *
     * @param string $type PQRS type
     * @return string HTML badge
     */
    public function typeBadge(string $type): string
    {
        $color = $this->getTypeColor($type);
        $label = $this->getTypeLabel($type);

        return sprintf(
            '<span class="fw-bold text-dark text-uppercase %s">%s</span>',
            h($color),
            h($label)
        );
    }

    /**
     * Render status badge
     *
     * @param string $status PQRS status
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

    // ========================================================================
    // SLA Visualization Methods (delegating to SLAHelper)
    // ========================================================================

    /**
     * Render first response SLA badge
     *
     * @param \Cake\Datasource\EntityInterface $pqr PQRS entity
     * @param bool $showPercentage Show percentage in badge (default: false)
     * @return string HTML badge
     */
    public function firstResponseSlaBadge($pqr, bool $showPercentage = false): string
    {
        $closedStatuses = ['resuelto', 'cerrado'];
        return $this->SLA->slaBadge($pqr, 'first_response_sla_due', $closedStatuses, $showPercentage);
    }

    /**
     * Render resolution SLA badge
     *
     * @param \Cake\Datasource\EntityInterface $pqr PQRS entity
     * @param bool $showPercentage Show percentage in badge (default: false)
     * @return string HTML badge
     */
    public function resolutionSlaBadge($pqr, bool $showPercentage = false): string
    {
        $closedStatuses = ['resuelto', 'cerrado'];
        return $this->SLA->slaBadge($pqr, 'resolution_sla_due', $closedStatuses, $showPercentage);
    }

    /**
     * Render first response SLA icon (for index views)
     *
     * @param \Cake\Datasource\EntityInterface $pqr PQRS entity
     * @return string HTML icon
     */
    public function firstResponseSlaIcon($pqr): string
    {
        $closedStatuses = ['resuelto', 'cerrado'];
        return $this->SLA->slaIcon($pqr, 'first_response_sla_due', $closedStatuses);
    }

    /**
     * Render resolution SLA icon (for index views)
     *
     * @param \Cake\Datasource\EntityInterface $pqr PQRS entity
     * @return string HTML icon
     */
    public function resolutionSlaIcon($pqr): string
    {
        $closedStatuses = ['resuelto', 'cerrado'];
        return $this->SLA->slaIcon($pqr, 'resolution_sla_due', $closedStatuses);
    }

    /**
     * Render first response SLA indicator (for detail views)
     *
     * @param \Cake\Datasource\EntityInterface $pqr PQRS entity
     * @param bool $showProgressBar Show progress bar (default: false)
     * @return string HTML indicator
     */
    public function firstResponseSlaIndicator($pqr, bool $showProgressBar = false): string
    {
        $closedStatuses = ['resuelto', 'cerrado'];
        return $this->SLA->slaIndicator($pqr, 'first_response_sla_due', $closedStatuses, $showProgressBar);
    }

    /**
     * Render resolution SLA indicator (for detail views)
     *
     * @param \Cake\Datasource\EntityInterface $pqr PQRS entity
     * @param bool $showProgressBar Show progress bar (default: false)
     * @return string HTML indicator
     */
    public function resolutionSlaIndicator($pqr, bool $showProgressBar = false): string
    {
        $closedStatuses = ['resuelto', 'cerrado'];
        return $this->SLA->slaIndicator($pqr, 'resolution_sla_due', $closedStatuses, $showProgressBar);
    }
}
