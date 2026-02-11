<?php
declare(strict_types=1);

namespace App\Model\Enum;

/**
 * Ticket Status Enum
 *
 * Replaces magic strings for ticket statuses throughout the codebase.
 */
enum TicketStatus: string
{
    case Nuevo = 'nuevo';
    case Abierto = 'abierto';
    case Pendiente = 'pendiente';
    case Resuelto = 'resuelto';
    case Convertido = 'convertido';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::Nuevo => 'Nuevo',
            self::Abierto => 'Abierto',
            self::Pendiente => 'Pendiente',
            self::Resuelto => 'Resuelto',
            self::Convertido => 'Convertido',
        };
    }

    /**
     * Statuses considered "resolved" (entity is locked)
     *
     * @return array<self>
     */
    public static function resolvedStatuses(): array
    {
        return [self::Resuelto, self::Convertido];
    }

    /**
     * Statuses considered "active" (entity is open)
     *
     * @return array<self>
     */
    public static function activeStatuses(): array
    {
        return [self::Nuevo, self::Abierto, self::Pendiente];
    }

    /**
     * All status values as strings
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn(self $s) => $s->value, self::cases());
    }

    /**
     * Resolved status values as strings
     *
     * @return array<string>
     */
    public static function resolvedValues(): array
    {
        return array_map(fn(self $s) => $s->value, self::resolvedStatuses());
    }

    /**
     * Active status values as strings
     *
     * @return array<string>
     */
    public static function activeValues(): array
    {
        return array_map(fn(self $s) => $s->value, self::activeStatuses());
    }
}