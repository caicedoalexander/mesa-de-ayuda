<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class SeedSlaSettings extends BaseMigration
{
    /**
     * Up Method - Seed SLA settings
     *
     * @return void
     */
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        $settings = [
            // PQRS SLA Settings (8 settings - 4 types × 2 metrics)
            [
                'setting_key' => 'sla_pqrs_peticion_first_response_days',
                'setting_value' => '2',
                'setting_type' => 'integer',
                'description' => 'SLA para primera respuesta en PQRS tipo Petición (días)',
                'created' => $now,
                'modified' => $now,
            ],
            [
                'setting_key' => 'sla_pqrs_peticion_resolution_days',
                'setting_value' => '5',
                'setting_type' => 'integer',
                'description' => 'SLA para resolución en PQRS tipo Petición (días)',
                'created' => $now,
                'modified' => $now,
            ],
            [
                'setting_key' => 'sla_pqrs_queja_first_response_days',
                'setting_value' => '1',
                'setting_type' => 'integer',
                'description' => 'SLA para primera respuesta en PQRS tipo Queja (días)',
                'created' => $now,
                'modified' => $now,
            ],
            [
                'setting_key' => 'sla_pqrs_queja_resolution_days',
                'setting_value' => '3',
                'setting_type' => 'integer',
                'description' => 'SLA para resolución en PQRS tipo Queja (días)',
                'created' => $now,
                'modified' => $now,
            ],
            [
                'setting_key' => 'sla_pqrs_reclamo_first_response_days',
                'setting_value' => '1',
                'setting_type' => 'integer',
                'description' => 'SLA para primera respuesta en PQRS tipo Reclamo (días)',
                'created' => $now,
                'modified' => $now,
            ],
            [
                'setting_key' => 'sla_pqrs_reclamo_resolution_days',
                'setting_value' => '3',
                'setting_type' => 'integer',
                'description' => 'SLA para resolución en PQRS tipo Reclamo (días)',
                'created' => $now,
                'modified' => $now,
            ],
            [
                'setting_key' => 'sla_pqrs_sugerencia_first_response_days',
                'setting_value' => '3',
                'setting_type' => 'integer',
                'description' => 'SLA para primera respuesta en PQRS tipo Sugerencia (días)',
                'created' => $now,
                'modified' => $now,
            ],
            [
                'setting_key' => 'sla_pqrs_sugerencia_resolution_days',
                'setting_value' => '7',
                'setting_type' => 'integer',
                'description' => 'SLA para resolución en PQRS tipo Sugerencia (días)',
                'created' => $now,
                'modified' => $now,
            ],

            // Compras SLA Settings (2 settings)
            [
                'setting_key' => 'sla_compras_first_response_days',
                'setting_value' => '1',
                'setting_type' => 'integer',
                'description' => 'SLA para primera respuesta en Compras (días)',
                'created' => $now,
                'modified' => $now,
            ],
            [
                'setting_key' => 'sla_compras_resolution_days',
                'setting_value' => '3',
                'setting_type' => 'integer',
                'description' => 'SLA para resolución en Compras (días)',
                'created' => $now,
                'modified' => $now,
            ],
        ];

        // Insert settings
        $table = $this->table('system_settings');
        $table->insert($settings)->save();
    }

    /**
     * Down Method - Remove SLA settings
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute("
            DELETE FROM system_settings
            WHERE setting_key LIKE 'sla_pqrs_%'
            OR setting_key LIKE 'sla_compras_%'
        ");
    }
}
