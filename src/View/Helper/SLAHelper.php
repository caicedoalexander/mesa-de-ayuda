<?php
declare(strict_types=1);

namespace App\View\Helper;

use Cake\View\Helper;
use Cake\Datasource\EntityInterface;
use Cake\I18n\FrozenTime;

/**
 * SLAHelper
 *
 * Shared visualization helper for SLA (Service Level Agreement) status.
 * Provides traffic light system (GREEN/YELLOW/RED) for ANY entity with SLA fields.
 *
 * Can be used by both PQRS and Compras modules for consistent SLA visualization.
 *
 * @package App\View\Helper
 */
class SLAHelper extends Helper
{
    /**
     * Get SLA status with traffic light calculation
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity with SLA field
     * @param string $slaField SLA field name ('first_response_sla_due' or 'resolution_sla_due')
     * @param array $closedStatuses Array of statuses considered closed
     * @return array Status information
     */
    public function getSlaStatus(EntityInterface $entity, string $slaField, array $closedStatuses): array
    {
        // If no SLA deadline set
        if (!$entity->$slaField) {
            return [
                'status' => 'none',
                'color' => 'secondary',
                'percentage' => 0,
                'icon' => 'bi bi-dash-circle',
                'label' => 'Sin SLA',
                'hoursLeft' => null,
                'hoursOver' => null,
            ];
        }

        // If entity is closed
        if (in_array($entity->status, $closedStatuses)) {
            return [
                'status' => 'completed',
                'color' => 'secondary',
                'percentage' => 100,
                'icon' => 'bi bi-check-circle-fill',
                'label' => 'Completado',
                'hoursLeft' => null,
                'hoursOver' => null,
            ];
        }

        $now = new FrozenTime();
        $deadline = $entity->$slaField;
        $created = $entity->created;

        // Calculate time metrics
        $totalSeconds = $deadline->diffInSeconds($created);
        $elapsedSeconds = $now->diffInSeconds($created);
        $percentageUsed = $totalSeconds > 0 ? ($elapsedSeconds / $totalSeconds) * 100 : 0;

        // Calculate hours left/over
        if ($now > $deadline) {
            // SLA breached
            $hoursOver = abs($now->diffInHours($deadline, false));
            $hoursLeft = null;

            return [
                'status' => 'breached',
                'color' => 'danger',
                'percentage' => min(100, $percentageUsed),
                'icon' => 'bi bi-exclamation-circle-fill',
                'label' => "Vencido ({$hoursOver}h)",
                'hoursLeft' => null,
                'hoursOver' => $hoursOver,
            ];
        }

        // Not breached - calculate status based on time remaining
        $hoursLeft = $now->diffInHours($deadline, false);

        // Traffic light thresholds
        if ($percentageUsed >= 75) {
            // < 25% time remaining - CRITICAL (RED)
            $status = 'critical';
            $color = 'danger';
            $icon = 'bi bi-exclamation-triangle-fill';
            $label = "Crítico ({$hoursLeft}h)";
        } elseif ($percentageUsed >= 50) {
            // 25-50% time remaining - WARNING (YELLOW)
            $status = 'warning';
            $color = 'warning';
            $icon = 'bi bi-exclamation-triangle';
            $label = "Alerta ({$hoursLeft}h)";
        } else {
            // > 50% time remaining - OK (GREEN)
            $status = 'ok';
            $color = 'success';
            $icon = 'bi bi-circle-fill';
            $label = "En tiempo ({$hoursLeft}h)";
        }

        return [
            'status' => $status,
            'color' => $color,
            'percentage' => min(100, $percentageUsed),
            'icon' => $icon,
            'label' => $label,
            'hoursLeft' => $hoursLeft,
            'hoursOver' => null,
        ];
    }

    /**
     * Display SLA badge with icon and label
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity with SLA field
     * @param string $slaField SLA field name
     * @param array $closedStatuses Closed statuses array
     * @param bool $showPercentage Show percentage used
     * @return string HTML badge
     */
    public function slaBadge(EntityInterface $entity, string $slaField, array $closedStatuses, bool $showPercentage = false): string
    {
        $status = $this->getSlaStatus($entity, $slaField, $closedStatuses);

        $percentageText = $showPercentage && $status['percentage'] > 0
            ? sprintf(' %.0f%%', $status['percentage'])
            : '';

        return sprintf(
            '<span class="badge bg-%s"><i class="%s"></i> %s%s</span>',
            h($status['color']),
            h($status['icon']),
            h($status['label']),
            $percentageText
        );
    }

    /**
     * Display simple SLA icon with tooltip
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity with SLA field
     * @param string $slaField SLA field name
     * @param array $closedStatuses Closed statuses array
     * @return string HTML icon with tooltip
     */
    public function slaIcon(EntityInterface $entity, string $slaField, array $closedStatuses): string
    {
        $status = $this->getSlaStatus($entity, $slaField, $closedStatuses);

        $tooltipTitle = $status['label'];
        if ($entity->$slaField) {
            $tooltipTitle .= ' - Límite: ' . $entity->$slaField->i18nFormat('dd/MM/yyyy HH:mm');
        }

        return sprintf(
            '<i class="%s text-%s" data-bs-toggle="tooltip" title="%s"></i>',
            h($status['icon']),
            h($status['color']),
            h($tooltipTitle)
        );
    }

    /**
     * Display detailed SLA indicator with progress bar
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity with SLA field
     * @param string $slaField SLA field name
     * @param array $closedStatuses Closed statuses array
     * @param bool $showProgressBar Show progress bar
     * @return string HTML indicator
     */
    public function slaIndicator(EntityInterface $entity, string $slaField, array $closedStatuses, bool $showProgressBar = false): string
    {
        $status = $this->getSlaStatus($entity, $slaField, $closedStatuses);

        $html = sprintf(
            '<div class="sla-indicator">%s',
            $this->slaBadge($entity, $slaField, $closedStatuses, true)
        );

        if ($showProgressBar && $status['percentage'] > 0) {
            $html .= sprintf(
                '<div class="progress mt-2" style="height: 6px;">
                    <div class="progress-bar bg-%s" role="progressbar" style="width: %.0f%%" aria-valuenow="%.0f" aria-valuemin="0" aria-valuemax="100"></div>
                </div>',
                h($status['color']),
                $status['percentage'],
                $status['percentage']
            );
        }

        $html .= '</div>';

        return $html;
    }
}
