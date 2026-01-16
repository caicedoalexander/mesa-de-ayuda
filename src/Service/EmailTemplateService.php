<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Log\Log;

/**
 * Email Template Service
 *
 * Responsible for loading and rendering email templates from database.
 * Single Responsibility: Template management only (no email sending).
 *
 * Usage:
 *   $service = new EmailTemplateService();
 *   $template = $service->getTemplate('nuevo_ticket');
 *   $rendered = $service->renderTemplate($template, ['ticket_number' => 'TK-001']);
 */
class EmailTemplateService
{
    use LocatorAwareTrait;

    /**
     * Get email template from database by key
     *
     * @param string $templateKey Template key (e.g., 'nuevo_ticket', 'ticket_estado')
     * @return array|null Template data with 'subject' and 'body_html' keys, or null if not found
     */
    public function getTemplate(string $templateKey): ?array
    {
        try {
            $templatesTable = $this->fetchTable('EmailTemplates');

            $template = $templatesTable->find()
                ->where([
                    'template_key' => $templateKey,
                    'is_active' => true,
                ])
                ->first();

            if (!$template) {
                Log::warning("Email template not found: {$templateKey}");
                return null;
            }

            return [
                'subject' => $template->subject,
                'body_html' => $template->body_html,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to load email template: {$templateKey}", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Render template by replacing variables
     *
     * Variables are replaced in {{variable}} format.
     * Example: "Hello {{name}}" with ['name' => 'John'] becomes "Hello John"
     *
     * @param array $template Template data with 'subject' and 'body_html' keys
     * @param array $variables Associative array of variable_name => value
     * @return array Rendered template with 'subject' and 'body' keys
     */
    public function renderTemplate(array $template, array $variables): array
    {
        $subject = $template['subject'] ?? '';
        $body = $template['body_html'] ?? '';

        // Replace variables in both subject and body
        foreach ($variables as $key => $value) {
            // Convert non-scalar values to empty string
            $valueStr = is_scalar($value) ? (string)$value : '';
            $placeholder = '{{' . $key . '}}';

            $subject = str_replace($placeholder, $valueStr, $subject);
            $body = str_replace($placeholder, $valueStr, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Get system-wide variables for email templates
     *
     * Returns common variables that are available in all templates,
     * such as system_title and current_year.
     *
     * @param array|null $systemConfig Optional system configuration to avoid DB queries
     * @return array System variables
     */
    public function getSystemVariables(?array $systemConfig = null): array
    {
        $systemTitle = 'Sistema de Soporte'; // Default

        // Use cached config if available
        if ($systemConfig !== null && isset($systemConfig['system_title'])) {
            $systemTitle = $systemConfig['system_title'];
        } else {
            // Fallback to DB query with cache
            try {
                $systemTitle = \Cake\Cache\Cache::remember('system_title', function () {
                    $settingsTable = $this->fetchTable('SystemSettings');
                    $setting = $settingsTable->find()
                        ->where(['setting_key' => 'system_title'])
                        ->first();
                    return $setting ? $setting->setting_value : 'Sistema de Soporte';
                }, '_cake_core_');
            } catch (\Exception $e) {
                Log::error('Failed to load system_title: ' . $e->getMessage());
            }
        }

        return [
            'system_title' => $systemTitle,
            'current_year' => date('Y'),
        ];
    }

    /**
     * Get template and render in one call (convenience method)
     *
     * @param string $templateKey Template key
     * @param array $variables Variables to replace
     * @param array|null $systemConfig Optional system configuration
     * @return array|null Rendered template with 'subject' and 'body', or null if template not found
     */
    public function getAndRender(string $templateKey, array $variables, ?array $systemConfig = null): ?array
    {
        $template = $this->getTemplate($templateKey);
        if (!$template) {
            return null;
        }

        // Merge system variables with provided variables
        $systemVars = $this->getSystemVariables($systemConfig);
        $allVariables = array_merge($systemVars, $variables);

        return $this->renderTemplate($template, $allVariables);
    }
}
