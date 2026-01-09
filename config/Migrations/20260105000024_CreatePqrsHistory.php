<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreatePqrsHistory Migration
 *
 * Creates the pqrs_history table for audit trail tracking of
 * PQRS modifications.
 *
 * @version 1.0.0 - Initial version (2026-01-05)
 */
class CreatePqrsHistory extends AbstractMigration
{
    /**
     * Create pqrs_history table
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('pqrs_history', ['signed' => false]);

        $table
            // Relationships
            ->addColumn('pqrs_id', 'integer', [
                'null' => false,
                'signed' => false,
                'comment' => 'Reference to the PQRS that was modified',
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
                'comment' => 'Name of the field that was changed',
            ])
            ->addColumn('old_value', 'text', [
                'null' => true,
                'comment' => 'Previous value before change',
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
            ->addIndex(['pqrs_id'], [
                'name' => 'idx_pqrs_history_pqrs_id',
            ])
            ->addIndex(['changed_by'], [
                'name' => 'idx_pqrs_history_changed_by',
            ])
            ->addIndex(['created'], [
                'name' => 'idx_pqrs_history_created',
            ])
            ->addIndex(['field_name'], [
                'name' => 'idx_pqrs_history_field_name',
            ])
            ->addIndex(['pqrs_id', 'created'], [
                'name' => 'idx_pqrs_history_pqrs_created',
            ])

            // Foreign key constraints
            ->addForeignKey('pqrs_id', 'pqrs', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_pqrs_history_pqrs',
            ])
            ->addForeignKey('changed_by', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_pqrs_history_user',
            ])

            ->create();
    }
}
