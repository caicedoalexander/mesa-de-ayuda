<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddSlaFieldsToPqrs extends BaseMigration
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
        $table = $this->table('pqrs');

        // Add closed_at field (missing from current PQRS schema)
        $table->addColumn('closed_at', 'datetime', [
            'default' => null,
            'null' => true,
            'comment' => 'Fecha de cierre',
            'after' => 'resolved_at'
        ]);

        // Add first response SLA deadline
        $table->addColumn('first_response_sla_due', 'datetime', [
            'default' => null,
            'null' => true,
            'comment' => 'Fecha límite SLA para primera respuesta',
            'after' => 'closed_at'
        ]);

        // Add resolution SLA deadline
        $table->addColumn('resolution_sla_due', 'datetime', [
            'default' => null,
            'null' => true,
            'comment' => 'Fecha límite SLA para resolución',
            'after' => 'first_response_sla_due'
        ]);

        // Add indexes for efficient SLA breach queries
        $table->addIndex(['first_response_sla_due'], ['name' => 'idx_first_response_sla'])
              ->addIndex(['resolution_sla_due'], ['name' => 'idx_resolution_sla']);

        $table->update();
    }
}
