<?php
declare(strict_types=1);

namespace App\Service\Traits;

/**
 * EntityTypeMapperTrait
 *
 * Provides consistent mapping methods for entity types (Ticket, PQRS, Compra).
 * Extracted from TicketSystemTrait for SRP compliance.
 *
 * Responsibilities:
 * - Entity type to table name mapping
 * - Foreign key name resolution
 * - Comments/History table name resolution
 * - Notification method name resolution
 *
 * @package App\Service\Traits
 */
trait EntityTypeMapperTrait
{
    /**
     * Get entity type from table source name
     *
     * @param string $source Source name (Tickets, Pqrs, Compras)
     * @return string Entity type (ticket, pqrs, compra)
     */
    protected function getEntityTypeFromSource(string $source): string
    {
        return match ($source) {
            'Tickets' => 'ticket',
            'Pqrs' => 'pqrs',
            'Compras' => 'compra',
            default => throw new \InvalidArgumentException("Unknown source: {$source}"),
        };
    }

    /**
     * Get entity table name from entity type
     *
     * @param string $entityType Entity type (ticket, pqrs, compra)
     * @return string Table name
     */
    protected function getEntityTableName(string $entityType): string
    {
        return match ($entityType) {
            'ticket' => 'Tickets',
            'pqrs' => 'Pqrs',
            'compra' => 'Compras',
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };
    }

    /**
     * Get comments table name from entity type
     *
     * @param string $entityType Entity type (ticket, pqrs, compra)
     * @return string Comments table name
     */
    protected function getCommentsTableName(string $entityType): string
    {
        return match ($entityType) {
            'ticket' => 'TicketComments',
            'pqrs' => 'PqrsComments',
            'compra' => 'ComprasComments',
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };
    }

    /**
     * Get history table name from entity type
     *
     * @param string $entityType Entity type (ticket, pqrs, compra)
     * @return string History table name
     */
    protected function getHistoryTableName(string $entityType): string
    {
        return match ($entityType) {
            'ticket' => 'TicketHistory',
            'pqrs' => 'PqrsHistory',
            'compra' => 'ComprasHistory',
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };
    }

    /**
     * Get foreign key name from entity type
     *
     * @param string $entityType Entity type (ticket, pqrs, compra)
     * @return string Foreign key name
     */
    protected function getForeignKeyName(string $entityType): string
    {
        return match ($entityType) {
            'ticket' => 'ticket_id',
            'pqrs' => 'pqrs_id',
            'compra' => 'compra_id',
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };
    }

    /**
     * Get status change notification method name
     *
     * @param string $entityType Entity type (ticket, pqrs, compra)
     * @return string Email service method name
     */
    protected function getStatusChangeNotificationMethod(string $entityType): string
    {
        return match ($entityType) {
            'ticket' => 'sendStatusChangeNotification',
            'pqrs' => 'sendPqrsStatusChangeNotification',
            'compra' => 'sendCompraStatusChangeNotification',
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };
    }

    /**
     * Get attachments table name from entity type
     *
     * @param string $entityType Entity type (ticket, pqrs, compra)
     * @return string Attachments table name
     */
    protected function getAttachmentsTableName(string $entityType): string
    {
        return match ($entityType) {
            'ticket' => 'Attachments',
            'pqrs' => 'PqrsAttachments',
            'compra' => 'ComprasAttachments',
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };
    }
}