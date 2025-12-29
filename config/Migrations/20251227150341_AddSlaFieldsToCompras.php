<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddSlaFieldsToCompras extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('compras');

        // Add first response SLA deadline
        $table->addColumn('first_response_sla_due', 'datetime', [
            'default' => null,
            'null' => true,
            'comment' => 'Fecha límite SLA para primera respuesta',
            'after' => 'sla_due_date'
        ]);

        // Add resolution SLA deadline
        $table->addColumn('resolution_sla_due', 'datetime', [
            'default' => null,
            'null' => true,
            'comment' => 'Fecha límite SLA para resolución',
            'after' => 'first_response_sla_due'
        ]);

        // Add indexes for efficient SLA breach queries
        $table->addIndex(['first_response_sla_due'], ['name' => 'idx_compras_first_response_sla'])
              ->addIndex(['resolution_sla_due'], ['name' => 'idx_compras_resolution_sla']);

        $table->update();

        // Note: Keeping sla_due_date field for backward compatibility (deprecated)
    }
}
