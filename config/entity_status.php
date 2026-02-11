<?php
declare(strict_types=1);

/**
 * Entity Status Configuration
 *
 * REFACTORED (TRAIT-003): Configuration extracted from ViewDataNormalizerTrait
 *
 * This file contains status display configuration with icons, colors (hex), and labels
 * for use in status badges, dropdowns, and filters.
 *
 * Colors match StatusHelper for system-wide consistency.
 *
 * @see \App\Controller\Traits\ViewDataNormalizerTrait
 * @see \App\View\Helper\StatusHelper
 */
return [
    // Status configurations per entity type
    'status' => [
        'ticket' => [
            'nuevo' => ['icon' => 'bi-circle-fill', 'color' => '#ffc107', 'label' => 'Nuevo'],
            'abierto' => ['icon' => 'bi-circle-fill', 'color' => '#dc3545', 'label' => 'Abierto'],
            'pendiente' => ['icon' => 'bi-circle-fill', 'color' => '#0d6efd', 'label' => 'Pendiente'],
            'resuelto' => ['icon' => 'bi-circle-fill', 'color' => '#198754', 'label' => 'Resuelto'],
            'convertido' => ['icon' => 'bi-arrow-left-right', 'color' => '#6c757d', 'label' => 'Convertido'],
        ],
        'pqrs' => [
            'nuevo' => ['icon' => 'bi-circle-fill', 'color' => '#ffc107', 'label' => 'Nuevo'],
            'en_revision' => ['icon' => 'bi-circle-fill', 'color' => '#0dcaf0', 'label' => 'En Revisión'],
            'en_proceso' => ['icon' => 'bi-circle-fill', 'color' => '#0d6efd', 'label' => 'En Proceso'],
            'resuelto' => ['icon' => 'bi-circle-fill', 'color' => '#198754', 'label' => 'Resuelto'],
            'cerrado' => ['icon' => 'bi-circle-fill', 'color' => '#6c757d', 'label' => 'Cerrado'],
        ],
        'compra' => [
            'nuevo' => ['icon' => 'bi-circle-fill', 'color' => '#ffc107', 'label' => 'Nuevo'],
            'en_revision' => ['icon' => 'bi-circle-fill', 'color' => '#0dcaf0', 'label' => 'En Revisión'],
            'aprobado' => ['icon' => 'bi-circle-fill', 'color' => '#198754', 'label' => 'Aprobado'],
            'en_proceso' => ['icon' => 'bi-circle-fill', 'color' => '#0d6efd', 'label' => 'En Proceso'],
            'completado' => ['icon' => 'bi-circle-fill', 'color' => '#28a745', 'label' => 'Completado'],
            'rechazado' => ['icon' => 'bi-circle-fill', 'color' => '#dc3545', 'label' => 'Rechazado'],
            'convertido' => ['icon' => 'bi-arrow-left-right', 'color' => '#6c757d', 'label' => 'Convertido'],
        ],
    ],

    // Priority options (same for all entity types)
    'priority' => [
        'baja' => 'Baja',
        'media' => 'Media',
        'alta' => 'Alta',
        'urgente' => 'Urgente',
    ],

    // Resolved/final statuses per entity type
    'resolved_statuses' => [
        'ticket' => ['resuelto', 'convertido'],
        'pqrs' => ['resuelto', 'cerrado'],
        'compra' => ['completado', 'rechazado', 'convertido'],
    ],
];