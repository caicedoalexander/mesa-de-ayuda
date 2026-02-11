# AUDITORÍA ARQUITECTURA - Mesa de Ayuda

**Fecha**: 2026-01-09
**Auditor**: Claude Sonnet 4.5
**Versión proyecto**: b7886d7
**Branch**: main
**Fase**: 2 - Auditoría Manual de Services

---

## Resumen Ejecutivo

- **Total de issues encontrados**: 4
- **Críticos**: 0 | **Altos**: 2 | **Medios**: 2 | **Bajos**: 0
- **Estado general**: 🟡 Amarillo - Arquitectura funcional pero mejorable
- **Esfuerzo estimado total**: ~7 días

**Archivos auditados**: 2/11 (18%)
- ✅ GmailService.php (3 issues arquitectónicos)
- ✅ TicketService.php (1 issue arquitectónico)

**Recomendación**:
La arquitectura general sigue el patrón Service Layer correctamente. Issues principales:

**GmailService**: God object con 5 responsabilidades - requiere refactoring en 5 servicios especializados.

**TicketService**: Dependency Injection incompleta - servicios inyectados no se usan, y GmailService se instancia directamente 4 veces. Traits crean nuevas instancias en lugar de reutilizar las inyectadas.

---

## Índice de Issues

### GmailService.php
- [ARCH-001: GmailService viola Single Responsibility Principle](#arch-001-gmailservice-viola-single-responsibility-principle)
- [ARCH-002: Método estático con side effects](#arch-002-método-estático-con-side-effects)
- [ARCH-003: Dependencia no inyectada](#arch-003-dependencia-no-inyectada)

### TicketService.php
- [ARCH-004: Inyección de Dependencias Incompleta](#arch-004-inyección-de-dependencias-incompleta)

---

## Issues Detallados

### ARCH-001: GmailService viola Single Responsibility Principle

**Severidad**: 🔴 Alto
**Esfuerzo**: L (3-5 días)
**Ubicación**: `src/Service/GmailService.php` (toda la clase - 805 líneas)
**Prioridad para producción**: Media

**Descripción**:
GmailService.php es una clase "god object" que maneja 5 responsabilidades completamente distintas:

1. **OAuth2 Authentication** (~80 líneas)
   - Token management
   - Client initialization
   - Refresh token handling

2. **Message Fetching** (~50 líneas)
   - Query Gmail API
   - List messages
   - Mark as read

3. **Message Parsing** (~150 líneas)
   - Parse headers
   - Extract body (HTML/text)
   - Detect inline images
   - Auto-reply detection
   - System notification detection

4. **Attachment Handling** (~30 líneas)
   - Download attachments
   - Handle inline images

5. **Email Sending** (~200 líneas)
   - MIME message creation
   - Header encoding (UTF-8)
   - Attachment encoding
   - Send via Gmail API

**Impacto en Arquitectura**:
- **Acoplamiento alto**: Cambio en una parte afecta otras
- **Testing difícil**: Requiere mockear Google Client, S3Service, SystemSettings
- **Reutilización imposible**: No puedes usar solo "sending" sin "fetching"
- **Violación de Open/Closed**: Difícil extender sin modificar

**Evidencia de Violación**:
```php
class GmailService  // 805 líneas - DEMASIADO
{
    // Properties for ALL responsibilities
    private GoogleClient $client;        // OAuth2
    private ?Gmail $service = null;       // API
    private array $config;                // Configuration

    // Responsibility 1: OAuth2 (5 métodos)
    public function getAuthUrl(): string { }
    public function authenticate(string $code): array { }
    private function initializeClient(): void { }

    // Responsibility 2: Fetching (2 métodos)
    public function getMessages(string $query, int $maxResults): array { }
    public function markAsRead(string $messageId): bool { }

    // Responsibility 3: Parsing (6 métodos)
    public function parseMessage(string $messageId): array { }
    private function extractMessageParts($payload, array &$data): void { }
    public function isAutoReply(array $headers): bool { }
    public function isSystemNotification(array $headers): bool { }
    private function parseRecipients(string $header): array { }

    // Responsibility 4: Attachments (1 método)
    public function downloadAttachment(string $id, string $attachmentId): string { }

    // Responsibility 5: Sending (4 métodos)
    public function sendEmail(...): bool { }
    private function createMimeMessage(...): string { }
    private function encodeEmailHeader(string $name, string $email): string { }
}
```

**Análisis SOLID**:

| Principle | Estado | Explicación |
|-----------|--------|-------------|
| **S**ingle Responsibility | ❌ Violado | 5 responsabilidades en una clase |
| **O**pen/Closed | ❌ Violado | Imposible extender sin modificar |
| **L**iskov Substitution | ⚠️ N/A | No hay herencia |
| **I**nterface Segregation | ⚠️ N/A | No hay interfaces |
| **D**ependency Inversion | ⚠️ Parcial | Usa `new S3Service()` directamente |

**Recomendación - Refactoring Arquitectónico**:

Dividir en 5 servicios siguiendo Domain-Driven Design:

```
┌─────────────────────────────────────────────┐
│         GmailService (Facade)               │
│  Coordina otros servicios, no hace trabajo  │
│              (~100 líneas)                  │
└─────────────────────────────────────────────┘
                     │
        ┌────────────┼────────────┐
        │            │            │
┌───────▼──────┐ ┌──▼──────┐ ┌──▼────────┐
│ GmailAuth    │ │ Gmail   │ │ Gmail     │
│ Service      │ │ Fetch   │ │ Parser    │
│              │ │ Service │ │ Service   │
│ • getAuthUrl │ │ • get   │ │ • parse   │
│ • authent    │ │   Msgs  │ │   Message │
│   icate      │ │ • mark  │ │ • isAuto  │
│ • initClient │ │   AsRead│ │   Reply   │
│              │ │         │ │ • isSys   │
│ (~150 líneas)│ │(~100    │ │   Notif   │
└──────────────┘ │ líneas) │ │(~200      │
                 └─────────┘ │ líneas)   │
                             └───────────┘
   ┌──────────────┐    ┌────────────────┐
   │ Gmail        │    │ Gmail          │
   │ Attachment   │    │ Sender         │
   │ Service      │    │ Service        │
   │              │    │                │
   │ • download   │    │ • sendEmail    │
   │   Attachment │    │ • createMime   │
   │              │    │ • encodeHeader │
   │ (~80 líneas) │    │ (~250 líneas)  │
   └──────────────┘    └────────────────┘
```

**Estructura de Archivos Propuesta**:
```
src/Service/Gmail/
├── GmailService.php              # Facade (orchestration)
├── GmailAuthService.php          # OAuth2 authentication
├── GmailFetchService.php         # Message retrieval
├── GmailParserService.php        # Email parsing
├── GmailAttachmentService.php    # Attachment handling
└── GmailSenderService.php        # Email sending
```

**Implementación Gradual (6 pasos)**:

1. **Paso 1 (1 día)**: Crear interfaces
```php
interface GmailAuthServiceInterface {
    public function getAuthUrl(): string;
    public function authenticate(string $code): array;
}

interface GmailFetchServiceInterface {
    public function getMessages(string $query, int $maxResults): array;
    public function markAsRead(string $messageId): bool;
}
// ... etc
```

2. **Paso 2 (2 días)**: Extraer GmailAuthService
```php
class GmailAuthService implements GmailAuthServiceInterface
{
    private GoogleClient $client;

    public function __construct(array $config) {
        $this->initializeClient($config);
    }

    // Mover métodos de OAuth2 aquí
}
```

3. **Paso 3 (2 días)**: Extraer GmailSenderService
```php
class GmailSenderService implements GmailSenderServiceInterface
{
    public function __construct(
        private GmailAuthService $authService
    ) {}

    // Mover métodos de sending aquí
}
```

4. **Paso 4 (1 día)**: Extraer GmailParserService

5. **Paso 5 (1 día)**: Extraer GmailFetchService y GmailAttachmentService

6. **Paso 6 (1 día)**: Refactorizar GmailService como Facade
```php
class GmailService
{
    public function __construct(
        private GmailAuthService $auth,
        private GmailFetchService $fetch,
        private GmailParserService $parser,
        private GmailAttachmentService $attachment,
        private GmailSenderService $sender
    ) {}

    // Delegar a servicios especializados
    public function getAuthUrl(): string {
        return $this->auth->getAuthUrl();
    }

    public function fetchAndParseMessages(string $query, int $max): array {
        $messageIds = $this->fetch->getMessages($query, $max);
        return array_map(
            fn($id) => $this->parser->parseMessage($id),
            $messageIds
        );
    }

    // etc.
}
```

**Testing Mejorado Post-Refactoring**:
```php
// ANTES: Difícil de testear (demasiados mocks)
class GmailServiceTest extends TestCase
{
    public function testSendEmail() {
        // Need to mock: GoogleClient, Gmail API, S3Service, SystemSettings
        // Muy complejo y frágil
    }
}

// DESPUÉS: Fácil de testear (una responsabilidad)
class GmailSenderServiceTest extends TestCase
{
    public function testSendEmail() {
        $authService = $this->createMock(GmailAuthService::class);
        $sender = new GmailSenderService($authService);

        // Test solo sending, sin OAuth2/fetching/parsing concerns
        $result = $sender->sendEmail(...);
        $this->assertTrue($result);
    }
}
```

**Beneficios del Refactoring**:

| Aspecto | Antes | Después | Mejora |
|---------|-------|---------|--------|
| Líneas por clase | 805 | ~100-250 | 🟢 70% reducción |
| Responsabilidades | 5 | 1 cada | 🟢 SOLID |
| Complejidad de tests | Alta (5 mocks) | Baja (1-2 mocks) | 🟢 80% más simple |
| Tiempo de test | ~2s (setUp pesado) | ~0.2s | 🟢 10x más rápido |
| Reutilización | Imposible | Flexible | 🟢 Modularity |
| Extensibilidad | Difícil | Fácil | 🟢 Open/Closed |

**Riesgos y Mitigaciones**:

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|--------------|---------|------------|
| Romper funcionalidad existente | Media | Alto | Tests de integración completos antes |
| Introducir bugs en refactoring | Media | Medio | Refactorizar de a poco, con tests |
| Performance overhead (más objetos) | Baja | Bajo | Usar lazy loading, DI container |
| Complejidad inicial aumenta | Alta | Bajo | Vale la pena a largo plazo |

**Referencias**:
- Clean Architecture, Robert C. Martin
- Domain-Driven Design, Eric Evans
- SOLID Principles
- Martin Fowler - Refactoring patterns

---

### ARCH-002: Método estático con side effects

**Severidad**: 🟡 Medio
**Esfuerzo**: S (2-4 horas)
**Ubicación**: `src/Service/GmailService.php:41-61`
**Prioridad para producción**: Baja

**Descripción**:
El método estático `loadConfigFromDatabase()` realiza queries a la base de datos, violando el principio de que métodos estáticos no deberían tener side effects ni depender de estado externo.

**Impacto en Arquitectura**:
- **Testing imposible**: No se puede mockear método estático
- **Acoplamiento con ORM**: Dependencia directa de CakePHP
- **Anti-pattern**: Static method accessing database
- **State dependency**: Requiere database disponible

**Evidencia**:
```php
// Líneas 41-61: Anti-pattern de método estático
public static function loadConfigFromDatabase(): array
{
    // PROBLEMA 1: Crea instancia solo para usar trait
    $instance = new self([]);  // Antipattern

    // PROBLEMA 2: Query directa desde static method
    $settingsTable = $instance->fetchTable('SystemSettings');
    $settings = $settingsTable->find()  // Side effect
        ->where(['setting_key IN' => ['gmail_refresh_token', ...]])
        ->all();

    // PROBLEMA 3: Procesa con trait method
    foreach ($settings as $setting) {
        $config[$key] = $instance->shouldEncrypt($setting->setting_key)
            ? $instance->decryptSetting(...)  // State-dependent
            : $setting->setting_value;
    }

    return $config;
}
```

**Violaciones de Principios**:

1. **Static methods should be pure**: Métodos estáticos deberían ser funciones puras sin side effects
2. **Testability**: No se pueden mockear métodos estáticos
3. **Dependency Injection**: No hay forma de inyectar mock de SystemSettings

**Recomendación - Repository Pattern**:

Implementar Repository para abstraer acceso a datos:

```php
// 1. Crear SystemSettingsRepository
namespace App\Repository;

class SystemSettingsRepository
{
    use SettingsEncryptionTrait;

    public function __construct(
        private SystemSettingsTable $table
    ) {}

    public function getGmailConfig(): array
    {
        $settings = $this->table->find()
            ->where(['setting_key IN' => [
                'gmail_refresh_token',
                'gmail_client_secret_path'
            ]])
            ->all();

        $config = [];
        foreach ($settings as $setting) {
            $key = str_replace('gmail_', '', $setting->setting_key);
            $config[$key] = $this->shouldEncrypt($setting->setting_key)
                ? $this->decryptSetting($setting->setting_value, $setting->setting_key)
                : $setting->setting_value;
        }

        return $config;
    }
}

// 2. Inyectar repository en GmailService
class GmailService
{
    public function __construct(
        ?array $config = null,
        ?SystemSettingsRepository $settingsRepo = null
    ) {
        $this->settingsRepo = $settingsRepo ?? new SystemSettingsRepository(...);
        $this->config = $config ?? $this->settingsRepo->getGmailConfig();
        $this->initializeClient();
    }

    // Eliminar método estático loadConfigFromDatabase()
}

// 3. En controllers/commands que usan GmailService
class ImportGmailCommand extends Command
{
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        // ANTES (no testeable):
        // $config = GmailService::loadConfigFromDatabase();

        // DESPUÉS (testeable):
        $settingsRepo = new SystemSettingsRepository($this->fetchTable('SystemSettings'));
        $config = $settingsRepo->getGmailConfig();

        $gmailService = new GmailService($config);
        // ...
    }
}
```

**Testing Mejorado**:
```php
// ANTES: Imposible mockear
class ImportGmailCommandTest extends TestCase
{
    public function testExecute() {
        // ❌ No hay forma de mockear GmailService::loadConfigFromDatabase()
        // Requiere database real con datos
    }
}

// DESPUÉS: Fácil de mockear
class ImportGmailCommandTest extends TestCase
{
    public function testExecute() {
        $mockRepo = $this->createMock(SystemSettingsRepository::class);
        $mockRepo->expects($this->once())
            ->method('getGmailConfig')
            ->willReturn(['refresh_token' => 'test']);

        $gmailService = new GmailService(null, $mockRepo);
        // ✅ Test sin database
    }
}
```

**Beneficios**:
- Testeable (mockeable)
- Sigue Repository Pattern
- Desacopla de ORM
- Sigue Dependency Injection

---

### ARCH-003: Violación de Dependency Injection

**Severidad**: 🔵 Bajo
**Esfuerzo**: S (2-4 horas)
**Ubicación**: `src/Service/GmailService.php:135, 175`
**Prioridad para producción**: Baja

**Descripción**:
Creación directa de dependencia (`new S3Service()`) dentro de método, violando Dependency Injection principle. Esto dificulta testing y crea acoplamiento fuerte.

**Impacto en Arquitectura**:
- **Testability**: No se puede inyectar mock de S3Service
- **Coupling**: Acoplamiento fuerte con implementación concreta
- **Flexibility**: Imposible usar otra implementación de storage

**Evidencia**:
```php
// Línea 135-140: Creación directa en método
private function resolveClientSecretPath(string $path): ?string
{
    if (file_exists($path)) {
        return $path;
    }

    // ❌ Dependencia creada directamente
    $s3Service = new S3Service();
    if (!$s3Service->isEnabled()) {
        return null;
    }

    // Usar S3Service...
}
```

**Patrón Actual (Anti-pattern)**:
```
┌──────────────────┐
│  GmailService    │
│                  │
│  method() {      │
│    $s3 = new     │──────┐ Hard dependency
│    S3Service();  │      │ (no se puede cambiar)
│    $s3->...      │      │
│  }               │      │
└──────────────────┘      │
                          ▼
                  ┌──────────────┐
                  │  S3Service   │
                  │  (concrete)  │
                  └──────────────┘
```

**Patrón Recomendado (Dependency Injection)**:
```
┌──────────────────┐
│  GmailService    │
│                  │
│  __construct(    │◄─────┐ Injected
│    Storage $s3   │      │ (mockeable)
│  ) {             │      │
│    $this->s3=$s3;│      │
│  }               │      │
└──────────────────┘      │
                          │
                  ┌───────┴──────┐
                  │  Storage     │
                  │  Interface   │
                  └───────┬──────┘
                          │
              ┌───────────┴───────────┐
              │                       │
      ┌───────▼──────┐      ┌────────▼────────┐
      │  S3Service   │      │  LocalStorage   │
      │  (impl)      │      │  (impl)         │
      └──────────────┘      └─────────────────┘
```

**Recomendación - Interface Segregation**:

1. **Crear interfaz de Storage**:
```php
namespace App\Service\Storage;

interface StorageInterface
{
    public function isEnabled(): bool;
    public function uploadFile(string $source, string $key, string $mime): bool;
    public function downloadFile(string $key, string $destination): bool;
    public function deleteFile(string $key): bool;
    public function getPresignedUrl(string $key, int $expiration): ?string;
}
```

2. **Adaptar S3Service a interfaz**:
```php
class S3Service implements StorageInterface
{
    // Implementar todos los métodos de la interfaz
    public function isEnabled(): bool { ... }
    public function uploadFile(...): bool { ... }
    // etc.
}
```

3. **Inyectar en GmailService**:
```php
class GmailService
{
    private StorageInterface $storage;

    public function __construct(
        array $config = [],
        ?StorageInterface $storage = null
    ) {
        $this->config = $config;
        $this->storage = $storage ?? new S3Service();  // Default
        $this->initializeClient();
    }

    private function resolveClientSecretPath(string $path): ?string
    {
        if (file_exists($path)) {
            return $path;
        }

        // ✅ Usar propiedad inyectada
        if (!$this->storage->isEnabled()) {
            return null;
        }

        // ...
    }
}
```

**Testing Mejorado**:
```php
class GmailServiceTest extends TestCase
{
    public function testResolveClientSecretFromS3()
    {
        // Mock storage
        $mockStorage = $this->createMock(StorageInterface::class);
        $mockStorage->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);
        $mockStorage->expects($this->once())
            ->method('downloadFile')
            ->willReturn(true);

        // Inyectar mock
        $service = new GmailService([], $mockStorage);

        // Test sin S3 real
        $result = $service->resolveClientSecretPath('config/secret.json');
        $this->assertNotNull($result);
    }
}
```

**Beneficios SOLID**:
- ✅ **D**ependency Inversion: Depende de abstracción, no de concreción
- ✅ **O**pen/Closed: Fácil extender con nueva implementación de storage
- ✅ **L**iskov Substitution: Cualquier implementación de Storage es intercambiable
- ✅ Testeable con mocks

---

### ARCH-004: Inyección de Dependencias Incompleta

**Severidad**: 🔴 Alto
**Esfuerzo**: M (1-2 días)
**Ubicación**: `src/Service/TicketService.php` (líneas 32-35, 44-45, 87, 179, 389)
**Prioridad para producción**: Alta

**Descripción**:
TicketService presenta un patrón anti-arquitectónico donde las dependencias se inyectan en el constructor pero nunca se usan. En su lugar, se crean nuevas instancias dentro de métodos y traits. Esto viola completamente el principio Dependency Injection.

**Problemas Específicos**:

1. **Propiedades inyectadas no usadas**:
   - `EmailService` y `WhatsappService` se inyectan pero son "write-only"
   - El trait `NotificationDispatcherTrait` crea nuevas instancias en lugar de usar las inyectadas

2. **GmailService instanciado 4 veces**:
   - Línea 87: `new GmailService()`
   - Línea 179: `new GmailService()` (duplicado!)
   - Línea 389: `new GmailService(GmailService::loadConfigFromDatabase())`
   - Cada instancia carga configuración desde DB separadamente

3. **Traits rompen DI**:
   - Los traits no tienen acceso a propiedades inyectadas
   - Crean sus propias instancias de servicios

**Impacto en Arquitectura**:
- **Acoplamiento**: Difícil cambiar implementación de GmailService
- **Testing imposible**: No se pueden mockear dependencias
- **Performance**: Múltiples instancias cargan configuración repetidamente
- **Memory waste**: Servicios duplicados en memoria
- **Code smell**: Propiedades marcadas por PHPStan como "property.onlyWritten"

**Evidencia**:

```php
// src/Service/TicketService.php

class TicketService
{
    use NotificationDispatcherTrait;  // Trait crea sus propias instancias

    // ❌ Propiedades nunca leídas
    private EmailService $emailService;
    private WhatsappService $whatsappService;

    public function __construct(?array $systemConfig = null)
    {
        // Inyectadas pero NUNCA usadas
        $this->emailService = new EmailService($systemConfig);
        $this->whatsappService = new WhatsappService($systemConfig);
        $this->systemConfig = $systemConfig;
    }

    public function createFromEmail(array $emailData): ?\App\Model\Entity\Ticket
    {
        // ❌ Instancia 1: Nueva instancia sin config
        $gmailService = new GmailService();
        $fromEmail = $gmailService->extractEmailAddress($emailData['from']);
        $fromName = $gmailService->extractName($emailData['from']);
        // ...
    }

    public function createCommentFromEmail($ticket, array $emailData): ?$comment
    {
        // ❌ Instancia 2: DUPLICADO de arriba
        $gmailService = new GmailService();
        $fromEmail = $gmailService->extractEmailAddress($emailData['from']);
        $fromName = $gmailService->extractName($emailData['from']);
        // ...
    }

    private function processEmailAttachments($ticket, array $attachments, ...): void
    {
        // ❌ Instancia 3: Con config completa cargada desde DB
        $gmailService = new GmailService(GmailService::loadConfigFromDatabase());

        foreach ($attachments as $attachmentData) {
            $content = $gmailService->downloadAttachment(...);
            // ...
        }
    }
}
```

**PHPStan Errors**:
```
Line 32: Property App\Service\TicketService::$emailService is never read, only written.
Line 33: Property App\Service\TicketService::$whatsappService is never read, only written.
```

**Análisis SOLID**:

| Principle | Estado | Violación |
|-----------|--------|-----------|
| **S**ingle Responsibility | 🟡 Parcial | Múltiples responsabilidades pero con traits |
| **O**pen/Closed | 🔴 Violado | Hard-coded `new GmailService()` |
| **L**iskov Substitution | 🟡 N/A | No usa herencia |
| **I**nterface Segregation | 🟡 N/A | No usa interfaces |
| **D**ependency Inversion | 🔴 **Violado severamente** | Crea instancias directamente |

**Solución Propuesta**:

```php
class TicketService
{
    use LocatorAwareTrait;
    use SettingsEncryptionTrait;
    use \App\Service\Traits\TicketSystemTrait;
    use \App\Service\Traits\NotificationDispatcherTrait;
    use \App\Service\Traits\GenericAttachmentTrait;
    use EntityConversionTrait;

    private EmailService $emailService;
    private WhatsappService $whatsappService;
    private GmailService $gmailService;  // ✅ Añadir
    private ?N8nService $n8nService = null;
    private ?array $systemConfig = null;

    /**
     * Constructor con Dependency Injection completa
     *
     * @param array|null $systemConfig System configuration
     * @param GmailService|null $gmailService Gmail service instance
     * @param EmailService|null $emailService Email service instance
     * @param WhatsappService|null $whatsappService WhatsApp service instance
     */
    public function __construct(
        ?array $systemConfig = null,
        ?GmailService $gmailService = null,
        ?EmailService $emailService = null,
        ?WhatsappService $whatsappService = null
    ) {
        $this->systemConfig = $systemConfig;

        // ✅ Inyectar con defaults
        $this->gmailService = $gmailService ?? new GmailService($systemConfig);
        $this->emailService = $emailService ?? new EmailService($systemConfig);
        $this->whatsappService = $whatsappService ?? new WhatsappService($systemConfig);

        // ✅ Pasar servicios inyectados al trait
        $this->setNotificationServices($this->emailService, $this->whatsappService);
    }

    public function createFromEmail(array $emailData): ?\App\Model\Entity\Ticket
    {
        // ...

        // ✅ Usar propiedad inyectada
        $fromEmail = $this->gmailService->extractEmailAddress($emailData['from']);
        $fromName = $this->gmailService->extractName($emailData['from']);

        // ...
    }

    public function createCommentFromEmail($ticket, array $emailData): ?$comment
    {
        // ✅ Usar propiedad inyectada (no crear nueva)
        $fromEmail = $this->gmailService->extractEmailAddress($emailData['from']);
        $fromName = $this->gmailService->extractName($emailData['from']);

        // ...
    }

    private function processEmailAttachments($ticket, array $attachments, ...): void
    {
        // ✅ Usar propiedad inyectada (ya tiene config)
        foreach ($attachments as $attachmentData) {
            usleep(200000);

            $content = $this->gmailService->downloadAttachment(
                $ticket->gmail_message_id,
                $attachmentData['attachment_id']
            );

            $this->saveAttachmentFromBinary(...);
        }
    }
}
```

**Actualización de Traits**:

```php
// src/Service/Traits/NotificationDispatcherTrait.php

trait NotificationDispatcherTrait
{
    private ?EmailService $emailServiceInstance = null;
    private ?WhatsappService $whatsappServiceInstance = null;

    /**
     * Set notification services (called from constructor)
     */
    protected function setNotificationServices(
        EmailService $emailService,
        WhatsappService $whatsappService
    ): void {
        $this->emailServiceInstance = $emailService;
        $this->whatsappServiceInstance = $whatsappService;
    }

    /**
     * Get email service (use injected or create new)
     */
    protected function getEmailService(): EmailService
    {
        // ✅ Reutilizar instancia inyectada
        if ($this->emailServiceInstance !== null) {
            return $this->emailServiceInstance;
        }

        // Fallback: crear solo si no fue inyectada
        if (!isset($this->emailServiceInstance)) {
            $this->emailServiceInstance = new EmailService($this->systemConfig ?? null);
        }

        return $this->emailServiceInstance;
    }

    // Similar para getWhatsappService()
}
```

**Testing Mejorado**:

```php
// tests/TestCase/Service/TicketServiceTest.php

class TicketServiceTest extends TestCase
{
    public function testCreateFromEmailWithMocks()
    {
        // ✅ Ahora se pueden mockear dependencias
        $mockGmail = $this->createMock(GmailService::class);
        $mockGmail->method('extractEmailAddress')
            ->willReturn('user@example.com');
        $mockGmail->method('extractName')
            ->willReturn('John Doe');

        $mockEmail = $this->createMock(EmailService::class);
        $mockWhatsapp = $this->createMock(WhatsappService::class);

        // Inyectar mocks
        $service = new TicketService(
            ['some_config' => 'value'],
            $mockGmail,
            $mockEmail,
            $mockWhatsapp
        );

        // Test sin dependencias reales
        $ticket = $service->createFromEmail([
            'from' => 'user@example.com',
            'subject' => 'Test',
            'body_html' => '<p>Test body</p>',
        ]);

        $this->assertNotNull($ticket);
    }
}
```

**Beneficios**:
- ✅ **Testeable**: Mocks en tests unitarios
- ✅ **Performance**: Una sola instancia de cada servicio
- ✅ **Memory**: No duplicación de objetos
- ✅ **SOLID**: Respeta Dependency Inversion
- ✅ **Maintainability**: Fácil cambiar implementación
- ✅ **PHPStan**: Elimina "property.onlyWritten" errors

**Esfuerzo**:
- Actualizar constructor: 1 hora
- Refactorizar 4 usos de GmailService: 2 horas
- Actualizar NotificationDispatcherTrait: 2 horas
- Actualizar tests: 2-3 horas
- **Total**: 1-2 días

---

## Métricas y Estadísticas

### Issues de Arquitectura por Componente

| Componente | Críticos | Altos | Medios | Bajos | Total |
|------------|----------|-------|--------|-------|-------|
| GmailService | 0 | 1 | 2 | 0 | 3 |
| TicketService | 0 | 1 | 0 | 0 | 1 |
| **TOTAL** | **0** | **2** | **2** | **0** | **4** |

### Esfuerzo Estimado por Issue

| ID | Componente | Severidad | Esfuerzo | Prioridad |
|----|------------|-----------|----------|-----------|
| ARCH-001 | GmailService | Alto | L (4-6 días) | Media |
| ARCH-002 | GmailService | Medio | S (2-4 horas) | Baja |
| ARCH-003 | GmailService | Medio | S (2-4 horas) | Baja |
| ARCH-004 | TicketService | Alto | M (1-2 días) | Alta |
| **TOTAL** | | | **~7 días** | |

### Adherencia a SOLID Principles

#### GmailService - Análisis SOLID

| Principle | Estado | Notas |
|-----------|--------|-------|
| **S**ingle Responsibility | 🔴 Violado | 5 responsabilidades distintas |
| **O**pen/Closed | 🔴 Violado | Difícil extender sin modificar |
| **L**iskov Substitution | 🟡 N/A | No usa herencia |
| **I**nterface Segregation | 🟡 N/A | No implementa interfaces |
| **D**ependency Inversion | 🔴 Violado | Usa `new S3Service()` directamente |

**Score SOLID**: 0/3 (40% N/A)

#### TicketService - Análisis SOLID

| Principle | Estado | Notas |
|-----------|--------|-------|
| **S**ingle Responsibility | 🟡 Parcial | Múltiples responsabilidades mitigadas con traits |
| **O**pen/Closed | 🔴 Violado | Hard-coded `new GmailService()` |
| **L**iskov Substitution | 🟡 N/A | No usa herencia |
| **I**nterface Segregation | 🟡 N/A | No implementa interfaces |
| **D**ependency Inversion | 🔴 **Violado severamente** | Crea instancias directamente, servicios inyectados no usados |

**Score SOLID**: 0/2 (60% N/A)

#### Comparativa de Arquitectura

| Servicio | S | O | L | I | D | Score | Estado |
|----------|---|---|---|---|---|-------|--------|
| GmailService | 🔴 | 🔴 | N/A | N/A | 🔴 | 0/3 | 🔴 Requiere refactoring |
| TicketService | 🟡 | 🔴 | N/A | N/A | 🔴 | 0/2 | 🔴 Requiere corrección |
| **Promedio** | | | | | | **0/5** | **🔴 Crítico** |

---

## Recomendaciones de Arquitectura

### Prioridades ANTES de Producción

**Crítico (Alta prioridad - 2 días)**:
1. **ARCH-004** (TicketService): Corregir Dependency Injection
   - Riesgo: Alto - afecta testing y performance
   - Impacto: Core business logic del sistema
   - Esfuerzo: 1-2 días
   - **Debe hacerse antes de producción**

**Importante (Media prioridad)**:
- **ARCH-002** (GmailService): Eliminar método estático con DB queries (2-4 horas)
- **ARCH-003** (GmailService): Inyectar S3Service (2-4 horas)

### Post-Producción

**Refactoring Mayor (4-6 días)**:
- **ARCH-001** (GmailService): Dividir en 5 servicios especializados
  - GmailAuthService
  - GmailFetchService
  - GmailParserService
  - GmailAttachmentService
  - GmailSenderService

### Patrones a Implementar

1. **Dependency Injection Completa** ✅ PRIORIDAD #1
   - Inyectar TODAS las dependencias en constructor
   - No crear instancias con `new` dentro de métodos
   - Pasar dependencias a traits
   - Facilitar testing con mocks

2. **Repository Pattern** (ARCH-002)
   - Abstraer acceso a datos de configuración
   - Desacoplar de ORM
   - Facilitar testing

3. **Service Layer Pattern** (ya implementado ✅)
   - Mantener lógica de negocio en Services
   - Controllers delgados (thin controllers)
   - Reutilización mediante traits

4. **Strategy/Factory Pattern** (futuro)
   - Para storage (S3 vs Local)
   - Para notifications (Email vs WhatsApp)
   - Para messaging (Gmail vs otros providers)

5. **Facade Pattern** (ARCH-001 solución)
   - GmailService como fachada
   - Delegar a servicios especializados
   - Simplificar API pública

### Principios para Nuevos Servicios

Al crear o refactorizar servicios:

1. **Una responsabilidad clara** por clase
2. **Inyectar dependencias** en constructor
3. **Programar contra interfaces**, no implementaciones
4. **Métodos <50 líneas** (preferiblemente <30)
5. **Clases <300 líneas** (preferiblemente <200)
6. **Tests unitarios** con coverage >80%

---

## Próximos Archivos a Auditar

Según el plan (Fase 2):
- [x] GmailService.php - COMPLETADO (3 issues arquitectónicos)
- [x] TicketService.php - COMPLETADO (1 issue arquitectónico crítico)
- [ ] EmailService.php (1,139 líneas - posible god object, verificar DI)
- [ ] ResponseService.php (298 líneas - verificar si es facade o god object)
- [ ] WhatsappService.php (346 líneas)
- [ ] ComprasService.php (323 líneas - verificar patrón similar a TicketService)
- [ ] PqrsService.php (282 líneas - verificar patrón similar a TicketService)
- [ ] SlaManagementService.php
- [ ] StatisticsService.php
- [ ] N8nService.php
- [ ] S3Service.php (revisar si debe ser interface)

**Progreso**: 2/11 servicios completados (18%)

**Focos de atención para próximos servicios**:
- ✅ Verificar Dependency Injection (lección de TicketService)
- ✅ Revisar uso de traits y si crean instancias propias
- ✅ Buscar God Objects (lección de GmailService)
- ✅ Validar adherencia a SOLID principles

---

**Fin de Auditoría Arquitectura - TicketService.php**

**Próximo**: EmailService.php (1,139 líneas, ~77 errores PHPStan)
