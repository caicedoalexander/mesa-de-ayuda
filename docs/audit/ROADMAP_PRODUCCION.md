# ROADMAP DE PRODUCCIÓN - Mesa de Ayuda

**Fecha**: 2026-01-13
**Versión**: 1.0
**Total Issues**: 77
**Estado Actual**: 🔴 NO GO - 2 bloqueadores críticos

---

## Resumen Ejecutivo

De los 77 issues encontrados en la auditoría:
- **2 son BLOQUEADORES** que impiden despliegue a producción
- **7 son CRÍTICOS** que deben resolverse antes de producción
- **28 son MEJORAS** recomendadas para post-producción
- **40 son OPCIONALES** que pueden posponerse indefinidamente

**Esfuerzo total**: ~50.3 días
**Esfuerzo pre-producción**: ~7 días (solo bloqueadores + críticos)
**Esfuerzo recomendado**: ~20 días (bloqueadores + críticos + mejoras prioritarias)

---

## Fase 0: BLOQUEADORES - Despliegue Imposible Sin Esto

**Duración**: 5-6 días
**Responsable**: Lead Developer + Security Team
**Status**: 🔴 DEBE completarse ANTES de producción

### BLK-001: N8nService SSL Verification Disabled (SEC-001)
**Severidad**: 🔴 CRÍTICO - Vulnerabilidad de Seguridad
**Esfuerzo**: 10 minutos
**Prioridad**: 1 (URGENTE)

**Problema**:
```php
// src/Service/N8nService.php línea 51
'verify' => false, // ⚠️ VULNERABLE A MITM ATTACKS
```

**Impacto**:
- Sistema vulnerable a ataques Man-in-the-Middle
- Certificados SSL falsos son aceptados
- Tráfico puede ser interceptado por atacantes
- **VIOLACIÓN de compliance de seguridad**

**Solución**:
```php
// Habilitar SSL verification:
'verify' => true,

// Si hay problemas con certificados autofirmados en dev:
'verify' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN) ? false : true,
```

**Testing requerido**:
1. Verificar que n8n usa certificado SSL válido
2. Test de conectividad en staging
3. Test de conectividad en producción

**Dependencias**: Ninguna
**Bloqueador para**: Despliegue a producción

---

### BLK-002: EmailService God Object (ARCH-005)

**Severidad**: 🔴 CRÍTICO - Arquitectura
**Esfuerzo**: 5-6 días
**Prioridad**: 2 (URGENTE)

**Problema**:
- 1,139 líneas en un solo archivo
- 80% de código duplicado entre métodos
- Maneja 3 módulos (Tickets/PQRS/Compras)
- 89 errores PHPStan
- Imposible de mantener

**Código duplicado**:
```php
// sendTicketCreatedEmail()      - 95 líneas
// sendPqrsCreatedEmail()         - 85 líneas (80% duplicado)
// sendCompraCreatedEmail()       - 80 líneas (80% duplicado)
// sendTicketCommentEmail()       - 90 líneas
// sendPqrsCommentEmail()         - 85 líneas (80% duplicado)
// sendCompraCommentEmail()       - 85 líneas (80% duplicado)
// ... 9 métodos más con duplicación
```

**Solución - Fase 1 (5-6 días)**:
```php
// 1. Extraer EmailTemplateService (1 día)
class EmailTemplateService {
    public function renderTemplate(string $template, array $data): string
    public function getTemplateVariables(string $entityType): array
}

// 2. Crear GenericEmailService base (1 día)
class GenericEmailService {
    public function sendEntityEmail(
        string $entityType,
        string $template,
        EntityInterface $entity,
        array $extraData = []
    ): bool
}

// 3. Refactorizar EmailService para usar GenericEmailService (2 días)
class EmailService {
    private GenericEmailService $genericEmailService;

    public function sendTicketCreatedEmail(Ticket $ticket): bool {
        return $this->genericEmailService->sendEntityEmail(
            'ticket',
            'nuevo_ticket',
            $ticket
        );
    }
}

// 4. Testing completo (1-2 días)
```

