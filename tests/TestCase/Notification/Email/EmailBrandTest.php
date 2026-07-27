<?php
declare(strict_types=1);

namespace App\Test\TestCase\Notification\Email;

use App\Notification\Email\EmailBrand;
use PHPUnit\Framework\TestCase;

final class EmailBrandTest extends TestCase
{
    public function testConstantsHaveExpectedValues(): void
    {
        self::assertSame('Compañía Operadora Portuaria Cafetera S.A.', EmailBrand::ORG_NAME);
        self::assertSame('Mesa de Ayuda', EmailBrand::TEAM_NAME);
    }

    /**
     * The helpdesk host restricts inbound traffic by IP, so Google's image proxy
     * cannot fetch anything served from it. The logo URL must stay pinned to the
     * public site and never be resolved against App.fullBaseUrl again.
     */
    public function testLogoUrlPointsToPublicPngHost(): void
    {
        self::assertSame(
            'https://www.copcsa.com/wp-content/uploads/2026/07/logo-mesa-ayuda.png',
            EmailBrand::logoUrl(),
        );
    }

    /**
     * Protects the two properties that would survive a legitimate URL change and
     * are what actually make it work: a relative or `http://` URL breaks in mail
     * clients, and a non-`.png` suffix reintroduces the original bug (mail clients
     * other than Apple Mail refuse to render SVG).
     */
    public function testLogoUrlKeepsHttpsSchemeAndPngExtension(): void
    {
        $url = EmailBrand::logoUrl();

        self::assertStringStartsWith('https://', $url);
        self::assertStringEndsWith('.png', $url);
    }
}
