<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateComprasAttachments Migration
 *
 * Creates the compras_attachments table for file uploads associated with
 * purchase requests. Similar structure to attachments table but specific
 * to compras module.
 *
 * @version 1.0.0 - Initial version (2026-01-05)
 */
class CreateComprasAttachments extends AbstractMigration
{
    /**
     * Create compras_attachments table
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('compras_attachments', ['signed' => false]);

        $table
            // Polymorphic relationships
            ->addColumn('compra_id', 'integer', [
                'null' => false,
                'signed' => false,
                'comment' => 'Reference to parent purchase request',
            ])
            ->addColumn('compras_comment_id', 'integer', [
                'null' => true,
                'signed' => false,
                'comment' => 'Reference to compras comment (null if attached to compra directly)',
            ])

            // File metadata
            ->addColumn('filename', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Sanitized filename stored on disk (unique, safe)',
            ])
            ->addColumn('original_filename', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Original filename uploaded by user (for display)',
            ])
            ->addColumn('file_path', 'string', [
                'limit' => 500,
                'null' => false,
                'comment' => 'Relative path from webroot (e.g., uploads/compras/123/file.pdf)',
            ])
            ->addColumn('file_size', 'integer', [
                'null' => true,
                'signed' => false,
                'comment' => 'File size in bytes',
            ])
            ->addColumn('mime_type', 'string', [
                'limit' => 100,
                'null' => true,
                'comment' => 'MIME type (e.g., application/pdf, image/jpeg)',
            ])

            // Inline image support
            ->addColumn('is_inline', 'boolean', [
                'default' => false,
                'null' => false,
                'comment' => 'True if embedded inline in email HTML',
            ])
            ->addColumn('content_id', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Content-ID for inline images (cid: references)',
            ])

            // Upload tracking
            ->addColumn('uploaded_by_user_id', 'integer', [
                'null' => true,
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
            ->addIndex(['compra_id'], [
                'name' => 'idx_compras_attachments_compra_id',
            ])
            ->addIndex(['compras_comment_id'], [
                'name' => 'idx_compras_attachments_comment_id',
            ])
            ->addIndex(['uploaded_by_user_id'], [
                'name' => 'idx_compras_attachments_uploaded_by',
            ])
            ->addIndex(['content_id'], [
                'name' => 'idx_compras_attachments_content_id',
            ])

            // Foreign key constraints
            ->addForeignKey('compra_id', 'compras', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_compras_attachments_compra',
            ])
            ->addForeignKey('compras_comment_id', 'compras_comments', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'fk_compras_attachments_comment',
            ])
            ->addForeignKey('uploaded_by_user_id', 'users', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'fk_compras_attachments_user',
            ])

            ->create();
    }
}
