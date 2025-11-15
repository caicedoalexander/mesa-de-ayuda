<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateTicketHistory extends BaseMigration
{
    /**
     * Change Method.
     *
     * Creates ticket_history table for audit trail of ticket changes
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('ticket_history');

        $table->addColumn('ticket_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
        ]);

        $table->addColumn('user_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'comment' => 'User who made the change (null for system changes)',
        ]);

        $table->addColumn('field_name', 'string', [
            'default' => null,
            'limit' => 50,
            'null' => false,
            'comment' => 'Field that was changed (status, assignee_id, priority, etc.)',
        ]);

        $table->addColumn('old_value', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);

        $table->addColumn('new_value', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);

        $table->addColumn('description', 'string', [
            'default' => null,
            'limit' => 500,
            'null' => true,
            'comment' => 'Human-readable description of the change',
        ]);

        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
        ]);

        $table->addForeignKey('ticket_id', 'tickets', 'id', [
            'delete' => 'CASCADE',
            'update' => 'CASCADE'
        ]);

        $table->addForeignKey('user_id', 'users', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'CASCADE'
        ]);

        $table->addIndex(['ticket_id', 'created']);
        $table->addIndex(['field_name']);

        $table->create();
    }
}