**Testing requerido**:
- Unit tests para GenericEmailService
- Integration tests para cada tipo de email
- Verificar que todos los templates funcionan
- Test de emails en staging

**Dependencias**: Ninguna
**Bloqueador para**: Mantenibilidad futura, onboarding de nuevos devs

---

## Fase 1: CRÍTICOS - Alta Prioridad Pre-Producción

**Duración**: 7-9 días adicionales (total: 12-15 días con bloqueadores)
**Status**: 🟡 Recomendado ANTES de producción

### CRIT-001: NotificationDispatcherTrait Property Dependencies (ARCH-016)

**Severidad**: 🔴 Alto
**Esfuerzo**: 2-3 días
**Prioridad**: 3

**Problema**:
ROOT CAUSE de 4 issues en servicios (ARCH-004, ARCH-007, ARCH-010, ARCH-011).
```php
// src/Service/Traits/NotificationDispatcherTrait.php
$this->emailService->{$methods['email']}($entity); // ⚠️ Asume propiedad
$this->whatsappService->{$methods['whatsapp']}($entity); // ⚠️ Asume propiedad
```

**Impacto**:
- 4 servicios con DI incorrecta
- No type-safe
- Difícil de testear

**Solución**:
```php
// Opción 1: Pasar services como parámetros
public function dispatchCreationNotifications(
    string $entityType,
    EntityInterface $entity,
    EmailService $emailService,
    WhatsappService $whatsappService,
    bool $sendEmail = true,
    bool $sendWhatsapp = true
): void

// Opción 2: Métodos abstractos
trait NotificationDispatcherTrait {
    abstract protected function getEmailService(): EmailService;
    abstract protected function getWhatsappService(): WhatsappService;

    protected function dispatchCreationNotifications(...) {
        $emailService = $this->getEmailService();
        // ...
    }
}
```

**Beneficio**: Resuelve automáticamente 4 issues de servicios al mismo tiempo.

**Dependencias**: BLK-002 (EmailService debe estar refactorizado primero)

---

### CRIT-002: GmailService Multiple Responsibilities (ARCH-001)

**Severidad**: 🔴 Alto
**Esfuerzo**: 3-4 días
**Prioridad**: 4

**Problema**:
805 líneas manejando 5 responsabilidades distintas:
1. OAuth2 authentication
2. Message fetching
3. Attachment downloads
4. Email parsing
5. Gmail API client management

**Solución**:
```php
// Dividir en servicios especializados:

// 1. GmailAuthService (OAuth2)
class GmailAuthService {
    public function getClient(): Google_Client
    public function refreshAccessToken(): void
}

// 2. GmailFetcherService (Message retrieval)
class GmailFetcherService {
    public function getMessages(int $maxResults): array
    public function markAsRead(string $messageId): void
}

// 3. GmailParserService (Email parsing)
class GmailParserService {
    public function parseMessage(Google_Service_Gmail_Message $message): array
    public function extractAttachments(Google_Service_Gmail_Message $message): array
}

// 4. GmailService (Facade simplificado)
class GmailService {
    // Coordina los 3 servicios anteriores
}
```

**Dependencias**: Ninguna (independiente de otros refactorings)

---

### CRIT-003: TicketSystemControllerTrait God Trait (CTRL-004)

**Severidad**: 🔴 Alto
**Esfuerzo**: 5-7 días
**Prioridad**: 5

**Problema**:
1,257 líneas - ARCHIVO MÁS GRANDE del proyecto
9+ responsabilidades distintas

**Solución propuesta** (ver CTRL-004 para detalles):
- Dividir en EntityCrudTrait (~200 líneas)
- Crear EntityIndexHelper class (~300 líneas)
- Crear EntityBulkOperationsService (~200 líneas)
- Mantener EntityViewTrait (~200 líneas)

