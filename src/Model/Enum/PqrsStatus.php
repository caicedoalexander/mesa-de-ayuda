<?php
declare(strict_types=1);

namespace App\Model\Enum;

/**
 * PQRS Status Enum
 *
 * Replaces magic strings for PQRS statuses throughout the codebase.
 */
enum PqrsStatus: string
{
    case Nuevo = 'nuevo';
    case EnRevision = 'en_revision';
    case EnProceso = 'en_proceso';
    case Resuelto = 'resuelto';
    case Cerrado = 'cerrado';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::Nuevo => 'Nuevo',
            self::EnRevision => 'En Revisión',
            self::EnProceso => 'En Proceso',
            self::Resuelto => 'Resuelto',
            self::Cerrado => 'Cerrado',
        };
    }

    /**
     * Statuses considered "resolved" (entity is locked)
     *
     * @return array<self>
     */
    public static function resolvedStatuses(): array
    {
        return [self::Resuelto, self::Cerrado];
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
}