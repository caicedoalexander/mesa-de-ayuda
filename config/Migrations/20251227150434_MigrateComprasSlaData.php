<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class MigrateComprasSlaData extends BaseMigration
{
    /**
     * Up Method - Migrate existing Compras SLA data to new fields
     *
     * @return void
     */
    public function up(): void
    {
        // Migrate existing sla_due_date to new fields
        $this->execute("
            UPDATE compras
            SET
                resolution_sla_due = sla_due_date,
                first_response_sla_due = DATE_ADD(created, INTERVAL 1 DAY)
            WHERE sla_due_date IS NOT NULL
        ");

        // For records without sla_due_date, calculate from defaults (3 days resolution, 1 day first response)
        $this->execute("
            UPDATE compras
            SET
                resolution_sla_due = DATE_ADD(created, INTERVAL 3 DAY),
                first_response_sla_due = DATE_ADD(created, INTERVAL 1 DAY)
            WHERE sla_due_date IS NULL
            AND resolution_sla_due IS NULL
        ");
    }

    /**
     * Down Method - Rollback SLA data migration
     *
     * @return void
     */
    public function down(): void
    {
        // Clear migrated SLA fields (data is safe in sla_due_date)
        $this->execute("
            UPDATE compras
            SET
                first_response_sla_due = NULL,
                resolution_sla_due = NULL
        ");
    }
}
