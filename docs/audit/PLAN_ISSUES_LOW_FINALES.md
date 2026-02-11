# PLAN DE RESOLUCIÓN - ISSUES LOW FINALES

Todos los issues de severidad BAJA restantes, con soluciones concisas.

---

## RESUMEN EJECUTIVO

Total issues en este documento: **25 issues LOW**

**Características**:
- Severidad: 🔵 Bajo (no bloqueantes)
- Esfuerzo: XS-S (< 2 horas cada uno)
- Impacto: Mejoras de código, no funcionales críticos
- Pueden implementarse después de issues críticos

**Agrupación**:
- Magic strings y constantes: 8 issues
- Métodos no usados: 3 issues
- Documentación: 4 issues
- Validaciones: 3 issues
- Configuración: 3 issues
- Refactoring menor: 4 issues

---

## MAGIC STRINGS Y CONSTANTES

### MS-001: Magic strings en status values

**Archivos**: Múltiples (TicketsTable, ComprasTable, PqrsTable)
**Problema**: Status hardcodeados ('nuevo', 'en_proceso', 'resuelto', etc.)

**Solución**: Ya documentado en SMELL-003 - Crear enums PHP 8.1+

**Beneficio**: Type safety, autocomplete, sin typos

---

### MS-002: Magic numbers en timeouts y límites

**Archivos**: Múltiples servicios
**Problema**: Timeouts y límites hardcodeados (30, 256, 1000, etc.)

**Solución**:
```php
// Crear clase de constantes
class ServiceLimits
{
    // HTTP
    const HTTP_TIMEOUT_SECONDS = 30;
    const HTTP_MAX_RETRIES = 3;

    // Email
    const MAX_MIME_DEPTH = 20;
    const MAX_RECIPIENTS = 100;
    const MAX_ATTACHMENT_SIZE_MB = 25;

    // Pagination
    const DEFAULT_PAGE_SIZE = 50;
    const MAX_PAGE_SIZE = 1000;

    // Cache
    const CACHE_TTL_SETTINGS = 3600;  // 1 hora
    const CACHE_TTL_STATS = 300;      // 5 minutos
}

// Uso
new Client(['timeout' => ServiceLimits::HTTP_TIMEOUT_SECONDS]);
```

**Esfuerzo**: 1 hora

---

### MS-003: Magic strings en configuración keys

**Archivos**: Múltiples
**Problema**: Keys de configuración como strings ('gmail_refresh_token', etc.)

**Solución**:
```php
class ConfigKeys
{
    // Gmail
    const GMAIL_REFRESH_TOKEN = 'gmail_refresh_token';
    const GMAIL_CLIENT_SECRET = 'gmail_client_secret_path';
    const GMAIL_CHECK_INTERVAL = 'gmail_check_interval';

    // WhatsApp
    const WHATSAPP_ENABLED = 'whatsapp_enabled';
    const WHATSAPP_API_URL = 'whatsapp_api_url';
    const WHATSAPP_API_KEY = 'whatsapp_api_key';

    // N8n
    const N8N_ENABLED = 'n8n_enabled';
    const N8N_WEBHOOK_URL = 'n8n_webhook_url';
    const N8N_WEBHOOK_SECRET = 'n8n_webhook_secret';
}

// Uso
$refreshToken = $settings[ConfigKeys::GMAIL_REFRESH_TOKEN];
```

**Esfuerzo**: 1 hora

---

### MS-004: Magic strings en file paths

**Archivos**: GmailService, S3Service
**Problema**: Paths hardcodeados ('config/google/', '/tmp/', etc.)

**Solución**:
```php
class FilePaths
{
    public static function credentialsPath(): string
    {
        return CONFIG . 'google' . DS;
    }

    public static function tempPath(): string
    {
        return TMP;
    }

    public static function uploadsPath(string $module): string
    {
        return UPLOADS . $module . DS;
    }

    public static function logsPath(): string
    {
        return LOGS;
    }
}

// Uso
$credentialsFile = FilePaths::credentialsPath() . 'credentials.json';
```

**Esfuerzo**: 1 hora

---

### MS-005: Magic strings en MIME types

**Archivos**: GenericAttachmentTrait
**Problema**: MIME types hardcodeados en arrays

