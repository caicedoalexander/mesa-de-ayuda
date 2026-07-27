<?php
declare(strict_types=1);

namespace App\Service\Auth;

use Cake\I18n\DateTime;
use DateTimeInterface;

/**
 * Política de expiración diaria de sesión.
 *
 * Lógica pura (sin estado, sin framework más allá de las fechas) para forzar
 * el re-login a partir de la próxima medianoche `America/Bogota`.
 */
final class SessionExpiryPolicy
{
    /**
     * Devuelve las 00:00 del día siguiente a $now, en la zona horaria de $now.
     *
     * @param \DateTimeInterface $now Momento de referencia (en producción,
     *   `DateTime::now()`, ya en la zona por defecto de la app).
     * @return \Cake\I18n\DateTime
     */
    public static function nextMidnight(DateTimeInterface $now): DateTime
    {
        return DateTime::parse($now)->addDays(1)->startOfDay();
    }

    /**
     * @param int $expiresAt Timestamp de expiración.
     * @param int $now Timestamp actual.
     * @return bool `true` si $now alcanzó o superó $expiresAt.
     */
    public static function isExpired(int $expiresAt, int $now): bool
    {
        return $now >= $expiresAt;
    }
}
