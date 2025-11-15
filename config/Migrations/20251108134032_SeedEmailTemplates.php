<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class SeedEmailTemplates extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function up(): void
    {
        $nuevoTicketHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #0066cc; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f8f9fa; padding: 20px; margin: 20px 0; }
        .ticket-info { background-color: white; padding: 15px; margin: 10px 0; border-left: 4px solid #0066cc; }
        .button { display: inline-block; background-color: #0066cc; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 15px 0; }
        .footer { text-align: center; color: #666; font-size: 12px; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Nuevo Ticket Creado</h1>
        </div>
        <div class="content">
            <p>Hola <strong>{{requester_name}}</strong>,</p>
            <p>Hemos recibido tu solicitud de soporte y hemos creado un ticket para darle seguimiento.</p>
            <div class="ticket-info">
                <p><strong>Número de Ticket:</strong> {{ticket_number}}</p>
                <p><strong>Asunto:</strong> {{subject}}</p>
                <p><strong>Estado:</strong> Nuevo</p>
                <p><strong>Fecha de creación:</strong> {{created_date}}</p>
            </div>
            <p>Te mantendremos informado sobre el progreso de tu ticket.</p>
            <a href="{{ticket_url}}" class="button">Ver Mi Ticket</a>
        </div>
        <div class="footer">
            <p>Este es un correo automático. Por favor no respondas a este mensaje.</p>
            <p>Sistema de Soporte - {{system_title}}</p>
        </div>
    </div>
</body>
</html>
HTML;

        $ticketAbiertoHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #0066cc; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f8f9fa; padding: 20px; margin: 20px 0; }
        .ticket-info { background-color: white; padding: 15px; margin: 10px 0; border-left: 4px solid #f44336; }
        .button { display: inline-block; background-color: #0066cc; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 15px 0; }
        .footer { text-align: center; color: #666; font-size: 12px; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Actualización de Ticket</h1>
        </div>
        <div class="content">
            <p>Hola <strong>{{requester_name}}</strong>,</p>
            <p>Tu ticket está siendo atendido por nuestro equipo de soporte.</p>
            <div class="ticket-info">
                <p><strong>Número de Ticket:</strong> {{ticket_number}}</p>
                <p><strong>Asunto:</strong> {{subject}}</p>
                <p><strong>Asignado a:</strong> {{assignee_name}}</p>
                <p><strong>Estado:</strong> Abierto</p>
            </div>
            <p>{{assignee_name}} se pondrá en contacto contigo pronto.</p>
            <a href="{{ticket_url}}" class="button">Ver Mi Ticket</a>
        </div>
        <div class="footer">
            <p>Este es un correo automático. Por favor no respondas a este mensaje.</p>
            <p>Sistema de Soporte - {{system_title}}</p>
        </div>
    </div>
</body>
</html>
HTML;

        $ticketResueltoHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4caf50; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f8f9fa; padding: 20px; margin: 20px 0; }
        .ticket-info { background-color: white; padding: 15px; margin: 10px 0; border-left: 4px solid #4caf50; }
        .success-icon { font-size: 48px; text-align: center; margin: 10px 0; }
        .button { display: inline-block; background-color: #4caf50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 15px 0; }
        .footer { text-align: center; color: #666; font-size: 12px; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Ticket Resuelto</h1>
        </div>
        <div class="content">
            <div class="success-icon">✓</div>
            <p>Hola <strong>{{requester_name}}</strong>,</p>
            <p>Tu ticket ha sido marcado como resuelto.</p>
            <div class="ticket-info">
                <p><strong>Número de Ticket:</strong> {{ticket_number}}</p>
                <p><strong>Asunto:</strong> {{subject}}</p>
                <p><strong>Resuelto por:</strong> {{assignee_name}}</p>
                <p><strong>Fecha de resolución:</strong> {{updated_date}}</p>
            </div>
            <p>Si consideras que el problema no ha sido resuelto, puedes responder a este correo para reabrir el ticket.</p>
            <a href="{{ticket_url}}" class="button">Ver Detalles</a>
        </div>
        <div class="footer">
            <p>Este es un correo automático. Por favor no respondas a este mensaje.</p>
            <p>Sistema de Soporte - {{system_title}}</p>
        </div>
    </div>
</body>
</html>
HTML;

        $data = [
            [
                'template_key' => 'nuevo_ticket',
                'subject' => '[Ticket #{{ticket_number}}] {{subject}}',
                'body_html' => $nuevoTicketHtml,
                'available_variables' => json_encode([
                    'ticket_number', 'subject', 'requester_name',
                    'created_date', 'ticket_url', 'system_title'
                ]),
                'is_active' => true,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'template_key' => 'ticket_abierto',
                'subject' => '[Ticket #{{ticket_number}}] Tu ticket está siendo atendido',
                'body_html' => $ticketAbiertoHtml,
                'available_variables' => json_encode([
                    'ticket_number', 'subject', 'requester_name',
                    'assignee_name', 'ticket_url', 'system_title'
                ]),
                'is_active' => true,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'template_key' => 'ticket_resuelto',
                'subject' => '[Ticket #{{ticket_number}}] Tu ticket ha sido resuelto',
                'body_html' => $ticketResueltoHtml,
                'available_variables' => json_encode([
                    'ticket_number', 'subject', 'requester_name',
                    'assignee_name', 'updated_date', 'ticket_url', 'system_title'
                ]),
                'is_active' => true,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
        ];

        $table = $this->table('email_templates');
        $table->insert($data)->save();
    }

    public function down(): void
    {
        $this->execute('DELETE FROM email_templates');
    }
}