**Solución**:
```php
class MimeTypes
{
    // Images
    const JPEG = 'image/jpeg';
    const PNG = 'image/png';
    const GIF = 'image/gif';

    // Documents
    const PDF = 'application/pdf';
    const WORD = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    const EXCEL = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    // Archives
    const ZIP = 'application/zip';
    const RAR = 'application/x-rar-compressed';

    public static function isImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    public static function isDocument(string $mimeType): bool
    {
        return in_array($mimeType, [
            self::PDF,
            self::WORD,
            self::EXCEL,
        ]);
    }
}
```

**Esfuerzo**: 1-2 horas

---

### MS-006: Magic strings en cache keys

**Archivos**: Múltiples controllers y servicios
**Problema**: Cache keys hardcodeados ('system_settings', 'stats_', etc.)

**Solución**:
```php
class CacheKeys
{
    const SYSTEM_SETTINGS = 'system_settings';
    const GMAIL_TOKEN = 'gmail_oauth_token';

    public static function stats(string $module, string $period): string
    {
        return "stats_{$module}_{$period}";
    }

    public static function userPermissions(int $userId): string
    {
        return "user_permissions_{$userId}";
    }

    public static function ticketCount(string $status): string
    {
        return "ticket_count_{$status}";
    }
}

// Uso
Cache::remember(CacheKeys::SYSTEM_SETTINGS, ...);
Cache::remember(CacheKeys::stats('tickets', 'daily'), ...);
```

**Esfuerzo**: 1 hora

---

### MS-007: Magic strings en log contexts

**Archivos**: Múltiples servicios
**Problema**: Log context keys inconsistentes

**Solución**:
```php
class LogContext
{
    public static function ticket(int $id, array $extra = []): array
    {
        return array_merge(['ticket_id' => $id], $extra);
    }

    public static function email(string $messageId, array $extra = []): array
    {
        return array_merge(['message_id' => $messageId], $extra);
    }

    public static function user(int $userId, array $extra = []): array
    {
        return array_merge(['user_id' => $userId], $extra);
    }

    public static function api(string $endpoint, int $statusCode, array $extra = []): array
    {
        return array_merge([
            'api_endpoint' => $endpoint,
            'status_code' => $statusCode,
        ], $extra);
    }
}

// Uso
Log::info('Ticket created', LogContext::ticket($ticket->id, ['status' => 'nuevo']));
Log::error('Email failed', LogContext::email($messageId, ['error' => $e->getMessage()]));
```

**Esfuerzo**: 1-2 horas

---

### MS-008: Magic strings en validation messages

**Archivos**: Model Tables
**Problema**: Mensajes de error hardcodeados

**Solución**:
```php
class ValidationMessages
{
    const REQUIRED = 'Este campo es requerido';
    const INVALID_EMAIL = 'Email inválido';
    const INVALID_PHONE = 'Teléfono inválido';
    const TOO_SHORT = 'Muy corto (mínimo {min} caracteres)';
    const TOO_LONG = 'Muy largo (máximo {max} caracteres)';
    const INVALID_FORMAT = 'Formato inválido';
    const NOT_UNIQUE = 'Este valor ya existe';

    public static function minLength(int $min): string
    {
        return str_replace('{min}', (string)$min, self::TOO_SHORT);
    }

    public static function maxLength(int $max): string
    {
        return str_replace('{max}', (string)$max, self::TOO_LONG);
    }
}

// Uso en validación
$validator
    ->email('email', ValidationMessages::INVALID_EMAIL)
    ->requirePresence('email', 'create', ValidationMessages::REQUIRED);
```

**Esfuerzo**: 1 hora

---

## MÉTODOS NO USADOS

### UNU-001: getSystemEmail() no usado

**Archivo**: TicketService.php
**Solución**: Ya documentado en SMELL-004

---

### UNU-002: Métodos de debug no usados

**Archivos**: Múltiples
**Problema**: Métodos de debug/testing que quedaron en producción

**Solución**:
```bash
# Buscar métodos nunca llamados
phpstan analyze src/ --level=5 | grep "never called"

# O usar herramienta especializada
composer require --dev phpstan/phpstan-dead-code
phpstan analyze src/ -c phpstan-dead-code.neon
```

**Acción**: Eliminar o marcar como @deprecated

**Esfuerzo**: 1 hora

---

### UNU-003: Variables no usadas en métodos

