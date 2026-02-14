<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * SeedSystemSettings Migration
 *
 * Seeds initial system configuration settings including:
 * - System branding (title)
 * - Gmail OAuth integration settings
 * - WhatsApp Evolution API configuration
 * - n8n webhook integration
 * - UI preferences (pagination, etc.)
 *
 * Note: Sensitive values (passwords, tokens) are left empty and must be
 * configured through the admin interface or manually in the database.
 *
 * @version 1.0.0 - Initial system settings (2026-01-05)
 */
class SeedSystemSettings extends AbstractMigration
{
    /**
     * Seed system settings
     *
     * @return void
     */
    public function up(): void
    {
        $timestamp = date('Y-m-d H:i:s');

        $data = [
            // System Branding
            [
                'setting_key' => 'system_title',
                'setting_value' => 'Mesa de Ayuda',
                'setting_type' => 'string',
                'description' => 'System title displayed in UI and emails',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],

            // Gmail OAuth Integration
            [
                'setting_key' => 'gmail_client_secret_path',
                'setting_value' => 'config/google/client_secret.json',
                'setting_type' => 'string',
                'description' => 'Path to Gmail OAuth2 client secret JSON file',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],
            [
                'setting_key' => 'gmail_refresh_token',
                'setting_value' => '',
                'setting_type' => 'encrypted',
                'description' => 'Gmail OAuth2 refresh token (encrypted, auto-generated)',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],
            [
                'setting_key' => 'gmail_user_email',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'Gmail account email for ticket import',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],
            [
                'setting_key' => 'gmail_check_interval',
                'setting_value' => '5',
                'setting_type' => 'integer',
                'description' => 'Email check interval in minutes',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],

            // WhatsApp Evolution API Configuration
            [
                'setting_key' => 'whatsapp_api_url',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'WhatsApp Evolution API base URL (e.g., http://localhost:8080)',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],
            [
                'setting_key' => 'whatsapp_api_key',
                'setting_value' => '',
                'setting_type' => 'encrypted',
                'description' => 'WhatsApp Evolution API authentication key (encrypted)',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],
            [
                'setting_key' => 'whatsapp_instance_name',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'WhatsApp instance name in Evolution API',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],
            [
                'setting_key' => 'whatsapp_tickets_number',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'WhatsApp number/group for ticket notifications (format: 5511999999999@s.whatsapp.net or groupid@g.us)',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],
            [
                'setting_key' => 'whatsapp_pqrs_number',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'WhatsApp number/group for PQRS notifications (format: 5511999999999@s.whatsapp.net or groupid@g.us)',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],
            [
                'setting_key' => 'whatsapp_compras_number',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'WhatsApp number/group for purchase request notifications (format: 5511999999999@s.whatsapp.net or groupid@g.us)',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],

            // n8n Automation Integration
            [
                'setting_key' => 'n8n_webhook_url',
                'setting_value' => '',
                'setting_type' => 'string',
                'description' => 'n8n webhook URL for AI tag classification and automation',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],
            [
                'setting_key' => 'n8n_enabled',
                'setting_value' => 'false',
                'setting_type' => 'boolean',
                'description' => 'Enable/disable n8n webhook integration',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],

            // UI Preferences
            [
                'setting_key' => 'tickets_per_page',
                'setting_value' => '25',
                'setting_type' => 'integer',
                'description' => 'Number of tickets to display per page',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],
            [
                'setting_key' => 'pqrs_per_page',
                'setting_value' => '25',
                'setting_type' => 'integer',
                'description' => 'Number of PQRS to display per page',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],
            [
                'setting_key' => 'compras_per_page',
                'setting_value' => '25',
                'setting_type' => 'integer',
                'description' => 'Number of purchase requests to display per page',
                'created' => $timestamp,
                'modified' => $timestamp,
            ],
        ];

        $table = $this->table('system_settings');
        $table->insert($data)->save();
    }

    /**
     * Remove seeded settings
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute('DELETE FROM system_settings');
    }
}
