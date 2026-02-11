<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Log\Log;

/**
 * WhatsApp Service
 *
 * Handles WhatsApp notifications via Evolution API:
 * - New ticket notifications (to tickets team)
 * - New PQRS notifications (to customer service team)
 * - New compra notifications (to purchasing team)
 */
class WhatsappService
{
    use LocatorAwareTrait;

    private \App\Service\Renderer\NotificationRenderer $renderer;

    /**
     * Evolution API configuration
     */
    private ?array $config = null;

    /**
     * Constructor
     *
     * @param array|null $systemConfig Optional system configuration to avoid redundant DB queries
     */
    public function __construct(?array $systemConfig = null)
    {
        $this->renderer = new \App\Service\Renderer\NotificationRenderer();

        // Pre-load config if provided
        if ($systemConfig !== null) {
            $this->loadConfigFromArray($systemConfig);
        }
    }

    /**
     * Load WhatsApp configuration from provided system settings array
     *
     * @param array $systemConfig System configuration array
     * @return void
     */
    private function loadConfigFromArray(array $systemConfig): void
    {
        // Check if WhatsApp is enabled
        if (empty($systemConfig['whatsapp_enabled']) || $systemConfig['whatsapp_enabled'] !== '1') {
            $this->config = null;
            return;
        }

        // Validate required settings
        if (
            empty($systemConfig['whatsapp_api_url']) ||
            empty($systemConfig['whatsapp_api_key']) ||
            empty($systemConfig['whatsapp_instance_name'])
        ) {
            Log::warning('WhatsApp configuration incomplete');
            $this->config = null;
            return;
        }

        $this->config = [
            'api_url' => rtrim($systemConfig['whatsapp_api_url'], '/'),
            'api_key' => $systemConfig['whatsapp_api_key'],
            'instance_name' => $systemConfig['whatsapp_instance_name'],
            'tickets_number' => $systemConfig['whatsapp_tickets_number'] ?? null,
            'pqrs_number' => $systemConfig['whatsapp_pqrs_number'] ?? null,
            'compras_number' => $systemConfig['whatsapp_compras_number'] ?? null,
        ];
    }

