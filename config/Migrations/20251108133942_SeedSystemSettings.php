<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class SeedSystemSettings extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function up(): void
    {
        $data = [
            [
                'setting_key' => 'system_title',
                'setting_value' => 'Sistema de Soporte',
                'setting_type' => 'string',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'smtp_host',
                'setting_value' => 'smtp.gmail.com',
                'setting_type' => 'string',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'smtp_port',
                'setting_value' => '587',
                'setting_type' => 'integer',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'smtp_username',
                'setting_value' => '',
                'setting_type' => 'string',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'smtp_password',
                'setting_value' => '',
                'setting_type' => 'string',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'smtp_encryption',
                'setting_value' => 'tls',
                'setting_type' => 'string',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'gmail_client_secret_path',
                'setting_value' => 'config/google/client_secret_637287765095-o43mlmj2kbi0cfhjj87vm5rcqe68kd1d.apps.googleusercontent.com.json',
                'setting_type' => 'string',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'gmail_refresh_token',
                'setting_value' => '',
                'setting_type' => 'string',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'gmail_check_interval',
                'setting_value' => '5',
                'setting_type' => 'integer',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'tickets_per_page',
                'setting_value' => '25',
                'setting_type' => 'integer',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
        ];

        $table = $this->table('system_settings');
        $table->insert($data)->save();
    }

    public function down(): void
    {
        $this->execute('DELETE FROM system_settings');
    }
}
