<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateTicketFollowers Migration
 *
 * Creates the ticket_followers junction table for many-to-many relationship
 * between tickets and users. Followers receive notifications about ticket
 * updates even if they are not the assignee or requester.
 *
 * Use cases:
 * - Team collaboration on complex tickets
 * - Manager oversight of specific cases
 * - Cross-department visibility
 *
 * @version 1.0.0 - Initial version (2026-01-05)
 */
class CreateTicketFollowers extends AbstractMigration
{
    /**
     * Create ticket_followers junction table
     *
     * @return void
     */
    public function change(): void
    {
        // Composite primary key on ticket_id + user_id (no auto-increment ID)
        $table = $this->table('ticket_followers', [
            'id' => false,
            'primary_key' => ['ticket_id', 'user_id'],
        ]);

        $table
            // Relationships
            ->addColumn('ticket_id', 'integer', [
                'null' => false,
                'signed' => false,
                'comment' => 'Reference to the ticket being followed',
            ])
            ->addColumn('user_id', 'integer', [
                'null' => false,
                'signed' => false,
                'comment' => 'User who is following the ticket',
            ])

            // Timestamp
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'When user started following the ticket',
            ])

            // Indexes for lookups
            ->addIndex(['user_id'], [
                'name' => 'idx_ticket_followers_user_id',
            ])
            ->addIndex(['ticket_id'], [
                'name' => 'idx_ticket_followers_ticket_id',
            ])

            // Foreign key constraints
            ->addForeignKey('ticket_id', 'tickets', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_ticket_followers_ticket',
            ])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_ticket_followers_user',
            ])

            ->create();
    }
}