**Impacto**: Reduce archivo más grande de 1,257 → ~200 líneas por componente

**Dependencias**: Ninguna

---

### CRIT-004: GenericAttachmentTrait Should Be Service (TRAIT-002)

**Severidad**: 🔴 Alto
**Esfuerzo**: 2-3 días
**Prioridad**: 6

**Problema**:
806 líneas en un trait - debería ser FileStorageService

**Solución**:
```php
// src/Service/FileStorageService.php
class FileStorageService {
    public function saveFile(
        UploadedFile $file,
        string $entityType,
        int $entityId
    ): Attachment

    public function validateFile(UploadedFile $file): bool
    public function getDownloadUrl(int $attachmentId): string
}

// Uso en servicios:
class TicketService {
    private FileStorageService $fileStorage;

    public function createTicket(...) {
        // ...
        $this->fileStorage->saveFile($file, 'ticket', $ticket->id);
    }
}
```

**Dependencias**: Ninguna

---

### CRIT-005: findWithFilters() Code Duplication (MODEL-001)

**Severidad**: 🔴 Alto
**Esfuerzo**: 3-4 días
**Prioridad**: 7

**Problema**:
~300 líneas duplicadas entre TicketsTable, ComprasTable, PqrsTable

**Solución**: Crear FilterableTrait (ver MODEL-001 para implementación completa)

**Beneficio**: Elimina ~270 líneas de código duplicado

**Dependencias**: Ninguna

---

### CRIT-006: StatisticsControllerTrait Property Dependencies (CTRL-006)

**Severidad**: 🔴 Alto
**Esfuerzo**: 1-2 días
**Prioridad**: 8

**Problema**:
Accede a `$this->statisticsService` y `$this->request` sin declararlas

**Solución**: Mismo patrón que CRIT-001 (inyectar como parámetros o métodos abstractos)

**Dependencias**: Ninguna

---

### CRIT-007: TicketService Creates GmailService Multiple Times (ARCH-008)

**Severidad**: 🔴 Alto
**Esfuerzo**: 1-2 días
**Prioridad**: 9

**Problema**:
```php
// Se crea GmailService 4 veces en diferentes métodos
$gmailService = new GmailService($this->systemConfig);
```

**Solución**:
```php
class TicketService {
    private ?GmailService $gmailService = null;

    public function __construct(
        ?array $systemConfig = null,
        ?GmailService $gmailService = null
    ) {
        $this->gmailService = $gmailService;
    }

    private function getGmailService(): GmailService {
        if ($this->gmailService === null) {
            $this->gmailService = new GmailService($this->systemConfig);
        }
        return $this->gmailService;
    }
}
```

**Dependencias**: CRIT-002 (GmailService refactor)

---

## Fase 2: MEJORAS PRIORITARIAS - Recomendadas Pre-Producción

**Duración**: 8-12 días adicionales
**Status**: 🟢 Opcional pero recomendado

### Grupo A: Dependency Injection Issues (5 issues)
- ARCH-003: TicketService unused injected services (1 día)
- ARCH-006: ResponseService creates services internally (1 día)
- ARCH-009: WhatsappService creates EmailService internally (1 día)
- ARCH-012: ComprasService creates services internally (1 día)
- ARCH-015: PqrsService creates services internally (1 día)

**Total**: 5 días
**Beneficio**: Type safety, testability, consistencia

---

### Grupo B: Code Quality Issues (3 issues)
- ARCH-002: GmailService long methods (2 días)
- CTRL-007: StatisticsControllerTrait long method (1-2 días)
- MODEL-002: generateXXXNumber() duplication (1-2 días)

**Total**: 4-6 días
**Beneficio**: Mantenibilidad, legibilidad

---

### Grupo C: Security & Best Practices (3 issues)
- CTRL-001: Database queries in AppController::beforeFilter() (2-4 horas)
- CTRL-002: FormProtection component disabled (1 hora)
- CTRL-003: Direct database queries in TicketsController (3-5 horas)

