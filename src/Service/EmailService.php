<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Mailer\Mailer;
use Cake\Mailer\TransportFactory;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Log\Log;
use Cake\Core\Configure;

/**
 * Email Service
 *
 * Handles all email notifications using templates from database:
 * - New ticket notifications
 * - Status change notifications
 * - New comment notifications
 */
class EmailService
{
    use LocatorAwareTrait;

    /**
     * Send new ticket notification to requester
     *
     * @param \App\Model\Entity\Ticket $ticket Ticket entity
     * @return bool Success status
     */
    public function sendNewTicketNotification($ticket): bool
    {
        try {
            // Load ticket with requester
            $ticketsTable = $this->fetchTable('Tickets');
            $ticket = $ticketsTable->get($ticket->id, contain: ['Requesters']);

            // Get email template
            $template = $this->getTemplate('nuevo_ticket');

            if (!$template) {
                Log::error('Email template not found: nuevo_ticket');
                return false;
            }

            // Replace variables in subject and body
            $variables = [
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'requester_name' => $ticket->requester->name,
                'created_date' => $ticket->created->i18nFormat('dd/MM/yyyy HH:mm'),
                'ticket_url' => $this->getTicketUrl($ticket->id),
            ];

            $subject = $this->replaceVariables($template->subject, $variables);
            $body = $this->replaceVariables($template->body_html, $variables);

            // Send email
            return $this->sendEmail($ticket->requester->email, $subject, $body);
        } catch (\Exception $e) {
            Log::error('Failed to send new ticket notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send status change notification
     *
     * @param \App\Model\Entity\Ticket $ticket Ticket entity
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @return bool Success status
     */
    public function sendStatusChangeNotification($ticket, string $oldStatus, string $newStatus): bool
    {
        try {
            // Load ticket with requester and assignee
            $ticketsTable = $this->fetchTable('Tickets');
            $ticket = $ticketsTable->get($ticket->id, contain: ['Requesters', 'Assignees']);

            // Determine template based on status
            $templateKey = match ($newStatus) {
                'abierto' => 'ticket_abierto',
                'resuelto' => 'ticket_resuelto',
                default => null,
            };

            if (!$templateKey) {
                // No specific template for this status change
                return true;
            }

            $template = $this->getTemplate($templateKey);

            if (!$template) {
                Log::error("Email template not found: {$templateKey}");
                return false;
            }

            // Replace variables
            $variables = [
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'requester_name' => $ticket->requester->name,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'assignee_name' => $ticket->assignee ? $ticket->assignee->name : 'No asignado',
                'updated_date' => $ticket->modified->i18nFormat('dd/MM/yyyy HH:mm'),
                'ticket_url' => $this->getTicketUrl($ticket->id),
            ];

            $subject = $this->replaceVariables($template->subject, $variables);
            $body = $this->replaceVariables($template->body_html, $variables);

            // Send email to requester
            return $this->sendEmail($ticket->requester->email, $subject, $body);
        } catch (\Exception $e) {
            Log::error('Failed to send status change notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send new comment notification
     *
     * @param \App\Model\Entity\Ticket $ticket Ticket entity
     * @param \App\Model\Entity\TicketComment $comment Comment entity
     * @return bool Success status
     */
    public function sendNewCommentNotification($ticket, $comment): bool
    {
        try {
            // Load entities with associations
            $ticketsTable = $this->fetchTable('Tickets');
            $ticket = $ticketsTable->get($ticket->id, contain: ['Requesters', 'Assignees']);

            $commentsTable = $this->fetchTable('TicketComments');
            $comment = $commentsTable->get($comment->id, contain: ['Users']);

            // Build email content
            $subject = "[Ticket #{$ticket->ticket_number}] Nuevo comentario";

            $body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #0066cc; color: white; padding: 20px; text-align: center; }
                    .content { background-color: #f8f9fa; padding: 20px; margin: 20px 0; }
                    .comment { background-color: white; padding: 15px; margin: 10px 0; border-left: 4px solid #0066cc; }
                    .footer { text-align: center; color: #666; font-size: 12px; padding: 20px; }
                    .button { display: inline-block; background-color: #0066cc; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Nuevo Comentario en tu Ticket</h2>
                    </div>
                    <div class='content'>
                        <p><strong>Ticket:</strong> #{$ticket->ticket_number}</p>
                        <p><strong>Asunto:</strong> {$ticket->subject}</p>
                        <p><strong>De:</strong> {$comment->user->name}</p>
                        <div class='comment'>
                            {$comment->body}
                        </div>
                        <p style='text-align: center; margin-top: 20px;'>
                            <a href='{$this->getTicketUrl($ticket->id)}' class='button'>Ver Ticket</a>
                        </p>
                    </div>
                    <div class='footer'>
                        <p>Sistema de Soporte</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            // Send email to requester
            return $this->sendEmail($ticket->requester->email, $subject, $body);
        } catch (\Exception $e) {
            Log::error('Failed to send new comment notification', [
                'ticket_id' => $ticket->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get email template from database
     *
     * @param string $templateKey Template key
     * @return \App\Model\Entity\EmailTemplate|null
     */
    private function getTemplate(string $templateKey): ?\App\Model\Entity\EmailTemplate
    {
        $templatesTable = $this->fetchTable('EmailTemplates');

        return $templatesTable->find()
            ->where([
                'template_key' => $templateKey,
                'is_active' => true,
            ])
            ->first();
    }

    /**
     * Replace variables in template string
     *
     * @param string $template Template string with {{variables}}
     * @param array $variables Associative array of variable name => value
     * @return string Template with replaced variables
     */
    private function replaceVariables(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace("{{" . $key . "}}", (string)$value, $template);
        }

        return $template;
    }

    /**
     * Send email using configured mailer
     *
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body HTML body
     * @return bool Success status
     */
    private function sendEmail(string $to, string $subject, string $body): bool
    {
        try {
            // Load SMTP config from database
            $settingsTable = $this->fetchTable('SystemSettings');
            $settings = $settingsTable->find()
                ->where(['setting_key IN' => [
                    'smtp_host', 'smtp_port', 'smtp_username',
                    'smtp_password', 'smtp_encryption', 'system_title'
                ]])
                ->all()
                ->combine('setting_key', 'setting_value')
                ->toArray();

            // Configure SMTP transport
            TransportFactory::setConfig('smtp_custom', [
                'className' => 'Smtp',
                'host' => $settings['smtp_host'] ?? 'smtp.gmail.com',
                'port' => (int)($settings['smtp_port'] ?? 587),
                'username' => $settings['smtp_username'] ?? '',
                'password' => $settings['smtp_password'] ?? '',
                'tls' => ($settings['smtp_encryption'] ?? 'tls') === 'tls',
            ]);

            // Create mailer
            $mailer = new Mailer('default');
            $mailer->setTransport('smtp_custom');

            // Set from address
            $fromEmail = $settings['smtp_username'] ?? 'noreply@localhost';
            $fromName = $settings['system_title'] ?? 'Sistema de Soporte';

            $mailer
                ->setEmailFormat('html')
                ->setFrom([$fromEmail => $fromName])
                ->setTo($to)
                ->setSubject($subject)
                ->deliver($body);

            Log::info('Email sent successfully', ['to' => $to, 'subject' => $subject]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send email', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get ticket URL
     *
     * @param int $ticketId Ticket ID
     * @return string Full URL to ticket
     */
    private function getTicketUrl(int $ticketId): string
    {
        $baseUrl = Configure::read('App.fullBaseUrl', 'http://localhost:8765');
        return $baseUrl . '/tickets/view/' . $ticketId;
    }

    /**
     * Get SMTP configuration from system settings
     *
     * @return array SMTP configuration
     */
    public function getSmtpConfig(): array
    {
        $settingsTable = $this->fetchTable('SystemSettings');
        $settings = $settingsTable->find()
            ->select(['setting_key', 'setting_value'])
            ->where(['setting_key LIKE' => 'smtp_%'])
            ->toArray();

        $config = [];
        foreach ($settings as $setting) {
            $key = str_replace('smtp_', '', $setting->setting_key);
            $config[$key] = $setting->setting_value;
        }

        return $config;
    }
}