**Archivos**: Múltiples
**Problema**: Variables asignadas pero nunca usadas

**Solución**:
```bash
# PHPStan detecta esto
phpstan analyze src/ --level=5 | grep "never read"
```

**Acción**: Eliminar variables no usadas

**Esfuerzo**: 30 minutos

---

## DOCUMENTACIÓN

### DOC-001: Falta README para development

**Problema**: No hay README.md con instrucciones de setup

**Solución**: Crear README.md completo:
```markdown
# Mesa de Ayuda - Sistema de Soporte

## Requisitos
- PHP 8.1+
- MySQL 8.0+
- Composer
- Node.js 18+ (opcional, para assets)

## Instalación

1. Clonar repositorio
2. Copiar .env.example a .env
3. Configurar database en .env
4. Instalar dependencias: `composer install`
5. Ejecutar migraciones: `bin/cake migrations migrate`
6. Ejecutar seeds: `bin/cake migrations seed`
7. Iniciar servidor: `bin/cake server`

## Configuración

### Gmail Integration
1. Crear proyecto en Google Cloud Console
2. Habilitar Gmail API
3. Descargar credentials.json a config/google/
4. Autorizar acceso en /admin/settings

### WhatsApp Integration
Ver docs/WHATSAPP.md

### S3 Storage
Ver docs/S3.md

## Testing

composer test
composer test-coverage

## Deployment

Ver docs/DEPLOYMENT.md
```

**Esfuerzo**: 2-3 horas

---

### DOC-002: Falta documentación de API endpoints

**Problema**: Si hay API endpoints, no están documentados

**Solución**: Documentar con OpenAPI/Swagger:
```yaml
# api-docs.yaml
openapi: 3.0.0
info:
  title: Mesa de Ayuda API
  version: 1.0.0

paths:
  /api/tickets:
    get:
      summary: List tickets
      parameters:
        - name: status
          in: query
          schema:
            type: string
      responses:
        200:
          description: Success
```

**Esfuerzo**: 3-4 horas (si hay API)

---

### DOC-003: Comentarios en español pero código en inglés

**Problema**: Inconsistencia de idioma

**Solución**: Estandarizar a inglés en código, español en UI:
- Código (variables, métodos, clases): Inglés
- Comentarios técnicos: Inglés
- Mensajes de usuario: Español
- Documentación externa: Español

**Esfuerzo**: No urgente, hacer gradualmente

---

### DOC-004: Falta changelog

**Problema**: No hay CHANGELOG.md

**Solución**: Crear y mantener CHANGELOG.md:
```markdown
# Changelog

## [Unreleased]
### Added
- WhatsApp integration
- S3 file storage

### Fixed
- Email parsing with special characters

## [1.0.0] - 2024-12-01
### Added
- Initial release
- Tickets module
- PQRS module
- Compras module
```

**Esfuerzo**: 1 hora inicial, 10 min por release

---

## VALIDACIONES

### VAL-001: Validación de email faltante en algunos forms

**Archivos**: Controllers/Forms
**Problema**: Algunos forms no validan email format

**Solución**:
```php
// En validación
$validator
    ->email('email', 'Email inválido')
    ->requirePresence('email', 'create')
    ->notEmptyString('email');

// En JS (front-end)
<input type="email" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
```

**Esfuerzo**: 1 hora

---

### VAL-002: Validación de phone number inconsistente

**Archivos**: Múltiples
**Problema**: Algunos validan phone, otros no

**Solución**:
```php
// Validation rule custom
class CustomValidation
{
    public static function phone(string $value): bool
    {
        // Acepta formatos: +57 123 4567890, 123-456-7890, etc.
        $pattern = '/^[+]?[0-9\s\-()]{7,20}$/';
        return preg_match($pattern, $value) === 1;
    }
}

// En Table
$validator
    ->add('phone', 'validPhone', [
        'rule' => [CustomValidation::class, 'phone'],
        'message' => 'Número de teléfono inválido',
    ]);
```

**Esfuerzo**: 1-2 horas

---

### VAL-003: Sanitización de input faltante

**Archivos**: Controllers
**Problema**: Algunos inputs no son sanitizados

