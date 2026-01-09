<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateTickets Migration
 *
 * Creates the tickets table with all fields including:
 * - Email threading support (gmail_message_id, gmail_thread_id)
 * - Email recipients tracking (email_to, email_cc)
 * - Channel tracking (email, web, api)
 * - Status tracking including 'convertido' for ticket-to-purchase conversions
 * - SLA tracking fields (first_response_at, resolved_at)
 * - Priority and assignment management
 *
 * @version 1.0.0 - Consolidated from 3 migrations (2026-01-05)
 */
class CreateTickets extends AbstractMigration
{
    /**
     * Create tickets table with complete schema
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('tickets', ['signed' => false]);

        $table
            // Ticket identification
            ->addColumn('ticket_number', 'string', [
                'limit' => 20,
                'null' => false,
                'comment' => 'Unique ticket identifier. Format: TKT-YYYY-NNNNN',
            ])

            // Gmail integration fields
            ->addColumn('gmail_message_id', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Gmail Message-ID for email threading and duplicate prevention',
            ])
            ->addColumn('gmail_thread_id', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Gmail Thread-ID for grouping related email conversations',
            ])

            // Email recipients tracking (for authorization and threading)
            ->addColumn('email_to', 'text', [
                'null' => true,
                'comment' => 'JSON array of To recipients from original email',
            ])
            ->addColumn('email_cc', 'text', [
                'null' => true,
                'comment' => 'JSON array of CC recipients from original email',
            ])

            // Ticket content
            ->addColumn('subject', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Ticket subject/title',
            ])
            ->addColumn('description', 'text', [
                'null' => false,
                'comment' => 'Ticket description/body (supports HTML)',
            ])

            // Channel tracking
            ->addColumn('channel', 'string', [
                'default' => 'email',
                'limit' => 20,
                'null' => false,
                'comment' => 'Creation channel: email (Gmail), web (manual), api (future)',
            ])

            // Status and priority
            ->addColumn('status', 'string', [
                'limit' => 20,
                'default' => 'nuevo',
                'null' => false,
                'comment' => 'Ticket status (nuevo|abierto|pendiente|resuelto|convertido). "convertido" = converted to purchase request',
            ])
            ->addColumn('priority', 'string', [
                'limit' => 20,
                'default' => 'media',
                'null' => false,
                'comment' => 'Ticket priority level (baja|media|alta|urgente)',
            ])

            // User relationships
            ->addColumn('requester_id', 'integer', [
                'null' => false,
                'signed' => false,
                'comment' => 'User who created/requested the ticket',
            ])
            ->addColumn('assignee_id', 'integer', [
                'null' => true,
                'signed' => false,
                'comment' => 'Agent assigned to handle the ticket',
            ])

            // Timestamps
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Ticket creation timestamp',
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
                'comment' => 'Timestamp when ticket was resolved (status=resuelto)',
            ])
            ->addColumn('first_response_at', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Timestamp of first agent response (for SLA metrics)',
            ])

            // Indexes for performance
            ->addIndex(['ticket_number'], [
                'unique' => true,
                'name' => 'idx_ticket_number_unique',
            ])
            ->addIndex(['gmail_message_id'], [
                'unique' => true,
                'name' => 'idx_gmail_message_id_unique',
            ])
            ->addIndex(['gmail_thread_id'], [
                'name' => 'idx_gmail_thread_id',
            ])
            ->addIndex(['priority'], [
                'name' => 'idx_tickets_priority',
            ])
            ->addIndex(['assignee_id'], [
                'name' => 'idx_tickets_assignee_id',
            ])
            ->addIndex(['requester_id'], [
                'name' => 'idx_tickets_requester_id',
            ])
            ->addIndex(['created'], [
                'name' => 'idx_tickets_created',
            ])
            ->addIndex(['channel'], [
                'name' => 'idx_tickets_channel',
            ])

            // Composite indexes for common queries
            ->addIndex(['status', 'priority'], [
                'name' => 'idx_tickets_status_priority',
            ])
            ->addIndex(['assignee_id', 'status'], [
                'name' => 'idx_tickets_assignee_status',
            ])
            ->addIndex(['status', 'created'], [
                'name' => 'idx_tickets_status_created',
            ])

            // Foreign key constraints
            ->addForeignKey('requester_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_tickets_requester',
            ])
            ->addForeignKey('assignee_id', 'users', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'fk_tickets_assignee',
            ])

            ->create();
    }
}
