<?php
declare(strict_types=1);

namespace App\Controller\Traits;

use Cake\Datasource\EntityInterface;

/**
 * EntityConfigTrait
 *
 * Provides configuration and metadata methods for entity types.
 * Shared by all entity-related controller traits.
 *
 * Responsibilities:
 * - Entity component resolution (table, service, display name)
 * - Status and priority configuration
 * - Entity locking logic (final states)
 * - Variable naming conventions
 */
trait EntityConfigTrait
{
    /**
     * Get entity components (table, service, display name) based on type
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @return array Associative array with keys: table, service, displayName, tableName, foreignKey
     */
    protected function getEntityComponents(string $entityType): array
    {
        $components = match ($entityType) {
            'ticket' => [
                'table' => $this->Tickets ?? $this->fetchTable('Tickets'),
                'service' => $this->ticketService ?? null,
                'displayName' => 'Ticket',
                'tableName' => 'Tickets',
                'foreignKey' => 'ticket_id',
            ],
            'pqrs' => [
                'table' => $this->Pqrs ?? $this->fetchTable('Pqrs'),
                'service' => $this->pqrsService ?? null,
                'displayName' => 'PQRS',
                'tableName' => 'Pqrs',
                'foreignKey' => 'pqrs_id',
            ],
            'compra' => [
                'table' => $this->Compras ?? $this->fetchTable('Compras'),
                'service' => $this->comprasService ?? null,
                'displayName' => 'Compra',
                'tableName' => 'Compras',
                'foreignKey' => 'compra_id',
            ],
            default => throw new \InvalidArgumentException("Invalid entity type: {$entityType}"),
        };

        // Backward compatibility with numeric indices
        return array_merge($components, [
            0 => $components['table'],
            1 => $components['service'],
            2 => $components['displayName'],
        ]);
    }

    /**
     * Get history table based on entity type
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @return \Cake\ORM\Table History table instance
     */
    protected function getHistoryTable(string $entityType): \Cake\ORM\Table
    {
        return match ($entityType) {
            'ticket' => $this->fetchTable('TicketHistory'),
            'pqrs' => $this->fetchTable('PqrsHistory'),
            'compra' => $this->fetchTable('ComprasHistory'),
            default => throw new \InvalidArgumentException("Invalid entity type: {$entityType}"),
        };
    }

    /**
     * Get tags table name based on entity type
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @return string Tags table name
     */
    protected function getTagsTableName(string $entityType): string
    {
        return match ($entityType) {
            'ticket' => 'TicketTags',
            'pqrs' => 'PqrsTags',
            'compra' => 'ComprasTags',
            default => throw new \InvalidArgumentException("Invalid entity type: {$entityType}"),
        };
    }

    /**
     * Get entity variable name for view (plural form for index)
     *
     * @param string $entityType Entity type
     * @return string Variable name
     */
    protected function getEntityVariable(string $entityType): string
    {
        return match ($entityType) {
            'ticket' => 'tickets',
            'pqrs' => 'pqrs',
            'compra' => 'compras',
            default => $entityType . 's',
        };
    }

    /**
     * Get single entity variable name for view (singular form for view)
     *
     * @param string $entityType Entity type
     * @return string Variable name
     */
    protected function getSingleEntityVariable(string $entityType): string
    {
        return match ($entityType) {
            'ticket' => 'ticket',
            'pqrs' => 'pqrs',
            'compra' => 'compra',
            default => $entityType,
        };
    }

    /**
     * Get status configuration for entity type
     *
     * @param string $entityType Entity type
     * @return array Status configuration
     */
    protected function getStatusConfig(string $entityType): array
    {
        return match ($entityType) {
            'ticket' => [
                'nuevo' => 'Nuevo',
                'abierto' => 'Abierto',
                'pendiente' => 'Pendiente',
                'resuelto' => 'Resuelto',
                'cerrado' => 'Cerrado',
            ],
            'pqrs' => [
                'nuevo' => 'Nuevo',
                'en_revision' => 'En Revisión',
                'en_proceso' => 'En Proceso',
                'resuelto' => 'Resuelto',
                'cerrado' => 'Cerrado',
                'convertido' => 'Convertido',
            ],
            'compra' => [
                'nuevo' => 'Nuevo',
                'en_revision' => 'En Revisión',
                'aprobado' => 'Aprobado',
                'en_proceso' => 'En Proceso',
                'completado' => 'Completado',
                'rechazado' => 'Rechazado',
            ],
            default => [],
        };
    }

    /**
     * Get priority configuration for entity type
     *
     * @param string $entityType Entity type
     * @return array Priority configuration
     */
    protected function getPriorityConfig(string $entityType): array
    {
        return [
            'baja' => 'Baja',
            'media' => 'Media',
            'alta' => 'Alta',
            'urgente' => 'Urgente',
        ];
    }

    /**
     * Get resolved/final statuses for entity type
     *
     * @param string $entityType Entity type
     * @return array Resolved statuses
     */
    protected function getResolvedStatuses(string $entityType): array
    {
        return match ($entityType) {
            'ticket' => ['resuelto', 'cerrado'],
            'pqrs' => ['resuelto', 'cerrado', 'convertido'],
            'compra' => ['completado', 'rechazado'],
            default => [],
        };
    }

    /**
     * Check if entity is locked (in final status)
     *
     * @param string $entityType Entity type
     * @param EntityInterface $entity Entity instance
     * @return bool True if entity is locked
     */
    protected function isEntityLocked(string $entityType, EntityInterface $entity): bool
    {
        $resolvedStatuses = $this->getResolvedStatuses($entityType);
        return in_array($entity->status, $resolvedStatuses, true);
    }

    /**
     * Get entity metadata for views
     *
     * @param string $entityType Entity type
     * @param EntityInterface $entity Entity instance
     * @return array Metadata array
     */
    protected function getEntityMetadata(string $entityType, EntityInterface $entity): array
    {
        $numberField = match ($entityType) {
            'ticket' => 'ticket_number',
            'pqrs' => 'pqrs_number',
            'compra' => 'compra_number',
            default => 'id',
        };

        return [
            'type' => $entityType,
            'id' => $entity->id,
            'number' => $entity->{$numberField} ?? $entity->id,
            'displayName' => $this->getEntityComponents($entityType)['displayName'],
        ];
    }

    /**
     * Get default users role filter based on entity type
     *
     * @param string $entityType Entity type
     * @return array|null Role filter
     */
    protected function getDefaultUsersRoleFilter(string $entityType): ?array
    {
        return match ($entityType) {
            'ticket' => ['admin', 'agent'],
            'pqrs' => ['servicio_cliente'],
            'compra' => ['compras'],
            default => null,
        };
    }

    /**
     * Get default agents role filter for entity type
     *
     * @param string $entityType Entity type
     * @return array Role filters
     */
    protected function getDefaultAgentsRoleFilter(string $entityType): array
    {
        return match ($entityType) {
            'ticket' => ['admin', 'agent'],
            'pqrs' => ['servicio_cliente'],
            'compra' => ['compras'],
            default => ['admin', 'agent'],
        };
    }
}