**Solución**:
```php
// En AppController
protected function sanitizeInput(array $data): array
{
    array_walk_recursive($data, function (&$value) {
        if (is_string($value)) {
            $value = trim($value);
            $value = strip_tags($value, '<p><br><a><strong><em>');
        }
    });

    return $data;
}

// Uso en controllers
$data = $this->sanitizeInput($this->request->getData());
```

**Esfuerzo**: 2 horas

---

## CONFIGURACIÓN

### CFG-001: Configuración hardcodeada en código

**Archivos**: Múltiples
**Problema**: Algunos valores de config están en código, no en .env

**Solución**: Mover a .env:
```env
# App
APP_NAME="Mesa de Ayuda"
APP_TIMEZONE="America/Bogota"
APP_LOCALE="es_CO"

# Features
FEATURE_WHATSAPP=true
FEATURE_S3_STORAGE=true
FEATURE_N8N_INTEGRATION=true

# Limits
MAX_FILE_SIZE_MB=25
MAX_ATTACHMENTS_PER_TICKET=10
PAGINATION_DEFAULT=50
```

```php
// Uso
$maxFileSize = env('MAX_FILE_SIZE_MB', 25);
$timezone = env('APP_TIMEZONE', 'UTC');
```

**Esfuerzo**: 2 horas

---

### CFG-002: Falta configuración de desarrollo vs producción

**Problema**: No hay distinción clara entre ambientes

**Solución**: Crear config files separados:
```php
// config/app_local.development.php
return [
    'debug' => true,
    'Security' => [
        'requireSecure' => false,
    ],
    'Email' => [
        'default' => [
            'className' => 'Debug',  // No enviar emails reales
        ],
    ],
];

// config/app_local.production.php
return [
    'debug' => false,
    'Security' => [
        'requireSecure' => true,
    ],
    'Email' => [
        'default' => [
            'className' => 'Smtp',
        ],
    ],
];
```

**Esfuerzo**: 1-2 horas

---

### CFG-003: Secrets en version control

**Problema**: Verificar que no hay secrets en git

