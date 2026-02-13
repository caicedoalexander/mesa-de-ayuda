<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Log\Log;
use Cake\I18n\FrozenTime;

/**
 * N8n Service
 *
 * Handles webhook integration with n8n for AI-powered tag assignment
 */
class N8nService
{
    use LocatorAwareTrait;
    use Traits\ConfigResolutionTrait;
    use Traits\SecureHttpTrait;

    private array $config;
    private ?array $systemConfig;

    /**
     * Constructor
     *
     * @param array|null $systemConfig Optional system configuration to avoid redundant DB queries
     */
    public function __construct(?array $systemConfig = null)
    {
        $this->systemConfig = $systemConfig;
        $this->config = $this->resolveSettingsBatch('n8n_enabled', 'n8n_settings', [
            'n8n_enabled',
            'n8n_webhook_url',
            'n8n_api_key',
            'n8n_send_tags_list',
            'n8n_timeout',
        ]);
    }

    /**
     * Send ticket created webhook to n8n
     *
     * @param \App\Model\Entity\Ticket $ticket Created ticket entity
     * @return bool Success status
     */
    public function sendTicketCreatedWebhook(\App\Model\Entity\Ticket $ticket): bool
    {
        // Check if n8n is enabled
        if (empty($this->config['n8n_enabled']) || $this->config['n8n_enabled'] !== '1') {
            Log::debug('n8n integration is disabled');
            return false;
        }

        // Check webhook URL is configured
        if (empty($this->config['n8n_webhook_url'])) {
            Log::warning('n8n webhook URL is not configured');
            return false;
        }

        try {
            // Build webhook payload
            $payload = $this->buildTicketPayload($ticket);

            // Send webhook
            $response = $this->sendWebhook($this->config['n8n_webhook_url'], $payload);

            if ($response['success']) {
                Log::info('n8n webhook sent successfully', [
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                ]);
                return true;
            } else {
                Log::warning('n8n webhook failed', [
                    'ticket_id' => $ticket->id,
                    'error' => $response['error'] ?? 'Unknown error',
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('n8n webhook exception: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
                'exception' => $e,
            ]);
            return false;
        }
    }

    /**
     * Build webhook payload for ticket
     *
     * @param \App\Model\Entity\Ticket $ticket Ticket entity
     * @return array Webhook payload
     */
    private function buildTicketPayload(\App\Model\Entity\Ticket $ticket): array
    {
        // Strip HTML for plain text version
        $descriptionPlain = strip_tags($ticket->description ?? '');

        // Build base payload
        $payload = [
            'event' => 'ticket.created',
            'timestamp' => FrozenTime::now()->toIso8601String(),
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'description_plain' => $descriptionPlain,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'created' => $ticket->created?->toIso8601String(),
                'gmail_message_id' => $ticket->gmail_message_id,
            ],
        ];

        // Add requester info if available
        if ($ticket->requester) {
            $payload['ticket']['requester'] = [
                'id' => $ticket->requester->id,
                'name' => $ticket->requester->name,
                'email' => $ticket->requester->email,
            ];

            // Add organization if available
            if ($ticket->requester->organization) {
                $payload['ticket']['requester']['organization'] = $ticket->requester->organization->name;
            }
        }

        // Add attachments info if requested
        if (!empty($ticket->attachments)) {
            $payload['ticket']['attachments'] = [];
            foreach ($ticket->attachments as $attachment) {
                $payload['ticket']['attachments'][] = [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'size' => $attachment->file_size,
                    'mime_type' => $attachment->mime_type,
                ];
            }
        }

        // Add available tags if enabled
        if (!empty($this->config['n8n_send_tags_list']) && $this->config['n8n_send_tags_list'] === '1') {
            $tagsTable = $this->fetchTable('Tags');
            $tags = $tagsTable->find()
                ->select(['id', 'name', 'color'])
                ->where(['is_active' => true])
                ->orderBy(['name' => 'ASC'])
                ->toArray();

            $payload['ticket']['available_tags'] = [];
            foreach ($tags as $tag) {
                $payload['ticket']['available_tags'][] = [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color ?? '#999999',
                ];
            }
        }

        // Add callback URL for n8n to update tags
        $payload['callback_url'] = $this->getCallbackUrl();

        // Add app info
        $payload['app_info'] = [
            'version' => '1.0',
            'environment' => env('APP_ENV', 'production'),
        ];

        return $payload;
    }

    /**
     * Send webhook via cURL
     *
     * @param string $url Webhook URL
     * @param array $payload Payload data
     * @return array Response with success status
     */
    private function sendWebhook(string $url, array $payload): array
    {
        $timeout = (int) ($this->config['n8n_timeout'] ?? 10);

        $headers = [
            'Content-Type: application/json',
            'User-Agent: TicketSystem/1.0',
        ];

        if (!empty($this->config['n8n_api_key'])) {
            $headers[] = 'X-API-Key: ' . $this->config['n8n_api_key'];
        }

        return $this->secureCurlPost($url, json_encode($payload), $headers, $timeout);
    }

    /**
     * Get callback URL for n8n to update tags
     *
     * @return string Callback URL
     */
    private function getCallbackUrl(): string
    {
        // You can implement this later when you need n8n to send tags back
        // For now, return a placeholder
        return env('APP_URL', 'http://localhost') . '/api/webhooks/n8n/tags';
    }

    /**
     * Test n8n connection
     *
     * @return array Result with success and message
     */
    public function testConnection(): array
    {
        if (empty($this->config['n8n_webhook_url'])) {
            return [
                'success' => false,
                'message' => 'URL del webhook de n8n no configurada',
            ];
        }

        try {
            $testPayload = [
                'event' => 'connection.test',
                'timestamp' => FrozenTime::now()->toIso8601String(),
                'test' => true,
            ];

            $response = $this->sendWebhook($this->config['n8n_webhook_url'], $testPayload);

            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'Conexión exitosa con n8n (HTTP ' . $response['http_code'] . ')',
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al conectar con n8n: ' . ($response['error'] ?? 'HTTP ' . ($response['http_code'] ?? 'unknown')),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }
}