    /**
     * Get WhatsApp configuration from system_settings (with cache)
     *
     * @return array|null Configuration array or null if not configured
     */
    private function getConfig(): ?array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        try {
            $settings = \Cake\Cache\Cache::remember('whatsapp_settings', function () {
                $settingsTable = $this->fetchTable('SystemSettings');
                return $settingsTable->find()
                    ->where([
                        'setting_key IN' => [
                            'whatsapp_enabled',
                            'whatsapp_api_url',
                            'whatsapp_api_key',
                            'whatsapp_instance_name',
                            'whatsapp_tickets_number',
                            'whatsapp_pqrs_number',
                            'whatsapp_compras_number',
                        ]
                    ])
                    ->all()
                    ->combine('setting_key', 'setting_value')
                    ->toArray();
            }, '_cake_core_');

            // Check if WhatsApp is enabled
            if (empty($settings['whatsapp_enabled']) || $settings['whatsapp_enabled'] !== '1') {
                $this->config = null;
                return null;
            }

            // Validate required settings
            if (
                empty($settings['whatsapp_api_url']) ||
                empty($settings['whatsapp_api_key']) ||
                empty($settings['whatsapp_instance_name'])
            ) {
                Log::warning('WhatsApp configuration incomplete');
                $this->config = null;
                return null;
            }

            $this->config = $settings;
            return $this->config;
        } catch (\Exception $e) {
            Log::error('Failed to load WhatsApp configuration', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Send WhatsApp message via Evolution API
     *
     * @param string $number WhatsApp number (can be individual or group ID)
     * @param string $text Message text
     * @return bool Success status
     */
    public function sendMessage(string $number, string $text): bool
    {
        $config = $this->getConfig();

        if (!$config) {
            Log::info('WhatsApp is disabled or not configured, skipping notification');
            return false;
        }

        try {
            $url = rtrim($config['whatsapp_api_url'], '/') .
                '/message/sendText/' .
                $config['whatsapp_instance_name'];

            $data = [
                'number' => $number,
                'text' => $text,
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'apikey: ' . $config['whatsapp_api_key'],
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if ($error) {
                Log::error('WhatsApp API cURL error', [
                    'error' => $error,
                    'number' => $number,
                ]);
                return false;
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                Log::info('WhatsApp message sent successfully', [
                    'number' => $number,
                    'http_code' => $httpCode,
                ]);
                return true;
            } else {
                Log::error('WhatsApp API returned error', [
                    'http_code' => $httpCode,
                    'response' => $response,
                    'number' => $number,
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp message', [
                'error' => $e->getMessage(),
                'number' => $number,
            ]);
            return false;
        }
    }

    /**
     * Send new ticket notification via WhatsApp
     *
     * @param \App\Model\Entity\Ticket $ticket Ticket entity
     * @return bool Success status
     */
    public function sendNewTicketNotification($ticket): bool
    {
        $ticketsTable = $this->fetchTable('Tickets');
        $ticket = $ticketsTable->get($ticket->id, contain: ['Requesters']);

        return $this->sendNewEntityNotification('ticket', $ticket);
    }

    /**
     * Send new PQRS notification via WhatsApp
     *
     * @param \App\Model\Entity\Pqr $pqrs PQRS entity
     * @return bool Success status
     */
    public function sendNewPqrsNotification($pqrs): bool
    {
        return $this->sendNewEntityNotification('pqrs', $pqrs);
    }

    /**
     * Send new Compra notification via WhatsApp
     *
     * @param \App\Model\Entity\Compra $compra Compra entity
     * @return bool Success status
     */
    public function sendNewCompraNotification($compra): bool
    {
        $comprasTable = $this->fetchTable('Compras');
        $compra = $comprasTable->get($compra->id, contain: ['Requesters', 'Assignees']);

        return $this->sendNewEntityNotification('compra', $compra);
    }

    /**
     * Send new entity notification via WhatsApp (generic)
     *
     * @param string $entityType 'ticket', 'pqrs', 'compra'
     * @param \Cake\Datasource\EntityInterface $entity Entity instance
     * @return bool Success status
     */
    private function sendNewEntityNotification(string $entityType, \Cake\Datasource\EntityInterface $entity): bool
    {
        try {
            $configMap = [
                'ticket' => ['numberKey' => 'whatsapp_tickets_number', 'renderer' => 'renderWhatsappNewTicket'],
                'pqrs' => ['numberKey' => 'whatsapp_pqrs_number', 'renderer' => 'renderWhatsappNewPqrs'],
                'compra' => ['numberKey' => 'whatsapp_compras_number', 'renderer' => 'renderWhatsappNewCompra'],
            ];

            $map = $configMap[$entityType];
            $config = $this->getConfig();

            if (!$config || empty($config[$map['numberKey']])) {
                Log::info("WhatsApp {$entityType} number not configured, skipping notification");
                return false;
            }

            $message = $this->renderer->{$map['renderer']}($entity);

            return $this->sendMessage($config[$map['numberKey']], $message);
        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp {$entityType} notification", [
                'entity_id' => $entity->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Test WhatsApp connection
     *
     * @param string $module Module to test ('tickets', 'pqrs', or 'compras')
     * @return array Test result with status and message
     */
    public function testConnection(string $module = 'tickets'): array
    {
        $config = $this->getConfig();

        if (!$config) {
            return [
                'success' => false,
                'message' => 'WhatsApp está deshabilitado o no configurado',
            ];
        }

        // Get the appropriate number based on module
        $numberKey = "whatsapp_{$module}_number";
        if (empty($config[$numberKey])) {
            return [
                'success' => false,
                'message' => "No se ha configurado un número de WhatsApp para {$module}",
            ];
        }

        $moduleLabels = [
            'tickets' => 'Tickets',
            'pqrs' => 'PQRS',
            'compras' => 'Compras',
        ];
        $moduleLabel = $moduleLabels[$module] ?? $module;

        $testMessage = "✅ Prueba de conexión - Evolution API\n\n" .
            "Este es un mensaje de prueba del módulo de {$moduleLabel}.\n" .
            "Si recibes este mensaje, la integración está funcionando correctamente.\n\n" .
            "_Sistema de Soporte - {$moduleLabel}_";

        $result = $this->sendMessage($config[$numberKey], $testMessage);

        return [
            'success' => $result,
            'message' => $result
                ? 'Mensaje de prueba enviado exitosamente'
                : 'Error al enviar mensaje de prueba. Revisa los logs para más detalles.',
        ];
    }
}
