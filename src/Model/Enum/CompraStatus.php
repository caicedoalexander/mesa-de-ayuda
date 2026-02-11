<?php
declare(strict_types=1);

namespace App\Model\Enum;

/**
 * Compra (Purchase Request) Status Enum
 *
 * Replaces magic strings for compra statuses throughout the codebase.
 */
enum CompraStatus: string
{
    case Nuevo = 'nuevo';
    case EnRevision = 'en_revision';
    case Aprobado = 'aprobado';
    case EnProceso = 'en_proceso';
    case Completado = 'completado';
    case Rechazado = 'rechazado';
    case Convertido = 'convertido';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::Nuevo => 'Nuevo',
            self::EnRevision => 'En Revisión',
            self::Aprobado => 'Aprobado',
            self::EnProceso => 'En Proceso',
            self::Completado => 'Completado',
            self::Rechazado => 'Rechazado',
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
        return [self::Completado, self::Rechazado, self::Convertido];
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
