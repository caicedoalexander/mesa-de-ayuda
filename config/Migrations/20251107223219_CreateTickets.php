<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateTickets extends BaseMigration
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
        $table = $this->table('tickets');
        $table->addColumn('ticket_number', 'string', [
            'default' => null,
            'limit' => 20,
            'null' => false,
        ]);
        $table->addColumn('gmail_message_id', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('gmail_thread_id', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('subject', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('description', 'text', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('status', 'string', [
            'default' => 'nuevo',
            'limit' => 20,
            'null' => false,
        ]);
        $table->addColumn('priority', 'string', [
            'default' => 'media',
            'limit' => 20,
            'null' => false,
        ]);
        $table->addColumn('requester_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
        ]);
        $table->addColumn('assignee_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
        ]);
        $table->addColumn('organization_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
        ]);
        $table->addColumn('channel', 'string', [
            'default' => 'email',
            'limit' => 50,
            'null' => false,
        ]);
        $table->addColumn('source_email', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('resolved_at', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('first_response_at', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addIndex(['ticket_number'], ['unique' => true]);
        $table->addIndex(['gmail_message_id'], ['unique' => true]);
        $table->addIndex(['status']);
        $table->addForeignKey('requester_id', 'users', 'id', [
            'delete' => 'RESTRICT',
            'update' => 'CASCADE'
        ]);
        $table->addForeignKey('assignee_id', 'users', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'CASCADE'
        ]);
        $table->addForeignKey('organization_id', 'organizations', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'CASCADE'
        ]);
        $table->create();
    }
}
