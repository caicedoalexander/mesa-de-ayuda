<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateEmailTemplates Migration
 *
 * Creates the email_templates table for dynamic email content management.
 * Templates support variable substitution for personalized emails.
 *
 * Template Variables (examples):
 * - {{ticket_number}}, {{subject}}, {{status}}
 * - {{requester_name}}, {{assignee_name}}
 * - {{comment_body}}, {{comment_author}}
 * - {{pqrs_number}}, {{pqrs_type}}
 * - {{compra_number}}, {{compra_description}}
 *
 * Common templates:
 * - ticket_created: New ticket notification
 * - ticket_assigned: Assignment notification
 * - ticket_comment: New comment notification
 * - ticket_status_changed: Status change notification
 * - pqrs_created: PQRS confirmation
 * - compra_created: Purchase request notification
 *
 * @version 1.0.0 - Initial version (2026-01-05)
 */
class CreateEmailTemplates extends AbstractMigration
{
    /**
     * Create email_templates table
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('email_templates', ['signed' => false]);

        $table
            // Template identification
            ->addColumn('template_key', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'Unique template identifier (e.g., ticket_created, pqrs_comment)',
            ])

            // Email content
            ->addColumn('subject', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Email subject line (supports variables)',
            ])
            ->addColumn('body_html', 'text', [
                'null' => true,
                'comment' => 'HTML email body (supports variables and HTML tags)',
            ])

            // Template metadata
            ->addColumn('available_variables', 'text', [
                'null' => true,
                'comment' => 'JSON array of available variables for this template',
            ])
            ->addColumn('is_active', 'boolean', [
                'default' => true,
                'null' => false,
                'comment' => 'Template status (active templates are used for sending)',
            ])

            // Timestamps
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Template creation timestamp',
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Last modification timestamp',
            ])

            // Indexes
            ->addIndex(['template_key'], [
                'unique' => true,
                'name' => 'idx_template_key_unique',
            ])
            ->addIndex(['is_active'], [
                'name' => 'idx_email_templates_is_active',
            ])

            ->create();
    }
}
