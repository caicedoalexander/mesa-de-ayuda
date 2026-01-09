<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateTicketsTags Migration
 *
 * Creates the tickets_tags junction table for many-to-many relationship
 * between tickets and tags.
 *
 * Tags enable:
 * - Categorization and filtering of tickets
 * - Automated classification via n8n AI integration
 * - Statistical analysis by category
 * - Quick search and reporting
 *
 * @version 1.0.0 - Initial version (2026-01-05)
 */
class CreateTicketsTags extends AbstractMigration
{
    /**
     * Create tickets_tags junction table
     *
     * @return void
     */
    public function change(): void
    {
        // Composite primary key on ticket_id + tag_id (no auto-increment ID)
        $table = $this->table('tickets_tags', [
            'id' => false,
            'primary_key' => ['ticket_id', 'tag_id'],
        ]);

        $table
            // Relationships
            ->addColumn('ticket_id', 'integer', [
                'null' => false,
                'signed' => false,
                'comment' => 'Reference to the ticket',
            ])
            ->addColumn('tag_id', 'integer', [
                'null' => false,
                'signed' => false,
                'comment' => 'Reference to the tag',
            ])

            // Indexes for lookups
            ->addIndex(['tag_id'], [
                'name' => 'idx_tickets_tags_tag_id',
            ])
            ->addIndex(['ticket_id'], [
                'name' => 'idx_tickets_tags_ticket_id',
            ])

            // Foreign key constraints
            ->addForeignKey('ticket_id', 'tickets', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_tickets_tags_ticket',
            ])
            ->addForeignKey('tag_id', 'tags', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_tickets_tags_tag',
            ])

            ->create();
    }
}
