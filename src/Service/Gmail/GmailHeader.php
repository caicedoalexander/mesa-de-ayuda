<?php
declare(strict_types=1);

namespace App\Service\Gmail;

/**
 * Gmail Header Constants
 *
 * Centralizes all email header name strings used when parsing Gmail messages.
 * Prevents typos and provides IDE autocompletion for header names.
 */
final class GmailHeader
{
    // Standard RFC headers
    public const FROM = 'From';
    public const TO = 'To';
    public const CC = 'Cc';
    public const SUBJECT = 'Subject';
    public const DATE = 'Date';
    public const CONTENT_ID = 'Content-ID';
    public const CONTENT_DISPOSITION = 'Content-Disposition';
    public const PRECEDENCE = 'Precedence';

    // Auto-reply detection headers
    public const AUTO_SUBMITTED = 'Auto-Submitted';
    public const X_AUTOREPLY = 'X-Autoreply';
    public const X_AUTORESPOND = 'X-Autorespond';

    // Custom application headers
    public const X_MESA_AYUDA_NOTIFICATION = 'X-Mesa-Ayuda-Notification';
}