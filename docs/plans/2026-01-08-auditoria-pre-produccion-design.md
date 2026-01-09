# Plan de Auditoría Pre-Producción - Mesa de Ayuda

**Fecha de creación**: 2026-01-08
**Proyecto**: Mesa de Ayuda - CakePHP 5.x
**Tipo**: Auditoría exhaustiva pre-producción
**Enfoque**: Híbrido (Automatizado + Manual) con énfasis en calidad de código

---

## Tabla de Contenidos

1. [Estructura General del Plan](#1-estructura-general-del-plan)
2. [Fase 1: Diagnóstico Automatizado](#2-fase-1---diagnóstico-automatizado-día-1)
3. [Fase 2: Auditoría Manual Sistemática](#3-fase-2---auditoría-manual-sistemática-días-2-6)
4. [Fase 3: Análisis de Áreas Específicas](#4-fase-3---análisis-de-áreas-específicas-días-7-10)
5. [Fase 4: Consolidación y Roadmap](#5-fase-4---consolidación-y-roadmap-día-11)
6. [Ejecución del Plan](#6-ejecución-del-plan)
7. [Estructura de Documentos de Salida](#7-estructura-de-documentos-de-salida)
8. [Criterios de Éxito](#8-criterios-de-éxito)

---

## 1. Estructura General del Plan

### 1.1 Objetivos de la Auditoría

Realizar un assessment exhaustivo del proyecto Mesa de Ayuda antes de salir a producción, con énfasis en **calidad de código y mantenibilidad**, pero cubriendo todas las áreas críticas:

- **Calidad de código** (35% del esfuerzo) - Duplicación, complejidad, patrones, SOLID
- **Seguridad** (25% del esfuerzo) - OWASP Top 10, manejo de credenciales
- **Performance** (20% del esfuerzo) - Queries, caching, workers
- **Arquitectura** (20% del esfuerzo) - Separación de capas, service layer pattern

### 1.2 Enfoque: Híbrido - Análisis Automatizado + Manual

**Fase 1**: Diagnóstico automatizado usando herramientas existentes (PHPStan, PHPCS, PHPUnit)
**Fase 2**: Auditoría manual sistemática priorizando archivos con issues detectados
**Fase 3**: Análisis de áreas específicas (seguridad, integraciones, performance)
**Fase 4**: Consolidación y generación de roadmap

### 1.3 Entregables

5 documentos especializados en `docs/audit/`:

1. **`AUDITORIA_CALIDAD_CODIGO.md`** - Duplicación, complejidad, patterns, testing
2. **`AUDITORIA_SEGURIDAD.md`** - Vulnerabilidades, auth, inputs, credenciales
3. **`AUDITORIA_PERFORMANCE.md`** - Queries, caching, workers, archivos
4. **`AUDITORIA_ARQUITECTURA.md`** - Service layer, SOLID, separación de responsabilidades
5. **`ROADMAP_PRODUCCION.md`** - Resumen ejecutivo, prioridades, estimaciones

### 1.4 Estrategia de Fixes

**Solo documentación** - No se harán refactorings durante la auditoría. Cada issue documentado incluirá:

- **Severidad** (Crítico/Alto/Medio/Bajo)
- **Ubicación exacta** (archivo:línea)
- **Descripción del problema**
- **Recomendación de solución**
- **Estimación de esfuerzo** (XS/S/M/L/XL)

### 1.5 Contexto del Proyecto

**Estadísticas actuales:**
- 88 archivos PHP en `src/`
- 25 archivos de tests
- 11 servicios principales
- 5 service traits
- 11 controllers (8 principales + 3 admin)
- 4 controller traits
- 17 tables + 17 entities
- 3 CLI commands
- 81 templates
- 27 migraciones activas + 40+ legacy

**Módulos principales:**
- **Soporte Interno (Tickets)**: Sistema de helpdesk interno con Gmail-to-Ticket
- **Gestión de Compras**: Purchase requests con workflows de aprobación
- **PQRS External**: Sistema público de peticiones/quejas/reclamos

**Integraciones externas:**
- Gmail API (OAuth2)
- WhatsApp Business (Evolution API)
- n8n (Webhooks para AI tagging)
- AWS S3 (File storage)

---

## 2. Fase 1 - Diagnóstico Automatizado (Día 1)

### 2.1 Objetivo

Ejecutar análisis estático con herramientas existentes para identificar hotspots y priorizar la auditoría manual.

### 2.2 Actividades

#### 2.2.1 PHPStan - Análisis de tipos y errores lógicos

```bash
composer stan -- --error-format=table > docs/audit/phpstan-results.txt
```

**Verificar:**
- Nivel 5 actual pasa sin errores
- Identificar archivos con más warnings
- Documentar ignorados en `phpstan.neon` que podrían ser issues reales

**Salida esperada:**
- Lista de archivos con más issues
- Tipos de errores más comunes
- Candidatos para revisión manual profunda

#### 2.2.2 PHPCS - Estándares de código CakePHP

```bash
composer cs-check > docs/audit/phpcs-results.txt
```

**Verificar:**
- Violaciones de estándares PSR-12
- Inconsistencias de formato
- Archivos con >10 violaciones para revisión manual

**Salida esperada:**
- Total de violaciones por tipo
- Archivos con peor score
- Patrones de violaciones comunes

#### 2.2.3 PHPUnit - Cobertura de tests actual

```bash
vendor/bin/phpunit --coverage-html docs/audit/coverage --coverage-text > docs/audit/phpunit-results.txt
```

**Verificar:**
- Generar reporte de cobertura actual (~25 tests)
- Identificar clases críticas sin tests (Services, Commands)
- Documentar gaps de testing

**Salida esperada:**
- Porcentaje de cobertura por clase
- Lista de archivos sin tests
- Áreas críticas desprotegidas

#### 2.2.4 Análisis manual de métricas básicas

**Archivos con alta complejidad:**
```bash
# Contar líneas por archivo (>500 líneas son candidatos)
find src -name "*.php" -exec wc -l {} + | sort -rn > docs/audit/lines-per-file.txt
```

**Métodos largos:**
- Identificar métodos con >50 líneas de código (revisar manualmente durante Fase 2)

**Clases grandes:**
- Listar clases con >10 métodos públicos (candidatos a violación SRP)

### 2.3 Salida de Fase 1

Un archivo **`docs/audit/DIAGNOSTICO_AUTOMATIZADO.md`** con:

- Tabla de archivos priorizados por cantidad de issues
- Lista de hotspots de complejidad
- Mapa de cobertura de tests
- Issues críticos detectados por herramientas
- Recomendación de orden de revisión para Fase 2

---

## 3. Fase 2 - Auditoría Manual Sistemática (Días 2-6)

### 3.1 Objetivo

Revisión manual profunda del código, priorizando archivos identificados en Fase 1, enfocada en calidad, duplicación, complejidad y patrones arquitectónicos.

### 3.2 Metodología de Revisión

Para cada archivo revisado, documentar:

- **Duplicación**: Código repetido entre Tickets/PQRS/Compras que debería estar en traits
- **Complejidad**: Métodos >50 líneas, anidamiento >4 niveles, condicionales complejos
- **Violaciones SOLID**: Clases con múltiples responsabilidades, acoplamiento alto
- **Patrones incorrectos**: Lógica de negocio en controllers, queries directas en vistas
- **Code smells**: Variables poco descriptivas, magic numbers, comentarios obsoletos

### 3.3 Subsección 2.1: Services Layer (Días 2-3)

#### 3.3.1 Servicios Principales (11 archivos)

Revisar en este orden (priorizando por criticidad):

1. **`GmailService.php`** - OAuth2, fetching, parsing (integración crítica)
2. **`TicketService.php`** - Core business logic de tickets
3. **`ResponseService.php`** - Facade pattern, verificar si es necesario o sobre-ingeniería
4. **`EmailService.php`** - Transactional emails
5. **`WhatsappService.php`** - Notificaciones externas
6. **`ComprasService.php`** - Purchase workflows
7. **`PqrsService.php`** - External PQRS
8. **`S3Service.php`** - File storage
9. **`SlaManagementService.php`** - SLA tracking
10. **`N8nService.php`** - Webhook integration
11. **`StatisticsService.php`** - Reporting

**Para cada servicio verificar:**
- ✓ Acepta `?array $systemConfig = null` (patrón documentado en CLAUDE.md)
- ✓ No hace queries directas (debe usar Tables)
- ✓ Manejo de errores con try-catch apropiados
- ✓ Métodos cohesivos (<50 líneas idealmente)
- ✓ Dependencias inyectadas correctamente
- ✗ NO contiene lógica de presentación
- ✗ NO tiene acoplamiento con controllers

#### 3.3.2 Service Traits (5 archivos)

1. **`TicketSystemTrait.php`** - ¿Se usa consistentemente en los 3 módulos?
2. **`NotificationDispatcherTrait.php`** - ¿Maneja errores de integraciones externas?
3. **`GenericAttachmentTrait.php`** - ¿Funciona igual para S3 y local?
4. **`StatisticsServiceTrait.php`** - ¿Evita duplicación real?
5. **`EntityConversionTrait.php`** - ¿Performance adecuado?

**Verificar:**
- ✓ Traits usados en múltiples lugares (no premature abstraction)
- ✓ Cohesión de métodos dentro del trait
- ✓ Documentación clara de uso
- ✗ NO son "god traits" con demasiadas responsabilidades

### 3.4 Subsección 2.2: Controllers (Días 4-5)

#### 3.4.1 Principio: Thin Controllers

Verificar que controllers solo:
- Reciben request
- Validan input básico
- Llaman a services
- Preparan respuesta/vista
- **NO contienen lógica de negocio**

#### 3.4.2 Controladores Principales (8 archivos)

1. **`AppController.php`** - Base, auth, settings loading
2. **`TicketsController.php`** - CRUD delegation
3. **`ComprasController.php`** - CRUD delegation
4. **`PqrsController.php`** - CRUD + public form
5. **`UsersController.php`** - User management
6. **`ErrorController.php`** - Error handling
7. **`HealthController.php`** - Health check
8. **`PagesController.php`** - Static pages

#### 3.4.3 Controladores Admin (3 archivos)

1. **`Admin/SettingsController.php`** - System config
2. **`Admin/SlaManagementController.php`** - SLA config
3. **`Admin/ConfigFilesController.php`** - File management

#### 3.4.4 Controller Traits (4 archivos)

1. **`ServiceInitializerTrait.php`** - DI helper
2. **`ViewDataNormalizerTrait.php`** - Formatting
3. **`StatisticsControllerTrait.php`** - Dashboard logic (¿debería estar en service?)
4. **`TicketSystemControllerTrait.php`** - Shared CRUD

#### 3.4.5 Para cada controller verificar:

- ✗ NO contiene queries directas
- ✗ NO contiene lógica de negocio compleja
- ✓ Métodos <30 líneas
- ✓ Usa services para operaciones
- ✓ Autorización correcta por rol
- ✓ Validación de input
- ✓ Manejo apropiado de errores

### 3.5 Subsección 2.3: Models (Día 6)

#### 3.5.1 Tables (17 archivos)

Revisar en grupos:

**Grupo Core (5):**
- `OrganizationsTable.php`
- `UsersTable.php`
- `SystemSettingsTable.php`
- `EmailTemplatesTable.php`
- `TagsTable.php`

**Grupo Tickets (6):**
- `TicketsTable.php`
- `TicketCommentsTable.php`
- `AttachmentsTable.php`
- `TicketHistoryTable.php`
- `TicketsTagsTable.php`
- `TicketFollowersTable.php`

**Grupo Compras (4):**
- `ComprasTable.php`
- `ComprasCommentsTable.php`
- `ComprasAttachmentsTable.php`
- `ComprasHistoryTable.php`

**Grupo PQRS (4):**
- `PqrsTable.php`
- `PqrsCommentsTable.php`
- `PqrsAttachmentsTable.php`
- `PqrsHistoryTable.php`

#### 3.5.2 Para cada Table verificar:

- ✓ Associations correctas (belongsTo, hasMany, belongsToMany)
- ✓ Validation rules completas
- ✓ Behaviors apropiados (Timestamp, etc.)
- ✗ NO contiene lógica de negocio (debe estar en Services)
- ✓ Custom finders apropiados si existen
- ✓ Índices definidos en migraciones

#### 3.5.3 Entities (17 archivos)

- Verificar si son solo auto-generados o tienen lógica custom
- Si tienen lógica, verificar que sean solo accessors/mutators simples
- ✗ NO deben tener lógica de negocio compleja
- ✓ Hidden fields configurados para passwords/tokens

### 3.6 Salida de Fase 2

Dos archivos actualizados con findings:

- **`docs/audit/AUDITORIA_CALIDAD_CODIGO.md`** - Issues de duplicación, complejidad, patterns
- **`docs/audit/AUDITORIA_ARQUITECTURA.md`** - Violaciones del service layer pattern, SOLID

Ambos con tablas de issues priorizadas por severidad.

---

## 4. Fase 3 - Análisis de Áreas Específicas (Días 7-10)

### 4.1 Objetivo

Auditoría profunda de áreas críticas que requieren expertise especializado: seguridad, performance, integraciones externas, y workers background.

### 4.2 Subsección 3.1: Seguridad (Días 7-8)

#### 4.2.1 Autenticación y Autorización

**Archivos a revisar:**
- `src/Controller/AppController.php` - Configuración de Authentication plugin
- `src/Model/Table/UsersTable.php` - Password hashing, validation
- Middleware de autenticación
- Control de acceso por roles (admin, agent, requester, compras, servicio_cliente)

**Checklist de revisión:**
- ✓ Passwords hasheados con DefaultPasswordHasher (bcrypt)
- ✓ Sessions configuradas con seguridad (httpOnly, secure en producción)
- ✓ CSRF protection habilitada
- ✓ Rutas admin protegidas correctamente
- ✓ `redirectByRole()` previene escalación de privilegios
- ✗ NO hay hardcoded credentials en código

#### 4.2.2 Inyección SQL y ORM

**Archivos a revisar:**
- Tables con custom finders
- Controllers que pasan parámetros a queries
- Services con búsquedas dinámicas

**Verificar:**
- ✓ Uso correcto de Query Builder (sin SQL raw vulnerable)
- ✓ Parámetros sanitizados cuando se usa `where()`
- ✗ NO se concatenan strings en queries
- ✓ Uso de prepared statements si hay SQL directo
- ✓ No hay `Connection::execute()` con input sin sanitizar

#### 4.2.3 XSS y Output Escaping

**Archivos a revisar:**
- Templates en `templates/` - uso de `h()` helper
- View Helpers personalizados
- Respuestas JSON sin sanitizar

**Verificar:**
- ✓ Todas las variables user-input escapadas con `h()`
- ✓ HTMLPurifier usado en `TicketService` para emails
- ✓ Content-Type headers correctos en respuestas JSON
- ✗ NO hay `echo` directo de input sin sanitizar
- ✓ JavaScript escapado en atributos HTML

#### 4.2.4 Manejo de Archivos

**Archivos a revisar:**
- `src/Service/Traits/GenericAttachmentTrait.php` - File uploads
- `src/Service/S3Service.php` - Storage
- `src/Model/Table/AttachmentsTable.php` y variantes

**Verificar:**
- ✓ Validación de tipos MIME
- ✓ Validación de extensiones permitidas (whitelist, no blacklist)
- ✓ Tamaño máximo de archivos configurado
- ✓ Nombres de archivo sanitizados (evitar path traversal)
- ✓ Archivos no ejecutables en webroot
- ✓ URLs de descarga con autenticación/autorización
- ✗ NO se ejecutan archivos subidos
- ✓ Archivos almacenados fuera de webroot o en S3

#### 4.2.5 Datos Sensibles

**Archivos a revisar:**
- `src/Model/Table/SystemSettingsTable.php` - Tokens, API keys
- `src/Utility/SettingsEncryptionTrait.php` - Encryption
- `config/app_local.php` - Configuration
- `.env` - Environment variables

**Verificar:**
- ✓ Tokens OAuth2 encriptados en BD
- ✓ `.env` y `app_local.php` en `.gitignore`
- ✓ No hay secrets en logs
- ✓ API keys no expuestas en respuestas JSON
- ✓ Encryption key seguro en `SECURITY_SALT`
- ✓ Credenciales no en código fuente
- ✓ No hay secrets en git history

#### 4.2.6 OWASP Top 10 - Otras vulnerabilidades

**A01 - Broken Access Control:**
- Verificar autorización en cada endpoint
- Verificar IDOR (Insecure Direct Object Reference)

**A02 - Cryptographic Failures:**
- Verificar uso de HTTPS en producción
- Verificar encriptación de datos sensibles

**A03 - Injection:**
- Ya cubierto en 4.2.2 (SQL)
- Verificar command injection en CLI commands

**A04 - Insecure Design:**
- Revisar diseño de features de seguridad

**A05 - Security Misconfiguration:**
- Revisar `config/app.php` para producción
- Verificar headers de seguridad (HSTS, X-Frame-Options, CSP)

**A06 - Vulnerable and Outdated Components:**
```bash
composer audit
```

**A07 - Identification and Authentication Failures:**
- Ya cubierto en 4.2.1

**A08 - Software and Data Integrity Failures:**
- Verificar validación de webhooks (n8n signature)

**A09 - Security Logging and Monitoring Failures:**
- Verificar logging de eventos de seguridad
- Verificar no se loggean datos sensibles

**A10 - Server-Side Request Forgery (SSRF):**
- Verificar URLs externas validadas (Gmail, WhatsApp, n8n, S3)

### 4.3 Subsección 3.2: Performance (Día 8)

#### 4.3.1 Database Queries

**N+1 Query Problem:**
- Listar todas las queries con `contain()` - verificar eager loading
- Identificar loops que hacen queries individuales
- Revisar lazy loading en relaciones

**Verificar:**
- ✓ Uso de `contain()` en lugar de lazy loading
- ✓ `select()` para limitar campos cuando no se necesitan todos
- ✓ Paginación en listados largos
- ✓ Índices en columnas de búsqueda frecuente
- ✓ No hay queries dentro de loops

**Herramienta:**
```php
// Habilitar query logging en development
Configure::write('debug', true);
// Revisar DebugKit para queries
```

#### 4.3.2 Caching

**Revisar estrategia de cache actual:**
- `SystemSettings` - cached 1 hora
- Otras entidades que se beneficiarían de cache

**Verificar:**
- ✓ `system_settings` usa `Cache::remember()` correctamente
- ✓ Cache invalidation cuando se actualizan settings
- ✓ Configuración de cache en `app.php` (File/Redis/Memcached)
- ✓ TTL apropiado por tipo de dato

**Identificar oportunidades:**
- Email templates (raramente cambian)
- Tags (estáticos)
- Estadísticas (pueden cachearse 5-15 min)

#### 4.3.3 File Storage

**Archivos a revisar:**
- `src/Service/S3Service.php` - Uploads/downloads
- `src/Service/Traits/GenericAttachmentTrait.php` - File handling

**Verificar:**
- ✓ S3 usado para producción (no filesystem local)
- ✓ Streaming de archivos grandes (no cargar todo en memoria)
- ✓ CDN/CloudFront para archivos estáticos si aplica
- ✓ Cleanup de archivos temporales
- ✓ Tamaño máximo de archivos configurado
- ✓ Multipart upload para archivos >100MB

#### 4.3.4 Background Workers

**Archivos a revisar:**
- `src/Command/GmailWorkerCommand.php` - Worker daemon
- `src/Command/ImportGmailCommand.php` - Tarea de importación
- Supervisor configuration

**Verificar:**
- ✓ Worker no consume memoria indefinidamente (memory leaks)
- ✓ Timeout configurado para evitar procesos zombies
- ✓ Logging de errores sin llenar disco (log rotation)
- ✓ Manejo de señales (SIGTERM, SIGINT) para graceful shutdown
- ✓ Reinicio automático en caso de crash
- ✓ Límite de emails procesados por ciclo (evitar overload)

### 4.4 Subsección 3.3: Integraciones Externas (Día 9)

#### 4.4.1 Gmail API

**Archivos a revisar:**
- `src/Service/GmailService.php` - OAuth2, fetching, parsing
- `config/google/credentials.json` - OAuth credentials
- Token refresh automático

**Verificar:**
- ✓ Manejo de errores de API (rate limits, network failures)
- ✓ Refresh token renovado antes de expirar
- ✓ Retry logic con exponential backoff
- ✓ Logging de errores sin exponer tokens
- ✓ Timeout configurado para requests HTTP
- ✗ ¿Qué pasa si Gmail está caído? (Documentar comportamiento)

#### 4.4.2 WhatsApp (Evolution API)

**Archivos a revisar:**
- `src/Service/WhatsappService.php` - Notification sending
- Configuration en `system_settings`

**Verificar:**
- ✓ Timeout configurado (no bloquear app si API lenta)
- ✓ Fallback si WhatsApp falla (continuar sin notificación)
- ✓ Queue para notificaciones si aplica
- ✓ Logging de errores
- ✓ Retry logic para fallos temporales

#### 4.4.3 n8n Webhooks

**Archivos a revisar:**
- `src/Service/N8nService.php` - AI tagging integration
- Webhook secret validation

**Verificar:**
- ✓ Signature validation de webhooks
- ✓ Timeout configurado
- ✓ Sistema funciona si n8n está deshabilitado
- ✓ Async processing si aplica
- ✓ Rate limiting para evitar abuse

#### 4.4.4 AWS S3

**Archivos a revisar:**
- `src/Service/S3Service.php` - File storage
- Credentials en `app_local.php`

**Verificar:**
- ✓ IAM credentials con mínimos permisos (principle of least privilege)
- ✓ Bucket policy seguro (no público)
- ✓ Signed URLs con expiración
- ✓ Fallback a local storage en development
- ✓ Error handling si S3 no disponible
- ✓ Versioning habilitado para disaster recovery

### 4.5 Subsección 3.4: Commands & Workers (Día 10)

#### 4.5.1 CLI Commands

**Archivos a revisar:**
- `src/Command/GmailWorkerCommand.php` - Background daemon
- `src/Command/ImportGmailCommand.php` - Import task
- `src/Command/TestEmailCommand.php` - Testing utility

**Verificar:**
- ✓ Argumentos validados correctamente
- ✓ Help text descriptivo
- ✓ Exit codes apropiados (0=success, >0=error)
- ✓ Logging a archivos no solo STDOUT
- ✓ Manejo de señales de shutdown (SIGTERM, SIGINT)
- ✓ Progress indicators para tareas largas
- ✓ Dry-run mode para testing

#### 4.5.2 Docker & Deployment

**Archivos a revisar:**
- `docker-compose.yml` - Development
- `docker-compose.prod.yml` - Production
- `Dockerfile` - Container build
- Supervisor config para worker

**Verificar:**
- ✓ Health checks configurados
- ✓ Resource limits (memory, CPU)
- ✓ Volumes persistentes para logs y uploads
- ✓ Restart policies apropiadas
- ✓ Secrets no hardcoded en Dockerfile
- ✓ Multi-stage builds para tamaño optimizado
- ✓ Non-root user en containers

### 4.6 Subsección 3.5: View Layer & Frontend (Día 10)

#### 4.6.1 View Helpers

**Archivos a revisar:**
- 6 helpers en `src/View/Helper/`

**Verificar:**
- ✓ Lógica simple, solo formateo/presentación
- ✗ NO contienen lógica de negocio
- ✓ Output escapado correctamente
- ✓ Métodos pequeños y cohesivos

#### 4.6.2 View Cells

**Archivos a revisar:**
- 4 cells para sidebars en `src/View/Cell/`

**Verificar:**
- ✓ Queries eficientes (no N+1)
- ✓ Cache si es contenido estático
- ✓ No hacen llamadas a APIs externas

#### 4.6.3 Templates

**Archivos a revisar:**
- 81 archivos `.php` en `templates/`

**Verificar:**
- ✓ Uso consistente de `h()` helper
- ✓ No hay código PHP complejo en vistas
- ✓ Layouts apropiados por rol
- ✓ Assets optimizados (CSS/JS minificado)
- ✓ No hay queries directas en templates

### 4.7 Salida de Fase 3

Tres archivos completados/actualizados:

- **`docs/audit/AUDITORIA_SEGURIDAD.md`** - Vulnerabilidades, fixes de seguridad necesarios
- **`docs/audit/AUDITORIA_PERFORMANCE.md`** - Bottlenecks, optimizaciones recomendadas
- **`docs/audit/AUDITORIA_ARQUITECTURA.md`** (actualizado) - Findings de integraciones

---

## 5. Fase 4 - Consolidación y Roadmap (Día 11)

### 5.1 Objetivo

Consolidar todos los findings, priorizar issues, crear roadmap ejecutable y preparar documentación final.

### 5.2 Clasificación de Issues

#### Matriz de Severidad

| Severidad | Criterio | Acción |
|-----------|----------|--------|
| **Crítico** | Vulnerabilidades de seguridad, pérdida de datos, sistema inoperable | Debe resolverse antes de producción |
| **Alto** | Performance degradado severo, bugs graves, violaciones arquitectónicas mayores | Altamente recomendado resolver pre-producción |
| **Medio** | Code smells, duplicación moderada, falta de tests, performance menor | Resolver en siguientes sprints |
| **Bajo** | Mejoras cosméticas, optimizaciones menores, documentación | Backlog de mejora continua |

#### Estimaciones de Esfuerzo

- **XS** (<2 horas): Quick fixes, ajustes de configuración
- **S** (2-4 horas): Refactorings pequeños, tests unitarios
- **M** (1-2 días): Refactorings medianos, features de seguridad
- **L** (3-5 días): Refactorings arquitectónicos, migraciones complejas
- **XL** (>1 semana): Re-arquitectura de módulos, migraciones de datos

### 5.3 Análisis de Deuda Técnica

Calcular métricas agregadas:

- Total de issues por severidad
- Total de esfuerzo estimado por categoría
- Cobertura de tests actual vs deseada
- Complejidad ciclomática promedio (si aplica)
- Porcentaje de código duplicado (si aplica)
- Número de violaciones SOLID

### 5.4 Generación de Roadmap

El archivo **`ROADMAP_PRODUCCION.md`** debe incluir:

#### 5.4.1 Resumen Ejecutivo

- Estado general del proyecto (🟢 Verde / 🟡 Amarillo / 🔴 Rojo)
- Issues críticos bloqueantes
- Recomendación: **Go / No-Go** para producción
- Estimación de esfuerzo total para remediación

#### 5.4.2 Tabla de Issues Priorizados

```markdown
| ID | Severidad | Categoría | Descripción | Ubicación | Esfuerzo | Prioridad |
|----|-----------|-----------|-------------|-----------|----------|-----------|
| SEC-001 | Crítico | Seguridad | XSS en templates | templates/Tickets/view.php:45 | S | Bloqueante |
| PERF-012 | Alto | Performance | N+1 queries | TicketsController.php:89 | M | Pre-prod |
```

#### 5.4.3 Fases de Remediación

**Fase 0 (Bloqueantes - ANTES de producción):**
- Issues críticos que impiden producción
- Vulnerabilidades de seguridad severas
- Bugs que causan pérdida de datos

**Fase 1 (Pre-lanzamiento - Altamente recomendado):**
- Issues altos de seguridad/performance
- Bugs severos no bloqueantes
- Refactorings arquitectónicos mayores

**Fase 2 (Post-lanzamiento - Primeros 30 días):**
- Mejoras de performance
- Code smells medianos
- Testing coverage

**Fase 3 (Backlog - Largo plazo):**
- Deuda técnica
- Optimizaciones menores
- Mejoras de documentación

#### 5.4.4 Criterios de Aceptación para Producción

**Criterios Go/No-Go:**
- [ ] 0 issues críticos de seguridad
- [ ] 0 vulnerabilidades conocidas en dependencies (`composer audit` limpio)
- [ ] Cobertura de tests >X% en services críticos (definir X)
- [ ] Performance benchmarks cumplidos (definir métricas)
- [ ] Documentación de deployment actualizada (EASYPANEL.md, DOCKER.md)
- [ ] Backup y disaster recovery probados
- [ ] Monitoring y alerting configurados
- [ ] SSL/HTTPS configurado
- [ ] Variables de entorno de producción configuradas

### 5.5 Salida de Fase 4

Un archivo final:

- **`docs/audit/ROADMAP_PRODUCCION.md`** - Documento ejecutivo con roadmap completo

---

## 6. Ejecución del Plan

### 6.1 Cronograma Sugerido

```
Día 1:  Fase 1 - Diagnóstico Automatizado
        └── PHPStan, PHPCS, PHPUnit, métricas

Día 2:  Fase 2.1 - Services (parte 1)
        └── GmailService, TicketService, ResponseService, EmailService

Día 3:  Fase 2.1 - Services (parte 2) + Traits
        └── WhatsappService, ComprasService, PqrsService, S3Service, SlaManagement, N8n, Statistics
        └── 5 Service Traits

Día 4:  Fase 2.2 - Controllers (principales)
        └── AppController, TicketsController, ComprasController, PqrsController
        └── UsersController, ErrorController, HealthController, PagesController

Día 5:  Fase 2.2 - Controllers (admin + traits)
        └── Admin/SettingsController, Admin/SlaManagementController, Admin/ConfigFilesController
        └── 4 Controller Traits

Día 6:  Fase 2.3 - Models (Tables + Entities)
        └── 17 Tables (Core, Tickets, Compras, PQRS)
        └── 17 Entities

Día 7:  Fase 3.1 - Seguridad (parte 1)
        └── Auth, SQL Injection, XSS, File Uploads

Día 8:  Fase 3.1 - Seguridad (parte 2) + 3.2 Performance
        └── Datos Sensibles, OWASP Top 10
        └── Queries, Caching, Files, Workers

Día 9:  Fase 3.3 - Integraciones Externas
        └── Gmail, WhatsApp, n8n, S3

Día 10: Fase 3.4 - Commands/Workers + 3.5 Views
        └── CLI Commands, Docker
        └── View Helpers, Cells, Templates

Día 11: Fase 4 - Consolidación y Roadmap
        └── Clasificación, Deuda Técnica, ROADMAP_PRODUCCION.md
```

### 6.2 Herramientas y Setup Inicial

#### Antes de empezar:

```bash
# Crear directorio de auditoría
mkdir -p docs/audit

# Instalar dependencias si faltan
composer install

# Verificar herramientas funcionan
composer stan
composer cs-check
composer test

# Crear branch de auditoría
git checkout -b audit/pre-produccion

# Primer commit con estructura
git add docs/
git commit -m "docs: estructura inicial de auditoría pre-producción"
```

### 6.3 Checklist de Ejecución

#### Por cada archivo auditado:

- [ ] Ejecutar análisis automatizado previo (PHPStan, PHPCS)
- [ ] Revisar manualmente el código línea por línea
- [ ] Documentar issues encontrados en archivo correspondiente
- [ ] Marcar severidad y esfuerzo estimado
- [ ] Agregar código de ejemplo/evidencia si aplica
- [ ] Referenciar líneas específicas (archivo:línea)
- [ ] Proponer recomendación de solución

#### Por cada fase completada:

- [ ] Actualizar documentos de auditoría
- [ ] Commit intermedio con findings
- [ ] Review cruzado si hay equipo
- [ ] Actualizar checklist de progreso

#### Al finalizar:

- [ ] Revisar todos los documentos para consistencia
- [ ] Generar resumen ejecutivo en ROADMAP_PRODUCCION.md
- [ ] Commit final de auditoría
- [ ] Presentar findings a stakeholders

---

## 7. Estructura de Documentos de Salida

### 7.1 Plantilla General

Todos los documentos en `docs/audit/` seguirán esta estructura:

```markdown
# AUDITORÍA [ÁREA] - Mesa de Ayuda

**Fecha**: [Fecha de auditoría]
**Auditor**: [Nombre/Claude]
**Versión proyecto**: [Git commit hash]
**Branch**: audit/pre-produccion

---

## Resumen Ejecutivo

- **Total de issues encontrados**: X
- **Críticos**: X | **Altos**: X | **Medios**: X | **Bajos**: X
- **Estado general**: 🔴 Rojo / 🟡 Amarillo / 🟢 Verde
- **Esfuerzo estimado total**: X días

**Recomendación**:
[Breve párrafo con recomendación principal]

---

## Índice de Issues

- [CATEGORIA-001: Título](#categoria-001-título)
- [CATEGORIA-002: Título](#categoria-002-título)
...

---

## Issues Detallados

### [CATEGORIA]-001: [Título descriptivo del issue]

**Severidad**: Crítico / Alto / Medio / Bajo
**Esfuerzo**: XS / S / M / L / XL
**Ubicación**: `src/Service/Example.php:123-145`
**Prioridad para producción**: Bloqueante / Alta / Media / Baja

**Descripción**:
[Explicación clara del problema encontrado]

**Evidencia**:
```php
// Código problemático (líneas 123-145)
public function problemMethod() {
    // ...
}
```

**Impacto**:
- [Qué consecuencias tiene este issue]
- [Qué puede fallar si no se resuelve]

**Recomendación**:
[Cómo resolverlo paso a paso]

```php
// Código sugerido
public function fixedMethod() {
    // ...
}
```

**Referencias**:
- [Enlaces a documentación relevante]
- [Issues similares en el proyecto]

---

[Repetir por cada issue...]

---

## Métricas y Estadísticas

### Issues por Severidad
| Severidad | Cantidad | Porcentaje |
|-----------|----------|------------|
| Crítico   | X        | X%         |
| Alto      | X        | X%         |
| Medio     | X        | X%         |
| Bajo      | X        | X%         |

### Esfuerzo Estimado por Categoría
| Categoría | Issues | Esfuerzo Total |
|-----------|--------|----------------|
| ...       | X      | X días         |

### Top 10 Archivos con Más Issues
| Archivo | Issues | Severidad Max |
|---------|--------|---------------|
| ...     | X      | Crítico       |

---

## Recomendaciones Generales

[Lista de recomendaciones transversales]

---

## Referencias

- **Archivos revisados**: [Lista completa]
- **Herramientas utilizadas**: PHPStan, PHPCS, PHPUnit
- **Estándares aplicados**: PSR-12, CakePHP Conventions, OWASP Top 10
- **Documentación consultada**: CLAUDE.md, AUDITORIA_MAPEO.md
```

### 7.2 Documentos Específicos

#### 7.2.1 AUDITORIA_CALIDAD_CODIGO.md

**Secciones:**
1. Resumen Ejecutivo
2. Duplicación de Código (issues DUP-XXX)
3. Complejidad Ciclomática (issues COM-XXX)
4. Code Smells (issues SMELL-XXX)
5. Testing Coverage (issues TST-XXX)
6. Documentación (issues DOC-XXX)
7. Métricas y Estadísticas
8. Recomendaciones

#### 7.2.2 AUDITORIA_SEGURIDAD.md

**Secciones:**
1. Resumen Ejecutivo
2. Autenticación y Autorización (SEC-AUTH-XXX)
3. Inyección SQL (SEC-SQL-XXX)
4. XSS y Output Escaping (SEC-XSS-XXX)
5. Manejo de Archivos (SEC-FILE-XXX)
6. Datos Sensibles (SEC-DATA-XXX)
7. OWASP Top 10 Compliance (SEC-OWASP-XXX)
8. Métricas y Estadísticas
9. Recomendaciones

#### 7.2.3 AUDITORIA_PERFORMANCE.md

**Secciones:**
1. Resumen Ejecutivo
2. Database Queries (PERF-DB-XXX)
3. Caching Strategy (PERF-CACHE-XXX)
4. File Storage (PERF-FILE-XXX)
5. Background Workers (PERF-WORKER-XXX)
6. Frontend Performance (PERF-FE-XXX)
7. Métricas y Estadísticas
8. Recomendaciones

#### 7.2.4 AUDITORIA_ARQUITECTURA.md

**Secciones:**
1. Resumen Ejecutivo
2. Service Layer Pattern (ARCH-SVC-XXX)
3. SOLID Principles (ARCH-SOLID-XXX)
4. Separación de Capas (ARCH-LAYER-XXX)
5. Patrones de Diseño (ARCH-PATTERN-XXX)
6. Integraciones Externas (ARCH-INT-XXX)
7. Métricas y Estadísticas
8. Recomendaciones

#### 7.2.5 ROADMAP_PRODUCCION.md

**Estructura:**
1. **Resumen Ejecutivo**
   - Estado del proyecto
   - Recomendación Go/No-Go
   - Issues críticos bloqueantes
   - Esfuerzo total estimado

2. **Dashboard de Issues**
   - Tabla consolidada de TODOS los issues
   - Filtrable por severidad, categoría, esfuerzo

3. **Fases de Remediación**
   - Fase 0: Bloqueantes (antes de producción)
   - Fase 1: Pre-lanzamiento
   - Fase 2: Post-lanzamiento (30 días)
   - Fase 3: Backlog

4. **Criterios Go/No-Go**
   - Checklist de requisitos para producción
   - Estado actual de cada criterio

5. **Estimaciones y Timeline**
   - Gantt chart textual
   - Recursos necesarios

6. **Plan de Monitoreo Post-Producción**
   - Métricas a vigilar
   - Alertas críticas
   - Revisiones programadas

---

## 8. Criterios de Éxito

### 8.1 La auditoría será exitosa cuando:

#### Completitud
- ✅ 88 archivos PHP en `src/` revisados
- ✅ 5 documentos especializados generados
- ✅ 1 roadmap ejecutable entregado
- ✅ Todas las fases completadas según cronograma

#### Calidad de Documentación
- ✅ Cada issue tiene: severidad, ubicación, descripción, recomendación, esfuerzo
- ✅ Issues priorizados con criterio objetivo
- ✅ Código de ejemplo/evidencia incluido cuando aplica
- ✅ Referencias a estándares y documentación

#### Accionabilidad
- ✅ Roadmap con fases claras y priorizadas
- ✅ Estimaciones realistas de esfuerzo
- ✅ Criterios Go/No-Go definidos para producción
- ✅ Recomendaciones específicas y aplicables

#### Cobertura Balanceada
- ✅ Seguridad (OWASP Top 10): ~25% del esfuerzo
- ✅ Calidad de código (énfasis principal): ~35% del esfuerzo
- ✅ Performance: ~20% del esfuerzo
- ✅ Arquitectura: ~20% del esfuerzo

### 8.2 Métricas de Calidad del Assessment

- **Tasa de detección**: % de issues reales encontrados vs total
- **Falsos positivos**: <10% de issues reportados
- **Precisión de estimaciones**: ±20% del esfuerzo real
- **Utilidad práctica**: >80% de recomendaciones implementables

### 8.3 Entregables Finales

**Estructura final en `docs/audit/`:**
```
docs/audit/
├── DIAGNOSTICO_AUTOMATIZADO.md
├── phpstan-results.txt
├── phpcs-results.txt
├── phpunit-results.txt
├── lines-per-file.txt
├── coverage/                      # HTML coverage report
├── AUDITORIA_CALIDAD_CODIGO.md
├── AUDITORIA_SEGURIDAD.md
├── AUDITORIA_PERFORMANCE.md
├── AUDITORIA_ARQUITECTURA.md
└── ROADMAP_PRODUCCION.md
```

---

## Conclusión

Este plan de auditoría proporciona un framework sistemático y exhaustivo para evaluar el proyecto Mesa de Ayuda antes de salir a producción. El enfoque híbrido (automatizado + manual) maximiza la eficiencia mientras mantiene la profundidad necesaria para una auditoría de calidad.

**Próximos Pasos:**
1. Revisar y aprobar este plan
2. Ejecutar Fase 1 (Diagnóstico Automatizado)
3. Presentar findings iniciales
4. Continuar con Fases 2-4 según cronograma
5. Tomar decisión Go/No-Go basada en roadmap final

---

**Documento generado**: 2026-01-08
**Versión**: 1.0
**Estado**: ✅ Aprobado para ejecución
