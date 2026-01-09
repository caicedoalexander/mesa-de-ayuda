<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreatePqrs Migration
 *
 * Creates the pqrs (Peticiones, Quejas, Reclamos y Sugerencias) table
 * for external customer requests submitted via public form.
 *
 * Key features:
 * - Public submission without authentication (captures requester info directly)
 * - Type-specific SLA targets (peticion, queja, reclamo, sugerencia)
 * - Channel tracking (web form, WhatsApp integration)
 * - IP tracking and fraud prevention (ip_address, user_agent)
 * - Source URL tracking for analytics
 *
 * Unlike tickets, PQRS do NOT require authenticated users - requesters
 * provide their contact information in the form.
 *
 * @version 1.0.0 - Consolidated from 3 migrations (2026-01-05)
 */
class CreatePqrs extends AbstractMigration
{
    /**
     * Create pqrs table with complete schema
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('pqrs', ['signed' => false]);

        $table
            // PQRS identification
            ->addColumn('pqrs_number', 'string', [
                'limit' => 20,
                'null' => false,
                'comment' => 'Unique PQRS identifier. Format: PQRS-YYYY-NNNNN',
            ])

            // PQRS type and classification
            ->addColumn('type', 'string', [
                'limit' => 20,
                'null' => false,
                'comment' => 'Type of request (peticion|queja|reclamo|sugerencia). Determines SLA targets',
            ])

            // PQRS content
            ->addColumn('subject', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'PQRS subject/title',
            ])
            ->addColumn('description', 'text', [
                'null' => false,
                'comment' => 'PQRS description/details (supports HTML)',
            ])

            // Status and priority
            ->addColumn('status', 'string', [
                'limit' => 20,
                'default' => 'nuevo',
                'null' => false,
                'comment' => 'PQRS status (nuevo|en_revision|en_proceso|resuelto|cerrado)',
            ])
            ->addColumn('priority', 'string', [
                'limit' => 20,
                'default' => 'media',
                'null' => false,
                'comment' => 'PQRS priority level (baja|media|alta|urgente)',
            ])

            // Requester information (public form - NO authenticated user required)
            ->addColumn('requester_name', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Full name of person submitting PQRS',
            ])
            ->addColumn('requester_email', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Email address for notifications',
            ])
            ->addColumn('requester_phone', 'string', [
                'limit' => 20,
                'null' => true,
                'comment' => 'Optional phone number',
            ])

            // Channel tracking
            ->addColumn('channel', 'string', [
                'default' => 'web',
                'limit' => 20,
                'null' => false,
                'comment' => 'Channel: web (public form), whatsapp',
            ])

            // Assignment
            ->addColumn('assignee_id', 'integer', [
                'null' => true,
                'signed' => false,
                'comment' => 'Customer service agent assigned to handle PQRS',
            ])

            // Submission metadata (for analytics and fraud prevention)
            ->addColumn('ip_address', 'string', [
                'limit' => 45,
                'null' => true,
                'comment' => 'IP address of submitter (IPv4 or IPv6)',
            ])
            ->addColumn('user_agent', 'text', [
                'null' => true,
                'comment' => 'Browser user agent string',
            ])
            ->addColumn('source_url', 'string', [
                'limit' => 500,
                'null' => true,
                'comment' => 'URL where form was submitted from',
            ])

            // Timestamps
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'PQRS creation timestamp',
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Last modification timestamp',
            ])

            // SLA and resolution tracking
            ->addColumn('resolved_at', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Timestamp when PQRS was marked as resolved',
            ])
            ->addColumn('first_response_at', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Timestamp of first agent response (for SLA metrics)',
            ])
            ->addColumn('closed_at', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Timestamp when PQRS was closed',
            ])
            ->addColumn('first_response_sla_due', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'SLA deadline for first response (varies by type)',
            ])
            ->addColumn('resolution_sla_due', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'SLA deadline for resolution (varies by type)',
            ])

            // Indexes for performance
            ->addIndex(['pqrs_number'], [
                'unique' => true,
                'name' => 'idx_pqrs_number_unique',
            ])
            ->addIndex(['priority'], [
                'name' => 'idx_pqrs_priority',
            ])
            ->addIndex(['assignee_id'], [
                'name' => 'idx_pqrs_assignee_id',
            ])
            ->addIndex(['type'], [
                'name' => 'idx_pqrs_type',
            ])
            ->addIndex(['status'], [
                'name' => 'idx_pqrs_status',
            ])
            ->addIndex(['channel'], [
                'name' => 'idx_pqrs_channel',
            ])
            ->addIndex(['requester_email'], [
                'name' => 'idx_pqrs_requester_email',
            ])
            ->addIndex(['first_response_sla_due'], [
                'name' => 'idx_pqrs_first_response_sla',
            ])
            ->addIndex(['resolution_sla_due'], [
                'name' => 'idx_pqrs_resolution_sla',
            ])

            // Composite indexes for common queries
            ->addIndex(['status', 'created'], [
                'name' => 'idx_pqrs_status_created',
            ])
            ->addIndex(['type', 'status'], [
                'name' => 'idx_pqrs_type_status',
            ])
            ->addIndex(['assignee_id', 'status'], [
                'name' => 'idx_pqrs_assignee_status',
            ])

            // Foreign key constraint
            ->addForeignKey('assignee_id', 'users', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'fk_pqrs_assignee',
            ])

            ->create();
    }
}
