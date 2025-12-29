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
     * @param \App\Model\Entity\Compra $compra Compra entity
     * @return array SLA data with color, percentage, and status
     */
    public function getSlaStatus(Compra $compra): array
    {
        // If no SLA date or already completed/rejected, return N/A
        if (!$compra->sla_due_date || in_array($compra->status, ['completado', 'rechazado'])) {
            return [
                'color' => 'secondary',
                'textColor' => 'text-muted',
                'bgColor' => 'bg-secondary',
                'percentage' => 0,
                'status' => 'completed',
                'label' => 'N/A',
                'icon' => 'bi-check-circle',
            ];
        }

        $now = new \Cake\I18n\DateTime();
        $created = $compra->created;
        $deadline = $compra->sla_due_date;

        // Calculate time elapsed and total time
        $totalSeconds = $deadline->diffInSeconds($created);
        $elapsedSeconds = $now->diffInSeconds($created);
        $remainingSeconds = $deadline->diffInSeconds($now);

        // Calculate percentage of time used (0-100)
        $percentageUsed = $totalSeconds > 0 ? ($elapsedSeconds / $totalSeconds) * 100 : 100;
        $percentageUsed = min(100, max(0, $percentageUsed)); // Clamp between 0-100

        // Determine if SLA is breached
        if ($now > $deadline) {
            // RED: SLA vencido
            return [
                'color' => 'danger',
                'textColor' => 'text-danger',
                'bgColor' => 'bg-danger',
                'percentage' => 100,
                'status' => 'breached',
                'label' => 'Vencido',
                'icon' => 'bi-exclamation-triangle-fill',
                'hoursOver' => ceil($remainingSeconds / 3600), // Hours overdue
            ];
        } elseif ($percentageUsed >= 75) {
            // RED: Menos de 25% de tiempo restante (crítico)
            return [
                'color' => 'danger',
                'textColor' => 'text-danger',
                'bgColor' => 'bg-danger',
                'percentage' => round($percentageUsed, 1),
                'status' => 'critical',
                'label' => 'Crítico',
                'icon' => 'bi-exclamation-triangle-fill',
                'hoursLeft' => ceil($remainingSeconds / 3600),
            ];
        } elseif ($percentageUsed >= 50) {
            // YELLOW: Entre 25% y 50% de tiempo restante (advertencia)
            return [
                'color' => 'warning',
                'textColor' => 'text-warning',
                'bgColor' => 'bg-warning',
                'percentage' => round($percentageUsed, 1),
                'status' => 'warning',
                'label' => 'Advertencia',
                'icon' => 'bi-exclamation-circle-fill',
                'hoursLeft' => ceil($remainingSeconds / 3600),
            ];
        } else {
            // GREEN: Más de 50% de tiempo restante (OK)
            return [
                'color' => 'success',
                'textColor' => 'text-success',
                'bgColor' => 'bg-success',
                'percentage' => round($percentageUsed, 1),
                'status' => 'ok',
                'label' => 'En tiempo',
                'icon' => 'bi-check-circle-fill',
                'hoursLeft' => ceil($remainingSeconds / 3600),
            ];
        }
    }

    /**
     * Render SLA badge with traffic light colors
     *
     * @param \App\Model\Entity\Compra $compra Compra entity
     * @param bool $showPercentage Show percentage in badge (default: false)
     * @return string HTML badge
     */
    public function slaBadge(Compra $compra, bool $showPercentage = false): string
    {
        $sla = $this->getSlaStatus($compra);

        if ($sla['status'] === 'completed') {
            return '<span class="text-muted">N/A</span>';
        }

        $label = $sla['label'];
        if ($showPercentage && isset($sla['hoursLeft'])) {
            $label .= ' (' . $sla['hoursLeft'] . 'h)';
        } elseif ($showPercentage && isset($sla['hoursOver'])) {
            $label .= ' (+' . $sla['hoursOver'] . 'h)';
        }

        return sprintf(
            '<span class="badge %s"><i class="%s"></i> %s</span>',
            h($sla['bgColor']),
            h($sla['icon']),
            h($label)
        );
    }

    /**
     * Render simple SLA icon indicator (for index views)
     *
     * @param \App\Model\Entity\Compra $compra Compra entity
     * @return string HTML icon
     */
    public function slaIcon(Compra $compra): string
    {
        if (!$compra->sla_due_date || in_array($compra->status, ['completado', 'rechazado'])) {
            return '<i class="bi bi-dash-circle text-muted" title="N/A"></i>';
        }

        $sla = $this->getSlaStatus($compra);
        $dateFormatted = $compra->sla_due_date->format('h:m a, d M');

        // Build tooltip text
        $tooltip = $sla['label'] . ' - ' . $dateFormatted;
        if (isset($sla['hoursLeft'])) {
            $tooltip .= ' (' . $sla['hoursLeft'] . 'h restantes)';
        } elseif (isset($sla['hoursOver'])) {
            $tooltip .= ' (+' . $sla['hoursOver'] . 'h de retraso)';
        }

        return sprintf(
            '<i class="%s %s" style="font-size: 1.2rem;" title="%s"></i>',
            h($sla['icon']),
            h($sla['textColor']),
            h($tooltip)
        );
    }

    /**
     * Render detailed SLA indicator with date and icon (for detail views)
     *
     * @param \App\Model\Entity\Compra $compra Compra entity
     * @param bool $showProgressBar Show progress bar (default: false)
     * @return string HTML indicator
     */
    public function slaIndicator(Compra $compra, bool $showProgressBar = false): string
    {
        if (!$compra->sla_due_date || in_array($compra->status, ['completado', 'rechazado'])) {
            return '<span class="text-muted">N/A</span>';
        }

        $sla = $this->getSlaStatus($compra);
        $dateFormatted = $compra->sla_due_date->format('h:m a, d');

        $html = '<div class="d-flex align-items-center gap-2">';
        $html .= sprintf('<i class="%s %s"></i>', h($sla['icon']), h($sla['textColor']));

        if ($sla['status'] === 'breached') {
            $html .= sprintf(
                '<span class="%s fw-bold">%s</span>',
                h($sla['textColor']),
                h($dateFormatted)
            );
        } else {
            $html .= sprintf(
                '<span class="%s">%s</span>',
                h($sla['textColor']),
                h($dateFormatted)
            );
        }

        if (isset($sla['hoursLeft'])) {
            $html .= sprintf(
                '<small class="%s">(%sh)</small>',
                h($sla['textColor']),
                h($sla['hoursLeft'])
            );
        } elseif (isset($sla['hoursOver'])) {
            $html .= sprintf(
                '<small class="%s">(+%sh)</small>',
                h($sla['textColor']),
                h($sla['hoursOver'])
            );
        }

        $html .= '</div>';

        // Optional progress bar
        if ($showProgressBar && $sla['status'] !== 'breached') {
            $html .= sprintf(
                '<div class="progress mt-1" style="height: 6px;">
                    <div class="progress-bar %s" role="progressbar" style="width: %s%%" aria-valuenow="%s" aria-valuemin="0" aria-valuemax="100"></div>
                </div>',
                h($sla['bgColor']),
                h($sla['percentage']),
                h($sla['percentage'])
            );
        }

        return $html;
    }
}
