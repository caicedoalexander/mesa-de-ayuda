# PLAN DE RESOLUCIÓN - ISSUES ADICIONALES

Issues documentados en este archivo complementario que se fusionarán al plan principal.

---

## ARCH-003: S3Service no inyectado en GmailService

**Severidad**: 🔵 Bajo | **Esfuerzo**: 2-4 horas

### Root Cause
Creación directa de `new S3Service()` en múltiples métodos sin inyección. Feature S3 agregado después sin refactorizar DI.

### Solución
```php
class GmailService
{
    private S3Service $s3Service;

    public function __construct(
        ?array $config = null,
        ?S3Service $s3Service = null
    ) {
        $this->config = $config ?? [];
        $this->s3Service = $s3Service ?? new S3Service();
        $this->initializeClient();
    }

    // Usar $this->s3Service en lugar de new S3Service()
}
```

### Beneficios
- Testeable con mocks
- Una instancia única
- Sigue DI principle

---

## COM-002: Recursión sin límite en extractMessageParts

**Severidad**: 🟡 Medio | **Esfuerzo**: <2 horas

### Root Cause
Método recursivo que procesa estructura MIME anidada sin límite de profundidad. Email malicioso puede causar stack overflow.

### Solución
```php
private const MAX_MIME_DEPTH = 20;

private function extractMessageParts($payload, array &$data, int $depth = 0): void
{
    if ($depth > self::MAX_MIME_DEPTH) {
        Log::warning('Email exceeded max MIME depth', ['depth' => $depth]);
        return;
    }

    // ... procesamiento ...

    if (!empty($parts)) {
        foreach ($parts as $part) {
            $this->extractMessageParts($part, $data, $depth + 1);
        }
    }
}
```

### Beneficios
- Protección contra DoS
- Worker estable
- Logging de emails sospechosos

---

## SMELL-002: Validación inconsistente de file_exists

**Severidad**: 🔵 Bajo | **Esfuerzo**: <1 hora

### Root Cause
Algunos métodos verifican `file_exists()` antes de usar archivo, otros no. Inconsistencia causa confusion.

### Solución
Agregar validación consistente en TODOS los métodos que leen archivos:

```php
private function loadCredentialsFile(string $path): ?array
{
    if (!file_exists($path)) {
        Log::warning("Credentials file not found: {$path}");
        return null;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        Log::error("Failed to read credentials file: {$path}");
        return null;
    }

    return json_decode($content, true);
}
```

**Pattern**: SIEMPRE verificar antes de leer/escribir archivos.

---

## ARCH-009: HTTP Client hardcodeado en WhatsappService

**Severidad**: 🔵 Bajo | **Esfuerzo**: 2-3 horas

### Root Cause
Usa cURL directamente en lugar de abstracción HTTP. Dificulta testing y flexibilidad.

### Solución
Inyectar HTTP client compatible con PSR-18:

```php
use Psr\Http\Client\ClientInterface;
use GuzzleHttp\Client;

class WhatsappService
{
    private ClientInterface $httpClient;

    public function __construct(
        ?ClientInterface $httpClient = null,
        ?array $systemConfig = null
    ) {
        $this->systemConfig = $systemConfig ?? [];
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => 30,
            'verify' => true,
        ]);
    }

    public function sendMessage(string $number, string $message): bool
    {
        $response = $this->httpClient->request('POST', $this->apiUrl . '/sendText', [
            'json' => [
                'number' => $number,
                'text' => $message,
            ],
            'headers' => [
                'apikey' => $this->apiKey,
            ],
        ]);

        return $response->getStatusCode() === 200;
    }
}
```

### Testing con Mocks
```php
public function testSendMessageWithMockedClient()
{
    $mockClient = $this->createMock(ClientInterface::class);
    $mockResponse = $this->createMock(ResponseInterface::class);

    $mockResponse->method('getStatusCode')->willReturn(200);
    $mockClient->expects($this->once())
        ->method('request')
        ->willReturn($mockResponse);

    $service = new WhatsappService($mockClient);
    $result = $service->sendMessage('1234567890', 'Test');

    $this->assertTrue($result);
}
```

---

## ARCH-012: cURL hardcoded en N8nService

**Severidad**: 🔵 Bajo | **Esfuerzo**: 2-3 horas

### Root Cause
Mismo problema que ARCH-009. Usa cURL directamente.

### Solución
Idéntica a ARCH-009 - inyectar ClientInterface:

```php
class N8nService
{
    private ClientInterface $httpClient;

    public function __construct(
        ?ClientInterface $httpClient = null,
        ?array $systemConfig = null
    ) {
        $this->systemConfig = $systemConfig ?? [];
        $this->httpClient = $httpClient ?? $this->getDefaultClient();
    }

    private function getDefaultClient(): ClientInterface
    {
        $isDebug = env('APP_DEBUG', false);
        $isDev = env('APP_ENV') === 'development';

        return new Client([
            'timeout' => 30,
            'verify' => !($isDebug && $isDev),  // SSL verification
        ]);
    }
}
```

