<?php
declare(strict_types=1);

namespace App\Service\Dto;

use App\Constants\SettingKeys;

final readonly class AppConfig
{
    /**
     * @param string $webhookGmailImportToken Shared secret for /webhooks/gmail/import (decrypted)
     */
    public function __construct(
        public string $webhookGmailImportToken,
    ) {
    }

    /**
     * @param array<string, mixed> $raw Raw settings array
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            webhookGmailImportToken: (string)($raw[SettingKeys::WEBHOOK_GMAIL_IMPORT_TOKEN] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            SettingKeys::WEBHOOK_GMAIL_IMPORT_TOKEN => $this->webhookGmailImportToken,
        ];
    }
}
