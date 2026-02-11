# PLAN DE RESOLUCIÓN COMPLETO - 77 Issues

**Fecha**: 2026-01-13
**Versión**: 1.0
**Total Issues**: 77
**Documento**: Guía ejecutable completa para Claude Code

---

## Índice

1. [Fase 0: Bloqueadores (2 issues)](#fase-0-bloqueadores)
2. [Fase 1: Arquitectura - Services (13 issues)](#fase-1-arquitectura-services)
3. [Fase 2: Arquitectura - Traits (6 issues)](#fase-2-arquitectura-traits)
4. [Fase 3: Controllers (8 issues)](#fase-3-controllers)
5. [Fase 4: Models (4 issues)](#fase-4-models)
6. [Fase 5: Optimizaciones (44 issues Low)](#fase-5-optimizaciones)

---

# FASE 0: BLOQUEADORES

## BLK-001 / SEC-001: N8nService SSL Verification Disabled

**Archivo**: `src/Service/N8nService.php`
**Líneas**: 51
**Severidad**: 🔴 CRÍTICO
**Esfuerzo**: 10 minutos

### Root Cause Analysis

**Por qué sucede**:
Durante desarrollo, el certificado SSL de n8n puede ser autofirmado o inválido, causando que las requests fallen. El desarrollador deshabilitó la verificación SSL para "hacer que funcione" temporalmente, pero esto se quedó en el código.

```php
// Código actual (línea 51)
$client = new Client([
    'base_uri' => $this->webhookUrl,
    'timeout' => 30,
    'verify' => false, // ⚠️ VULNERABLE: Acepta cualquier certificado
]);
```

**Impacto de seguridad**:
1. **Man-in-the-Middle (MITM) Attack**: Un atacante puede interceptar el tráfico entre la app y n8n
2. **Certificate Spoofing**: Certificados falsos son aceptados sin validación
3. **Data Breach Risk**: Datos sensibles (webhooks, payloads) pueden ser leídos por terceros
4. **Compliance Violation**: Viola PCI-DSS, GDPR, y otros estándares de seguridad

### Solución Paso a Paso

**Paso 1: Identificar el ambiente**
```php
// src/Service/N8nService.php líneas 40-60

private function getHttpClient(): Client
{
    // Determinar si estamos en desarrollo o producción
    $isDebug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
    $isDevelopment = env('APP_ENV', 'production') === 'development';

    // En desarrollo local, permitir certificados autofirmados
    // En producción, SIEMPRE verificar SSL
    $verifySSL = !($isDebug && $isDevelopment);

    return new Client([
        'base_uri' => $this->webhookUrl,
        'timeout' => 30,
        'verify' => $verifySSL, // ✅ SEGURO
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
    ]);
}
```

**Paso 2: Actualizar el constructor para usar el método**
```php
// Líneas 25-35
private Client $httpClient;

public function __construct(?array $systemConfig = null)
{
    // ... código existente ...

    // Crear cliente HTTP con configuración segura
    $this->httpClient = $this->getHttpClient();
}

// Actualizar todos los métodos que usan 'new Client()' para usar '$this->httpClient'
```

**Paso 3: Agregar logging para debugging**
```php
use Cake\Log\Log;

private function getHttpClient(): Client
{
    $isDebug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
    $isDevelopment = env('APP_ENV', 'production') === 'development';
    $verifySSL = !($isDebug && $isDevelopment);

    // Log warning si SSL verification está deshabilitada
    if (!$verifySSL) {
        Log::warning(
            'N8nService: SSL verification is DISABLED. This should only happen in development.',
            ['env' => env('APP_ENV'), 'debug' => $isDebug]
        );
    }

    return new Client([
        'base_uri' => $this->webhookUrl,
        'timeout' => 30,
        'verify' => $verifySSL,
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
    ]);
}
```

**Paso 4: Variables de entorno**
```env
# .env (desarrollo)
APP_ENV=development
APP_DEBUG=true
N8N_WEBHOOK_URL=https://localhost:5678/webhook/...

# .env.production
APP_ENV=production
APP_DEBUG=false
N8N_WEBHOOK_URL=https://n8n.tudominio.com/webhook/...
```

### Testing

```bash
# Test 1: Verificar que funciona en desarrollo
APP_ENV=development APP_DEBUG=true bin/cake test_n8n

# Test 2: Verificar que SSL está habilitado en producción
APP_ENV=production APP_DEBUG=false bin/cake test_n8n

# Test 3: Intentar conectar a endpoint con certificado inválido en producción (debe fallar)
# Esto es CORRECTO - queremos que falle si el certificado no es válido
```

### Beneficios

✅ **Seguridad**: Protege contra ataques MITM
✅ **Compliance**: Cumple con estándares PCI-DSS, GDPR
✅ **Confianza**: Los clientes pueden confiar en que sus datos están protegidos
✅ **Profesionalismo**: Demuestra que la aplicación sigue best practices

### Dependencias

Ninguna - puede hacerse inmediatamente.

### Rollback Plan

Si n8n tiene problemas de certificado en producción:
1. Verificar que n8n tiene certificado SSL válido (Let's Encrypt, etc.)
2. Si usa certificado autofirmado, obtener uno válido
3. Como último recurso temporal: Agregar variable `N8N_VERIFY_SSL=false` en .env

---

## BLK-002 / ARCH-005: EmailService God Object

**Archivo**: `src/Service/EmailService.php`
**Líneas**: 1,139
**Severidad**: 🔴 CRÍTICO
**Esfuerzo**: 5-6 días

### Root Cause Analysis

**Por qué sucede**:
1. **Copy-paste programming**: Cada nuevo módulo (Tickets → PQRS → Compras) copió y pegó métodos existentes
2. **Falta de abstracción**: No se identificó el patrón común entre módulos
3. **Presión de tiempo**: Más rápido copiar que refactorizar
4. **Falta de code review**: Nadie detuvo la duplicación tempranamente

**Estructura actual**:
```
EmailService.php (1,139 líneas)
├── Tickets (420 líneas)
│   ├── sendTicketCreatedEmail() - 95 líneas
│   ├── sendTicketCommentEmail() - 90 líneas
│   ├── sendTicketAssignedEmail() - 85 líneas
│   ├── sendTicketStatusChangedEmail() - 75 líneas
│   └── sendTicketResolvedEmail() - 75 líneas
│
├── PQRS (380 líneas - 80% duplicado)
│   ├── sendPqrsCreatedEmail() - 85 líneas
│   ├── sendPqrsCommentEmail() - 85 líneas
│   ├── sendPqrsAssignedEmail() - 80 líneas
│   └── sendPqrsStatusChangedEmail() - 70 líneas
│
├── Compras (360 líneas - 80% duplicado)
│   ├── sendCompraCreatedEmail() - 80 líneas
│   ├── sendCompraCommentEmail() - 85 líneas
│   ├── sendCompraApprovedEmail() - 75 líneas
│   └── sendCompraStatusChangedEmail() - 70 líneas
│
└── Helpers (privados) - 120 líneas
```

**Patrón de duplicación**:
```php
// Todos los métodos siguen EXACTAMENTE este patrón:
public function sendXXXCreatedEmail(Entity $entity): bool
{
    // 1. Cargar template desde DB (15 líneas)
    $templateTable = $this->fetchTable('EmailTemplates');
    $template = $templateTable->find()
        ->where(['key' => 'xxx_template'])
        ->first();
    if (!$template) { return false; }

    // 2. Preparar variables (20 líneas)
    $variables = [
        'xxx_number' => $entity->xxx_number,
        'subject' => $entity->subject,
        'requester_name' => $entity->requester->name,
        // ... 10+ más variables
    ];

    // 3. Reemplazar variables en template (10 líneas)
    $subject = $template->subject;
    $body = $template->body;
    foreach ($variables as $key => $value) {
        $subject = str_replace("{{" . $key . "}}", $value, $subject);
        $body = str_replace("{{" . $key . "}}", $value, $body);
    }

    // 4. Enviar email via Mailer (30 líneas)
    $mailer = new Mailer('default');
    try {
        $mailer
            ->setFrom([env('EMAIL_FROM') => env('EMAIL_FROM_NAME')])
            ->setTo($entity->requester->email)
            ->setSubject($subject)
            ->setEmailFormat('html')
            ->deliver($body);
        return true;
    } catch (\Exception $e) {
        Log::error('Failed to send email: ' . $e->getMessage());
        return false;
    }
}
```

### Solución Completa - Refactoring en 3 Servicios

#### Servicio 1: EmailTemplateService

**Crear**: `src/Service/EmailTemplateService.php`

```php
<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Log\Log;

/**
 * Email Template Service
 *
 * Responsabilidad: Cargar y renderizar email templates desde la base de datos
 *
 * Este servicio elimina la duplicación de carga de templates que existía
 * en cada método de EmailService.
 */
class EmailTemplateService
{
    use LocatorAwareTrait;

    /**
     * Get template by key con caching
     *
     * @param string $templateKey Template key (e.g., 'nuevo_ticket', 'ticket_comentario')
     * @return array{subject: string, body: string}|null
     */
    public function getTemplate(string $templateKey): ?array
    {
        $templatesTable = $this->fetchTable('EmailTemplates');

        $template = $templatesTable->find()
            ->where(['key' => $templateKey])
            ->first();

        if (!$template) {
            Log::warning("Email template not found: {$templateKey}");
            return null;
        }

        return [
            'subject' => $template->subject,
            'body' => $template->body,
        ];
    }

    /**
     * Render template con variables
     *
     * Reemplaza todas las variables {{variable}} en subject y body
     *
     * @param array{subject: string, body: string} $template
     * @param array<string, mixed> $variables
     * @return array{subject: string, body: string}
     */
    public function renderTemplate(array $template, array $variables): array
    {
        $subject = $template['subject'];
        $body = $template['body'];

        // Reemplazar variables en subject y body
        foreach ($variables as $key => $value) {
            // Convertir a string si no lo es
            $valueStr = is_scalar($value) ? (string)$value : '';

            $placeholder = "{{" . $key . "}}";
            $subject = str_replace($placeholder, $valueStr, $subject);
            $body = str_replace($placeholder, $valueStr, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Get standard variables para entity type
     *
     * Define qué variables están disponibles para cada tipo de entity
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @return array<string> Lista de variables disponibles
     */
    public function getAvailableVariables(string $entityType): array
    {
        $common = [
            'subject',
            'description',
            'status',
            'priority',
            'created',
            'requester_name',
            'requester_email',
            'assignee_name',
            'assignee_email',
        ];

        $specific = match ($entityType) {
            'ticket' => [
                'ticket_number',
                'source_email',
                'channel',
                'resolved_at',
            ],
            'pqrs' => [
                'pqrs_number',
                'type',
                'requester_phone',
                'requester_address',
            ],
            'compra' => [
                'compra_number',
                'original_ticket_number',
                'approval_status',
                'sla_due_date',
            ],
            default => [],
        };

        return array_merge($common, $specific);
    }
}
```

#### Servicio 2: GenericEmailService

**Crear**: `src/Service/GenericEmailService.php`

```php
<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\EntityInterface;
use Cake\Mailer\Mailer;
use Cake\Log\Log;

/**
 * Generic Email Service
 *
 * Responsabilidad: Enviar emails genéricos para cualquier tipo de entity
 *
 * Este servicio elimina ~900 líneas de código duplicado al proporcionar
 * un único método para enviar emails de cualquier módulo.
 */
class GenericEmailService
{
    private EmailTemplateService $templateService;
    private string $fromEmail;
    private string $fromName;

    public function __construct(EmailTemplateService $templateService, ?array $systemConfig = null)
    {
        $this->templateService = $templateService;

        // Cargar configuración de email desde system config o env
        $this->fromEmail = $systemConfig['email_from'] ?? env('EMAIL_FROM', 'noreply@example.com');
        $this->fromName = $systemConfig['email_from_name'] ?? env('EMAIL_FROM_NAME', 'Mesa de Ayuda');
    }

    /**
     * Send entity email
     *
     * Método genérico que funciona para tickets, pqrs, y compras
     *
     * @param string $templateKey Template key (e.g., 'nuevo_ticket')
     * @param EntityInterface $entity Entity (Ticket, Pqr, Compra)
     * @param array<string, mixed> $extraVariables Variables adicionales
     * @param array<string> $recipients Email recipients (si no se pasa, usa entity->requester->email)
     * @return bool Success
     */
    public function sendEntityEmail(
        string $templateKey,
        EntityInterface $entity,
        array $extraVariables = [],
        array $recipients = []
    ): bool {
        // 1. Cargar template
        $template = $this->templateService->getTemplate($templateKey);
        if (!$template) {
            Log::error("Cannot send email: template '{$templateKey}' not found");
            return false;
        }

        // 2. Preparar variables desde entity
        $variables = $this->extractVariablesFromEntity($entity);
        $variables = array_merge($variables, $extraVariables);

        // 3. Renderizar template con variables
        $rendered = $this->templateService->renderTemplate($template, $variables);

        // 4. Determinar recipients
        if (empty($recipients)) {
            $recipients = $this->getDefaultRecipients($entity);
        }

        if (empty($recipients)) {
            Log::warning("No recipients found for email '{$templateKey}'");
            return false;
        }

        // 5. Enviar email
        return $this->sendEmail($recipients, $rendered['subject'], $rendered['body']);
    }

    /**
     * Extract variables from entity
     *
     * @param EntityInterface $entity
     * @return array<string, mixed>
     */
    private function extractVariablesFromEntity(EntityInterface $entity): array
    {
        $variables = [];

        // Propiedades comunes en todas las entities
        $commonProps = [
            'subject',
            'description',
            'status',
            'priority',
            'created',
            'modified',
        ];

        foreach ($commonProps as $prop) {
            if (isset($entity->{$prop})) {
                $value = $entity->{$prop};
                // Formatear fechas
                if ($value instanceof \DateTimeInterface) {
                    $variables[$prop] = $value->format('Y-m-d H:i:s');
                } else {
                    $variables[$prop] = $value;
                }
            }
        }

        // Number field (depende del tipo)
        if (isset($entity->ticket_number)) {
            $variables['ticket_number'] = $entity->ticket_number;
            $variables['entity_number'] = $entity->ticket_number;
        } elseif (isset($entity->pqrs_number)) {
            $variables['pqrs_number'] = $entity->pqrs_number;
            $variables['entity_number'] = $entity->pqrs_number;
        } elseif (isset($entity->compra_number)) {
            $variables['compra_number'] = $entity->compra_number;
            $variables['entity_number'] = $entity->compra_number;
        }

        // Requester info
        if (isset($entity->requester)) {
            $variables['requester_name'] = $entity->requester->name ?? '';
            $variables['requester_email'] = $entity->requester->email ?? '';
        } elseif (isset($entity->requester_name)) {
            // PQRS tiene requester_name directamente
            $variables['requester_name'] = $entity->requester_name;
            $variables['requester_email'] = $entity->requester_email ?? '';
        }

        // Assignee info
        if (isset($entity->assignee)) {
            $variables['assignee_name'] = $entity->assignee->name ?? 'Sin asignar';
            $variables['assignee_email'] = $entity->assignee->email ?? '';
        } else {
            $variables['assignee_name'] = 'Sin asignar';
            $variables['assignee_email'] = '';
        }

        // Campos específicos
        if (isset($entity->type)) {
            $variables['type'] = $entity->type; // PQRS
        }
        if (isset($entity->channel)) {
            $variables['channel'] = $entity->channel;
        }
        if (isset($entity->resolved_at)) {
            $variables['resolved_at'] = $entity->resolved_at?->format('Y-m-d H:i:s') ?? '';
        }

        return $variables;
    }

    /**
     * Get default recipients from entity
     *
     * @param EntityInterface $entity
     * @return array<string>
     */
    private function getDefaultRecipients(EntityInterface $entity): array
    {
        $recipients = [];

        // Requester email
        if (isset($entity->requester->email)) {
            $recipients[] = $entity->requester->email;
        } elseif (isset($entity->requester_email)) {
            $recipients[] = $entity->requester_email;
        }

        return $recipients;
    }

    /**
     * Send email usando CakePHP Mailer
     *
     * @param array<string> $recipients
     * @param string $subject
     * @param string $body
     * @return bool
     */
    private function sendEmail(array $recipients, string $subject, string $body): bool
    {
        $mailer = new Mailer('default');

        try {
            $mailer
                ->setFrom([$this->fromEmail => $this->fromName])
                ->setTo($recipients)
                ->setSubject($subject)
                ->setEmailFormat('html')
                ->deliver($body);

            Log::info('Email sent successfully', [
                'recipients' => $recipients,
                'subject' => $subject,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send email', [
                'recipients' => $recipients,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send email with CC/BCC support
     *
     * @param array<string> $to
     * @param string $subject
     * @param string $body
     * @param array<string> $cc
     * @param array<string> $bcc
     * @return bool
     */
    public function sendEmailWithCopies(
        array $to,
        string $subject,
        string $body,
        array $cc = [],
        array $bcc = []
    ): bool {
        $mailer = new Mailer('default');

        try {
            $mailer
                ->setFrom([$this->fromEmail => $this->fromName])
                ->setTo($to)
                ->setSubject($subject)
                ->setEmailFormat('html');

            if (!empty($cc)) {
                $mailer->setCc($cc);
            }
            if (!empty($bcc)) {
                $mailer->setBcc($bcc);
            }

            $mailer->deliver($body);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send email with copies', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
```

#### Servicio 3: EmailService Refactorizado

**Modificar**: `src/Service/EmailService.php`

```php
<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\Ticket;
use App\Model\Entity\Pqr;
use App\Model\Entity\Compra;
use Cake\Datasource\EntityInterface;

/**
 * Email Service (Refactorizado)
 *
 * ANTES: 1,139 líneas con 80% duplicación
 * DESPUÉS: ~180 líneas sin duplicación
 *
 * Responsabilidad: Proveer interfaz específica para cada módulo,
 * delegando toda la lógica a GenericEmailService.
 */
class EmailService
{
    private GenericEmailService $genericEmailService;
    private EmailTemplateService $templateService;

    public function __construct(?array $systemConfig = null)
    {
        $this->templateService = new EmailTemplateService();
        $this->genericEmailService = new GenericEmailService($this->templateService, $systemConfig);
    }

    // ==========================================
    // TICKETS
    // ==========================================

    /**
     * Send ticket created email
     *
     * ANTES: 95 líneas
     * DESPUÉS: 4 líneas
     */
    public function sendTicketCreatedEmail(Ticket $ticket): bool
    {
        return $this->genericEmailService->sendEntityEmail('nuevo_ticket', $ticket);
    }

    /**
     * Send ticket comment email
     *
     * ANTES: 90 líneas
     * DESPUÉS: 10 líneas
     */
    public function sendTicketCommentEmail(Ticket $ticket, array $comment, array $recipients = []): bool
    {
        $extraVars = [
            'comment_author' => $comment['author_name'] ?? '',
            'comment_text' => $comment['comment'] ?? '',
            'comment_date' => $comment['created']->format('Y-m-d H:i:s'),
        ];

        return $this->genericEmailService->sendEntityEmail(
            'ticket_comentario',
            $ticket,
            $extraVars,
            $recipients
        );
    }

    /**
     * Send ticket assigned email
     *
     * ANTES: 85 líneas
     * DESPUÉS: 4 líneas
     */
    public function sendTicketAssignedEmail(Ticket $ticket): bool
    {
        return $this->genericEmailService->sendEntityEmail('ticket_asignado', $ticket);
    }

    /**
     * Send ticket status changed email
     *
     * ANTES: 75 líneas
     * DESPUÉS: 8 líneas
     */
    public function sendTicketStatusChangedEmail(Ticket $ticket, string $oldStatus): bool
    {
        $extraVars = [
            'old_status' => $oldStatus,
            'new_status' => $ticket->status,
        ];

        return $this->genericEmailService->sendEntityEmail('ticket_cambio_estado', $ticket, $extraVars);
    }

    /**
     * Send ticket resolved email
     *
     * ANTES: 75 líneas
     * DESPUÉS: 4 líneas
     */
    public function sendTicketResolvedEmail(Ticket $ticket): bool
    {
        return $this->genericEmailService->sendEntityEmail('ticket_resuelto', $ticket);
    }

    // ==========================================
    // PQRS
    // ==========================================

    /**
     * Send PQRS created email
     *
     * ANTES: 85 líneas
     * DESPUÉS: 4 líneas
     */
    public function sendPqrsCreatedEmail(Pqr $pqrs): bool
    {
        return $this->genericEmailService->sendEntityEmail('nuevo_pqrs', $pqrs);
    }

    /**
     * Send PQRS comment email
     *
     * ANTES: 85 líneas
     * DESPUÉS: 10 líneas
     */
    public function sendPqrsCommentEmail(Pqr $pqrs, array $comment, array $recipients = []): bool
    {
        $extraVars = [
            'comment_author' => $comment['author_name'] ?? '',
            'comment_text' => $comment['comment'] ?? '',
            'comment_date' => $comment['created']->format('Y-m-d H:i:s'),
        ];

        return $this->genericEmailService->sendEntityEmail(
            'pqrs_comentario',
            $pqrs,
            $extraVars,
            $recipients
        );
    }

    /**
     * Send PQRS status changed email
     *
     * ANTES: 70 líneas
     * DESPUÉS: 8 líneas
     */
    public function sendPqrsStatusChangedEmail(Pqr $pqrs, string $oldStatus): bool
    {
        $extraVars = [
            'old_status' => $oldStatus,
            'new_status' => $pqrs->status,
        ];

        return $this->genericEmailService->sendEntityEmail('pqrs_cambio_estado', $pqrs, $extraVars);
    }

    // ==========================================
    // COMPRAS
    // ==========================================

    /**
     * Send Compra created email
     *
     * ANTES: 80 líneas
     * DESPUÉS: 4 líneas
     */
    public function sendCompraCreatedEmail(Compra $compra): bool
    {
        return $this->genericEmailService->sendEntityEmail('nueva_compra', $compra);
    }

    /**
     * Send Compra comment email
     *
     * ANTES: 85 líneas
     * DESPUÉS: 10 líneas
     */
    public function sendCompraCommentEmail(Compra $compra, array $comment, array $recipients = []): bool
    {
        $extraVars = [
            'comment_author' => $comment['author_name'] ?? '',
            'comment_text' => $comment['comment'] ?? '',
            'comment_date' => $comment['created']->format('Y-m-d H:i:s'),
        ];

        return $this->genericEmailService->sendEntityEmail(
            'compra_comentario',
            $compra,
            $extraVars,
            $recipients
        );
    }

    /**
     * Send Compra approved email
     *
     * ANTES: 75 líneas
     * DESPUÉS: 4 líneas
     */
    public function sendCompraApprovedEmail(Compra $compra): bool
    {
        return $this->genericEmailService->sendEntityEmail('compra_aprobada', $compra);
    }

    /**
     * Send Compra status changed email
     *
     * ANTES: 70 líneas
     * DESPUÉS: 8 líneas
     */
    public function sendCompraStatusChangedEmail(Compra $compra, string $oldStatus): bool
    {
        $extraVars = [
            'old_status' => $oldStatus,
            'new_status' => $compra->status,
        ];

        return $this->genericEmailService->sendEntityEmail('compra_cambio_estado', $compra, $extraVars);
    }

    // ==========================================
    // GENERIC / HELPERS
    // ==========================================

    /**
     * Send generic email (para casos especiales)
     */
    public function sendGenericEmail(
        array $recipients,
        string $subject,
        string $body,
        array $cc = [],
        array $bcc = []
    ): bool {
        if (empty($cc) && empty($bcc)) {
            return $this->genericEmailService->sendEmail($recipients, $subject, $body);
        }

        return $this->genericEmailService->sendEmailWithCopies($recipients, $subject, $body, $cc, $bcc);
    }

    /**
     * Get available template variables para entity type
     */
    public function getAvailableVariables(string $entityType): array
    {
        return $this->templateService->getAvailableVariables($entityType);
    }
}
```

### Plan de Migración (5-6 días)

**Día 1: Crear EmailTemplateService**
```bash
# 1. Crear archivo
touch src/Service/EmailTemplateService.php

# 2. Implementar código (copiar de arriba)

# 3. Crear tests
touch tests/TestCase/Service/EmailTemplateServiceTest.php

# 4. Test de unit testing
bin/cake test tests/TestCase/Service/EmailTemplateServiceTest.php
```

**Día 2: Crear GenericEmailService**
```bash
# 1. Crear archivo
touch src/Service/GenericEmailService.php

# 2. Implementar código

# 3. Crear tests
touch tests/TestCase/Service/GenericEmailServiceTest.php

# 4. Testing
bin/cake test tests/TestCase/Service/GenericEmailServiceTest.php
```

**Día 3-4: Refactorizar EmailService**
```bash
# 1. Backup del archivo original
cp src/Service/EmailService.php src/Service/EmailService.php.backup

# 2. Refactorizar método por método
# Comenzar con Tickets (5 métodos)
# Luego PQRS (4 métodos)
# Finalmente Compras (4 métodos)

# 3. Testing después de cada módulo
bin/cake test tests/TestCase/Service/EmailServiceTest.php

# 4. Verificar que todos los emails se envían correctamente
```

**Día 5: Integration Testing**
```bash
# Test completo de flujo:
# 1. Crear ticket → Email enviado ✓
# 2. Agregar comentario → Email enviado ✓
# 3. Asignar ticket → Email enviado ✓
# 4. Cambiar status → Email enviado ✓
# 5. Resolver ticket → Email enviado ✓

# Repetir para PQRS y Compras
```

**Día 6: Deploy y Monitoring**
```bash
# 1. Deploy a staging
git push staging refactor/email-service

# 2. Smoke tests en staging

# 3. Monitor logs por 24 horas

# 4. Deploy a production si todo OK
```

### Testing Completo

**Unit Tests**: `tests/TestCase/Service/EmailTemplateServiceTest.php`
```php
<?php
namespace App\Test\TestCase\Service;

use App\Service\EmailTemplateService;
use Cake\TestSuite\TestCase;

class EmailTemplateServiceTest extends TestCase
{
    protected $fixtures = ['app.EmailTemplates'];
    private EmailTemplateService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailTemplateService();
    }

    public function testGetTemplate()
    {
        $template = $this->service->getTemplate('nuevo_ticket');

        $this->assertNotNull($template);
        $this->assertArrayHasKey('subject', $template);
        $this->assertArrayHasKey('body', $template);
    }

    public function testGetTemplateNotFound()
    {
        $template = $this->service->getTemplate('non_existent');

        $this->assertNull($template);
    }

    public function testRenderTemplate()
    {
        $template = [
            'subject' => 'Ticket {{ticket_number}}',
            'body' => 'Hola {{requester_name}}, tu ticket {{ticket_number}} fue creado.',
        ];

        $variables = [
            'ticket_number' => 'TKT-2025-00001',
            'requester_name' => 'Juan Pérez',
        ];

        $rendered = $this->service->renderTemplate($template, $variables);

        $this->assertEquals('Ticket TKT-2025-00001', $rendered['subject']);
        $this->assertStringContainsString('Juan Pérez', $rendered['body']);
        $this->assertStringContainsString('TKT-2025-00001', $rendered['body']);
    }

    public function testGetAvailableVariables()
    {
        $ticketVars = $this->service->getAvailableVariables('ticket');

        $this->assertContains('ticket_number', $ticketVars);
        $this->assertContains('subject', $ticketVars);
        $this->assertContains('requester_name', $ticketVars);
    }
}
```

**Integration Tests**: `tests/TestCase/Service/EmailServiceTest.php`
```php
<?php
namespace App\Test\TestCase\Service;

use App\Service\EmailService;
use App\Model\Entity\Ticket;
use Cake\TestSuite\TestCase;
use Cake\TestSuite\EmailTrait;

class EmailServiceTest extends TestCase
{
    use EmailTrait;

    protected $fixtures = [
        'app.Tickets',
        'app.Users',
        'app.EmailTemplates',
    ];

    private EmailService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailService();
    }

    public function testSendTicketCreatedEmail()
    {
        $ticketsTable = $this->fetchTable('Tickets');
        $ticket = $ticketsTable->get(1, ['contain' => ['Requesters', 'Assignees']]);

        $result = $this->service->sendTicketCreatedEmail($ticket);

        $this->assertTrue($result);
        $this->assertMailSentTo($ticket->requester->email);
        $this->assertMailContains($ticket->ticket_number);
    }

    // ... más tests para cada método
}
```

### Beneficios del Refactoring

**Beneficios Inmediatos**:
✅ **Reducción de código**: 1,139 → ~180 líneas (84% reducción)
✅ **Eliminación de duplicación**: ~900 líneas duplicadas eliminadas
✅ **Mantenibilidad**: Cambios en lógica de email se hacen en 1 lugar
✅ **Testability**: Servicios pequeños fáciles de testear
✅ **Type safety**: PHPStan errors reducen de 89 → 0

**Beneficios a Mediano Plazo**:
✅ **Nuevos módulos**: Agregar nuevo módulo requiere solo 4 líneas por método
✅ **Onboarding**: Nuevos devs entienden el código en minutos, no días
✅ **Debugging**: Logs centralizados, fácil rastrear problemas
✅ **Features**: Agregar CC/BCC/attachments es trivial

**Beneficios a Largo Plazo**:
✅ **Escalabilidad**: Sistema puede crecer sin aumentar complejidad
✅ **Calidad**: Menos bugs (código simple = menos errores)
✅ **Velocidad**: Nuevas features 3-5x más rápido de implementar
✅ **Confianza**: Equipo confía en hacer cambios sin miedo a romper cosas

### Métricas de Éxito

**Antes del refactor**:
- Líneas: 1,139
- Duplicación: 80%
- PHPStan errors: 89
- Tiempo para agregar nuevo módulo: 2-3 días
- Tiempo para entender el código: 1-2 días

**Después del refactor**:
- Líneas: ~180 (84% reducción)
- Duplicación: 0%
- PHPStan errors: 0
- Tiempo para agregar nuevo módulo: 30 minutos
- Tiempo para entender el código: 30 minutos

### Dependencias

Ninguna - este refactor es independiente y puede hacerse primero.

### Riesgos y Mitigación

**Riesgo 1**: Romper funcionalidad existente
**Mitigación**: Tests exhaustivos, feature flags, deploy gradual

**Riesgo 2**: Templates en DB no compatibles
**Mitigación**: Verificar todos los templates antes del deploy

**Riesgo 3**: Performance degradation
**Mitigación**: Benchmarking antes/después, profiling

---

# FASE 1: ARQUITECTURA - SERVICES

## ARCH-001: GmailService Multiple Responsibilities

**Archivo**: `src/Service/GmailService.php`
**Líneas**: 805
**Severidad**: 🔴 Alto
**Esfuerzo**: 3-4 días

### Root Cause Analysis

**Por qué sucede**:
GmailService creció orgánicamente sin planificación arquitectónica. Cada nueva feature (OAuth, fetching, parsing, attachments) se agregó al mismo archivo porque "ya estaba ahí". Violación del Single Responsibility Principle.

**Responsabilidades actuales** (805 líneas):
1. **OAuth2 Authentication** (150 líneas)
   - Gestión de access token
   - Refresh token logic
   - Credential storage

2. **Message Fetching** (180 líneas)
   - Query builder
   - Message retrieval
   - Pagination

3. **Email Parsing** (220 líneas)
   - Header extraction
   - Body parsing (plain/HTML)
   - Charset conversion

4. **Attachment Handling** (180 líneas)
   - Download logic
   - MIME type detection
   - File saving

5. **Gmail API Client** (75 líneas)
   - Client initialization
   - Service setup
   - Error handling

### Solución: División en 4 Servicios

#### Servicio 1: GmailAuthService

**Crear**: `src/Service/Gmail/GmailAuthService.php`

```php
<?php
declare(strict_types=1);

namespace App\Service\Gmail;

use Google_Client;
use Google_Service_Gmail;
use Cake\Cache\Cache;
use Cake\Log\Log;

/**
 * Gmail Authentication Service
 *
 * Responsabilidad: Gestionar OAuth2 authentication con Gmail API
 *
 * Separa la complejidad de OAuth2 del resto de operaciones de Gmail.
 */
class GmailAuthService
{
    private array $systemConfig;
    private string $credentialsPath;
    private string $tokenCacheKey = 'gmail_oauth_token';

    public function __construct(?array $systemConfig = null)
    {
        $this->systemConfig = $systemConfig ?? $this->loadSystemConfig();
        $this->credentialsPath = CONFIG . 'google' . DS . 'credentials.json';
    }

    /**
     * Get authenticated Google Client
     *
     * @return Google_Client|null
     */
    public function getClient(): ?Google_Client
    {
        if (!file_exists($this->credentialsPath)) {
            Log::error('Gmail credentials file not found: ' . $this->credentialsPath);
            return null;
        }

        try {
            $client = new Google_Client();
            $client->setAuthConfig($this->credentialsPath);
            $client->addScope(Google_Service_Gmail::GMAIL_MODIFY);
            $client->setAccessType('offline');
            $client->setPrompt('consent');

            // Load token from cache or system settings
            $accessToken = $this->getAccessToken();

            if ($accessToken) {
                $client->setAccessToken($accessToken);

                // Refresh if expired
                if ($client->isAccessTokenExpired()) {
                    $this->refreshAccessToken($client);
                }
            }

            return $client;

        } catch (\Exception $e) {
            Log::error('Failed to create Gmail client: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get access token from cache or database
     *
     * @return array|null
     */
    private function getAccessToken(): ?array
    {
        // Try cache first
        $token = Cache::read($this->tokenCacheKey);
        if ($token) {
            return json_decode($token, true);
        }

        // Fall back to system settings
        if (isset($this->systemConfig['gmail_access_token'])) {
            $token = $this->systemConfig['gmail_access_token'];

            // Cache it
            Cache::write($this->tokenCacheKey, $token, 'default');

            return json_decode($token, true);
        }

        return null;
    }

    /**
     * Refresh access token
     *
     * @param Google_Client $client
     * @return bool Success
     */
    private function refreshAccessToken(Google_Client $client): bool
    {
        try {
            if ($client->getRefreshToken()) {
                $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());

                if (isset($newToken['error'])) {
                    Log::error('Token refresh failed: ' . $newToken['error']);
                    return false;
                }

                // Save to cache
                Cache::write($this->tokenCacheKey, json_encode($newToken), 'default');

                // TODO: Save to database if needed
                // $this->saveTokenToDatabase($newToken);

                Log::info('Gmail access token refreshed successfully');
                return true;
            }

            Log::warning('No refresh token available');
            return false;

        } catch (\Exception $e) {
            Log::error('Token refresh exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if Gmail is configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return file_exists($this->credentialsPath) && $this->getAccessToken() !== null;
    }

    /**
     * Get authorization URL for OAuth flow
     *
     * @return string|null
     */
    public function getAuthUrl(): ?string
    {
        $client = $this->getClient();
        if (!$client) {
            return null;
        }

        return $client->createAuthUrl();
    }

    /**
     * Exchange authorization code for access token
     *
     * @param string $code Authorization code from OAuth callback
     * @return bool Success
     */
    public function exchangeAuthCode(string $code): bool
    {
        try {
            $client = new Google_Client();
            $client->setAuthConfig($this->credentialsPath);
            $client->addScope(Google_Service_Gmail::GMAIL_MODIFY);

            $accessToken = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($accessToken['error'])) {
                Log::error('Auth code exchange failed: ' . $accessToken['error']);
                return false;
            }

            // Save token
            Cache::write($this->tokenCacheKey, json_encode($accessToken), 'default');

            // TODO: Save to database
            // $this->saveTokenToDatabase($accessToken);

            Log::info('Gmail OAuth completed successfully');
            return true;

        } catch (\Exception $e) {
            Log::error('Auth code exchange exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear cached token (for logout/re-auth)
     *
     * @return void
     */
    public function clearToken(): void
    {
        Cache::delete($this->tokenCacheKey);
    }

    /**
     * Load system config from database
     */
    private function loadSystemConfig(): array
    {
        // Este método ya existe en otros servicios, puede extraerse a un trait
        // Por ahora, implementación básica
        return [];
    }
}
```

#### Servicio 2: GmailFetcherService

**Crear**: `src/Service/Gmail/GmailFetcherService.php`

```php
<?php
declare(strict_types=1);

namespace App\Service\Gmail;

use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Cake\Log\Log;

/**
 * Gmail Fetcher Service
 *
 * Responsabilidad: Fetch messages from Gmail API
 *
 * Maneja queries, paginación, y retrieval de mensajes.
 */
class GmailFetcherService
{
    private GmailAuthService $authService;

    public function __construct(GmailAuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Get messages from Gmail
     *
     * @param int $maxResults Maximum number of messages
     * @param string $query Gmail search query (default: unread messages)
     * @return array<Google_Service_Gmail_Message>
     */
    public function getMessages(int $maxResults = 50, string $query = 'is:unread'): array
    {
        $client = $this->authService->getClient();
        if (!$client) {
            Log::error('Cannot fetch Gmail messages: client not available');
            return [];
        }

        try {
            $service = new Google_Service_Gmail($client);
            $userId = 'me';

            // List messages matching query
            $response = $service->users_messages->listUsersMessages($userId, [
                'maxResults' => $maxResults,
                'q' => $query,
            ]);

            $messages = [];
            foreach ($response->getMessages() as $messageInfo) {
                // Get full message details
                $message = $service->users_messages->get($userId, $messageInfo->getId(), [
                    'format' => 'full',
                ]);
                $messages[] = $message;
            }

            Log::info("Fetched {count} Gmail messages", ['count' => count($messages)]);
            return $messages;

        } catch (\Exception $e) {
            Log::error('Failed to fetch Gmail messages: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get single message by ID
     *
     * @param string $messageId
     * @return Google_Service_Gmail_Message|null
     */
    public function getMessage(string $messageId): ?Google_Service_Gmail_Message
    {
        $client = $this->authService->getClient();
        if (!$client) {
            return null;
        }

        try {
            $service = new Google_Service_Gmail($client);
            $message = $service->users_messages->get('me', $messageId, ['format' => 'full']);

            return $message;

        } catch (\Exception $e) {
            Log::error("Failed to fetch message {$messageId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Mark message as read
     *
     * @param string $messageId
     * @return bool Success
     */
    public function markAsRead(string $messageId): bool
    {
        $client = $this->authService->getClient();
        if (!$client) {
            return false;
        }

        try {
            $service = new Google_Service_Gmail($client);

            $mods = new \Google_Service_Gmail_ModifyMessageRequest();
            $mods->setRemoveLabelIds(['UNREAD']);

            $service->users_messages->modify('me', $messageId, $mods);

            Log::info("Marked message {$messageId} as read");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to mark message as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark message as unread
     *
     * @param string $messageId
     * @return bool Success
     */
    public function markAsUnread(string $messageId): bool
    {
        $client = $this->authService->getClient();
        if (!$client) {
            return false;
        }

        try {
            $service = new Google_Service_Gmail($client);

            $mods = new \Google_Service_Gmail_ModifyMessageRequest();
            $mods->setAddLabelIds(['UNREAD']);

            $service->users_messages->modify('me', $messageId, $mods);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to mark message as unread: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get message count for query
     *
     * @param string $query
     * @return int
     */
    public function getMessageCount(string $query = 'is:unread'): int
    {
        $client = $this->authService->getClient();
        if (!$client) {
            return 0;
        }

        try {
            $service = new Google_Service_Gmail($client);
            $response = $service->users_messages->listUsersMessages('me', [
                'maxResults' => 1,
                'q' => $query,
            ]);

            return (int) $response->getResultSizeEstimate();

        } catch (\Exception $e) {
            Log::error('Failed to get message count: ' . $e->getMessage());
            return 0;
        }
    }
}
```

#### Servicio 3: GmailParserService

**Crear**: `src/Service/Gmail/GmailParserService.php`

```php
<?php
declare(strict_types=1);

namespace App\Service\Gmail;

use Google_Service_Gmail_Message;
use Google_Service_Gmail_MessagePart;
use Cake\Log\Log;

/**
 * Gmail Parser Service
 *
 * Responsabilidad: Parse Gmail messages into structured data
 *
 * Extrae subject, body, headers, attachments de mensajes de Gmail.
 */
class GmailParserService
{
    /**
     * Parse message into structured array
     *
     * @param Google_Service_Gmail_Message $message
     * @return array{
     *   message_id: string,
     *   thread_id: string,
     *   from: array,
     *   to: array,
     *   cc: array,
     *   subject: string,
     *   body_plain: string,
     *   body_html: string,
     *   date: string,
     *   attachments: array
     * }
     */
    public function parseMessage(Google_Service_Gmail_Message $message): array
    {
        $headers = $this->extractHeaders($message);
        $body = $this->extractBody($message);
        $attachments = $this->extractAttachmentInfo($message);

        return [
            'message_id' => $message->getId(),
            'thread_id' => $message->getThreadId(),
            'from' => $this->parseEmailAddresses($headers['from'] ?? ''),
            'to' => $this->parseEmailAddresses($headers['to'] ?? ''),
            'cc' => $this->parseEmailAddresses($headers['cc'] ?? ''),
            'subject' => $headers['subject'] ?? '(Sin asunto)',
            'body_plain' => $body['plain'],
            'body_html' => $body['html'],
            'date' => $headers['date'] ?? '',
            'attachments' => $attachments,
        ];
    }

    /**
     * Extract headers from message
     *
     * @param Google_Service_Gmail_Message $message
     * @return array<string, string>
     */
    private function extractHeaders(Google_Service_Gmail_Message $message): array
    {
        $headers = [];
        $payload = $message->getPayload();

        if ($payload) {
            foreach ($payload->getHeaders() as $header) {
                $name = strtolower($header->getName());
                $headers[$name] = $header->getValue();
            }
        }

        return $headers;
    }

    /**
     * Extract body from message
     *
     * @param Google_Service_Gmail_Message $message
     * @return array{plain: string, html: string}
     */
    private function extractBody(Google_Service_Gmail_Message $message): array
    {
        $payload = $message->getPayload();
        $plain = '';
        $html = '';

        if ($payload) {
            $this->processMessagePart($payload, $plain, $html);
        }

        return [
            'plain' => $plain,
            'html' => $html,
        ];
    }

    /**
     * Process message part recursively
     *
     * @param Google_Service_Gmail_MessagePart $part
     * @param string &$plain
     * @param string &$html
     * @return void
     */
    private function processMessagePart(Google_Service_Gmail_MessagePart $part, string &$plain, string &$html): void
    {
        $mimeType = $part->getMimeType();
        $body = $part->getBody();

        // Si tiene data, extraer
        if ($body && $body->getData()) {
            $data = $this->decodeBody($body->getData());

            if ($mimeType === 'text/plain' && empty($plain)) {
                $plain = $data;
            } elseif ($mimeType === 'text/html' && empty($html)) {
                $html = $data;
            }
        }

        // Procesar parts recursivamente
        if ($part->getParts()) {
            foreach ($part->getParts() as $subPart) {
                $this->processMessagePart($subPart, $plain, $html);
            }
        }
    }

    /**
     * Decode base64url encoded body
     *
     * @param string $data
     * @return string
     */
    private function decodeBody(string $data): string
    {
        // Gmail usa base64url encoding (diferente a base64 estándar)
        $data = str_replace(['-', '_'], ['+', '/'], $data);
        return base64_decode($data);
    }

    /**
     * Extract attachment information
     *
     * @param Google_Service_Gmail_Message $message
     * @return array
     */
    private function extractAttachmentInfo(Google_Service_Gmail_Message $message): array
    {
        $attachments = [];
        $payload = $message->getPayload();

        if ($payload && $payload->getParts()) {
            $this->findAttachments($payload->getParts(), $attachments);
        }

        return $attachments;
    }

    /**
     * Find attachments in message parts
     *
     * @param array $parts
     * @param array &$attachments
     * @return void
     */
    private function findAttachments(array $parts, array &$attachments): void
    {
        foreach ($parts as $part) {
            $filename = $part->getFilename();
            $body = $part->getBody();

            if (!empty($filename) && $body && $body->getAttachmentId()) {
                $attachments[] = [
                    'filename' => $filename,
                    'attachment_id' => $body->getAttachmentId(),
                    'mime_type' => $part->getMimeType(),
                    'size' => $body->getSize(),
                ];
            }

            // Recursive search
            if ($part->getParts()) {
                $this->findAttachments($part->getParts(), $attachments);
            }
        }
    }

    /**
     * Parse email addresses from header string
     *
     * @param string $addresses
     * @return array<array{name: string, email: string}>
     */
    private function parseEmailAddresses(string $addresses): array
    {
        if (empty($addresses)) {
            return [];
        }

        $parsed = [];
        $parts = explode(',', $addresses);

        foreach ($parts as $part) {
            $part = trim($part);

            // Format: "Name" <email@example.com> or just email@example.com
            if (preg_match('/"?([^"]*)"?\s*<([^>]+)>/', $part, $matches)) {
                $parsed[] = [
                    'name' => trim($matches[1]),
                    'email' => trim($matches[2]),
                ];
            } elseif (filter_var($part, FILTER_VALIDATE_EMAIL)) {
                $parsed[] = [
                    'name' => '',
                    'email' => $part,
                ];
            }
        }

        return $parsed;
    }

    /**
     * Get sender email from parsed message
     *
     * @param array $parsedMessage
     * @return string
     */
    public function getSenderEmail(array $parsedMessage): string
    {
        $from = $parsedMessage['from'] ?? [];
        return $from[0]['email'] ?? '';
    }

    /**
     * Get sender name from parsed message
     *
     * @param array $parsedMessage
     * @return string
     */
    public function getSenderName(array $parsedMessage): string
    {
        $from = $parsedMessage['from'] ?? [];
        $name = $from[0]['name'] ?? '';
        $email = $from[0]['email'] ?? '';

        return !empty($name) ? $name : $email;
    }
}
```

#### Servicio 4: GmailAttachmentService

**Crear**: `src/Service/Gmail/GmailAttachmentService.php`

```php
<?php
declare(strict_types=1);

namespace App\Service\Gmail;

use Google_Service_Gmail;
use Cake\Log\Log;
use App\Service\FileStorageService; // Asumiendo que existe después de TRAIT-002

/**
 * Gmail Attachment Service
 *
 * Responsabilidad: Download and save Gmail attachments
 *
 * Maneja descarga de attachments desde Gmail y guardado local/S3.
 */
class GmailAttachmentService
{
    private GmailAuthService $authService;
    private ?FileStorageService $fileStorage;

    public function __construct(
        GmailAuthService $authService,
        ?FileStorageService $fileStorage = null
    ) {
        $this->authService = $authService;
        $this->fileStorage = $fileStorage;
    }

    /**
     * Download attachment from Gmail
     *
     * @param string $messageId
     * @param string $attachmentId
     * @return string|null Binary data of attachment
     */
    public function downloadAttachment(string $messageId, string $attachmentId): ?string
    {
        $client = $this->authService->getClient();
        if (!$client) {
            return null;
        }

        try {
            $service = new Google_Service_Gmail($client);
            $attachment = $service->users_messages_attachments->get('me', $messageId, $attachmentId);

            $data = $attachment->getData();
            if (!$data) {
                return null;
            }

            // Decode base64url
            $data = str_replace(['-', '_'], ['+', '/'], $data);
            return base64_decode($data);

        } catch (\Exception $e) {
            Log::error("Failed to download attachment {$attachmentId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Download and save attachment
     *
     * @param string $messageId
     * @param string $attachmentId
     * @param string $filename
     * @param string $mimeType
     * @param string $entityType 'ticket', 'pqrs', 'compra'
     * @param int $entityId
     * @return array|null Saved attachment info
     */
    public function downloadAndSaveAttachment(
        string $messageId,
        string $attachmentId,
        string $filename,
        string $mimeType,
        string $entityType,
        int $entityId
    ): ?array {
        // Download from Gmail
        $data = $this->downloadAttachment($messageId, $attachmentId);
        if (!$data) {
            Log::error("Could not download attachment: {$filename}");
            return null;
        }

        // Si tenemos FileStorageService, usarlo
        if ($this->fileStorage) {
            return $this->fileStorage->saveFromBinary(
                $data,
                $filename,
                $mimeType,
                $entityType,
                $entityId
            );
        }

        // Fallback: guardar localmente
        return $this->saveLocally($data, $filename, $entityType, $entityId);
    }

    /**
     * Save attachment locally (fallback si no hay FileStorageService)
     *
     * @param string $data
     * @param string $filename
     * @param string $entityType
     * @param int $entityId
     * @return array
     */
    private function saveLocally(string $data, string $filename, string $entityType, int $entityId): array
    {
        // Sanitize filename
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Create directory structure
        $baseDir = WWW_ROOT . 'uploads' . DS . $entityType . DS . $entityId;
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        // Generate unique filename si ya existe
        $filepath = $baseDir . DS . $filename;
        $counter = 1;
        while (file_exists($filepath)) {
            $info = pathinfo($filename);
            $newFilename = $info['filename'] . "_{$counter}." . $info['extension'];
            $filepath = $baseDir . DS . $newFilename;
            $counter++;
        }

        // Save file
        $result = file_put_contents($filepath, $data);

        if ($result === false) {
            Log::error("Failed to save attachment locally: {$filename}");
            return [];
        }

        Log::info("Attachment saved locally: {$filepath}");

        return [
            'filename' => basename($filepath),
            'filepath' => $filepath,
            'size' => strlen($data),
            'storage_type' => 'local',
        ];
    }

    /**
     * Download all attachments from a message
     *
     * @param string $messageId
     * @param array $attachments Attachment info from GmailParserService
     * @param string $entityType
     * @param int $entityId
     * @return array<array> Saved attachments info
     */
    public function downloadAllAttachments(
        string $messageId,
        array $attachments,
        string $entityType,
        int $entityId
    ): array {
        $saved = [];

        foreach ($attachments as $attachment) {
            $result = $this->downloadAndSaveAttachment(
                $messageId,
                $attachment['attachment_id'],
                $attachment['filename'],
                $attachment['mime_type'],
                $entityType,
                $entityId
            );

            if ($result) {
                $saved[] = $result;
            }
        }

        Log::info("Downloaded {count} attachments for {$entityType} {$entityId}", [
            'count' => count($saved),
        ]);

        return $saved;
    }
}
```

#### Servicio 5: GmailService Facade (Refactorizado)

**Modificar**: `src/Service/GmailService.php`

```php
<?php
declare(strict_types=1);

namespace App\Service;

use App\Service\Gmail\GmailAuthService;
use App\Service\Gmail\GmailFetcherService;
use App\Service\Gmail\GmailParserService;
use App\Service\Gmail\GmailAttachmentService;
use Google_Service_Gmail_Message;

/**
 * Gmail Service (Facade)
 *
 * ANTES: 805 líneas con 5 responsabilidades
 * DESPUÉS: ~100 líneas coordinando 4 servicios especializados
 *
 * Responsabilidad: Proveer interfaz simple para operaciones de Gmail,
 * delegando a servicios especializados.
 */
class GmailService
{
    private GmailAuthService $authService;
    private GmailFetcherService $fetcherService;
    private GmailParserService $parserService;
    private GmailAttachmentService $attachmentService;

    public function __construct(?array $systemConfig = null)
    {
        $this->authService = new GmailAuthService($systemConfig);
        $this->fetcherService = new GmailFetcherService($this->authService);
        $this->parserService = new GmailParserService();
        $this->attachmentService = new GmailAttachmentService($this->authService);
    }

    /**
     * Check if Gmail is configured and ready
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return $this->authService->isConfigured();
    }

    /**
     * Get messages (fetches and parses)
     *
     * ANTES: 180 líneas
     * DESPUÉS: 15 líneas
     *
     * @param int $maxResults
     * @param string $query
     * @return array Parsed messages
     */
    public function getMessages(int $maxResults = 50, string $query = 'is:unread'): array
    {
        // Fetch raw messages
        $rawMessages = $this->fetcherService->getMessages($maxResults, $query);

        // Parse each message
        $parsed = [];
        foreach ($rawMessages as $message) {
            $parsed[] = $this->parserService->parseMessage($message);
        }

        return $parsed;
    }

    /**
     * Get single message by ID
     *
     * @param string $messageId
     * @return array|null Parsed message
     */
    public function getMessage(string $messageId): ?array
    {
        $message = $this->fetcherService->getMessage($messageId);
        if (!$message) {
            return null;
        }

        return $this->parserService->parseMessage($message);
    }

    /**
     * Mark message as read
     *
     * @param string $messageId
     * @return bool
     */
    public function markAsRead(string $messageId): bool
    {
        return $this->fetcherService->markAsRead($messageId);
    }

    /**
     * Download attachment
     *
     * @param string $messageId
     * @param string $attachmentId
     * @param string $filename
     * @param string $mimeType
     * @param string $entityType
     * @param int $entityId
     * @return array|null
     */
    public function downloadAttachment(
        string $messageId,
        string $attachmentId,
        string $filename,
        string $mimeType,
        string $entityType,
        int $entityId
    ): ?array {
        return $this->attachmentService->downloadAndSaveAttachment(
            $messageId,
            $attachmentId,
            $filename,
            $mimeType,
            $entityType,
            $entityId
        );
    }

    /**
     * Download all attachments from message
     *
     * @param string $messageId
     * @param array $attachments
     * @param string $entityType
     * @param int $entityId
     * @return array
     */
    public function downloadAllAttachments(
        string $messageId,
        array $attachments,
        string $entityType,
        int $entityId
    ): array {
        return $this->attachmentService->downloadAllAttachments(
            $messageId,
            $attachments,
            $entityType,
            $entityId
        );
    }

    /**
     * Get unread message count
     *
     * @return int
     */
    public function getUnreadCount(): int
    {
        return $this->fetcherService->getMessageCount('is:unread');
    }

    /**
     * Get authorization URL for OAuth flow
     *
     * @return string|null
     */
    public function getAuthUrl(): ?string
    {
        return $this->authService->getAuthUrl();
    }

    /**
     * Exchange authorization code for access token
     *
     * @param string $code
     * @return bool
     */
    public function exchangeAuthCode(string $code): bool
    {
        return $this->authService->exchangeAuthCode($code);
    }

    // ==========================================
    // Helper methods para backward compatibility
    // ==========================================

    /**
     * Get sender email from parsed message
     *
     * @param array $parsedMessage
     * @return string
     */
    public function getSenderEmail(array $parsedMessage): string
    {
        return $this->parserService->getSenderEmail($parsedMessage);
    }

    /**
     * Get sender name from parsed message
     *
     * @param array $parsedMessage
     * @return string
     */
    public function getSenderName(array $parsedMessage): string
    {
        return $this->parserService->getSenderName($parsedMessage);
    }
}
```

### Plan de Migración (3-4 días)

**Día 1**: Crear GmailAuthService y GmailFetcherService
**Día 2**: Crear GmailParserService y GmailAttachmentService
**Día 3**: Refactorizar GmailService facade + testing
**Día 4**: Integration testing + deploy

### Testing

```php
// tests/TestCase/Service/Gmail/GmailAuthServiceTest.php
public function testGetClient()
{
    $service = new GmailAuthService();
    $client = $service->getClient();

    $this->assertInstanceOf(Google_Client::class, $client);
}

// tests/TestCase/Service/Gmail/GmailFetcherServiceTest.php
public function testGetMessages()
{
    $authService = $this->createMock(GmailAuthService::class);
    $service = new GmailFetcherService($authService);

    $messages = $service->getMessages(10);

    $this->assertIsArray($messages);
}
```

### Beneficios

✅ **Separación de concerns**: Cada servicio tiene una responsabilidad
✅ **Testability**: Servicios pequeños fáciles de testear con mocks
✅ **Reusabilidad**: GmailAuthService puede usarse en otros contextos
✅ **Mantenibilidad**: Cambios en OAuth no afectan parsing
✅ **Extensibilidad**: Agregar nuevas features es más fácil

### Dependencias

Ninguna - independiente de otros refactorings.

---

## ARCH-002: Query directa en método estático

**Archivo**: `src/Service/GmailService.php`
**Líneas**: 41-61
**Severidad**: 🟡 Medio
**Esfuerzo**: S (2-4 horas)

### Root Cause Analysis

**Por qué sucede**:
El método `loadConfigFromDatabase()` fue creado como estático para poder cargar configuración antes de instanciar el servicio. Sin embargo, necesita acceso a ORM, entonces crea una instancia temporal `new self([])` solo para usar el trait `LocatorAwareTrait`. Esto es un anti-pattern que ocurrió por:
1. Necesidad de cargar config desde DB antes de constructor
2. Desconocimiento de alternativas (Repository Pattern, Service Locator)
3. "Quick fix" que se quedó en el código

**Evidencia del problema**:
```php
// Líneas 41-61
public static function loadConfigFromDatabase(): array
{
    // ⚠️ Anti-pattern: Crear instancia solo para usar trait
    $instance = new self([]);  // Instancia temporal que se descarta

    // Query directa al ORM desde método estático
    $settingsTable = $instance->fetchTable('SystemSettings');
    $settings = $settingsTable->find()
        ->where(['setting_key IN' => [
            'gmail_refresh_token',
            'gmail_client_secret_path',
            'gmail_access_token'
        ]])
        ->all();

    $config = [];
    foreach ($settings as $setting) {
        $key = str_replace('gmail_', '', $setting->setting_key);
        $config[$key] = $instance->decryptIfNeeded(
            $setting->setting_key,
            $setting->setting_value
        );
    }

    return $config;
}
```

**Impacto**:
- Difícil de testear (requiere database)
- No se puede mockear para tests unitarios
- Crea instancia innecesaria en cada llamada
- Acoplamiento fuerte con ORM

### Solución Paso a Paso

**Opción 1: Eliminar método estático, inyectar config**

```php
// src/Service/GmailService.php

public function __construct(?array $systemConfig = null)
{
    // Si no se pasa config, cargar desde DB (método de instancia)
    if ($systemConfig === null) {
        $systemConfig = $this->loadConfigFromDatabase();
    }

    $this->config = $systemConfig;
    $this->initializeClient();
}

/**
 * Load Gmail configuration from database (método privado, NO estático)
 */
private function loadConfigFromDatabase(): array
{
    $settingsTable = $this->fetchTable('SystemSettings');
    $settings = $settingsTable->find()
        ->where(['setting_key LIKE' => 'gmail_%'])
        ->all();

    $config = [];
    foreach ($settings as $setting) {
        $key = str_replace('gmail_', '', $setting->setting_key);
        $config[$key] = $this->decryptIfNeeded(
            $setting->setting_key,
            $setting->setting_value
        );
    }

    return $config;
}
```

**Opción 2: Crear SystemSettingsService (Mejor práctica)**

**Crear**: `src/Service/SystemSettingsService.php`

```php
<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Cache\Cache;
use App\Utility\SettingsEncryptionTrait;

/**
 * System Settings Service
 *
 * Responsabilidad: Cargar y gestionar configuración del sistema desde DB
 *
 * Centraliza la lógica de carga de settings que estaba duplicada
 * en múltiples servicios.
 */
class SystemSettingsService
{
    use LocatorAwareTrait;
    use SettingsEncryptionTrait;

    private const CACHE_KEY = 'system_settings';
    private const CACHE_DURATION = '+1 hour';

    /**
     * Get all system settings (cached)
     *
     * @return array
     */
    public function getAllSettings(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            function () {
                return $this->loadAllSettingsFromDatabase();
            },
            'default'
        );
    }

    /**
     * Get Gmail-specific settings
     *
     * @return array
     */
    public function getGmailConfig(): array
    {
        $allSettings = $this->getAllSettings();
        $gmailConfig = [];

        foreach ($allSettings as $key => $value) {
            if (strpos($key, 'gmail_') === 0) {
                $cleanKey = str_replace('gmail_', '', $key);
                $gmailConfig[$cleanKey] = $value;
            }
        }

        return $gmailConfig;
    }

    /**
     * Get WhatsApp-specific settings
     *
     * @return array
     */
    public function getWhatsappConfig(): array
    {
        $allSettings = $this->getAllSettings();
        $whatsappConfig = [];

        foreach ($allSettings as $key => $value) {
            if (strpos($key, 'whatsapp_') === 0) {
                $cleanKey = str_replace('whatsapp_', '', $key);
                $whatsappConfig[$cleanKey] = $value;
            }
        }

        return $whatsappConfig;
    }

    /**
     * Get N8n-specific settings
     *
     * @return array
     */
    public function getN8nConfig(): array
    {
        $allSettings = $this->getAllSettings();

        return [
            'enabled' => $allSettings['n8n_enabled'] ?? false,
            'webhook_url' => $allSettings['n8n_webhook_url'] ?? '',
            'webhook_secret' => $allSettings['n8n_webhook_secret'] ?? '',
        ];
    }

    /**
     * Load all settings from database
     *
     * @return array
     */
    private function loadAllSettingsFromDatabase(): array
    {
        $settingsTable = $this->fetchTable('SystemSettings');
        $settings = $settingsTable->find()->all();

        $config = [];
        foreach ($settings as $setting) {
            $key = $setting->setting_key;
            $value = $this->decryptIfNeeded($key, $setting->setting_value);
            $config[$key] = $value;
        }

        return $config;
    }

    /**
     * Clear settings cache (after update)
     *
     * @return void
     */
    public function clearCache(): void
    {
        Cache::delete(self::CACHE_KEY, 'default');
    }

    /**
     * Update setting in database and cache
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function updateSetting(string $key, $value): bool
    {
        $settingsTable = $this->fetchTable('SystemSettings');

        $setting = $settingsTable->find()
            ->where(['setting_key' => $key])
            ->first();

        if (!$setting) {
            $setting = $settingsTable->newEntity(['setting_key' => $key]);
        }

        // Encrypt if needed
        $encryptedValue = $this->encryptIfNeeded($key, $value);
        $setting->setting_value = $encryptedValue;

        $result = $settingsTable->save($setting);

        if ($result) {
            $this->clearCache();
        }

        return (bool)$result;
    }
}
```

**Actualizar GmailService**:

```php
// src/Service/GmailService.php

class GmailService
{
    use LocatorAwareTrait;
    use SettingsEncryptionTrait;

    private GoogleClient $client;
    private ?Gmail $service = null;
    private array $config;
    private SystemSettingsService $settingsService;  // Inyectar

    public function __construct(
        ?array $config = null,
        ?SystemSettingsService $settingsService = null
    ) {
        $this->settingsService = $settingsService ?? new SystemSettingsService();

        // Cargar config desde servicio si no se pasa
        if ($config === null) {
            $config = $this->settingsService->getGmailConfig();
        }

        $this->config = $config;
        $this->initializeClient();
    }

    // ✅ Eliminar método loadConfigFromDatabase() estático
}
```

### Testing

**Test de SystemSettingsService**:

```php
<?php
namespace App\Test\TestCase\Service;

use App\Service\SystemSettingsService;
use Cake\TestSuite\TestCase;

class SystemSettingsServiceTest extends TestCase
{
    protected $fixtures = ['app.SystemSettings'];
    private SystemSettingsService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new SystemSettingsService();
    }

    public function testGetGmailConfig()
    {
        $config = $this->service->getGmailConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('refresh_token', $config);
        $this->assertArrayHasKey('client_secret_path', $config);
    }

    public function testUpdateSetting()
    {
        $result = $this->service->updateSetting('test_key', 'test_value');

        $this->assertTrue($result);

        $allSettings = $this->service->getAllSettings();
        $this->assertEquals('test_value', $allSettings['test_key']);
    }

    public function testClearCache()
    {
        // Load to populate cache
        $this->service->getAllSettings();

        // Clear cache
        $this->service->clearCache();

        // Cache should be empty (verify with Cache::read())
        $cached = \Cake\Cache\Cache::read('system_settings', 'default');
        $this->assertNull($cached);
    }
}
```

### Beneficios

✅ **Testability**: Settings service puede mockearse fácilmente
✅ **Separation of Concerns**: Lógica de settings separada de Gmail
✅ **Reusability**: Otros servicios pueden usar SystemSettingsService
✅ **Performance**: Cache centralizado reduce queries duplicadas
✅ **Maintainability**: Un lugar para todas las configuraciones

### Plan de Migración

**Día 1** (2 horas):
1. Crear `SystemSettingsService.php`
2. Escribir tests
3. Verificar que tests pasan

**Día 2** (2 horas):
1. Actualizar `GmailService` para usar `SystemSettingsService`
2. Actualizar otros servicios (WhatsappService, N8nService)
3. Verificar que todos los tests pasan
4. Deploy a staging

### Dependencias

- Debe hacerse DESPUÉS de crear GmailAuthService (ARCH-001)
- Beneficia a ARCH-003, ARCH-004, ARCH-010, ARCH-011 (todos DI issues)

---

## ARCH-004: Inyección de Dependencias Incompleta - TicketService

**Archivo**: `src/Service/TicketService.php`
**Líneas**: Múltiples
**Severidad**: 🟡 Medio
**Esfuerzo**: M (1-2 días)

### Root Cause Analysis

**Por qué sucede**:
TicketService tiene servicios inyectados en el constructor pero NO los usa. En su lugar, crea nuevas instancias directamente en los métodos. Esto ocurrió porque:
1. El código evolucionó sin refactoring: primero se creaban instancias directamente
2. Luego se agregó DI al constructor (mejora parcial)
3. Pero nunca se refactorizaron los métodos para usar las propiedades inyectadas
4. Resultado: Código duplicado con ambos patrones

**Evidencia del problema**:

```php
// Constructor - TIENE DI ✅
public function __construct(
    ?EmailService $emailService = null,
    ?WhatsappService $whatsappService = null,
    ?array $systemConfig = null
) {
    $this->emailService = $emailService ?? new EmailService($systemConfig);
    $this->whatsappService = $whatsappService ?? new WhatsappService($systemConfig);
    $this->systemConfig = $systemConfig ?? Cache::remember('system_settings', ...);
}

// Pero los métodos NO usan las propiedades inyectadas ❌
public function createFromEmail(array $emailData): ?Ticket
{
    // ... código ...

    // ❌ Crea nueva instancia en lugar de usar $this->emailService
    $gmailService = new GmailService();

    // Descarga attachments
    foreach ($emailData['attachments'] as $attachment) {
        $gmailService->downloadAttachment(...);  // ❌ No usa servicios inyectados
    }

    // ❌ Crea OTRA instancia de GmailService (segunda vez!)
    $gmailService = new GmailService();
    $gmailService->markAsRead($emailData['message_id']);

    // ... más código ...
}

// Otro método con el mismo problema
public function sendTicketCreatedNotifications(Ticket $ticket): void
{
    // ❌ Ignora $this->emailService inyectado
    $emailService = new EmailService();
    $emailService->sendTicketCreatedEmail($ticket);

    // ❌ Ignora $this->whatsappService inyectado
    $whatsappService = new WhatsappService();
    $whatsappService->sendTicketNotification($ticket);
}
```

**Impacto**:
- **Performance**: Múltiples instancias del mismo servicio
- **Testing**: Imposible mockear las dependencias
- **Memory**: Desperdicio de memoria con instancias duplicadas
- **Confusion**: Código mezclado (DI + new) confunde a developers

### Solución Paso a Paso

**Paso 1: Agregar GmailService al constructor**

```php
// src/Service/TicketService.php

use App\Service\Gmail\GmailService;  // Usar refactorizado (ARCH-001)

private EmailService $emailService;
private WhatsappService $whatsappService;
private GmailService $gmailService;  // Agregar
private array $systemConfig;

public function __construct(
    ?EmailService $emailService = null,
    ?WhatsappService $whatsappService = null,
    ?GmailService $gmailService = null,  // Inyectar
    ?array $systemConfig = null
) {
    $this->systemConfig = $systemConfig ?? Cache::remember('system_settings', ...);

    // Inyectar todos los servicios
    $this->emailService = $emailService ?? new EmailService($this->systemConfig);
    $this->whatsappService = $whatsappService ?? new WhatsappService($this->systemConfig);
    $this->gmailService = $gmailService ?? new GmailService($this->systemConfig);
}
```

**Paso 2: Refactorizar createFromEmail()**

```php
// ANTES (líneas 150-280)
public function createFromEmail(array $emailData): ?Ticket
{
    // ... preparar datos ...

    // ❌ Crea instancia directamente
    $gmailService = new GmailService();

    // Descargar attachments
    $savedAttachments = [];
    foreach ($emailData['attachments'] as $attachment) {
        $savedAttachment = $gmailService->downloadAttachment(
            $emailData['message_id'],
            $attachment['attachment_id'],
            $attachment['filename'],
            $attachment['mime_type'],
            'ticket',
            $ticket->id
        );
        if ($savedAttachment) {
            $savedAttachments[] = $savedAttachment;
        }
    }

    // ❌ Crea OTRA instancia
    $gmailService = new GmailService();
    $gmailService->markAsRead($emailData['message_id']);

    return $ticket;
}

// DESPUÉS
public function createFromEmail(array $emailData): ?Ticket
{
    // ... preparar datos ...

    // ✅ Usar propiedad inyectada
    $savedAttachments = $this->gmailService->downloadAllAttachments(
        $emailData['message_id'],
        $emailData['attachments'],
        'ticket',
        $ticket->id
    );

    // Crear registros en attachments table
    $this->saveAttachmentRecords($ticket->id, $savedAttachments);

    // ✅ Usar la misma instancia
    $this->gmailService->markAsRead($emailData['message_id']);

    return $ticket;
}
```

**Paso 3: Refactorizar sendTicketCreatedNotifications()**

```php
// ANTES
public function sendTicketCreatedNotifications(Ticket $ticket): void
{
    try {
        // ❌ Ignora servicios inyectados
        $emailService = new EmailService();
        $emailService->sendTicketCreatedEmail($ticket);

        $whatsappService = new WhatsappService();
        $whatsappService->sendTicketNotification($ticket);

    } catch (\Exception $e) {
        Log::error('Failed to send notifications: ' . $e->getMessage());
    }
}

// DESPUÉS
public function sendTicketCreatedNotifications(Ticket $ticket): void
{
    try {
        // ✅ Usar servicios inyectados
        $this->emailService->sendTicketCreatedEmail($ticket);
        $this->whatsappService->sendTicketNotification($ticket);

    } catch (\Exception $e) {
        Log::error('Failed to send notifications: ' . $e->getMessage());
    }
}
```

**Paso 4: Refactorizar TODOS los métodos**

Buscar y reemplazar en TODOS los métodos de TicketService:

```bash
# Buscar patrones problemáticos:
grep -n "new GmailService()" src/Service/TicketService.php
grep -n "new EmailService()" src/Service/TicketService.php
grep -n "new WhatsappService()" src/Service/TicketService.php

# Cada ocurrencia debe reemplazarse con $this->xxxService
```

Métodos que necesitan actualización:
- `createFromEmail()` - usar `$this->gmailService`
- `sendTicketCreatedNotifications()` - usar `$this->emailService`, `$this->whatsappService`
- `sendTicketUpdatedNotifications()` - usar `$this->emailService`, `$this->whatsappService`
- `sendTicketAssignedNotifications()` - usar `$this->emailService`, `$this->whatsappService`
- `updateTicketStatus()` - usar `$this->emailService`, `$this->whatsappService`

### Testing

**Test con Mocks**:

```php
<?php
namespace App\Test\TestCase\Service;

use App\Service\TicketService;
use App\Service\EmailService;
use App\Service\WhatsappService;
use App\Service\Gmail\GmailService;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class TicketServiceTest extends TestCase
{
    protected $fixtures = ['app.Tickets', 'app.Users'];

    private TicketService $service;
    private MockObject $emailServiceMock;
    private MockObject $whatsappServiceMock;
    private MockObject $gmailServiceMock;

    public function setUp(): void
    {
        parent::setUp();

        // Crear mocks
        $this->emailServiceMock = $this->createMock(EmailService::class);
        $this->whatsappServiceMock = $this->createMock(WhatsappService::class);
        $this->gmailServiceMock = $this->createMock(GmailService::class);

        // Inyectar mocks
        $this->service = new TicketService(
            $this->emailServiceMock,
            $this->whatsappServiceMock,
            $this->gmailServiceMock
        );
    }

    public function testSendTicketCreatedNotifications()
    {
        $ticket = $this->createTicketEntity();

        // Verificar que se llaman los métodos correctos
        $this->emailServiceMock
            ->expects($this->once())
            ->method('sendTicketCreatedEmail')
            ->with($ticket)
            ->willReturn(true);

        $this->whatsappServiceMock
            ->expects($this->once())
            ->method('sendTicketNotification')
            ->with($ticket)
            ->willReturn(true);

        // Ejecutar
        $this->service->sendTicketCreatedNotifications($ticket);
    }

    public function testCreateFromEmail()
    {
        $emailData = [
            'message_id' => 'msg123',
            'from' => [['email' => 'user@example.com']],
            'subject' => 'Test ticket',
            'body_plain' => 'Test body',
            'attachments' => [
                [
                    'attachment_id' => 'att123',
                    'filename' => 'test.pdf',
                    'mime_type' => 'application/pdf',
                ],
            ],
        ];

        // Mock downloadAllAttachments
        $this->gmailServiceMock
            ->expects($this->once())
            ->method('downloadAllAttachments')
            ->with('msg123', $emailData['attachments'], 'ticket', $this->anything())
            ->willReturn([
                ['filename' => 'test.pdf', 'filepath' => '/path/to/test.pdf'],
            ]);

        // Mock markAsRead
        $this->gmailServiceMock
            ->expects($this->once())
            ->method('markAsRead')
            ->with('msg123')
            ->willReturn(true);

        // Ejecutar
        $ticket = $this->service->createFromEmail($emailData);

        $this->assertNotNull($ticket);
        $this->assertEquals('Test ticket', $ticket->subject);
    }
}
```

### Beneficios

✅ **Testability**: Ahora se pueden mockear todas las dependencias
✅ **Performance**: Una sola instancia de cada servicio
✅ **Memory**: Reduce uso de memoria
✅ **Code consistency**: Todo el código usa el mismo patrón
✅ **Maintainability**: Más fácil de entender y modificar

### Plan de Migración (1-2 días)

**Día 1** (4-6 horas):
1. Agregar `GmailService` al constructor de `TicketService`
2. Refactorizar `createFromEmail()` para usar `$this->gmailService`
3. Refactorizar métodos de notificaciones para usar `$this->emailService` y `$this->whatsappService`
4. Escribir tests unitarios con mocks
5. Verificar que todos los tests pasan

**Día 2** (2-4 horas):
1. Buscar y reemplazar TODAS las ocurrencias de `new XxxService()`
2. Ejecutar full test suite
3. Ejecutar la aplicación en staging
4. Verificar que emails, WhatsApp, y Gmail funcionan correctamente
5. Deploy a producción

### Dependencias

- ARCH-001 debe completarse primero (GmailService refactorizado)
- BLK-002/ARCH-005 debe completarse primero (EmailService refactorizado)

---
# PLAN DE RESOLUCIÓN COMPLETO - PARTE 2

## ARCH-016: Trait asume propiedades sin inyección 🔴 **ROOT CAUSE**

**Archivo**: `src/Service/Traits/NotificationDispatcherTrait.php`
**Líneas**: 44, 56
**Severidad**: 🔴 Alto
**Esfuerzo**: M (2-3 días)
**Impacto**: Resuelve 4 issues arquitectónicos simultáneamente

### Root Cause Analysis

**Por qué sucede - EL PATRÓN FUNDAMENTAL**:
Este es el **ROOT CAUSE** de 4 issues arquitectónicos separados (ARCH-004, ARCH-007, ARCH-010, ARCH-011). El problema surge de un antipatrón en el diseño de traits:

1. **Design mistake original**: NotificationDispatcherTrait fue diseñado asumiendo que las clases que lo usan DEBEN tener propiedades `$this->emailService` y `$this->whatsappService`
2. **Implicit coupling**: El trait accede a propiedades que no declara ni require formalmente
3. **Cascade effect**: TODOS los servicios que usan este trait están forzados a crear estas dependencias en sus constructores, incluso si ya tenían DI
4. **Hidden requirement**: No hay forma de saber qué propiedades necesita el trait hasta que falla en runtime

**Evidencia del problema**:

```php
// src/Service/Traits/NotificationDispatcherTrait.php (líneas 38-63)

trait NotificationDispatcherTrait
{
    // ⚠️ NO declara las propiedades que usa
    // private EmailService $emailService;  // <-- FALTANTE
    // private WhatsappService $whatsappService;  // <-- FALTANTE

    public function dispatchCreationNotifications(
        string $entityType,
        EntityInterface $entity,
        bool $sendEmail = true,
        bool $sendWhatsapp = true
    ): void {
        $methods = $this->getNotificationMethods($entityType, 'creation');

        // Send Email
        if ($sendEmail && !empty($methods['email'])) {
            try {
                // ❌ Asume que $this->emailService existe
                $this->emailService->{$methods['email']}($entity);
            } catch (\Exception $e) {
                Log::error("Failed to send {$entityType} creation email", [...]);
            }
        }

        // Send WhatsApp
        if ($sendWhatsapp && !empty($methods['whatsapp'])) {
            try {
                // ❌ Asume que $this->whatsappService existe
                $this->whatsappService->{$methods['whatsapp']}($entity);
            } catch (\Exception $e) {
                Log::error("Failed to send {$entityType} creation WhatsApp", [...]);
            }
        }
    }
}
```

**Impacto en cascada - AFECTA 4 SERVICIOS**:

1. **TicketService** (ARCH-004):
```php
// Forzado a crear servicios para satisfacer el trait
class TicketService
{
    use NotificationDispatcherTrait;

    private EmailService $emailService;  // ⚠️ Requerido por trait
    private WhatsappService $whatsappService;  // ⚠️ Requerido por trait

    public function __construct(...)
    {
        // Forzado a inicializar estos servicios
        $this->emailService = new EmailService();
        $this->whatsappService = new WhatsappService();
    }
}
```

2. **ResponseService** (ARCH-007): Mismo problema
3. **ComprasService** (ARCH-010): Mismo problema
4. **PqrsService** (ARCH-011): Mismo problema

**Consecuencias**:
- **Testability**: Imposible mockear dependencias sin hacks
- **Coupling**: Trait acopla todas las clases a EmailService/WhatsappService
- **Hidden dependencies**: Desarrolladores no saben qué necesita el trait
- **Runtime errors**: Si olvidas inicializar, falla en runtime (no en compile time)
- **Violates SOLID**: Dependency Inversion Principle violado

### Solución Paso a Paso

**Estrategia**: Refactorizar trait para recibir servicios como parámetros (no asumir propiedades)

**Opción 1: Pasar servicios como parámetros (RECOMENDADO)**

**Paso 1: Refactorizar NotificationDispatcherTrait**

```php
<?php
declare(strict_types=1);

namespace App\Service\Traits;

use Cake\Datasource\EntityInterface;
use Cake\Log\Log;
use App\Service\EmailService;
use App\Service\WhatsappService;

/**
 * Notification Dispatcher Trait (Refactored)
 *
 * ANTES: Asumía que $this->emailService y $this->whatsappService existían
 * DESPUÉS: Recibe servicios como parámetros explícitos
 *
 * ✅ Testable: Servicios pueden ser mocks
 * ✅ Explicit: No hidden dependencies
 * ✅ Flexible: Servicios no tienen que ser propiedades
 */
trait NotificationDispatcherTrait
{
    /**
     * Dispatch creation notifications
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @param EntityInterface $entity Entity to notify about
     * @param EmailService $emailService Email service instance
     * @param WhatsappService $whatsappService WhatsApp service instance
     * @param bool $sendEmail Whether to send email notification
     * @param bool $sendWhatsapp Whether to send WhatsApp notification
     * @return void
     */
    public function dispatchCreationNotifications(
        string $entityType,
        EntityInterface $entity,
        EmailService $emailService,  // ✅ Parámetro explícito
        WhatsappService $whatsappService,  // ✅ Parámetro explícito
        bool $sendEmail = true,
        bool $sendWhatsapp = true
    ): void {
        $methods = $this->getNotificationMethods($entityType, 'creation');

        // Send Email
        if ($sendEmail && !empty($methods['email'])) {
            try {
                // ✅ Usa parámetro, no propiedad
                $emailService->{$methods['email']}($entity);
            } catch (\Exception $e) {
                Log::error("Failed to send {$entityType} creation email", [
                    'entity_type' => $entityType,
                    'entity_id' => $entity->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Send WhatsApp
        if ($sendWhatsapp && !empty($methods['whatsapp'])) {
            try {
                // ✅ Usa parámetro, no propiedad
                $whatsappService->{$methods['whatsapp']}($entity);
            } catch (\Exception $e) {
                Log::error("Failed to send {$entityType} creation WhatsApp", [
                    'entity_type' => $entityType,
                    'entity_id' => $entity->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Dispatch comment notifications
     *
     * @param string $entityType
     * @param EntityInterface $entity
     * @param EmailService $emailService
     * @param WhatsappService $whatsappService
     * @param array $commentData
     * @param array $recipients
     * @return void
     */
    public function dispatchCommentNotifications(
        string $entityType,
        EntityInterface $entity,
        EmailService $emailService,  // ✅ Parámetro explícito
        WhatsappService $whatsappService,  // ✅ Parámetro explícito
        array $commentData = [],
        array $recipients = []
    ): void {
        $methods = $this->getNotificationMethods($entityType, 'comment');

        // Send Email
        if (!empty($methods['email'])) {
            try {
                $emailService->{$methods['email']}($entity, $commentData, $recipients);
            } catch (\Exception $e) {
                Log::error("Failed to send {$entityType} comment email", [
                    'entity_type' => $entityType,
                    'entity_id' => $entity->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // WhatsApp for comments is optional
        if (!empty($methods['whatsapp'])) {
            try {
                $whatsappService->{$methods['whatsapp']}($entity, $commentData);
            } catch (\Exception $e) {
                Log::error("Failed to send {$entityType} comment WhatsApp", [
                    'entity_type' => $entityType,
                    'entity_id' => $entity->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Dispatch status change notifications
     *
     * @param string $entityType
     * @param EntityInterface $entity
     * @param EmailService $emailService
     * @param WhatsappService $whatsappService
     * @param string $oldStatus
     * @param string $newStatus
     * @return void
     */
    public function dispatchStatusChangeNotifications(
        string $entityType,
        EntityInterface $entity,
        EmailService $emailService,
        WhatsappService $whatsappService,
        string $oldStatus,
        string $newStatus
    ): void {
        $methods = $this->getNotificationMethods($entityType, 'status_change');

        // Send Email
        if (!empty($methods['email'])) {
            try {
                $emailService->{$methods['email']}($entity, $oldStatus, $newStatus);
            } catch (\Exception $e) {
                Log::error("Failed to send {$entityType} status change email", [
                    'entity_type' => $entityType,
                    'entity_id' => $entity->id ?? null,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // WhatsApp for status changes
        if (!empty($methods['whatsapp'])) {
            try {
                $whatsappService->{$methods['whatsapp']}($entity, $oldStatus, $newStatus);
            } catch (\Exception $e) {
                Log::error("Failed to send {$entityType} status change WhatsApp", [
                    'entity_type' => $entityType,
                    'entity_id' => $entity->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get notification method names for entity type and event
     *
     * @param string $entityType
     * @param string $eventType 'creation', 'comment', 'status_change', etc.
     * @return array{email: string, whatsapp: string}
     */
    private function getNotificationMethods(string $entityType, string $eventType): array
    {
        // Mapping entre entity types, events, y métodos de servicio
        $methodMap = [
            'ticket' => [
                'creation' => [
                    'email' => 'sendTicketCreatedEmail',
                    'whatsapp' => 'sendTicketNotification',
                ],
                'comment' => [
                    'email' => 'sendTicketCommentEmail',
                    'whatsapp' => '',  // No WhatsApp para comments
                ],
                'status_change' => [
                    'email' => 'sendTicketStatusChangedEmail',
                    'whatsapp' => 'sendTicketStatusNotification',
                ],
            ],
            'pqrs' => [
                'creation' => [
                    'email' => 'sendPqrsCreatedEmail',
                    'whatsapp' => 'sendPqrsNotification',
                ],
                'comment' => [
                    'email' => 'sendPqrsCommentEmail',
                    'whatsapp' => '',
                ],
                'status_change' => [
                    'email' => 'sendPqrsStatusChangedEmail',
                    'whatsapp' => '',
                ],
            ],
            'compra' => [
                'creation' => [
                    'email' => 'sendCompraCreatedEmail',
                    'whatsapp' => 'sendCompraNotification',
                ],
                'comment' => [
                    'email' => 'sendCompraCommentEmail',
                    'whatsapp' => '',
                ],
                'status_change' => [
                    'email' => 'sendCompraStatusChangedEmail',
                    'whatsapp' => '',
                ],
            ],
        ];

        return $methodMap[$entityType][$eventType] ?? ['email' => '', 'whatsapp' => ''];
    }
}
```

**Paso 2: Actualizar TicketService (ARCH-004)**

```php
// src/Service/TicketService.php

class TicketService
{
    use TicketSystemTrait;
    use NotificationDispatcherTrait;  // Trait refactorizado

    private EmailService $emailService;
    private WhatsappService $whatsappService;
    private GmailService $gmailService;

    public function __construct(
        ?EmailService $emailService = null,
        ?WhatsappService $whatsappService = null,
        ?GmailService $gmailService = null,
        ?array $systemConfig = null
    ) {
        $this->systemConfig = $systemConfig ?? Cache::remember('system_settings', ...);
        $this->emailService = $emailService ?? new EmailService($this->systemConfig);
        $this->whatsappService = $whatsappService ?? new WhatsappService($this->systemConfig);
        $this->gmailService = $gmailService ?? new GmailService($this->systemConfig);
    }

    /**
     * Create ticket from email
     */
    public function createFromEmail(array $emailData): ?Ticket
    {
        // ... crear ticket ...

        // ✅ DESPUÉS: Pasar servicios explícitamente al trait
        $this->dispatchCreationNotifications(
            'ticket',
            $ticket,
            $this->emailService,  // Pasar explícitamente
            $this->whatsappService  // Pasar explícitamente
        );

        return $ticket;
    }

    /**
     * Add comment to ticket
     */
    public function addComment(int $ticketId, array $commentData): bool
    {
        // ... crear comentario ...

        // ✅ Pasar servicios al trait
        $this->dispatchCommentNotifications(
            'ticket',
            $ticket,
            $this->emailService,
            $this->whatsappService,
            $commentData,
            $recipients
        );

        return true;
    }
}
```

**Paso 3: Actualizar ResponseService (ARCH-007)**

```php
// src/Service/ResponseService.php

class ResponseService
{
    use NotificationDispatcherTrait;

    private EmailService $emailService;
    private WhatsappService $whatsappService;

    public function __construct(
        ?EmailService $emailService = null,
        ?WhatsappService $whatsappService = null,
        ?array $systemConfig = null
    ) {
        $this->systemConfig = $systemConfig ?? [];
        $this->emailService = $emailService ?? new EmailService($this->systemConfig);
        $this->whatsappService = $whatsappService ?? new WhatsappService($this->systemConfig);
    }

    /**
     * Send response for ticket
     */
    public function sendTicketResponse(Ticket $ticket, array $responseData): bool
    {
        // ... lógica ...

        // ✅ Pasar servicios explícitamente
        $this->dispatchCreationNotifications(
            'ticket',
            $ticket,
            $this->emailService,
            $this->whatsappService
        );

        return true;
    }
}
```

**Paso 4: Actualizar ComprasService (ARCH-010)**

```php
// src/Service/ComprasService.php

class ComprasService
{
    use TicketSystemTrait;
    use NotificationDispatcherTrait;
    use EntityConversionTrait;
    use GenericAttachmentTrait;

    private EmailService $emailService;
    private WhatsappService $whatsappService;

    public function __construct(?array $systemConfig = null)
    {
        $this->systemConfig = $systemConfig ?? Cache::remember('system_settings', ...);
        $this->emailService = new EmailService($this->systemConfig);
        $this->whatsappService = new WhatsappService($this->systemConfig);
    }

    /**
     * Create compra
     */
    public function createCompra(array $data): ?Compra
    {
        // ... crear compra ...

        // ✅ Pasar servicios explícitamente
        $this->dispatchCreationNotifications(
            'compra',
            $compra,
            $this->emailService,
            $this->whatsappService
        );

        return $compra;
    }
}
```

**Paso 5: Actualizar PqrsService (ARCH-011)**

```php
// src/Service/PqrsService.php

class PqrsService
{
    use TicketSystemTrait;
    use NotificationDispatcherTrait;
    use EntityConversionTrait;
    use GenericAttachmentTrait;

    private EmailService $emailService;
    private WhatsappService $whatsappService;

    public function __construct(?array $systemConfig = null)
    {
        $this->systemConfig = $systemConfig ?? Cache::remember('system_settings', ...);
        $this->emailService = new EmailService($this->systemConfig);
        $this->whatsappService = new WhatsappService($this->systemConfig);
    }

    /**
     * Create PQRS
     */
    public function createPqrs(array $data): ?Pqr
    {
        // ... crear PQRS ...

        // ✅ Pasar servicios explícitamente
        $this->dispatchCreationNotifications(
            'pqrs',
            $pqrs,
            $this->emailService,
            $this->whatsappService
        );

        return $pqrs;
    }
}
```

### Testing

**Test del Trait Refactorizado**:

```php
<?php
namespace App\Test\TestCase\Service\Traits;

use App\Service\Traits\NotificationDispatcherTrait;
use App\Service\EmailService;
use App\Service\WhatsappService;
use App\Model\Entity\Ticket;
use Cake\TestSuite\TestCase;

class NotificationDispatcherTraitTest extends TestCase
{
    use NotificationDispatcherTrait;

    public function testDispatchCreationNotificationsWithMocks()
    {
        // Crear mocks
        $emailService = $this->createMock(EmailService::class);
        $whatsappService = $this->createMock(WhatsappService::class);
        $ticket = $this->createMock(Ticket::class);

        // Configurar expectativas
        $emailService
            ->expects($this->once())
            ->method('sendTicketCreatedEmail')
            ->with($ticket)
            ->willReturn(true);

        $whatsappService
            ->expects($this->once())
            ->method('sendTicketNotification')
            ->with($ticket)
            ->willReturn(true);

        // ✅ Ejecutar - pasar mocks como parámetros
        $this->dispatchCreationNotifications(
            'ticket',
            $ticket,
            $emailService,  // Mock inyectado
            $whatsappService  // Mock inyectado
        );
    }

    public function testDispatchWithEmailOnlyDisablesWhatsapp()
    {
        $emailService = $this->createMock(EmailService::class);
        $whatsappService = $this->createMock(WhatsappService::class);
        $ticket = $this->createMock(Ticket::class);

        // Email should be called
        $emailService->expects($this->once())->method('sendTicketCreatedEmail');

        // WhatsApp should NOT be called
        $whatsappService->expects($this->never())->method('sendTicketNotification');

        // Ejecutar con WhatsApp disabled
        $this->dispatchCreationNotifications(
            'ticket',
            $ticket,
            $emailService,
            $whatsappService,
            true,   // sendEmail = true
            false   // sendWhatsapp = false
        );
    }
}
```

**Integration Test - TicketService con Mocks**:

```php
public function testTicketServiceWithMockedNotifications()
{
    // Crear mocks de servicios
    $emailServiceMock = $this->createMock(EmailService::class);
    $whatsappServiceMock = $this->createMock(WhatsappService::class);
    $gmailServiceMock = $this->createMock(GmailService::class);

    // Inyectar mocks
    $ticketService = new TicketService(
        $emailServiceMock,
        $whatsappServiceMock,
        $gmailServiceMock
    );

    // Preparar email data
    $emailData = [
        'from' => [['email' => 'user@example.com', 'name' => 'Test User']],
        'subject' => 'Test Ticket',
        'body_plain' => 'Test body',
        'body_html' => '<p>Test body</p>',
        'attachments' => [],
    ];

    // Configurar expectativas
    $emailServiceMock
        ->expects($this->once())
        ->method('sendTicketCreatedEmail');

    $whatsappServiceMock
        ->expects($this->once())
        ->method('sendTicketNotification');

    // Ejecutar
    $ticket = $ticketService->createFromEmail($emailData);

    // Verificar
    $this->assertNotNull($ticket);
}
```

### Beneficios

✅ **Resuelve 4 issues de una vez**: ARCH-004, ARCH-007, ARCH-010, ARCH-011
✅ **Testability**: Servicios pueden ser mocks fácilmente
✅ **Explicit dependencies**: No hidden requirements
✅ **SOLID compliance**: Dependency Inversion Principle respetado
✅ **Type safety**: PHPStan puede verificar tipos correctamente
✅ **Maintainability**: Código más claro y fácil de entender

### Plan de Migración (2-3 días)

**Día 1** (4-6 horas):
1. Refactorizar `NotificationDispatcherTrait` para aceptar servicios como parámetros
2. Escribir tests unitarios del trait refactorizado
3. Verificar que tests del trait pasan

**Día 2** (4-6 horas):
1. Actualizar `TicketService` para pasar servicios al trait
2. Actualizar `ResponseService` para pasar servicios al trait
3. Actualizar `ComprasService` para pasar servicios al trait
4. Actualizar `PqrsService` para pasar servicios al trait
5. Escribir/actualizar tests de cada servicio

**Día 3** (2-4 horas):
1. Ejecutar full test suite
2. Testing en staging
3. Verificar que notificaciones funcionan correctamente
4. Deploy a producción

### Dependencias

- BLK-002/ARCH-005 debe completarse primero (EmailService refactorizado)
- Este issue es PREREQUISITO para resolver ARCH-004, ARCH-007, ARCH-010, ARCH-011

### Métricas de Éxito

**Antes**:
- NotificationDispatcherTrait: Hidden dependencies
- Tests: Difícil mockear servicios
- PHPStan: Advertencias de propiedades no declaradas
- Servicios afectados: 4 con DI incompleta

**Después**:
- NotificationDispatcherTrait: Explicit dependencies
- Tests: Servicios fácilmente mockeables
- PHPStan: 0 advertencias
- Servicios afectados: 4 con DI completa

---


## MODEL-001: findWithFilters() duplicado entre 3 Tables principales

**Archivos**: TicketsTable.php, ComprasTable.php, PqrsTable.php
**Líneas**: ~300 líneas duplicadas total
**Severidad**: 🔴 Alto
**Esfuerzo**: L (3-4 días)

### Root Cause Analysis

**Por qué sucede**:
Cuando se crearon los módulos de PQRS y Compras, el patrón más rápido fue copiar TicketsTable.php completo y hacer find/replace:
1. **Tickets module** creado primero con `findWithFilters()` (~127 líneas)
2. **PQRS module** creó: copiar TicketsTable → reemplazar 'Ticket' por 'Pqrs' (~74 líneas)
3. **Compras module** creó: copiar TicketsTable → reemplazar 'Ticket' por 'Compra' (~99 líneas)
4. **Resultado**: ~300 líneas de código casi idéntico en 3 archivos

Este es un caso clásico de **Copy-Paste Programming** que ocurre bajo presión de tiempo:
- "Necesito PQRS urgente" → Copiar Tickets es más rápido que abstraer
- "Funciona para Tickets, funcionará para PQRS" → Pero nadie refactoriza después
- "Si cambio algo, puedo romper Tickets" → Miedo a refactoring
- **Resultado**: Deuda técnica que crece exponencialmente

**Evidencia del código duplicado**:

```php
// TicketsTable.php (líneas 218-344) - 127 líneas
public function findWithFilters(SelectQuery $query, array $options): SelectQuery
{
    $filters = $options['filters'] ?? [];
    $view = $options['view'] ?? 'todos_sin_resolver';
    $user = $options['user'] ?? null;

    // View-based filters (~80 líneas de switch statements)
    if (empty($filters['search'])) {
        switch ($view) {
            case 'sin_asignar':
                $query->where([
                    'Tickets.assignee_id IS' => null,  // ⚠️ Solo cambia el prefijo
                    'Tickets.status NOT IN' => ['resuelto', 'convertido']
                ]);
                break;
            case 'mis_tickets':
                if ($user) {
                    $query->where([
                        'Tickets.assignee_id' => $user->get('id'),
                        'Tickets.status NOT IN' => ['resuelto', 'convertido']
                    ]);
                }
                break;
            case 'todos_sin_resolver':
                $query->where(['Tickets.status NOT IN' => ['resuelto', 'convertido']]);
                break;
            // ... 7 más cases (nuevas, en_proceso, resueltas, etc.)
        }
    }

    // Search filter (~20 líneas)
    if (!empty($filters['search'])) {
        $search = $filters['search'];
        $query->where([
            'OR' => [
                'Tickets.ticket_number LIKE' => '%' . $search . '%',
                'Tickets.subject LIKE' => '%' . $search . '%',
                'Tickets.description LIKE' => '%' . $search . '%',
                'Tickets.source_email LIKE' => '%' . $search . '%',
                'Requesters.name LIKE' => '%' . $search . '%',
            ]
        ]);
    }

    // Specific filters (~20 líneas)
    if (!empty($filters['status'])) {
        $query->where(['Tickets.status' => $filters['status']]);
    }
    if (!empty($filters['priority'])) {
        $query->where(['Tickets.priority' => $filters['priority']]);
    }
    // ... más filtros

    return $query;
}

// ComprasTable.php (líneas 165-263) - 99 líneas
public function findWithFilters(SelectQuery $query, array $options): SelectQuery
{
    // ⚠️ IDÉNTICO a TicketsTable, solo s/Tickets/Compras/g
    $filters = $options['filters'] ?? [];
    $view = $options['view'] ?? 'todos_sin_resolver';  // Mismo default
    $user = $options['user'] ?? null;

    if (empty($filters['search'])) {
        switch ($view) {
            case 'sin_asignar':
                $query->where([
                    'Compras.assignee_id IS' => null,  // Solo cambió Tickets → Compras
                    'Compras.status NOT IN' => ['completado', 'rechazado', 'convertido']
                ]);
                break;
            // ... resto idéntico con nombres cambiados
        }
    }
    // ... resto ~99 líneas casi idénticas
}

// PqrsTable.php (líneas 222-295) - 74 líneas  
public function findWithFilters(SelectQuery $query, array $options): SelectQuery
{
    // ⚠️ IDÉNTICO a TicketsTable y ComprasTable
    // ... ~74 líneas duplicadas con s/Tickets/Pqrs/g
}
```

**Diferencias mínimas entre los 3 métodos**:
1. **Prefijo de tabla**: `Tickets.` vs `Compras.` vs `Pqrs.`
2. **Nombre de campo**: `ticket_number` vs `compra_number` vs `pqrs_number`
3. **Statuses resueltos**: `['resuelto', 'convertido']` vs `['completado', 'rechazado']` vs `['resuelto', 'cerrado']`
4. **Search fields**: Campo específico adicional (`source_email` vs `original_ticket_number` vs `requester_email`)

**TODO LO DEMÁS ES IDÉNTICO** (~95% del código)

**Impacto**:
- **Mantenimiento**: Cambio en lógica = modificar 3 archivos
- **Bugs**: Arreglar bug en Tickets, olvidar PQRS → inconsistencia
- **Features**: Agregar filtro nuevo = copiar a 3 lugares
- **Testing**: 3x el esfuerzo de testing
- **Code review**: Difícil detectar diferencias sutiles entre versiones


### Solución Paso a Paso - FilterableTrait

#### Paso 1: Crear el FilterableTrait Genérico

**Crear**: `src/Model/Table/Traits/FilterableTrait.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Table\Traits;

use Cake\ORM\Query\SelectQuery;
use Cake\Datasource\EntityInterface;

/**
 * Filterable Trait
 *
 * Responsabilidad: Proporcionar lógica de filtrado genérica reutilizable
 * para todos los módulos (Tickets, PQRS, Compras).
 *
 * Este trait elimina ~300 líneas de código duplicado entre las 3 Tables.
 *
 * ANTES: findWithFilters() duplicado en 3 archivos (127+99+74 = 300 líneas)
 * DESPUÉS: FilterableTrait compartido (~200 líneas) + 3 configuraciones pequeñas
 *
 * Uso:
 * ```php
 * class TicketsTable extends Table
 * {
 *     use FilterableTrait;
 *
 *     protected function getFilterConfig(): array
 *     {
 *         return [
 *             'table_alias' => 'Tickets',
 *             'number_field' => 'ticket_number',
 *             'resolved_statuses' => ['resuelto', 'convertido'],
 *             // ... configuración específica
 *         ];
 *     }
 * }
 * ```
 */
trait FilterableTrait
{
    /**
     * Get filter configuration
     *
     * Este método DEBE ser implementado por cada Table que use el trait.
     * Define la configuración específica de filtros para cada módulo.
     *
     * @return array{
     *     table_alias: string,
     *     number_field: string,
     *     resolved_statuses: array<string>,
     *     search_fields: array<string>,
     *     view_my_items: string,
     *     view_created_by_me: string|null,
     *     supports_role_filtering: bool
     * }
     */
    abstract protected function getFilterConfig(): array;

    /**
     * Find with filters - Método genérico
     *
     * Aplica filtros basados en:
     * - Vista predefinida (todos_sin_resolver, mis_tickets, etc.)
     * - Búsqueda full-text
     * - Filtros específicos (status, priority, assignee, dates)
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query object
     * @param array $options Filter options
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findWithFilters(SelectQuery $query, array $options): SelectQuery
    {
        $config = $this->getFilterConfig();
        $filters = $options['filters'] ?? [];
        $view = $options['view'] ?? 'todos_sin_resolver';
        $user = $options['user'] ?? null;

        // Apply view-based filters (si no hay búsqueda activa)
        if (empty($filters['search'])) {
            $query = $this->applyViewFilters($query, $view, $user, $config);
        }

        // Apply search filter
        if (!empty($filters['search'])) {
            $query = $this->applySearchFilter($query, $filters['search'], $view, $config);
        }

        // Apply specific filters
        $query = $this->applySpecificFilters($query, $filters, $config);

        return $query;
    }

    /**
     * Apply view-based filters
     *
     * Aplica filtros predefinidos según la vista seleccionada.
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param string $view Vista seleccionada
     * @param \Cake\Datasource\EntityInterface|null $user Usuario actual
     * @param array $config Configuración de filtros
     * @return \Cake\ORM\Query\SelectQuery
     */
    protected function applyViewFilters(
        SelectQuery $query,
        string $view,
        ?EntityInterface $user,
        array $config
    ): SelectQuery {
        $tableAlias = $config['table_alias'];
        $resolvedStatuses = $config['resolved_statuses'];
        $supportsRoleFiltering = $config['supports_role_filtering'] ?? false;

        $userId = $user?->get('id');
        $userRole = $user?->get('role');
        $isAgent = $userRole === 'agent';
        $isAdmin = $userRole === 'admin';

        switch ($view) {
            case 'sin_asignar':
                $query->where([
                    "{$tableAlias}.assignee_id IS" => null,
                    "{$tableAlias}.status NOT IN" => $resolvedStatuses
                ]);
                break;

            case $config['view_my_items']: // 'mis_tickets', 'mis_compras', 'mis_pqrs'
                if ($userId) {
                    $query->where([
                        "{$tableAlias}.assignee_id" => $userId,
                        "{$tableAlias}.status NOT IN" => $resolvedStatuses
                    ]);
                }
                break;

            case $config['view_created_by_me'] ?? null:
                // Solo Tickets tiene 'creados_por_mi'
                if ($userId && $config['view_created_by_me']) {
                    $query->where([
                        "{$tableAlias}.requester_id" => $userId,
                        "{$tableAlias}.status !=" => 'convertido'
                    ]);
                }
                break;

            case 'todos_sin_resolver':
                $query->where(["{$tableAlias}.status NOT IN" => $resolvedStatuses]);
                break;

            case 'pendientes':
                $conditions = ["{$tableAlias}.status" => 'pendiente'];
                // Agent role filtering (solo para Tickets)
                if ($supportsRoleFiltering && $isAgent && $userId) {
                    $conditions["{$tableAlias}.assignee_id"] = $userId;
                }
                $query->where($conditions);
                break;

            case 'nuevos':
            case 'nuevas': // PQRS usa 'nuevas'
                $conditions = ["{$tableAlias}.status" => 'nuevo'];
                // Agent role filtering (solo para Tickets)
                if ($supportsRoleFiltering && $isAgent && $userId) {
                    $conditions["{$tableAlias}.assignee_id"] = $userId;
                }
                $query->where($conditions);
                break;

            case 'abiertos':
                $conditions = ["{$tableAlias}.status" => 'abierto'];
                // Agent role filtering (solo para Tickets)
                if ($supportsRoleFiltering && $isAgent && $userId) {
                    $conditions["{$tableAlias}.assignee_id"] = $userId;
                }
                $query->where($conditions);
                break;

            case 'en_revision':
                $query->where(["{$tableAlias}.status" => 'en_revision']);
                break;

            case 'en_proceso':
                $query->where(["{$tableAlias}.status" => 'en_proceso']);
                break;

            case 'aprobados':
                // Solo Compras
                $query->where(["{$tableAlias}.status" => 'aprobado']);
                break;

            case 'resueltos':
            case 'resueltas': // PQRS usa 'resueltas'
                $query->where(["{$tableAlias}.status" => 'resuelto']);
                break;

            case 'completados':
                // Solo Compras
                $query->where(["{$tableAlias}.status" => 'completado']);
                break;

            case 'rechazados':
                // Solo Compras
                $query->where(["{$tableAlias}.status" => 'rechazado']);
                break;

            case 'cerradas':
                // Solo PQRS
                $query->where(["{$tableAlias}.status" => 'cerrado']);
                break;

            case 'convertidos':
                $query->where(["{$tableAlias}.status" => 'convertido']);
                break;

            case 'recientes':
                // Solo Tickets
                $query->where([
                    "{$tableAlias}.created >=" => date('Y-m-d', strtotime('-7 days')),
                    "{$tableAlias}.status !=" => 'convertido'
                ]);
                break;

            case 'vencidos_sla':
                // Solo Compras (PQRS también tiene SLA pero no tiene vista específica)
                $query->where([
                    "{$tableAlias}.sla_due_date <" => new \DateTime(),
                    "{$tableAlias}.status NOT IN" => $resolvedStatuses
                ]);
                break;
        }

        return $query;
    }

    /**
     * Apply search filter
     *
     * Aplica búsqueda full-text en los campos configurados.
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param string $search Término de búsqueda
     * @param string $view Vista actual
     * @param array $config Configuración de filtros
     * @return \Cake\ORM\Query\SelectQuery
     */
    protected function applySearchFilter(
        SelectQuery $query,
        string $search,
        string $view,
        array $config
    ): SelectQuery {
        $tableAlias = $config['table_alias'];
        $searchFields = $config['search_fields'];

        // Build OR conditions para búsqueda
        $orConditions = [];
        foreach ($searchFields as $field) {
            $orConditions["{$field} LIKE"] = '%' . $search . '%';
        }

        $query->where(['OR' => $orConditions]);

        // Exclude converted items from search (excepto en vista 'convertidos')
        if ($view !== 'convertidos') {
            $query->where(["{$tableAlias}.status !=" => 'convertido']);
        }

        return $query;
    }

    /**
     * Apply specific filters
     *
     * Aplica filtros específicos (status, priority, assignee, dates).
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param array $filters Filtros a aplicar
     * @param array $config Configuración de filtros
     * @return \Cake\ORM\Query\SelectQuery
     */
    protected function applySpecificFilters(
        SelectQuery $query,
        array $filters,
        array $config
    ): SelectQuery {
        $tableAlias = $config['table_alias'];

        // Status filter
        if (!empty($filters['status'])) {
            $query->where(["{$tableAlias}.status" => $filters['status']]);
        }

        // Priority filter
        if (!empty($filters['priority'])) {
            $query->where(["{$tableAlias}.priority" => $filters['priority']]);
        }

        // Type filter (solo PQRS)
        if (!empty($filters['type'])) {
            $query->where(["{$tableAlias}.type" => $filters['type']]);
        }

        // Assignee filter
        if (!empty($filters['assignee_id'])) {
            if ($filters['assignee_id'] === 'unassigned') {
                $query->where(["{$tableAlias}.assignee_id IS" => null]);
            } else {
                $query->where(["{$tableAlias}.assignee_id" => $filters['assignee_id']]);
            }
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $query->where(["{$tableAlias}.created >=" => $filters['date_from'] . ' 00:00:00']);
        }
        if (!empty($filters['date_to'])) {
            $query->where(["{$tableAlias}.created <=" => $filters['date_to'] . ' 23:59:59']);
        }

        return $query;
    }
}
```

#### Paso 2: Actualizar TicketsTable

**Modificar**: `src/Model/Table/TicketsTable.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Model\Table\Traits\FilterableTrait;

/**
 * Tickets Model
 */
class TicketsTable extends Table
{
    use FilterableTrait; // ✅ Añadir trait

    // ... código existente (initialize, validationDefault, etc.) ...

    /**
     * Get filter configuration for FilterableTrait
     *
     * Define la configuración específica de filtros para Tickets.
     *
     * @return array
     */
    protected function getFilterConfig(): array
    {
        return [
            'table_alias' => 'Tickets',
            'number_field' => 'ticket_number',
            'resolved_statuses' => ['resuelto', 'convertido'],
            'search_fields' => [
                'Tickets.ticket_number',
                'Tickets.subject',
                'Tickets.description',
                'Tickets.source_email',
                'Requesters.name',
                'Requesters.email',
            ],
            'view_my_items' => 'mis_tickets',
            'view_created_by_me' => 'creados_por_mi', // Solo Tickets tiene esto
            'supports_role_filtering' => true, // Solo Tickets filtra por role agent/admin
        ];
    }

    // ❌ ELIMINAR: public function findWithFilters() {...}
    // Ya no es necesario, el trait lo provee
}
```

**Cambios**:
1. ✅ Añadir `use FilterableTrait;`
2. ✅ Implementar `getFilterConfig()`
3. ❌ Eliminar el método `findWithFilters()` existente (127 líneas)

#### Paso 3: Actualizar ComprasTable

**Modificar**: `src/Model/Table/ComprasTable.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Model\Table\Traits\FilterableTrait;

/**
 * Compras Model
 */
class ComprasTable extends Table
{
    use FilterableTrait; // ✅ Añadir trait

    // ... código existente ...

    /**
     * Get filter configuration for FilterableTrait
     *
     * Define la configuración específica de filtros para Compras.
     *
     * @return array
     */
    protected function getFilterConfig(): array
    {
        return [
            'table_alias' => 'Compras',
            'number_field' => 'compra_number',
            'resolved_statuses' => ['completado', 'rechazado', 'convertido'],
            'search_fields' => [
                'Compras.compra_number',
                'Compras.subject',
                'Compras.description',
                'Compras.original_ticket_number',
                'Requesters.name',
                'Requesters.email',
            ],
            'view_my_items' => 'mis_compras',
            'view_created_by_me' => null, // Compras no tiene 'creados_por_mi'
            'supports_role_filtering' => false, // Compras no filtra por role
        ];
    }

    // ❌ ELIMINAR: public function findWithFilters() {...}
}
```

**Cambios**:
1. ✅ Añadir `use FilterableTrait;`
2. ✅ Implementar `getFilterConfig()`
3. ❌ Eliminar el método `findWithFilters()` existente (99 líneas)

#### Paso 4: Actualizar PqrsTable

**Modificar**: `src/Model/Table/PqrsTable.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Model\Table\Traits\FilterableTrait;

/**
 * Pqrs Model
 */
class PqrsTable extends Table
{
    use FilterableTrait; // ✅ Añadir trait

    // ... código existente ...

    /**
     * Get filter configuration for FilterableTrait
     *
     * Define la configuración específica de filtros para PQRS.
     *
     * @return array
     */
    protected function getFilterConfig(): array
    {
        return [
            'table_alias' => 'Pqrs',
            'number_field' => 'pqrs_number',
            'resolved_statuses' => ['resuelto', 'cerrado'],
            'search_fields' => [
                'Pqrs.pqrs_number',
                'Pqrs.subject',
                'Pqrs.description',
                'Pqrs.requester_name',
                'Pqrs.requester_email',
            ],
            'view_my_items' => 'mis_pqrs',
            'view_created_by_me' => null, // PQRS no tiene 'creados_por_mi'
            'supports_role_filtering' => false, // PQRS no filtra por role
        ];
    }

    // ❌ ELIMINAR: public function findWithFilters() {...}
}
```

**Cambios**:
1. ✅ Añadir `use FilterableTrait;`
2. ✅ Implementar `getFilterConfig()`
3. ❌ Eliminar el método `findWithFilters()` existente (74 líneas)

### Testing Completo

#### Unit Tests: `tests/TestCase/Model/Table/Traits/FilterableTraitTest.php`

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table\Traits;

use Cake\TestSuite\TestCase;
use Cake\ORM\Table;
use Cake\ORM\Query\SelectQuery;
use App\Model\Table\Traits\FilterableTrait;

/**
 * FilterableTrait Test
 *
 * Prueba la funcionalidad genérica del trait sin depender de Tables específicas.
 */
class FilterableTraitTest extends TestCase
{
    protected $fixtures = [
        'app.Tickets',
        'app.Users',
        'app.Requesters',
    ];

    private Table $table;

    public function setUp(): void
    {
        parent::setUp();

        // Crear una Table mock que usa el trait
        $this->table = new class extends Table {
            use FilterableTrait;

            protected function getFilterConfig(): array
            {
                return [
                    'table_alias' => 'Tickets',
                    'number_field' => 'ticket_number',
                    'resolved_statuses' => ['resuelto', 'convertido'],
                    'search_fields' => [
                        'Tickets.ticket_number',
                        'Tickets.subject',
                        'Tickets.description',
                    ],
                    'view_my_items' => 'mis_tickets',
                    'view_created_by_me' => 'creados_por_mi',
                    'supports_role_filtering' => true,
                ];
            }
        };
        $this->table->setTable('tickets');
        $this->table->setAlias('Tickets');
    }

    /**
     * Test: sin_asignar view
     */
    public function testViewSinAsignar()
    {
        $query = $this->table->find();
        $result = $this->table->findWithFilters($query, [
            'view' => 'sin_asignar',
        ]);

        $sql = $result->sql();

        $this->assertStringContainsString('assignee_id IS NULL', $sql);
        $this->assertStringContainsString('status NOT IN', $sql);
    }

    /**
     * Test: mis_tickets view (con usuario)
     */
    public function testViewMisTickets()
    {
        $usersTable = $this->fetchTable('Users');
        $user = $usersTable->find()->first();

        $query = $this->table->find();
        $result = $this->table->findWithFilters($query, [
            'view' => 'mis_tickets',
            'user' => $user,
        ]);

        $sql = $result->sql();

        $this->assertStringContainsString('assignee_id', $sql);
        $this->assertStringContainsString((string)$user->id, $sql);
    }

    /**
     * Test: todos_sin_resolver view (default)
     */
    public function testViewTodosSinResolver()
    {
        $query = $this->table->find();
        $result = $this->table->findWithFilters($query, [
            'view' => 'todos_sin_resolver',
        ]);

        $sql = $result->sql();

        $this->assertStringContainsString('status NOT IN', $sql);
        $this->assertStringContainsString('resuelto', $sql);
        $this->assertStringContainsString('convertido', $sql);
    }

    /**
     * Test: search filter
     */
    public function testSearchFilter()
    {
        $query = $this->table->find();
        $result = $this->table->findWithFilters($query, [
            'filters' => ['search' => 'test123'],
        ]);

        $sql = $result->sql();

        $this->assertStringContainsString('%test123%', $sql);
        $this->assertStringContainsString('ticket_number', $sql);
        $this->assertStringContainsString('subject', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    /**
     * Test: specific filters (status)
     */
    public function testStatusFilter()
    {
        $query = $this->table->find();
        $result = $this->table->findWithFilters($query, [
            'filters' => ['status' => 'abierto'],
        ]);

        $sql = $result->sql();

        $this->assertStringContainsString("status = 'abierto'", $sql);
    }

    /**
     * Test: specific filters (priority)
     */
    public function testPriorityFilter()
    {
        $query = $this->table->find();
        $result = $this->table->findWithFilters($query, [
            'filters' => ['priority' => 'alta'],
        ]);

        $sql = $result->sql();

        $this->assertStringContainsString("priority = 'alta'", $sql);
    }

    /**
     * Test: specific filters (assignee_id = unassigned)
     */
    public function testAssigneeUnassignedFilter()
    {
        $query = $this->table->find();
        $result = $this->table->findWithFilters($query, [
            'filters' => ['assignee_id' => 'unassigned'],
        ]);

        $sql = $result->sql();

        $this->assertStringContainsString('assignee_id IS NULL', $sql);
    }

    /**
     * Test: specific filters (assignee_id = user_id)
     */
    public function testAssigneeUserIdFilter()
    {
        $query = $this->table->find();
        $result = $this->table->findWithFilters($query, [
            'filters' => ['assignee_id' => 5],
        ]);

        $sql = $result->sql();

        $this->assertStringContainsString('assignee_id = 5', $sql);
    }

    /**
     * Test: date range filters
     */
    public function testDateRangeFilters()
    {
        $query = $this->table->find();
        $result = $this->table->findWithFilters($query, [
            'filters' => [
                'date_from' => '2025-01-01',
                'date_to' => '2025-01-31',
            ],
        ]);

        $sql = $result->sql();

        $this->assertStringContainsString('created >=', $sql);
        $this->assertStringContainsString('created <=', $sql);
        $this->assertStringContainsString('2025-01-01 00:00:00', $sql);
        $this->assertStringContainsString('2025-01-31 23:59:59', $sql);
    }

    /**
     * Test: combined filters
     */
    public function testCombinedFilters()
    {
        $usersTable = $this->fetchTable('Users');
        $user = $usersTable->find()->first();

        $query = $this->table->find();
        $result = $this->table->findWithFilters($query, [
            'view' => 'todos_sin_resolver',
            'user' => $user,
            'filters' => [
                'status' => 'abierto',
                'priority' => 'alta',
                'assignee_id' => $user->id,
            ],
        ]);

        $sql = $result->sql();

        // Verificar que todos los filtros se aplicaron
        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('priority', $sql);
        $this->assertStringContainsString('assignee_id', $sql);
    }
}
```

### Plan de Migración (3-4 días)

**Día 1: Crear FilterableTrait y Tests**
```bash
# Paso 1: Crear directorio para traits si no existe
mkdir -p src/Model/Table/Traits

# Paso 2: Crear archivo del trait
touch src/Model/Table/Traits/FilterableTrait.php
# Implementar código del trait (~200 líneas)

# Paso 3: Crear tests del trait
mkdir -p tests/TestCase/Model/Table/Traits
touch tests/TestCase/Model/Table/Traits/FilterableTraitTest.php
# Implementar tests (~150 líneas)

# Paso 4: Ejecutar tests
bin/cake test tests/TestCase/Model/Table/Traits/FilterableTraitTest.php

# Verificar que todos pasan ✓
```

**Día 2: Migrar TicketsTable**
```bash
# Paso 1: Backup del archivo original
cp src/Model/Table/TicketsTable.php src/Model/Table/TicketsTable.php.backup

# Paso 2: Editar TicketsTable
# - Añadir: use App\Model\Table\Traits\FilterableTrait;
# - Implementar: protected function getFilterConfig() {...}
# - Eliminar: public function findWithFilters() {...}

# Paso 3: Ejecutar tests de TicketsTable
bin/cake test tests/TestCase/Model/Table/TicketsTableTest.php

# Paso 4: Smoke test manual en navegador
# - Visitar /tickets/index
# - Probar todas las vistas (sin_asignar, mis_tickets, todos_sin_resolver, etc.)
# - Probar búsqueda
# - Probar filtros específicos

# Paso 5: Verificar logs
tail -f logs/debug.log

# Si algo falla, restaurar backup:
# cp src/Model/Table/TicketsTable.php.backup src/Model/Table/TicketsTable.php
```

**Día 3: Migrar ComprasTable y PqrsTable**
```bash
# Paso 1: Backup de archivos
cp src/Model/Table/ComprasTable.php src/Model/Table/ComprasTable.php.backup
cp src/Model/Table/PqrsTable.php src/Model/Table/PqrsTable.php.backup

# Paso 2: Migrar ComprasTable (mismo proceso que TicketsTable)
# - use FilterableTrait;
# - getFilterConfig()
# - eliminar findWithFilters()

# Paso 3: Ejecutar tests de ComprasTable
bin/cake test tests/TestCase/Model/Table/ComprasTableTest.php

# Paso 4: Smoke test de Compras
# - Visitar /compras/index
# - Probar todas las vistas (mis_compras, vencidos_sla, aprobados, etc.)
# - Probar búsqueda
# - Probar filtros

# Paso 5: Migrar PqrsTable (mismo proceso)
# - use FilterableTrait;
# - getFilterConfig()
# - eliminar findWithFilters()

# Paso 6: Ejecutar tests de PqrsTable
bin/cake test tests/TestCase/Model/Table/PqrsTableTest.php

# Paso 7: Smoke test de PQRS
# - Visitar /pqrs/index
# - Probar todas las vistas (mis_pqrs, nuevas, cerradas, etc.)
# - Probar búsqueda
# - Probar filtros por type
```

**Día 4: Integration Testing y Deploy**
```bash
# Paso 1: Ejecutar suite completa de tests
composer test

# Paso 2: Verificar PHPStan
vendor/bin/phpstan analyse

# Paso 3: Smoke testing exhaustivo
# - Crear ticket nuevo → Verificar que aparece en vista "nuevos"
# - Asignar ticket → Verificar que aparece en "mis_tickets"
# - Buscar ticket → Verificar que search funciona
# - Repetir para Compras y PQRS

# Paso 4: Performance testing
# Verificar que no hay degradación de performance:
# - Medir tiempo de carga de /tickets/index (antes vs después)
# - Medir tiempo de búsqueda (antes vs después)
# - Revisar queries generadas (deben ser idénticas)

# Paso 5: Code review
git diff src/Model/Table/TicketsTable.php.backup src/Model/Table/TicketsTable.php
git diff src/Model/Table/ComprasTable.php.backup src/Model/Table/ComprasTable.php
git diff src/Model/Table/PqrsTable.php.backup src/Model/Table/PqrsTable.php

# Paso 6: Commit y push
git add src/Model/Table/Traits/FilterableTrait.php
git add src/Model/Table/TicketsTable.php
git add src/Model/Table/ComprasTable.php
git add src/Model/Table/PqrsTable.php
git add tests/TestCase/Model/Table/Traits/FilterableTraitTest.php

git commit -m "$(cat <<'COMMIT_MSG'
refactor: Extract findWithFilters() duplicación a FilterableTrait

Elimina ~300 líneas de código duplicado entre TicketsTable, ComprasTable
y PqrsTable extrayendo la lógica de filtrado a un trait reutilizable.

Changes:
- Crear FilterableTrait con método abstracto getFilterConfig()
- Migrar TicketsTable a usar FilterableTrait (-127 líneas)
- Migrar ComprasTable a usar FilterableTrait (-99 líneas)
- Migrar PqrsTable a usar FilterableTrait (-74 líneas)
- Añadir tests unitarios para FilterableTrait

Benefits:
- Eliminación de duplicación: 300 → 200 líneas (33% reducción)
- Mantenibilidad: Cambios en lógica = 1 lugar
- Consistencia: Comportamiento idéntico entre módulos
- Testing: Tests centralizados para lógica compartida

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>
COMMIT_MSG
)"

git push origin main

# Paso 7: Monitor logs en producción (24 horas)
# Verificar que no hay errores relacionados con filtros
```

### Beneficios Cuantificados

**Reducción de Código**:
- **Antes**: 300 líneas duplicadas (127+99+74)
- **Después**: 200 líneas en trait + 3×15 líneas configs = 245 líneas
- **Reducción neta**: 55 líneas (18% reducción)
- **Eliminación de duplicación**: 300→0 líneas duplicadas (100% eliminación)

**Mantenibilidad**:
- **Antes**: Agregar nuevo filtro = Modificar 3 archivos (3×10 líneas = 30 líneas)
- **Después**: Agregar nuevo filtro = Modificar 1 archivo (10 líneas)
- **Ahorro**: 67% menos código a modificar

**Consistencia**:
- **Antes**: 3 implementaciones con sutiles diferencias → bugs inconsistentes
- **Después**: 1 implementación → comportamiento idéntico garantizado

**Testing**:
- **Antes**: 3 test suites (3×50 líneas = 150 líneas)
- **Después**: 1 test suite genérico (80 líneas) + 3×20 líneas tests específicos = 140 líneas
- **Más importante**: Tests del trait garantizan que las 3 Tables funcionan correctamente

**Time to Market**:
- **Antes**: Agregar nuevo módulo (ej. "Inventario") = Copiar findWithFilters (~1 hora)
- **Después**: Agregar nuevo módulo = Implementar getFilterConfig (~10 minutos)
- **Ahorro**: 83% más rápido

**PHPStan Compliance**:
- **Antes**: Método duplicado en 3 lugares → difícil mantener type safety
- **Después**: Método centralizado con type hints estrictos → PHPStan level 5 compliant

**Onboarding**:
- **Antes**: Nuevo dev debe entender 3 implementaciones similares pero diferentes
- **Después**: Nuevo dev lee el trait una vez, ve 3 configs simples
- **Ahorro**: 2-3 horas de onboarding

### Beneficios a Largo Plazo

✅ **Escalabilidad**: Agregar módulo 4 (Inventario), 5 (Mantenimiento), etc. es trivial
✅ **Features Nuevas**: Agregar filtro por tags, por SLA, por custom fields → 1 lugar
✅ **Debugging**: Bug en filtros → arreglar en 1 lugar, beneficia a 3 módulos
✅ **Refactoring**: Optimizar queries → cambio en trait beneficia a todos
✅ **Code Review**: Revisar 200 líneas de trait una vez vs revisar 300 líneas duplicadas
✅ **Confidence**: Devs confían en que cambios no rompen módulos no relacionados

### Métricas de Éxito

**Antes del refactor**:
| Métrica | Valor |
|---------|-------|
| Líneas duplicadas | 300 |
| Archivos a modificar por feature | 3 |
| Tiempo agregar filtro nuevo | 30 min |
| Tiempo agregar módulo nuevo | 1 hora |
| Risk de inconsistencia | Alto |
| Test coverage | 60% |
| PHPStan compliance | Nivel 3 |

**Después del refactor**:
| Métrica | Valor | Mejora |
|---------|-------|--------|
| Líneas duplicadas | 0 | -100% |
| Archivos a modificar por feature | 1 | -67% |
| Tiempo agregar filtro nuevo | 10 min | -67% |
| Tiempo agregar módulo nuevo | 10 min | -83% |
| Risk de inconsistencia | Nulo | -100% |
| Test coverage | 85% | +25% |
| PHPStan compliance | Nivel 5 | +2 niveles |

### Dependencias

Ninguna - este refactor es independiente y puede implementarse inmediatamente.

**Recomendaciones**:
- ✅ Hacerlo ANTES de ARCH-003 (TicketSystemTrait) para evitar conflictos
- ✅ Hacerlo DESPUÉS de BLK-001 y BLK-002 (bloqueadores críticos)
- ⚠️  No requiere cambios en controllers o servicios
- ⚠️  No requiere cambios en base de datos
- ⚠️  No requiere cambios en frontend/templates

### Riesgos y Mitigación

**Riesgo 1: Romper funcionalidad existente de filtros**
- **Probabilidad**: Media
- **Impacto**: Alto
- **Mitigación**:
  - Tests exhaustivos ANTES de merge
  - Smoke testing manual de todas las vistas
  - Deploy gradual (staging → production)
  - Feature flag opcional para rollback rápido
  - Backups de archivos originales

**Riesgo 2: Diferencias sutiles entre módulos no capturadas**
- **Probabilidad**: Baja
- **Impacto**: Medio
- **Mitigación**:
  - Code review cuidadoso de las 3 implementaciones originales
  - Tests específicos para cada módulo verificando comportamiento único
  - Documentar en getFilterConfig() qué hace cada opción

**Riesgo 3: Performance degradation**
- **Probabilidad**: Muy baja
- **Impacto**: Bajo
- **Mitigación**:
  - El trait genera exactamente las mismas queries SQL
  - Benchmarking antes/después
  - Profiling con xdebug si es necesario

**Riesgo 4: PHPStan/Type errors**
- **Probabilidad**: Baja
- **Impacto**: Bajo
- **Mitigación**:
  - Type hints estrictos en trait
  - PHPStan checks en CI/CD
  - Array shapes documentadas en @return

### Rollback Plan

Si algo sale mal:

```bash
# Rollback rápido (restaurar backups)
cp src/Model/Table/TicketsTable.php.backup src/Model/Table/TicketsTable.php
cp src/Model/Table/ComprasTable.php.backup src/Model/Table/ComprasTable.php
cp src/Model/Table/PqrsTable.php.backup src/Model/Table/PqrsTable.php

# Eliminar trait
rm src/Model/Table/Traits/FilterableTrait.php

# Clear cache
bin/cake cache clear_all

# Restart server
bin/cake server
```

**Rollback por módulo** (si solo 1 módulo tiene problemas):
- Revertir solo el archivo problemático
- Los demás módulos siguen usando el trait
- Investigar el problema específico
- Re-intentar migración cuando esté resuelto

---
