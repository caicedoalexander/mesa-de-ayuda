<?php
declare(strict_types=1);

namespace App\View\Helper;

use Cake\View\Helper;

/**
 * Status Helper
 *
 * Centralizes all status, priority, and type color definitions
 * to ensure consistency across the entire application.
 * Colors match those used in statistics charts.
 */
class StatusHelper extends Helper
{
    /**
     * Priority colors (universal across all modules)
     *
     * @var array<string, string>
     */
    private const PRIORITY_COLORS = [
        'baja' => '#6c757d',    // Gray
        'media' => '#0dcaf0',   // Cyan
        'alta' => '#ffc107',    // Yellow
        'urgente' => '#dc3545', // Red
    ];

    /**
     * Priority labels
     *
     * @var array<string, string>
     */
    private const PRIORITY_LABELS = [
        'baja' => 'Baja',
        'media' => 'Media',
        'alta' => 'Alta',
        'urgente' => 'Urgente',
    ];

    /**
     * Ticket status colors
     *
     * @var array<string, string>
     */
    private const TICKET_STATUS_COLORS = [
        'nuevo' => '#ffc107',      // Yellow
        'abierto' => '#dc3545',    // Red
        'pendiente' => '#0d6efd',  // Blue
        'resuelto' => '#198754',   // Green
        'convertido' => '#6c757d', // Gray
    ];

    /**
     * Ticket status labels
     *
     * @var array<string, string>
     */
    private const TICKET_STATUS_LABELS = [
        'nuevo' => 'Nuevo',
        'abierto' => 'Abierto',
        'pendiente' => 'Pendiente',
        'resuelto' => 'Resuelto',
        'convertido' => 'Convertido',
    ];

    /**
     * PQRS status colors
     *
     * @var array<string, string>
     */
    private const PQRS_STATUS_COLORS = [
        'nuevo' => '#ffc107',       // Yellow
        'en_revision' => '#0dcaf0', // Cyan
        'en_proceso' => '#0d6efd',  // Blue
        'resuelto' => '#198754',    // Green
        'cerrado' => '#6c757d',     // Gray
    ];

    /**
     * PQRS status labels
     *
     * @var array<string, string>
     */
    private const PQRS_STATUS_LABELS = [
        'nuevo' => 'Nuevo',
        'en_revision' => 'En Revisión',
        'en_proceso' => 'En Proceso',
        'resuelto' => 'Resuelto',
        'cerrado' => 'Cerrado',
    ];

    /**
     * Compras status colors
     *
     * @var array<string, string>
     */
    private const COMPRAS_STATUS_COLORS = [
        'nuevo' => '#ffc107',       // Yellow
        'en_revision' => '#0dcaf0', // Cyan
        'aprobado' => '#198754',    // Green
        'en_proceso' => '#0d6efd',  // Blue
        'completado' => '#28a745',  // Dark Green
        'rechazado' => '#dc3545',   // Red
        'convertido' => '#6c757d',  // Gray
    ];

    /**
     * Compras status labels
     *
     * @var array<string, string>
     */
    private const COMPRAS_STATUS_LABELS = [
        'nuevo' => 'Nuevo',
        'en_revision' => 'En Revisión',
        'aprobado' => 'Aprobado',
        'en_proceso' => 'En Proceso',
        'completado' => 'Completado',
        'rechazado' => 'Rechazado',
        'convertido' => 'Convertido',
    ];

    /**
     * PQRS type colors
     *
     * @var array<string, string>
     */
    private const TYPE_COLORS = [
        'peticion' => '#3B82F6',    // Blue
        'queja' => '#F59E0B',       // Orange/Yellow
        'reclamo' => '#EF4444',     // Red
        'sugerencia' => '#00A85E',  // Green
    ];

    /**
     * PQRS type labels
     *
     * @var array<string, string>
     */
    private const TYPE_LABELS = [
        'peticion' => 'Petición',
        'queja' => 'Queja',
        'reclamo' => 'Reclamo',
        'sugerencia' => 'Sugerencia',
    ];

    /**
     * Get color for a priority
     *
     * @param string $priority Priority value
     * @return string Hex color code
     */
    public function priorityColor(string $priority): string
    {
        return self::PRIORITY_COLORS[strtolower($priority)] ?? '#6c757d';
    }

    /**
     * Get label for a priority
     *
     * @param string $priority Priority value
     * @return string Label
     */
    public function priorityLabel(string $priority): string
    {
        return self::PRIORITY_LABELS[strtolower($priority)] ?? ucfirst($priority);
    }