**Total**: 1 día
**Beneficio**: Seguridad CSRF, mejor arquitectura

---

## Fase 3: MEJORAS OPCIONALES - Post-Producción

**Duración**: 15-20 días
**Status**: ⚪ Puede posponerse

### Mejoras de Arquitectura (6 issues)
- ARCH-004, ARCH-007, ARCH-010, ARCH-011: Resueltos por CRIT-001
- ARCH-013: S3Service unused in EntityConversionTrait (2 días)
- ARCH-014: TicketSystemTrait large size (3 días)

**Total**: 5 días

### Mejoras de Traits (2 issues)
- TRAIT-001: TicketSystemTrait large but acceptable (3 días)
- TRAIT-003: ViewDataNormalizerTrait hardcoded config (2-4 horas) - NO RECOMENDADO

**Total**: 3 días

### PHPStan & Documentation (3 issues)
- CTRL-005: PHPStan trait property access errors (2-3 días)
- MODEL-003: Incomplete DocBlocks (1-2 horas)
- MODEL-004: PHPStan propertyTag errors (<1 hora) - Ignorar en config

**Total**: 2-3 días

### Total Fase 3: 10-11 días

---

## Fase 4: IGNORAR - No Requiere Acción

**Status**: ⚪ Documentado pero sin plan de acción

### Issues que NO deben cambiarse:
1. **TRAIT-003** (ViewDataNormalizerTrait hardcoded config):
   - El código actual es EXCELENTE
   - Type-safe, cached by opcache
   - Externalizar añadiría complejidad innecesaria

2. **MODEL-004** (PHPStan propertyTag errors):
   - Inherente a cómo CakePHP genera DocBlocks
   - Agregar ignore rule en phpstan.neon

3. **35+ LOW severity issues**:
   - No afectan funcionalidad
   - No afectan seguridad
   - Mejoras cosméticas

---

## Calendario Propuesto

### Opción 1: FAST TRACK (Solo Bloqueadores)
**Duración**: 5-6 días
**Risk**: Alto - Deuda técnica significativa

```
Día 1:     BLK-001 (N8nService SSL) - COMPLETADO
Días 2-6:  BLK-002 (EmailService refactor)
Día 7:     Testing & Deploy
```

**Resultado**: Sistema funcional pero difícil de mantener

---

### Opción 2: RECOMENDADO (Bloqueadores + Críticos)
**Duración**: 15-20 días
**Risk**: Medio - Deuda técnica controlada

```
Semana 1:
- Día 1: BLK-001 (N8nService SSL)
- Días 2-6: BLK-002 (EmailService refactor)

Semana 2:
- Días 8-10: CRIT-001 (NotificationDispatcherTrait)
- Días 11-13: CRIT-002 (GmailService split)
- Día 14: CRIT-006 (StatisticsControllerTrait)

Semana 3:
- Días 15-18: CRIT-003 (TicketSystemControllerTrait)
- Días 19-20: CRIT-004 (GenericAttachmentTrait)

Testing & Deploy: Días 21-22
```

**Resultado**: Sistema sólido, mantenible, listo para escalar

---

### Opción 3: IDEAL (Bloqueadores + Críticos + Mejoras)
**Duración**: 25-30 días
**Risk**: Bajo - Excelente calidad

```
Semanas 1-3: Igual que Opción 2

Semana 4:
- Grupo A: DI Issues (5 días)

Semana 5:
- Grupo B: Code Quality (4-6 días)
- Grupo C: Security (1 día)

Testing & Deploy: Días 31-32
```

**Resultado**: Sistema production-grade de alta calidad

---

## Criterios de Decisión

### ✅ Ir con FAST TRACK si:
- Presión extrema de tiempo
- MVP mínimo viable aceptable
- Equipo disponible para mantenimiento constante
- Plan claro para abordar deuda técnica post-launch

