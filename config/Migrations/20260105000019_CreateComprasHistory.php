<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateComprasHistory Migration
 *
 * Creates the compras_history table for audit trail tracking of
 * purchase request modifications.
 *
 * @version 1.0.0 - Initial version (2026-01-05)
 */
class CreateComprasHistory extends AbstractMigration
{
    /**
     * Create compras_history table
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('compras_history', ['signed' => false]);

        $table
            // Relationships
            ->addColumn('compra_id', 'integer', [
                'null' => false,
                'signed' => false,
                'comment' => 'Reference to the purchase request that was modified',
            ])
            ->addColumn('changed_by', 'integer', [
                'null' => true,
                'signed' => false,
                'comment' => 'User who made the change',
            ])

            // Change tracking
            ->addColumn('field_name', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'Name of the field that was changed',
            ])
            ->addColumn('old_value', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Previous value before change',
            ])
            ->addColumn('new_value', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'New value after change',
            ])
            ->addColumn('description', 'text', [
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
            ->addIndex(['compra_id'], [
                'name' => 'idx_compras_history_compra_id',
            ])
            ->addIndex(['changed_by'], [
                'name' => 'idx_compras_history_changed_by',
            ])
            ->addIndex(['field_name'], [
                'name' => 'idx_compras_history_field_name',
            ])
            ->addIndex(['compra_id', 'created'], [
                'name' => 'idx_compras_history_compra_created',
            ])

            // Foreign key constraints
            ->addForeignKey('compra_id', 'compras', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_compras_history_compra',
            ])
            ->addForeignKey('changed_by', 'users', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'fk_compras_history_user',
            ])

            ->create();
    }
}
