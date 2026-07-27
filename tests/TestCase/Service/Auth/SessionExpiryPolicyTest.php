<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\Auth;

use App\Service\Auth\SessionExpiryPolicy;
use Cake\I18n\DateTime;
use PHPUnit\Framework\TestCase;

final class SessionExpiryPolicyTest extends TestCase
{
    public function testNextMidnightFromAfternoonReturnsStartOfNextDay(): void
    {
        $now = new DateTime('2026-07-21 15:30:00', 'America/Bogota');

        $result = SessionExpiryPolicy::nextMidnight($now);

        $this->assertSame('2026-07-22 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testNextMidnightJustBeforeMidnightReturnsSameUpcomingMidnight(): void
    {
        $now = new DateTime('2026-07-21 23:59:00', 'America/Bogota');

        $result = SessionExpiryPolicy::nextMidnight($now);

        $this->assertSame('2026-07-22 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testNextMidnightJustAfterMidnightReturnsNextDay(): void
    {
        $now = new DateTime('2026-07-21 00:01:00', 'America/Bogota');

        $result = SessionExpiryPolicy::nextMidnight($now);

        $this->assertSame('2026-07-22 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testNextMidnightExactlyAtMidnightReturnsNextDay(): void
    {
        $now = new DateTime('2026-07-21 00:00:00', 'America/Bogota');

        $result = SessionExpiryPolicy::nextMidnight($now);

        $this->assertSame('2026-07-22 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testIsExpiredWhenNowEqualsExpiry(): void
    {
        $this->assertTrue(SessionExpiryPolicy::isExpired(1000, 1000));
    }

    public function testIsExpiredWhenNowAfterExpiry(): void
    {
        $this->assertTrue(SessionExpiryPolicy::isExpired(1000, 1001));
    }

    public function testNotExpiredWhenNowBeforeExpiry(): void
    {
        $this->assertFalse(SessionExpiryPolicy::isExpired(1000, 999));
    }
}