### ✅ Ir con RECOMENDADO si:
- Balance entre tiempo y calidad
- Equipo pequeño (<3 developers)
- Expectativa de crecimiento en 6 meses
- Presupuesto para ~3 semanas de desarrollo

### ✅ Ir con IDEAL si:
- Calidad es prioridad #1
- Equipo mediano/grande (3+ developers)
- Sistema mission-critical
- Budget para 1 mes de desarrollo pre-launch

---

## Recursos Necesarios

### Fast Track (6 días):
- 1 Senior Developer (Full-time)
- 1 QA Engineer (Part-time)

### Recomendado (20 días):
- 1 Senior Developer (Full-time)
- 1 Mid-level Developer (Full-time semanas 2-3)
- 1 QA Engineer (Part-time)

### Ideal (30 días):
- 1 Senior Developer (Full-time)
- 2 Mid-level Developers (Full-time)
- 1 QA Engineer (Half-time)
- 1 Security Reviewer (2-3 días)

---

## Métricas de Éxito

### Pre-Deploy:
- [ ] 0 errores PHPStan nivel 5 en archivos críticos
- [ ] BLK-001 y BLK-002 resueltos al 100%
- [ ] Code coverage >70% en servicios críticos
- [ ] Security scan sin vulnerabilidades HIGH/CRITICAL
- [ ] Load testing: 100 concurrent users sin degradación

### Post-Deploy (30 días):
- [ ] 0 bugs críticos reportados
- [ ] Tiempo de respuesta <500ms (p95)
- [ ] Uptime >99.5%
- [ ] 0 vulnerabilidades de seguridad
- [ ] Developer onboarding time <2 días

---

## Riesgos y Mitigaciones

### Riesgo 1: EmailService refactor rompe funcionalidad existente
**Probabilidad**: Media
**Impacto**: Alto
**Mitigación**:
- Tests exhaustivos antes de refactor
- Feature flags para rollback rápido
- Deploy gradual (canary deployment)

### Riesgo 2: Subestimación de esfuerzos
**Probabilidad**: Media
**Impacto**: Medio
**Mitigación**:
- Buffer de 20% en estimaciones
- Daily standups para tracking
- Re-priorización semanal

### Riesgo 3: Regresiones en funcionalidad existente
**Probabilidad**: Baja-Media
**Impacto**: Alto
**Mitigación**:
- Testing automatizado completo
- Staging environment idéntico a producción
- Smoke tests post-deploy

---

## Recomendación Final

**Ir con OPCIÓN 2: RECOMENDADO (15-20 días)**

**Justificación**:
1. Resuelve los 2 bloqueadores críticos de seguridad/arquitectura
2. Elimina 7 issues críticos que dificultan mantenimiento
3. Balance óptimo entre tiempo y calidad
4. Deuda técnica controlada y documentada
5. Sistema mantenible por equipo pequeño

**Deuda técnica restante post-Opción 2**:
- 28 mejoras de Medium severity (8-12 días adicionales)
- 40 mejoras de Low severity (15-20 días adicionales)
- Total deuda: ~25-30 días de trabajo

**Plan para deuda técnica**:
- Sprint post-launch dedicado (1-2 semanas)
- 20% de cada sprint futuro dedicado a mejoras
- Objetivo: Resolver Fase 2 en 3 meses post-launch

---

## Siguiente Paso Inmediato

**ACCIÓN REQUERIDA HOY**:

1. **Decisión de Management**: ¿Qué opción elegimos? (Fast Track / Recomendado / Ideal)

2. **Si Recomendado/Ideal**:
   - Crear branch `refactor/pre-production`
   - Asignar developers a BLK-001 (10 minutos)
   - Comenzar planning detallado de BLK-002 (EmailService)

3. **Setup de Testing**:
   - Crear staging environment
   - Setup CI/CD pipeline con PHPStan
   - Configurar coverage reports

**¿Listo para comenzar?** 🚀
