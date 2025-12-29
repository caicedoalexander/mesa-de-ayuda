<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add Compra Comentario Email Template Migration
 *
 * Adds the missing 'compra_comentario' email template to complete
 * the 4 notification types for Compras (matching Tickets and PQRS).
 *
 * This template is used for standalone comment notifications without
 * status changes (via sendCompraCommentNotification).
 */
class AddCompraComentarioTemplate extends BaseMigration
{
    /**
     * Up Method.
     *
     * @return void
     */
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->table('email_templates')
            ->insert([
                [
                    'template_key' => 'compra_comentario',
                    'subject' => '[Compra #{{compra_number}}] Nuevo comentario',
                    'body_html' => null,
                    'available_variables' => json_encode([
                        'compra_number',
                        'subject',
                        'requester_name',
                        'comment_author',
                        'comment_body',
                        'attachments_list',
                        'compra_url',
                        'system_title',
                        'agent_profile_image_url',
                        'agent_name'
                    ]),
                    'is_active' => true,
                    'created' => $now,
                    'modified' => $now,
                ],
            ])
            ->save();
    }

    /**
     * Down Method.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute("DELETE FROM email_templates WHERE template_key = 'compra_comentario'");
    }
}
