<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class DeleteObsoleteSystemSettings extends BaseMigration
{
    /**
     * Removes orphan rows left in system_settings after the removal of the
     * `system_title` and `gmail_check_interval` settings. Nothing reads these
     * values anymore (títulos y remitente usan EmailBrand::TEAM_NAME; la
     * frecuencia de ingesta la controla n8n).
     */
    public function up(): void
    {
        $this->execute(
            "DELETE FROM system_settings WHERE setting_key IN ('system_title', 'gmail_check_interval')",
        );
    }

    /**
     * Irreversible on purpose: no re-sembramos ajustes obsoletos.
     */
    public function down(): void
    {
    }
}
