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
}
