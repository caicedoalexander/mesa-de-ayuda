<?php
declare(strict_types=1);

namespace App\Notification\Email;

/**
 * Static branding constants for the email footer.
 *
 * Intentionally a code-side configuration: changing these requires a deploy,
 * which is fine for a single-organization installation.
 */
final class EmailBrand
{
    public const ORG_NAME = 'Compañía Operadora Portuaria Cafetera S.A.';
    public const TEAM_NAME = 'Mesa de Ayuda';

    /**
     * Absolute URL to the logo asset, hosted on the organization's public
     * website rather than on this app.
     *
     * Two constraints force this: the helpdesk host only accepts traffic from
     * whitelisted IPs, so Google's image proxy cannot fetch the asset, and mail
     * clients other than Apple Mail refuse to render SVG. The served file is a
     * 96x96 PNG; the source of truth for it lives in `webroot/img/`.
     */
    public static function logoUrl(): string
    {
        return 'https://www.copcsa.com/wp-content/uploads/2026/07/logo-mesa-ayuda.png';
    }
}
