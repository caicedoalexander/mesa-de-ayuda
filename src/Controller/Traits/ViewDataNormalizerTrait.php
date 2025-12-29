<?php
declare(strict_types=1);

namespace App\Controller\Traits;

/**
 * View Data Normalizer Trait
 *
 * Provides standardized data structures for view templates across all entity types
 * (Tickets, PQRS, Compras). This ensures consistent data format and eliminates
 * duplication in templates.
 *
 * Usage:
 * - Call getEntityMetadata() to get field mappings
 * - Call getStatusConfig() to get status display configuration
 * - Call getPriorityConfig() to get priority options
 * - Call getResolvedStatuses() to get statuses considered "resolved"
 *
 * @since December 2024
 */
trait ViewDataNormalizerTrait
{
    /**
     * Get normalized entity metadata for views
     *
     * Returns standardized field mappings so templates can work generically
     * across different entity types without hardcoded field names.
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @param object $entity Entity instance (optional, for future extensions)
     * @return array Metadata configuration
     */
    protected function getEntityMetadata(string $entityType, $entity = null): array
    {
        return match ($entityType) {
            'ticket' => [
                'numberField' => 'ticket_number',
                'numberLabel' => 'Ticket',
                'commentsField' => 'ticket_comments',
                'attachmentsField' => 'attachments',
                'descriptionField' => 'description',
                'subjectField' => 'subject',
                'createdField' => 'created',
                'resolvedField' => 'resolved_at',
                'statusField' => 'status',
                'priorityField' => 'priority',
                'containerClass' => 'ticket-view-container',
                'marqueeClass' => 'ticket-subject',
            ],
            'pqrs' => [
                'numberField' => 'pqrs_number',
                'numberLabel' => 'PQRS',
                'commentsField' => 'pqrs_comments',
                'attachmentsField' => 'pqrs_attachments',
                'descriptionField' => 'description',
                'subjectField' => 'subject',
                'createdField' => 'created',
                'resolvedField' => 'resolved_at',
                'statusField' => 'status',
                'priorityField' => 'priority',
                'containerClass' => 'pqrs-view-container',
                'marqueeClass' => 'pqrs-subject',
            ],
            'compra' => [
                'numberField' => 'compra_number',
                'numberLabel' => 'Compra',
                'commentsField' => 'compras_comments',
                'attachmentsField' => 'compras_attachments',
                'descriptionField' => 'description',
                'subjectField' => 'subject',
                'createdField' => 'created',
                'resolvedField' => 'resolved_at',
                'statusField' => 'status',
                'priorityField' => 'priority',
                'containerClass' => 'compras-view-container',
                'marqueeClass' => 'compra-subject',
            ],
            default => throw new \InvalidArgumentException("Invalid entity type: {$entityType}"),
        };
    }

    /**
     * Get status configuration for entity type
     *
     * Returns status display configuration with icons, colors (hex), and labels
     * for use in status badges, dropdowns, and filters.
     *
     * Colors match StatusHelper for system-wide consistency.
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @return array Status configuration [status_key => ['icon', 'color', 'label']]
     */
    protected function getStatusConfig(string $entityType): array
    {
        return match ($entityType) {
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
            default => throw new \InvalidArgumentException("Invalid entity type: {$entityType}"),
        };
    }

    /**
     * Get priority configuration for entity type
     *
     * Returns priority options for dropdowns and display.
     * Currently same for all entity types.
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @return array Priority configuration [priority_key => label]
     */
    protected function getPriorityConfig(string $entityType): array
    {
        // Same for all types currently
        return [
            'baja' => 'Baja',
            'media' => 'Media',
            'alta' => 'Alta',
            'urgente' => 'Urgente',
        ];
    }

    /**
     * Get resolved statuses for entity type
     *
     * Returns array of status keys that are considered "resolved"
     * for determining if resolved_at timestamp should be shown.
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @return array Array of status keys
     */
    protected function getResolvedStatuses(string $entityType): array
    {
        return match ($entityType) {
            'ticket' => ['resuelto', 'convertido'],
            'pqrs' => ['resuelto', 'cerrado'],
            'compra' => ['completado', 'rechazado', 'convertido'],
            default => throw new \InvalidArgumentException("Invalid entity type: {$entityType}"),
        };
    }

    /**
     * Check if an entity is locked (in a final/closed status)
     *
     * Locked entities cannot be modified (no status changes, priority changes,
     * reassignments, new comments, or file attachments allowed).
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @param object $entity Entity instance
     * @return bool True if entity is locked
     */
    protected function isEntityLocked(string $entityType, $entity): bool
    {
        $finalStatuses = $this->getResolvedStatuses($entityType);
        return in_array($entity->status, $finalStatuses, true);
    }
}
