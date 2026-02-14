<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * RemoveObsoleteSystemSettings Migration
 *
 * Removes obsolete system_settings keys that were created by the initial
 * SeedSystemSettings migration but later renamed in the codebase:
 *
 * - whatsapp_api_token → replaced by whatsapp_api_key
 * - whatsapp_instance  → replaced by whatsapp_instance_name
 *
 * Also adds whatsapp_enabled, whatsapp_api_key, whatsapp_instance_name,
 * n8n_api_key, n8n_send_tags_list, and n8n_timeout if they don't exist,
 * since these were missing from the original seed migration.
 *
 * @version 1.0.0 - Cleanup obsolete keys (2026-02-14)
 */
class RemoveObsoleteSystemSettings extends AbstractMigration
{
    /**
     * Remove obsolete keys and ensure current keys exist
     *
     * @return void
     */
    public function up(): void
    {
        // Remove obsolete keys
        $this->execute("
            DELETE FROM system_settings
            WHERE setting_key IN ('whatsapp_api_token', 'whatsapp_instance')
        ");

        // Ensure current keys exist (skip if already present)
        $timestamp = date('Y-m-d H:i:s');

        $newSettings = [
            [
                'setting_key' => 'whatsapp_enabled',
                'setting_value' => '0',
                'setting_type' => 'boolean',
                'description' => 'Enable/disable WhatsApp notifications',
            ],
            [
                'setting_key' => 'n8n_api_key',
                'setting_value' => '',
                'setting_type' => 'encrypted',
                'description' => 'n8n webhook authentication key (encrypted)',
            ],
            [
                'setting_key' => 'n8n_send_tags_list',
                'setting_value' => '0',
                'setting_type' => 'boolean',
                'description' => 'Include available tags list in n8n webhook payload',
            ],
            [
                'setting_key' => 'n8n_timeout',
                'setting_value' => '30',
                'setting_type' => 'integer',
                'description' => 'Timeout in seconds for n8n webhook requests',
            ],
        ];

        foreach ($newSettings as $setting) {
            $exists = $this->fetchRow(
                "SELECT id FROM system_settings WHERE setting_key = '{$setting['setting_key']}'"
            );

            if (!$exists) {
                $this->table('system_settings')->insert([
                    'setting_key' => $setting['setting_key'],
                    'setting_value' => $setting['setting_value'],
                    'setting_type' => $setting['setting_type'],
                    'description' => $setting['description'],
                    'created' => $timestamp,
                    'modified' => $timestamp,
                ])->save();
            }
        }
    }

    /**
     * Restore obsolete keys (rollback)
     *
     * @return void
     */
    public function down(): void
    {
        $timestamp = date('Y-m-d H:i:s');

        $this->table('system_settings')->insert([
            [
                'setting_key' => 'whatsapp_api_token',
                'setting_value' => '',
                'setting_type' => 'encrypted',
                'description' => 'WhatsApp Evolution API authentication token (encrypted)',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],
            [
                'setting_key' => 'whatsapp_instance',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'WhatsApp instance name in Evolution API',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],
        ])->save();
    }
}
