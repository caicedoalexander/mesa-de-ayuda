<?php
declare(strict_types=1);

namespace App\Service\Dto;

use App\Constants\SettingKeys;

/**
 * Gmail OAuth configuration extracted from system_settings.
 *
 * Note: this is the SETTINGS shape (refresh token + client secret JSON string),
 * not the runtime Gmail config (decoded JSON + redirect URI) used by GmailService
 * directly — those are loaded via GmailService::loadConfigFromDatabase().
 */
final readonly class GmailConfig
{
    /**
     * @param string $refreshToken Gmail OAuth refresh token (decrypted)
     * @param string $clientSecretJson Gmail client secret JSON string (decrypted)
     * @param string $userEmail Gmail user email
     */
    public function __construct(
        public string $refreshToken,
        public string $clientSecretJson,
        public string $userEmail,
    ) {
    }

    /**
     * @param array<string, mixed> $raw Raw settings array
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            refreshToken: (string)($raw[SettingKeys::GMAIL_REFRESH_TOKEN] ?? ''),
            clientSecretJson: (string)($raw[SettingKeys::GMAIL_CLIENT_SECRET_JSON] ?? ''),
            userEmail: (string)($raw[SettingKeys::GMAIL_USER_EMAIL] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            SettingKeys::GMAIL_REFRESH_TOKEN => $this->refreshToken,
            SettingKeys::GMAIL_CLIENT_SECRET_JSON => $this->clientSecretJson,
            SettingKeys::GMAIL_USER_EMAIL => $this->userEmail,
        ];
    }
}