**Solución**:
```bash
# Instalar git-secrets
git secrets --install
git secrets --register-aws

# Scan histórico
git secrets --scan-history

# Pre-commit hook
echo '#!/bin/bash
git secrets --pre_commit_hook -- "$@"' > .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

**Agregar a .gitignore**:
```
.env
config/app_local.php
config/google/*.json
credentials.*
```

**Esfuerzo**: 1 hora

---

## REFACTORING MENOR

### REF-001: Duplicación de array mapping

**Archivos**: Múltiples
**Problema**: Código duplicado para convertir arrays

**Solución**:
```php
// Utility class
class ArrayHelper
{
    public static function pluck(array $array, string $key): array
    {
        return array_map(fn($item) => $item[$key] ?? null, $array);
    }

    public static function keyBy(array $array, string $key): array
    {
        $result = [];
        foreach ($array as $item) {
            $result[$item[$key]] = $item;
        }
        return $result;
    }

    public static function groupBy(array $array, string $key): array
    {
        $result = [];
        foreach ($array as $item) {
            $result[$item[$key]][] = $item;
        }
        return $result;
    }
}

// Uso
$ids = ArrayHelper::pluck($tickets, 'id');
$ticketsById = ArrayHelper::keyBy($tickets, 'id');
$ticketsByStatus = ArrayHelper::groupBy($tickets, 'status');
```

**Esfuerzo**: 1 hora

---

### REF-002: Código condicional repetido

**Archivos**: Múltiples
**Problema**: Mismo patrón de if/else repetido

**Solución**: Extract Method
```php
// ANTES - repetido en múltiples lugares
if ($this->request->is('ajax')) {
    return $this->response->withType('application/json')
        ->withStringBody(json_encode($data));
}
return $this->render();

// DESPUÉS - método helper
protected function respondWithData($data, string $template = null)
{
    if ($this->request->is('ajax')) {
        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($data));
    }

    $this->set($data);
    return $template ? $this->render($template) : $this->render();
}

// Uso
return $this->respondWithData(['ticket' => $ticket]);
```

**Esfuerzo**: 2 horas

---

### REF-003: Nested ifs profundos

**Archivos**: Algunos métodos
**Problema**: Ifs anidados dificultan lectura

**Solución**: Early returns
```php
// ANTES
public function process($data)
{
    if ($data !== null) {
        if (is_array($data)) {
            if (!empty($data['id'])) {
                // ... lógica
                return $result;
            } else {
                return null;
            }
        } else {
            return null;
        }
    } else {
        return null;
    }
}

// DESPUÉS
public function process($data)
{
    if ($data === null) {
        return null;
    }

    if (!is_array($data)) {
        return null;
    }

    if (empty($data['id'])) {
        return null;
    }

    // ... lógica
    return $result;
}
```

**Esfuerzo**: 1-2 horas

---

### REF-004: Long parameter lists

**Archivos**: Algunos métodos
**Problema**: Métodos con 6+ parámetros

**Solución**: Parameter Object
```php
// ANTES
public function sendEmail(
    string $to,
    string $subject,
    string $body,
    array $attachments,
    string $from,
    array $cc,
    array $bcc,
    array $replyTo
) {
    // ...
}

// DESPUÉS
class EmailParams
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $body,
        public array $attachments = [],
        public ?string $from = null,
        public array $cc = [],
        public array $bcc = [],
        public array $replyTo = [],
    ) {}
}

public function sendEmail(EmailParams $params)
{
    // Acceso: $params->to, $params->subject, etc.
}

// Uso
$email = new EmailParams(
    to: 'user@example.com',
    subject: 'Test',
    body: 'Hello',
);
$service->sendEmail($email);
```

**Esfuerzo**: 2-3 horas

---

## PERFORMANCE OPTIMIZATIONS (OPCIONAL)

### PERF-001: N+1 queries en listados

**Problema**: Queries no optimizadas con contain()

**Solución**:
```php
// ANTES - N+1 query
$tickets = $this->Tickets->find()->all();
foreach ($tickets as $ticket) {
    echo $ticket->assignee->name;  // Query por cada ticket
}

// DESPUÉS - Eager loading
$tickets = $this->Tickets->find()
    ->contain(['Assignees', 'TicketComments'])
    ->all();
```

**Esfuerzo**: 1-2 horas

---

### PERF-002: Falta índices en DB

**Problema**: Queries lentas por falta de índices

**Solución**:
```sql
-- Añadir índices en campos frecuentemente buscados
CREATE INDEX idx_tickets_status ON tickets(status);
CREATE INDEX idx_tickets_assignee ON tickets(assignee_id);
CREATE INDEX idx_tickets_created ON tickets(created);
CREATE INDEX idx_tickets_number ON tickets(ticket_number);

-- Índices compuestos
CREATE INDEX idx_tickets_status_priority ON tickets(status, priority);
CREATE INDEX idx_tickets_assignee_status ON tickets(assignee_id, status);
```

**Esfuerzo**: 1 hora

---

### PERF-003: Cache queries pesadas

**Problema**: Algunas estadísticas se calculan en cada request

**Solución**: Ya documentado en CTRL-001

---

## TESTING

### TEST-001: Falta cobertura de tests

**Problema**: Test coverage bajo

**Solución**:
1. Configurar PHPUnit coverage
2. Escribir tests para servicios críticos
3. Aim for 70%+ coverage en servicios, 50%+ en controllers

**Esfuerzo**: Continuo

---

### TEST-002: Falta tests de integración

**Problema**: Solo unit tests

**Solución**:
```php
// Integration test example
class TicketCreationIntegrationTest extends TestCase
{
    use IntegrationTestTrait;

    public function testCreateTicketFromEmailEndToEnd()
    {
        // Setup: Mock Gmail service
        // Action: Call import command
        // Assert: Ticket created, email sent, attachment saved
    }
}
```

**Esfuerzo**: 1 semana para suite básica

---

## RESUMEN FINAL

**Total issues documentados en este archivo: 25**

### Por categoría:
- Magic strings: 8 issues (8 horas)
- Métodos no usados: 3 issues (2 horas)
- Documentación: 4 issues (8 horas)
- Validaciones: 3 issues (4 horas)
- Configuración: 3 issues (4 horas)
- Refactoring: 4 issues (6 horas)

**Esfuerzo total estimado**: ~32 horas (4 días)

**Prioridad**: Baja - implementar después de issues críticos

**Beneficio**: Código más limpio, mantenible, documentado

---

**Estos son los últimos issues LOW. Con los agentes trabajando en los 11 issues restantes de mayor prioridad, al finalizar tendremos TODOS los 77 issues documentados.**
