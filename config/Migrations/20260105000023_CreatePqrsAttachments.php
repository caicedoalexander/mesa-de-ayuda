<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreatePqrsAttachments Migration
 *
 * Creates the pqrs_attachments table for file uploads associated with PQRS.
 * Supports uploads from both public form submissions (uploaded_by = NULL)
 * and agent responses (uploaded_by = user_id).
 *
 * @version 1.0.0 - Initial version (2026-01-05)
 */
class CreatePqrsAttachments extends AbstractMigration
{
    /**
     * Create pqrs_attachments table
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('pqrs_attachments', ['signed' => false]);

        $table
            // Polymorphic relationships
            ->addColumn('pqrs_id', 'integer', [
                'null' => false,
                'signed' => false,
                'comment' => 'Reference to parent PQRS',
            ])
            ->addColumn('comment_id', 'integer', [
                'null' => true,
                'signed' => false,
                'comment' => 'Reference to PQRS comment (null if attached to PQRS directly)',
            ])

            // File metadata
            ->addColumn('filename', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Sanitized filename stored on disk',
            ])
            ->addColumn('file_path', 'string', [
                'limit' => 500,
                'null' => false,
                'comment' => 'Relative path from webroot (e.g., uploads/pqrs/123/file.pdf)',
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

            // Upload tracking
            ->addColumn('uploaded_by', 'integer', [
                'null' => true,
                'signed' => false,
                'comment' => 'User who uploaded the file (NULL for public submissions)',
            ])

            // Timestamp
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'File upload timestamp',
            ])

            // Indexes for performance
            ->addIndex(['pqrs_id'], [
                'name' => 'idx_pqrs_attachments_pqrs_id',
            ])
            ->addIndex(['comment_id'], [
                'name' => 'idx_pqrs_attachments_comment_id',
            ])
            ->addIndex(['uploaded_by'], [
                'name' => 'idx_pqrs_attachments_uploaded_by',
            ])

            // Foreign key constraints
            ->addForeignKey('pqrs_id', 'pqrs', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_pqrs_attachments_pqrs',
            ])
            ->addForeignKey('comment_id', 'pqrs_comments', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_pqrs_attachments_comment',
            ])
            ->addForeignKey('uploaded_by', 'users', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'fk_pqrs_attachments_user',
            ])

            ->create();
    }
}
