<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateTicketComments extends BaseMigration
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
        $table = $this->table('ticket_comments');
        $table->addColumn('ticket_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
        ]);
        $table->addColumn('user_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
        ]);
        $table->addColumn('comment_type', 'string', [
            'default' => 'public',
            'limit' => 20,
            'null' => false,
        ]);
        $table->addColumn('body', 'text', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('is_system_comment', 'boolean', [
            'default' => false,
            'null' => false,
        ]);
        $table->addColumn('gmail_message_id', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('sent_as_email', 'boolean', [
            'default' => false,
            'null' => false,
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
            'delete' => 'RESTRICT',
            'update' => 'CASCADE'
        ]);
        $table->create();
    }
}
