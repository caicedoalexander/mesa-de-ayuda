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