    /**
     * Get color for a status based on entity type
     *
     * @param string $status Status value
     * @param string $entityType Entity type: 'ticket', 'pqrs', or 'compra'
     * @return string Hex color code
     */
    public function statusColor(string $status, string $entityType = 'ticket'): string
    {
        $statusLower = strtolower($status);

        return match($entityType) {
            'pqrs' => self::PQRS_STATUS_COLORS[$statusLower] ?? '#6c757d',
            'compra' => self::COMPRAS_STATUS_COLORS[$statusLower] ?? '#6c757d',
            default => self::TICKET_STATUS_COLORS[$statusLower] ?? '#6c757d',
        };
    }

    /**
     * Get label for a status based on entity type
     *
     * @param string $status Status value
     * @param string $entityType Entity type: 'ticket', 'pqrs', or 'compra'
     * @return string Label
     */
    public function statusLabel(string $status, string $entityType = 'ticket'): string
    {
        $statusLower = strtolower($status);

        return match($entityType) {
            'pqrs' => self::PQRS_STATUS_LABELS[$statusLower] ?? ucfirst($status),
            'compra' => self::COMPRAS_STATUS_LABELS[$statusLower] ?? ucfirst($status),
            default => self::TICKET_STATUS_LABELS[$statusLower] ?? ucfirst($status),
        };
    }

    /**
     * Get color for a PQRS type
     *
     * @param string $type Type value
     * @return string Hex color code
     */
    public function typeColor(string $type): string
    {
        return self::TYPE_COLORS[strtolower($type)] ?? '#6c757d';
    }

    /**
     * Get label for a PQRS type
     *
     * @param string $type Type value
     * @return string Label
     */
    public function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[strtolower($type)] ?? ucfirst($type);
    }

    /**
     * Render a status badge with consistent styling
     *
     * @param string $status Status value
     * @param string $entityType Entity type: 'ticket', 'pqrs', or 'compra'
     * @param array $options Additional options: ['url' => string]
     * @return string HTML badge
     */
    public function statusBadge(string $status, string $entityType = 'ticket', array $options = []): string
    {
        $color = $this->statusColor($status, $entityType);
        $label = $this->statusLabel($status, $entityType);

        $badge = sprintf(
            '<span class="badge" style="background-color: %s; color: white; border-radius: 8px; padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">%s</span>',
            h($color),
            h($label)
        );

        $url = $options['url'] ?? null;
        if ($url) {
            return $this->getView()->Html->link($badge, $url, ['escape' => false, 'class' => 'text-decoration-none']);
        }

        return $badge;
    }

    /**
     * Render a priority badge with consistent styling
     *
     * @param string $priority Priority value
     * @param array $options Additional options: ['url' => string]
     * @return string HTML badge
     */
    public function priorityBadge(string $priority, array $options = []): string
    {
        $color = $this->priorityColor($priority);
        $label = $this->priorityLabel($priority);

        $badge = sprintf(
            '<span class="badge" style="background-color: %s; color: white; border-radius: 8px; padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">%s</span>',
            h($color),
            h($label)
        );

        $url = $options['url'] ?? null;
        if ($url) {
            return $this->getView()->Html->link($badge, $url, ['escape' => false, 'class' => 'text-decoration-none']);
        }

        return $badge;
    }

    /**
     * Render a type badge with consistent styling (PQRS only)
     *
     * @param string $type Type value
     * @param array $options Additional options: ['url' => string]
     * @return string HTML badge
     */
    public function typeBadge(string $type, array $options = []): string
    {
        $color = $this->typeColor($type);
        $label = $this->typeLabel($type);

        $badge = sprintf(
            '<span class="badge" style="background-color: %s; color: white; border-radius: 8px; padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">%s</span>',
            h($color),
            h($label)
        );

        $url = $options['url'] ?? null;
        if ($url) {
            return $this->getView()->Html->link($badge, $url, ['escape' => false, 'class' => 'text-decoration-none']);
        }

        return $badge;
    }

    /**
     * Legacy method for backward compatibility
     * Maps old badge() calls to new statusBadge()
     *
     * @param string $label Status label
     * @param array $options Additional options
     * @return string HTML badge
     * @deprecated Use statusBadge() instead
     */
    public function badge(string $label, array $options = []): string
    {
        return $this->statusBadge($label, 'ticket', $options);
    }
}
