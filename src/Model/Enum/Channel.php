<?php
declare(strict_types=1);

namespace App\Model\Enum;

/**
 * Communication Channel Enum
 *
 * Replaces magic strings for communication channels throughout the codebase.
 */
enum Channel: string
{
    case Email = 'email';
    case Web = 'web';
    case Whatsapp = 'whatsapp';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Web => 'Web',
            self::Whatsapp => 'WhatsApp',
        };
    }

    /**
     * All channel values as strings
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn(self $c) => $c->value, self::cases());
    }
}