---

## SEC-002: AWS Credentials desde Configure

**Severidad**: 🟡 Medio | **Esfuerzo**: 1-2 horas

### Root Cause
S3Service lee credenciales AWS desde `Configure::read('S3')` hardcoded. Debería usar variables de entorno.

**Evidencia actual**:
```php
public function __construct()
{
    $config = Configure::read('S3');  // ❌ Hardcoded
    $this->bucket = $config['bucket'] ?? '';
    $this->credentials = $config['credentials'] ?? [];
}
```

### Solución
Leer de variables de entorno:

```php
public function __construct(?array $config = null)
{
    if ($config === null) {
        $config = $this->loadFromEnvironment();
    }

    $this->enabled = $config['enabled'] ?? false;
    $this->bucket = $config['bucket'] ?? '';
    $this->region = $config['region'] ?? 'us-east-1';
    $this->credentials = $config['credentials'] ?? [];

    if ($this->enabled) {
        $this->initializeClient();
    }
}

private function loadFromEnvironment(): array
{
    return [
        'enabled' => filter_var(env('S3_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'bucket' => env('S3_BUCKET', ''),
        'region' => env('S3_REGION', 'us-east-1'),
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID', ''),
            'secret' => env('AWS_SECRET_ACCESS_KEY', ''),
        ],
    ];
}
```

**Configuración en .env**:
```env
S3_ENABLED=true
S3_BUCKET=mesa-ayuda-uploads
S3_REGION=us-east-1
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=...
```

### Beneficios
- Sigue 12-factor app
- Secrets en environment, no en código
- Más fácil rotación de credenciales
- Compatible con Docker secrets

---

## ARCH-014: Dependencia en CakePHP Configure (S3Service)

**Severidad**: 🔵 Bajo | **Esfuerzo**: incluido en SEC-002

### Root Cause
Acoplamiento fuerte a `Configure::read()` estático. Dificulta testing.

### Solución
Ya resuelta en SEC-002 - inyectar config en constructor elimina dependencia de Configure.

---

## MODEL-003: DocBlocks incompletos en Tables

**Severidad**: 🔵 Bajo | **Esfuerzo**: 2-3 horas

### Root Cause
Algunas Tables tienen DocBlocks autogenerados pero no actualizados cuando se agregan métodos custom.

### Solución
Agregar DocBlocks completos a métodos públicos:

```php
/**
 * Find tickets with filters and views
 *
 * Custom finder que aplica filtros dinámicos basados en:
 * - Vista seleccionada (sin_asignar, mis_tickets, etc.)
 * - Búsqueda por texto en múltiples campos
 * - Filtros específicos (status, priority, assignee, dates)
 *
 * @param \Cake\ORM\Query\SelectQuery $query Query object
 * @param array $options Options array with:
 *   - filters: array Filter criteria
 *   - view: string View name
 *   - user: \App\Model\Entity\User Current user for user-specific filters
 * @return \Cake\ORM\Query\SelectQuery Modified query
 */
public function findWithFilters(SelectQuery $query, array $options): SelectQuery
{
    // ...
}
```

**Script para verificar**:
```bash
# Buscar métodos públicos sin DocBlock
phpstan analyze src/Model/Table --level=5 | grep "PHPDoc tag @return"
```

---

## MODEL-004: PHPStan propertyTag errors

**Severidad**: 🔵 Bajo | **Esfuerzo**: 1-2 horas

### Root Cause
PHPStan reporta ~24 errores de @property tags en DocBlocks de Tables. Algunos tags están desactualizados o incorrectos.

### Solución
Actualizar @property tags en clase DocBlocks:

```php
/**
 * Tickets Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Requesters
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Assignees
 * @property \App\Model\Table\TicketCommentsTable&\Cake\ORM\Association\HasMany $TicketComments
 * @property \App\Model\Table\AttachmentsTable&\Cake\ORM\Association\HasMany $Attachments
 * @property \App\Model\Table\TagsTable&\Cake\ORM\Association\BelongsToMany $Tags
 *
 * @method \App\Model\Entity\Ticket newEmptyEntity()
 * @method \App\Model\Entity\Ticket newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Ticket get(mixed $primaryKey, ...)
 * @method \App\Model\Entity\Ticket findOrCreate($search, ?callable $callback = null, ...)
 * @method \App\Model\Entity\Ticket patchEntity(\Cake\Datasource\EntityInterface $entity, ...)
 * @method \App\Model\Entity\Ticket|false save(\Cake\Datasource\EntityInterface $entity, ...)
 */
class TicketsTable extends Table
{
    // ...
}
```

**Herramienta**: CakePHP puede regenerar esto automáticamente:
```bash
bin/cake bake model Tickets --force --no-test
# Copiar solo los DocBlocks actualizados
```

---

## CTRL-001: Database queries in AppController::beforeFilter()

