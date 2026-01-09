<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateAttachments Migration
 *
 * Creates the attachments table for file uploads associated with tickets.
 * This table uses a polymorphic pattern to support attachments for:
 * - Tickets (direct attachment)
 * - TicketComments (attachment in a reply)
 *
 * Features:
 * - Inline images support (for email HTML content)
 * - Original filename preservation (for display)
 * - File metadata tracking (size, mime type)
 *
 * @version 1.0.0 - Consolidated from 2 migrations (2026-01-05)
 */
class CreateAttachments extends AbstractMigration
{
    /**
     * Create attachments table with complete schema
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('attachments', ['signed' => false]);

        $table
            // Polymorphic relationships
            ->addColumn('ticket_id', 'integer', [
                'null' => false,
                'signed' => false,
                'comment' => 'Reference to parent ticket',
            ])
            ->addColumn('comment_id', 'integer', [
                'null' => true,
                'signed' => false,
                'comment' => 'Reference to ticket comment (null if attached to ticket directly)',
            ])

            // File metadata
            ->addColumn('filename', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Sanitized filename stored on disk (unique, safe)',
            ])
            ->addColumn('original_filename', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Original filename uploaded by user (for display)',
            ])
            ->addColumn('file_path', 'string', [
                'limit' => 500,
                'null' => false,
                'comment' => 'Relative path from webroot (e.g., uploads/tickets/123/file.pdf)',
            ])
            ->addColumn('file_size', 'integer', [
                'null' => false,
                'comment' => 'File size in bytes',
            ])
            ->addColumn('mime_type', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'MIME type (e.g., application/pdf, image/jpeg)',
            ])

            // Inline image support (for HTML emails)
            ->addColumn('is_inline', 'boolean', [
                'default' => false,
                'null' => false,
                'comment' => 'True if embedded inline in email HTML (vs regular attachment)',
            ])
            ->addColumn('content_id', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Content-ID for inline images (cid: references in HTML)',
            ])

            // Upload tracking
            ->addColumn('uploaded_by', 'integer', [
                'null' => false,
                'signed' => false,
                'comment' => 'User who uploaded the file',
            ])

            // Timestamp
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'File upload timestamp',
            ])

            // Indexes for performance
            ->addIndex(['ticket_id'], [
                'name' => 'idx_attachments_ticket_id',
            ])
            ->addIndex(['comment_id'], [
                'name' => 'idx_attachments_comment_id',
            ])
            ->addIndex(['content_id'], [
                'name' => 'idx_attachments_content_id',
            ])
            ->addIndex(['uploaded_by'], [
                'name' => 'idx_attachments_uploaded_by',
            ])
            ->addIndex(['created'], [
                'name' => 'idx_attachments_created',
            ])

            // Composite indexes for common queries
            ->addIndex(['ticket_id', 'is_inline'], [
                'name' => 'idx_attachments_ticket_inline',
            ])

            // Foreign key constraints
            ->addForeignKey('ticket_id', 'tickets', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_attachments_ticket',
            ])
            ->addForeignKey('comment_id', 'ticket_comments', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_attachments_comment',
            ])
            ->addForeignKey('uploaded_by', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_attachments_user',
            ])

            ->create();
    }
}
