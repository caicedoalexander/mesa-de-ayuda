<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateCompras Migration
 *
 * Creates the compras (purchase requests) table. Purchase requests are typically
 * converted from tickets when they require procurement action.
 *
 * Features:
 * - Converted from tickets (original_ticket_number tracking)
 * - Channel inheritance from original ticket
 * - Email recipient tracking for CC notifications
 * - SLA management with first response and resolution deadlines
 * - Priority and status workflow management
 *
 * Status workflow:
 * - nuevo → en_revision → aprobado → en_proceso → completado
 * - Can be rejected at any stage
 *
 * @version 1.0.0 - Consolidated from 5 migrations (2026-01-05)
 */
class CreateCompras extends AbstractMigration
{
    /**
     * Create compras table with complete schema
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('compras', ['signed' => false]);

        $table
            // Purchase request identification
            ->addColumn('compra_number', 'string', [
                'limit' => 20,
                'null' => false,
                'comment' => 'Unique purchase request identifier. Format: CPR-YYYY-NNNNN',
            ])
            ->addColumn('original_ticket_number', 'string', [
                'limit' => 20,
                'null' => true,
                'comment' => 'Reference to original ticket number (if converted)',
            ])

            // Purchase request content
            ->addColumn('subject', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Purchase request subject/title',
            ])
            ->addColumn('description', 'text', [
                'null' => false,
                'comment' => 'Purchase request description/details (supports HTML)',
            ])

            // Channel tracking
            ->addColumn('channel', 'string', [
                'default' => 'email',
                'limit' => 20,
                'null' => false,
                'comment' => 'Creation channel: email, web (inherited from original ticket)',
            ])

            // Email recipients tracking
            ->addColumn('email_to', 'json', [
                'default' => null,
                'null' => true,
                'comment' => 'JSON array of primary email recipients',
            ])
            ->addColumn('email_cc', 'json', [
                'default' => null,
                'null' => true,
                'comment' => 'JSON array of CC email recipients (e.g., managers)',
            ])

            // Status and priority
            ->addColumn('status', 'string', [
                'limit' => 20,
                'default' => 'nuevo',
                'null' => false,
                'comment' => 'Purchase request status (nuevo|en_revision|aprobado|en_proceso|completado|rechazado)',
            ])
            ->addColumn('priority', 'string', [
                'limit' => 20,
                'default' => 'media',
                'null' => false,
                'comment' => 'Purchase request priority level (baja|media|alta|urgente)',
            ])

            // User relationships
            ->addColumn('requester_id', 'integer', [
                'null' => false,
                'signed' => false,
                'comment' => 'User who requested the purchase',
            ])
            ->addColumn('assignee_id', 'integer', [
                'null' => true,
                'signed' => false,
                'comment' => 'Purchase team member assigned to process this request',
            ])

            // Timestamps
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Purchase request creation timestamp',
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Last modification timestamp',
            ])

            // SLA tracking
            ->addColumn('resolved_at', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Timestamp when request was completed or rejected',
            ])
            ->addColumn('first_response_at', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Timestamp of first purchase team response (for SLA metrics)',
            ])
            ->addColumn('sla_due_date', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Legacy SLA field (deprecated, use resolution_sla_due instead)',
            ])
            ->addColumn('first_response_sla_due', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'SLA deadline for first response',
            ])
            ->addColumn('resolution_sla_due', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'SLA deadline for resolution/completion',
            ])

            // Indexes for performance
            ->addIndex(['compra_number'], [
                'unique' => true,
                'name' => 'idx_compra_number_unique',
            ])
            ->addIndex(['original_ticket_number'], [
                'name' => 'idx_original_ticket_number',
            ])
            ->addIndex(['priority'], [
                'name' => 'idx_compras_priority',
            ])
            ->addIndex(['assignee_id'], [
                'name' => 'idx_compras_assignee_id',
            ])
            ->addIndex(['requester_id'], [
                'name' => 'idx_compras_requester_id',
            ])
            ->addIndex(['sla_due_date'], [
                'name' => 'idx_compras_sla_due_date',
            ])
            ->addIndex(['first_response_sla_due'], [
                'name' => 'idx_compras_first_response_sla',
            ])
            ->addIndex(['resolution_sla_due'], [
                'name' => 'idx_compras_resolution_sla',
            ])
            ->addIndex(['channel'], [
                'name' => 'idx_compras_channel',
            ])

            // Composite indexes for common queries
            ->addIndex(['status', 'created'], [
                'name' => 'idx_compras_status_created',
            ])
            ->addIndex(['assignee_id', 'status'], [
                'name' => 'idx_compras_assignee_status',
            ])

            // Foreign key constraints
            ->addForeignKey('requester_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_compras_requester',
            ])
            ->addForeignKey('assignee_id', 'users', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'fk_compras_assignee',
            ])

            ->create();
    }
}