**Severidad**: 🟡 Medio | **Esfuerzo**: 2-3 horas

### Root Cause
`AppController::beforeFilter()` carga system_settings desde DB en CADA request. Afecta performance.

**Código actual**:
```php
public function beforeFilter(EventInterface $event)
{
    parent::beforeFilter($event);

    // ❌ Query en cada request
    $settingsTable = $this->fetchTable('SystemSettings');
    $settings = $settingsTable->find()->all();

    $this->set('systemSettings', $settings);
}
```

### Solución
Usar cache con TTL de 1 hora:

```php
use Cake\Cache\Cache;

public function beforeFilter(EventInterface $event)
{
    parent::beforeFilter($event);

    // ✅ Cache con 1 hora TTL
    $settings = Cache::remember('system_settings', function () {
        $settingsTable = $this->fetchTable('SystemSettings');
        return $settingsTable->find()->all()->toArray();
    }, 'default');

    $this->set('systemSettings', $settings);
}
```

**Invalidar cache cuando se actualizan settings**:
```php
// En Admin/SettingsController::save()
public function save()
{
    // ... guardar settings ...

    // Invalidar cache
    Cache::delete('system_settings', 'default');

    $this->Flash->success('Settings saved');
}
```

### Beneficios
- Performance: 1 query/hora en lugar de 1 query/request
- Escalabilidad: Reduce carga de DB significativamente
- Response time: ~50-100ms más rápido por request

---

## CTRL-002: FormProtection component disabled

**Severidad**: 🟡 Medio | **Esfuerzo**: 2-4 horas

### Root Cause
FormProtection deshabilitado globalmente, probablemente por problemas con AJAX requests.

**Código actual**:
```php
// AppController
public function initialize(): void
{
    parent::initialize();

    // ❌ Deshabilitado globalmente
    // $this->loadComponent('FormProtection');
}
```

### Solución
Re-habilitar con excepciones para API endpoints:

```php
public function initialize(): void
{
    parent::initialize();

    // ✅ Habilitar con configuración
    $this->loadComponent('Security', [
        'blackHoleCallback' => 'forceSSL',
        'requireSecure' => env('APP_ENV') === 'production',
    ]);

    $this->loadComponent('FormProtection', [
        'validate' => true,
        'unlockedActions' => [
            'api',  // Endpoints API sin CSRF
        ],
    ]);
}

// Deshabilitar solo para API endpoints específicos
public function beforeFilter(EventInterface $event)
{
    parent::beforeFilter($event);

    // Deshabilitar CSRF para API calls
    if ($this->request->is('ajax') && $this->request->getParam('prefix') === 'Api') {
        $this->getEventManager()->off($this->FormProtection);
    }
}
```

### Beneficios
- Seguridad: Protección CSRF en forms
- Flexibilidad: API endpoints pueden trabajar sin CSRF
- Best practice: FormProtection es recomendado por CakePHP

---

## CTRL-003: Direct database queries en TicketsController

**Severidad**: 🔵 Bajo | **Esfuerzo**: 1-2 horas

### Root Cause
Algunos métodos del controller hacen queries directas en lugar de usar métodos del servicio.

**Código actual**:
```php
// TicketsController::dashboard()
public function dashboard()
{
    // ❌ Query directa en controller
    $openTickets = $this->Tickets->find()
        ->where(['status NOT IN' => ['resuelto', 'cerrado']])
        ->count();

    $this->set('openTickets', $openTickets);
}
```

### Solución
Usar StatisticsService que ya existe:

```php
public function dashboard()
{
    // ✅ Usar servicio
    $stats = $this->statisticsService->getTicketStatistics();

    $this->set('stats', $stats);
}
```

O crear método en TicketsTable si no existe:

```php
// TicketsTable
public function getOpenCount(): int
{
    return $this->find()
        ->where(['status NOT IN' => ['resuelto', 'cerrado']])
        ->count();
}

// Controller
public function dashboard()
{
    $openTickets = $this->Tickets->getOpenCount();
    $this->set('openTickets', $openTickets);
}
```

---

## RESUMEN DE ISSUES DOCUMENTADOS EN ESTE ARCHIVO

Total: 15 issues

### Arquitectura (7)
- ARCH-003: S3Service no inyectado
- ARCH-009: HTTP Client hardcodeado (WhatsApp)
- ARCH-012: cURL hardcoded (N8n)
- ARCH-014: Dependencia en Configure

### Complejidad (1)
- COM-002: Recursión sin límite

### Code Smells (1)
- SMELL-002: file_exists inconsistente

### Seguridad (1)
- SEC-002: AWS credentials desde Configure

### Models (2)
- MODEL-003: DocBlocks incompletos
- MODEL-004: PHPStan propertyTag errors

### Controllers (3)
- CTRL-001: DB queries en beforeFilter
- CTRL-002: FormProtection disabled
- CTRL-003: Direct queries en controller

---

**Estos issues se fusionarán al plan principal una vez completados los agentes background.**
