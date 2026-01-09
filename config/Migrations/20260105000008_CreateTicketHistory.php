<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateTicketHistory Migration
 *
 * Creates the ticket_history table for audit trail tracking.
 * Records all changes made to ticket fields including:
 * - Status changes
 * - Priority changes
 * - Assignment changes
 * - Any other field modifications
 *
 * This provides complete accountability and change history for compliance
 * and debugging purposes.
 *
 * @version 1.0.0 - Initial version (2026-01-05)
 */
class CreateTicketHistory extends AbstractMigration
{
    /**
     * Create ticket_history table
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('ticket_history', ['signed' => false]);

        $table
            // Relationships
            ->addColumn('ticket_id', 'integer', [
                'null' => false,
                'signed' => false,
                'comment' => 'Reference to the ticket that was modified',
            ])
            ->addColumn('changed_by', 'integer', [
                'null' => false,
                'signed' => false,
                'comment' => 'User who made the change',
            ])

            // Change tracking
            ->addColumn('field_name', 'string', [
                'limit' => 50,
                'null' => false,
                'comment' => 'Name of the field that was changed (e.g., status, priority, assignee_id)',
            ])
            ->addColumn('old_value', 'text', [
                'null' => true,
                'comment' => 'Previous value before change (null for new records)',
            ])
            ->addColumn('new_value', 'text', [
                'null' => true,
                'comment' => 'New value after change',
            ])
            ->addColumn('description', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Human-readable description of the change',
            ])

            // Timestamp
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'When the change occurred',
            ])

            // Indexes for performance
            ->addIndex(['ticket_id'], [
                'name' => 'idx_ticket_history_ticket_id',
            ])
            ->addIndex(['changed_by'], [
                'name' => 'idx_ticket_history_changed_by',
            ])
            ->addIndex(['created'], [
                'name' => 'idx_ticket_history_created',
            ])
            ->addIndex(['field_name'], [
                'name' => 'idx_ticket_history_field_name',
            ])

            // Composite indexes for common queries
            ->addIndex(['ticket_id', 'created'], [
                'name' => 'idx_ticket_history_ticket_created',
            ])
            ->addIndex(['ticket_id', 'field_name'], [
                'name' => 'idx_ticket_history_ticket_field',
            ])

            // Foreign key constraints
            ->addForeignKey('ticket_id', 'tickets', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_ticket_history_ticket',
            ])
            ->addForeignKey('changed_by', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_ticket_history_user',
            ])

            ->create();
    }
}
