<?php
declare(strict_types=1);

namespace App\Model\Enum;

/**
 * Priority Enum
 *
 * Replaces magic strings for priority values throughout the codebase.
 */
enum Priority: string
{
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';
    case Urgente = 'urgente';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::Baja => 'Baja',
            self::Media => 'Media',
            self::Alta => 'Alta',
            self::Urgente => 'Urgente',
        };
    }

    /**
     * All priority values as strings
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn(self $p) => $p->value, self::cases());
    }

    /**
     * Labels indexed by value (for dropdowns)
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label();
        }
        return $result;
    }
}
