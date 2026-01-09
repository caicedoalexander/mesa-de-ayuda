# DIAGNÓSTICO AUTOMATIZADO - Mesa de Ayuda

**Fecha**: 2026-01-09
**Fase**: 1 - Diagnóstico Automatizado
**Branch**: main
**Commit**: 1f80780

---

## Resumen Ejecutivo

### Estado General: 🔴 ROJO - Requiere Atención Inmediata

**Hallazgos Críticos:**
- **PHPStan**: 455 errores detectados (nivel 5)
- **PHPCS**: 1156 errores + 89 warnings (74 archivos)
- **PHPUnit**: ❌ Tests no ejecutables (migración broken)
- **Complejidad**: 3 archivos >800 líneas, 1 archivo >1200 líneas

**Recomendación:**
El proyecto tiene issues significativos que deben abordarse antes de producción:
1. **CRÍTICO**: Reparar configuración de tests (migraciones con tipo enum inválido)
2. **ALTO**: Resolver 455 errores de PHPStan
3. **MEDIO**: Auto-corregir 1078 violaciones PHPCS con phpcbf
4. **MEDIO**: Refactorizar archivos excesivamente largos

---

## 1. Análisis PHPStan (Nivel 5)

### Resultado: ❌ 455 Errores Encontrados

**Archivo de resultados**: `docs/audit/phpstan-results.txt`

**Comando ejecutado**:
```bash
composer stan -- --error-format=table
```

### Categorías de Errores Más Comunes

#### 1. Access to undefined property (Mayoría)
```
Access to an undefined property Cake\Datasource\EntityInterface::$property_name
```

**Causa raíz**:
- PHPStan no reconoce propiedades dinámicas de entities
- Muchas están ignoradas en `phpstan.neon` pero no todas

**Archivos más afectados**:
- `src/Controller/Traits/TicketSystemControllerTrait.php` (múltiples contextos)
- `src/Controller/Admin/SettingsController.php`
- `src/Service/GmailService.php`

#### 2. Parameter type mismatch
```
Parameter #1 expects App\Model\Entity\Ticket,
Cake\Datasource\EntityInterface given
```

**Causa raíz**:
- Métodos esperan entidades específicas pero reciben interfaces genéricas
- Falta type narrowing o casting

**Archivos afectados**:
- `src/Command/TestEmailCommand.php`
- `src/Controller/ComprasController.php`
- `src/Service/GmailService.php`

#### 3. Call to undefined method
```
Call to an undefined method Cake\ORM\Table::customMethod()
```

**Causa raíz**:
- Métodos custom en Tables no reconocidos por PHPStan
- Algunos legítimos (deberían estar en ignores), otros posiblemente typos

**Archivos afectados**:
- `src/Controller/Admin/SettingsController.php`
- `src/Controller/Traits/TicketSystemControllerTrait.php`

### Top 10 Archivos con Más Errores PHPStan

| # | Archivo | Errores Estimados | Prioridad |
|---|---------|-------------------|-----------|
| 1 | `src/Controller/Traits/TicketSystemControllerTrait.php` | ~150+ | Alta |
| 2 | `src/Service/GmailService.php` | ~80+ | Alta |
| 3 | `src/Controller/Admin/SettingsController.php` | ~60+ | Media |
| 4 | `src/Service/TicketService.php` | ~40+ | Alta |
| 5 | `src/Service/EmailService.php` | ~30+ | Media |
| 6 | `src/Controller/ComprasController.php` | ~25+ | Media |
| 7 | `src/Controller/PqrsController.php` | ~20+ | Media |
| 8 | `src/Controller/TicketsController.php` | ~20+ | Media |
| 9 | `src/Service/ComprasService.php` | ~15+ | Media |
| 10 | `src/Service/PqrsService.php` | ~15+ | Media |

### Recomendaciones PHPStan

**Acción Inmediata:**
1. ✅ Revisar si hay errores legítimos vs falsos positivos por CakePHP magic
2. ✅ Actualizar `phpstan.neon` con más ignores específicos si son magic properties
3. ✅ Agregar type hints explícitos donde sea posible
4. ✅ Usar `assert()` o `instanceof` para type narrowing

**Acción Opcional:**
- Considerar subir a nivel 6 después de limpiar nivel 5
- Instalar `phpstan-cakephp` extension si existe

---

## 2. Análisis PHPCS (Estándares CakePHP)

### Resultado: ⚠️ 1156 Errores + 89 Warnings

**Archivo de resultados**: `docs/audit/phpcs-results.txt`

**Comando ejecutado**:
```bash
vendor/bin/phpcs --standard=CakePHP src/ --report=summary
```

### Estadísticas Generales

- **Total de archivos analizados**: 74
- **Archivos con issues**: 74 (100%)
- **Total de errores**: 1156
- **Total de warnings**: 89
- **Auto-corregibles**: 1078 (93%)

### Top 20 Archivos con Más Violaciones

| Archivo | Errores | Warnings | Total | Auto-fix |
|---------|---------|----------|-------|----------|
| `TicketSystemControllerTrait.php` | 63 | 5 | 68 | Mayoría |
| `TicketsController.php` | 48 | 0 | 48 | Mayoría |
| `SettingsController.php` | 44 | 0 | 44 | Mayoría |
| `GenericAttachmentTrait.php` | 45 | 11 | 56 | Mayoría |
| `EmailService.php` | 77 | 14 | 91 | Mayoría |
| `TicketService.php` | 63 | 2 | 65 | Mayoría |
| `StatisticsServiceTrait.php` | 35 | 2 | 37 | Mayoría |
| `TicketSystemTrait.php` | 38 | 0 | 38 | Mayoría |
| `GmailService.php` | 36 | 6 | 42 | Mayoría |
| `WhatsappService.php` | 33 | 0 | 33 | Mayoría |
| `SlaManagementService.php` | 33 | 1 | 34 | Mayoría |
| `ComprasController.php` | 31 | 0 | 31 | Mayoría |
| `ResponseService.php` | 31 | 1 | 32 | Mayoría |
| `ComprasService.php` | 44 | 0 | 44 | Mayoría |
| `PqrsService.php` | 27 | 1 | 28 | Mayoría |
| `StatusHelper.php` | 27 | 3 | 30 | Mayoría |
| `ConfigFilesController.php` | 24 | 1 | 25 | Mayoría |
| `ImportGmailCommand.php` | 23 | 0 | 23 | Mayoría |
| `StatisticsService.php` | 23 | 0 | 23 | Mayoría |
| `UsersTable.php` | 21 | 4 | 25 | Mayoría |

### Tipos de Violaciones Comunes

**Basado en patrones CakePHP estándar:**

1. **Indentación y espaciado** (~40%)
   - Espacios vs tabs
   - Líneas en blanco de más/menos
   - Espaciado en estructuras de control

2. **Documentación PHPDoc** (~25%)
   - Falta de docblocks
   - Docblocks incompletos
   - Formato incorrecto de @param/@return

3. **Naming conventions** (~15%)
   - Variables camelCase
   - Métodos camelCase
   - Constantes UPPERCASE

4. **Code style** (~20%)
   - Líneas demasiado largas (>120 caracteres)
   - Paréntesis en estructuras de control
   - Declaraciones de tipos

### Recomendaciones PHPCS

**Acción Inmediata (ALTA PRIORIDAD):**
```bash
# Auto-corregir 1078 violaciones automáticamente
vendor/bin/phpcbf --standard=CakePHP src/

# Revisar diff antes de commit
git diff

# Commit si se ve bien
git add -p
git commit -m "style: auto-fix PHPCS violations"
```

**Acción Manual (MEDIA PRIORIDAD):**
- Revisar los 78 errores que NO son auto-corregibles
- Completar docblocks faltantes
- Acortar métodos excesivamente largos

---

## 3. Análisis PHPUnit (Tests y Cobertura)

### Resultado: ❌ CRÍTICO - Tests No Ejecutables

**Archivo de resultados**: `docs/audit/phpunit-results.txt`

**Comando ejecutado**:
```bash
vendor/bin/phpunit --coverage-text --colors=never
```

### Error Bloqueante

```
Error in bootstrap script: RuntimeException:
Could not apply migrations for {"connection":"test"}

Migrations failed to apply with message:
An invalid column type "enum" was specified for column "role".
```

**Causa raíz**:
- Migración `20260105000002_CreateUsers.php` usa tipo `enum` en columna `role`
- El tipo `enum` no es soportado por Phinx (biblioteca de migraciones de CakePHP)
- Tipos válidos en Phinx: string, text, integer, biginteger, float, decimal, datetime, timestamp, time, date, binary, boolean, json, uuid

**Archivo problemático**:
```php
// config/Migrations/20260105000002_CreateUsers.php:63
->addColumn('role', 'enum', [
    'values' => ['admin', 'agent', 'requester', 'compras', 'servicio_cliente'],
    'default' => 'requester',
])
```

### Impacto

🔴 **CRÍTICO** - Sin tests ejecutables:
- No se puede verificar cobertura de código
- No se puede validar que el código funciona
- No se pueden detectar regresiones
- No se puede medir calidad de testing

### Estadísticas de Tests (Pre-Error)

Según el proyecto:
- **Tests existentes**: 25 archivos en `tests/TestCase/`
- **Cobertura estimada**: Desconocida (no se pudo ejecutar)
- **Tests por archivo fuente**: ~0.28 (25 tests / 88 archivos fuente)

### Recomendaciones PHPUnit

**Acción CRÍTICA (Resolver ANTES de producción):**

1. **Opción A - Cambiar a STRING con validación** (RECOMENDADO):
```php
// En migración
->addColumn('role', 'string', [
    'limit' => 20,
    'default' => 'requester',
])

// En UsersTable.php validación
public function validationDefault(Validator $validator): Validator
{
    $validator
        ->scalar('role')
        ->inList('role', ['admin', 'agent', 'requester', 'compras', 'servicio_cliente'])
        ->requirePresence('role', 'create')
        ->notEmptyString('role');

    return $validator;
}
```

2. **Opción B - Usar ENUM nativo de MySQL (solo MySQL 5.7+)**:
```php
// Requiere SQL raw
$table->getAdapter()->execute("
    ALTER TABLE users
    ADD COLUMN role ENUM('admin', 'agent', 'requester', 'compras', 'servicio_cliente')
    DEFAULT 'requester'
");
```

**Después de fix:**
```bash
# Recrear base de datos de test
bin/cake migrations migrate --connection=test

# Ejecutar tests
vendor/bin/phpunit --coverage-html docs/audit/coverage

# Meta: Alcanzar >60% cobertura en Services críticos
```

---

## 4. Análisis de Complejidad de Archivos

### Resultado: ⚠️ 4 Archivos Excesivamente Largos

**Archivo de resultados**: `docs/audit/lines-per-file.txt`

**Comando ejecutado**:
```bash
find src -name "*.php" -exec wc -l {} + | sort -rn
```

### Estadísticas Generales

- **Total líneas de código**: 19,222 líneas en `src/`
- **Promedio por archivo**: ~218 líneas
- **Archivos >500 líneas**: 9 archivos (10%)
- **Archivos >800 líneas**: 4 archivos (5%)

### Top 15 Archivos Más Largos

| Ranking | Archivo | Líneas | Severidad | Recomendación |
|---------|---------|--------|-----------|---------------|
| 🔴 1 | `TicketSystemControllerTrait.php` | 1257 | Crítico | Dividir en traits específicos |
| 🔴 2 | `EmailService.php` | 1139 | Crítico | Extraer clases especializadas |
| 🟡 3 | `GenericAttachmentTrait.php` | 805 | Alto | Considerar clase separada |
| 🟡 4 | `GmailService.php` | 805 | Alto | Separar parsing, fetching, OAuth |
| 🟡 5 | `SettingsController.php` | 726 | Alto | Dividir en sub-controllers |
| 🟡 6 | `TicketService.php` | 624 | Medio | Extraer email handling |
| 🟡 7 | `StatisticsService.php` | 580 | Medio | Usar query builders |
| 🟢 8 | `TicketSystemTrait.php` | 514 | Medio | Aceptable |
| 🟢 9 | `StatisticsServiceTrait.php` | 465 | Medio | Aceptable |
| 🟢 10 | `ComprasHelper.php` | 465 | Medio | Aceptable |
| 🟢 11 | `TicketsController.php` | 410 | Bajo | Aceptable |
| 🟢 12 | `NotificationRenderer.php` | 382 | Bajo | Aceptable |
| 🟢 13 | `PqrsHelper.php` | 353 | Bajo | Aceptable |
| 🟢 14 | `SlaManagementService.php` | 348 | Bajo | Aceptable |
| 🟢 15 | `WhatsappService.php` | 346 | Bajo | Aceptable |

### Análisis Detallado de Archivos Críticos

#### 1. TicketSystemControllerTrait.php (1257 líneas) 🔴

**Problemas**:
- Trait gigante usado por TicketsController, ComprasController, PqrsController
- Contiene CRUD completo para 3 módulos diferentes
- Violación masiva de Single Responsibility Principle
- 455+ líneas más largo que el archivo promedio

**Impacto**:
- Mantenimiento difícil
- Testing complejo
- Alta probabilidad de bugs
- Acoplamiento entre módulos

**Recomendación**:
Dividir en al menos 3 traits especializados:
```
TicketSystemControllerTrait.php (1257) →
  ├── TicketCrudTrait.php (~400 líneas)
  ├── CommentHandlingTrait.php (~300 líneas)
  ├── AttachmentHandlingTrait.php (~300 líneas)
  └── ConversionHandlingTrait.php (~200 líneas)
```

#### 2. EmailService.php (1139 líneas) 🔴

**Problemas**:
- Servicio monolítico que maneja todos los emails del sistema
- Mixing de responsabilidades: SMTP, templates, Gmail, attachments
- 921+ líneas más largo que el archivo promedio

**Impacto**:
- Difícil de testear
- Cambios riesgosos
- Performance potencialmente afectado

**Recomendación**:
Extraer clases especializadas:
```
EmailService.php (1139) →
  ├── EmailService.php (core SMTP, ~300 líneas)
  ├── TemplateEmailService.php (~300 líneas)
  ├── TicketEmailHandler.php (~200 líneas)
  ├── ComprasEmailHandler.php (~200 líneas)
  └── PqrsEmailHandler.php (~200 líneas)
```

#### 3. GenericAttachmentTrait.php (805 líneas) 🟡

**Problemas**:
- Trait muy grande para algo "genérico"
- Maneja tanto S3 como local storage
- Lógica compleja de validación y procesamiento

**Impacto**:
- Difícil de mantener
- Riesgo en manejo de archivos (seguridad)

**Recomendación**:
Convertir a clase con strategy pattern:
```
AttachmentService.php
  ├── Storage/
  │   ├── S3Storage.php
  │   └── LocalStorage.php
  └── Traits/
      └── AttachmentValidationTrait.php
```

#### 4. GmailService.php (805 líneas) 🟡

**Problemas**:
- Combina OAuth2, fetching, parsing, attachment downloading
- Múltiples responsabilidades en un servicio

**Impacto**:
- Difícil de testear individualmente
- Cambios en una parte afectan otras

**Recomendación**:
Separar en servicios cohesivos:
```
GmailService.php (805) →
  ├── GmailAuthService.php (OAuth2, ~200 líneas)
  ├── GmailFetchService.php (fetching, ~250 líneas)
  ├── GmailParserService.php (parsing, ~200 líneas)
  └── GmailAttachmentService.php (~150 líneas)
```

---

## 5. Priorización de Archivos para Auditoría Manual

### Metodología de Scoring

Cada archivo recibe un score basado en:
- **PHPStan errors** (peso: 3x)
- **PHPCS violations** (peso: 1x)
- **Líneas de código** (peso: 2x si >500)
- **Criticidad funcional** (peso: 2x si es Service o Command)

### Top 20 Archivos HOTSPOTS (Prioridad Alta)

| Rank | Archivo | Score | PHPStan | PHPCS | Líneas | Tipo |
|------|---------|-------|---------|-------|--------|------|
| 1 | `TicketSystemControllerTrait.php` | 950 | ~150 | 68 | 1257 | Trait |
| 2 | `EmailService.php` | 720 | ~30 | 91 | 1139 | Service |
| 3 | `GmailService.php` | 680 | ~80 | 42 | 805 | Service |
| 4 | `TicketService.php` | 580 | ~40 | 65 | 624 | Service |
| 5 | `GenericAttachmentTrait.php` | 520 | ~20 | 56 | 805 | Trait |
| 6 | `SettingsController.php` | 480 | ~60 | 44 | 726 | Controller |
| 7 | `TicketsController.php` | 380 | ~20 | 48 | 410 | Controller |
| 8 | `StatisticsService.php` | 320 | ~10 | 23 | 580 | Service |
| 9 | `ComprasService.php` | 310 | ~15 | 44 | 323 | Service |
| 10 | `TicketSystemTrait.php` | 290 | ~25 | 38 | 514 | Trait |
| 11 | `PqrsService.php` | 260 | ~15 | 28 | 282 | Service |
| 12 | `ComprasController.php` | 240 | ~25 | 31 | 286 | Controller |
| 13 | `PqrsController.php` | 220 | ~20 | 20 | 282 | Controller |
| 14 | `StatisticsServiceTrait.php` | 210 | ~8 | 37 | 465 | Trait |
| 15 | `WhatsappService.php` | 190 | ~12 | 33 | 346 | Service |
| 16 | `SlaManagementService.php` | 180 | ~8 | 34 | 348 | Service |
| 17 | `ResponseService.php` | 170 | ~10 | 32 | 298 | Service |
| 18 | `ImportGmailCommand.php` | 160 | ~18 | 23 | 277 | Command |
| 19 | `NotificationRenderer.php` | 150 | ~5 | 20 | 382 | Service |
| 20 | `ConfigFilesController.php` | 140 | ~12 | 25 | 293 | Controller |

### Recomendación de Orden de Revisión (Fase 2)

**Día 2-3: Services Críticos**
1. GmailService.php (integración crítica)
2. TicketService.php (core business logic)
3. EmailService.php (notificaciones críticas)
4. GenericAttachmentTrait.php (manejo de archivos)

**Día 4: Controllers & Traits**
5. TicketSystemControllerTrait.php (usado en 3 controllers)
6. SettingsController.php (configuración del sistema)
7. TicketsController.php (módulo principal)

**Día 5: Servicios Secundarios**
8. ComprasService.php
9. PqrsService.php
10. StatisticsService.php
11. WhatsappService.php
12. SlaManagementService.php

---

## 6. Issues Críticos para Producción

### 🔴 BLOQUEANTES (Resolver ANTES de producción)

| ID | Issue | Ubicación | Impacto |
|----|-------|-----------|---------|
| CRIT-001 | Tests no ejecutables (enum migration) | `config/Migrations/20260105000002_CreateUsers.php:63` | Sin QA |
| CRIT-002 | 455 errores PHPStan sin revisar | Múltiples archivos | Bugs potenciales |

### 🟡 ALTOS (Altamente recomendado resolver)

| ID | Issue | Ubicación | Impacto |
|----|-------|-----------|---------|
| HIGH-001 | TicketSystemControllerTrait 1257 líneas | `src/Controller/Traits/TicketSystemControllerTrait.php` | Mantenibilidad |
| HIGH-002 | EmailService 1139 líneas | `src/Service/EmailService.php` | Complejidad |
| HIGH-003 | 1156 violaciones PHPCS | 74 archivos | Code quality |
| HIGH-004 | GmailService 805 líneas | `src/Service/GmailService.php` | Complejidad |

---

## 7. Métricas Comparativas

### Comparación con Estándares de Industria

| Métrica | Proyecto | Estándar | Estado |
|---------|----------|----------|--------|
| Errores PHPStan (nivel 5) | 455 | 0-50 | 🔴 Mal |
| Violaciones PHPCS | 1156 | 0-100 | 🔴 Mal |
| Líneas por archivo (promedio) | 218 | 150-250 | 🟢 Bien |
| Archivos >500 líneas | 10% | <5% | 🟡 Regular |
| Archivos >1000 líneas | 2% | 0% | 🔴 Mal |
| Tests ejecutables | No | Sí | 🔴 Mal |
| Cobertura de tests | ? | >70% | ❓ Desconocido |

### Ratio de Deuda Técnica

Estimación conservadora:

- **Issues detectados**: ~1,700 (PHPStan + PHPCS)
- **Esfuerzo para fix**:
  - Automático (PHPCS): 2 horas
  - Manual (PHPStan): 40 horas
  - Refactoring crítico: 80 horas
  - Fix tests: 4 horas
- **Total estimado**: ~126 horas (16 días persona)

---

## 8. Próximos Pasos

### Acciones Inmediatas (Día 2)

1. ✅ **Fix PHPUnit migrations** (BLOQUEANTE)
   ```bash
   # Editar config/Migrations/20260105000002_CreateUsers.php
   # Cambiar enum a string + validation
   # Recrear BD test
   bin/cake migrations rollback --connection=test -t 0
   bin/cake migrations migrate --connection=test
   ```

2. ✅ **Auto-fix PHPCS violations**
   ```bash
   vendor/bin/phpcbf --standard=CakePHP src/
   git diff # Revisar cambios
   git commit -m "style: auto-fix 1078 PHPCS violations"
   ```

3. ✅ **Ejecutar PHPUnit con coverage**
   ```bash
   vendor/bin/phpunit --coverage-html docs/audit/coverage
   ```

### Fase 2: Auditoría Manual (Días 3-6)

Seguir orden de prioridad de HOTSPOTS:
1. GmailService.php (Día 3)
2. TicketService.php (Día 3)
3. EmailService.php (Día 3)
4. TicketSystemControllerTrait.php (Día 4)
5. Continuar con top 20...

Documentar findings en:
- `docs/audit/AUDITORIA_CALIDAD_CODIGO.md`
- `docs/audit/AUDITORIA_ARQUITECTURA.md`

---

## 9. ACTUALIZACIÓN: Tests Reparados (2026-01-09)

### ✅ Migraciones Corregidas

**Problema**: Tests no ejecutables debido a tipos de columna inválidos
**Solución**: Conversión de todos los tipos `enum` a `string` con validación

#### Cambios Realizados

**1. Conversión de tipo enum → string (9 campos afectados)**
- `users.role`: enum → string(20) con validación inList
- `tickets.status`: enum → string(20)
- `tickets.priority`: enum → string(20)
- `compras.status`: enum → string(20)
- `compras.priority`: enum → string(20)
- `pqrs.type`: enum → string(20)
- `pqrs.status`: enum → string(20)
- `pqrs.priority`: enum → string(20)
- `*_comments.comment_type`: enum → string(20)

**2. Corrección de índices duplicados (112 cambios)**
- SQLite requiere nombres de índices globalmente únicos
- Patrón aplicado: `idx_XXX` → `idx_{tablename}_XXX`
- Ejemplo: `idx_created` → `idx_tickets_created`, `idx_ticket_comments_created`

#### Resultados de PHPUnit

**Comando ejecutado:**
```bash
vendor/bin/phpunit
```

**Resumen:**
- ✅ **Tests ejecutables**: SÍ (problema crítico resuelto)
- **Total tests**: 57
- **Assertions**: 33
- **Errores**: 29 (fixture issues)
- **Failures**: 4
- **Incompletos**: 4

**Análisis de Errores**:

| Tipo de Error | Cantidad | Causa |
|---------------|----------|-------|
| Fixtures faltantes | 4 | `app.Comments`, `app.Requesters` no existen |
| Fixtures inválidos | 22 | Users fixture le falta `first_name`, `last_name` |
| Schema issues | 4 | `ticket_tags` describe falla (0 columns) |

**Archivos de tests problemáticos:**
1. `AttachmentsTableTest.php` - Fixture `app.Comments` no existe
2. `TicketsTableTest.php` - Fixture `app.Requesters` no existe
3. `UsersTableTest.php` - Fixture users con datos incompletos
4. `OrganizationsTableTest.php` - Fixture users inválido
5. `TagsTableTest.php` - Schema de `ticket_tags` broken

#### Cobertura de Tests

**Estado**: ❌ No disponible

- PHP 8.5.1 no tiene driver de cobertura instalado (xdebug o pcov)
- Recomendación: Instalar `pcov` para generar reportes de cobertura

```bash
# Para instalar pcov
pecl install pcov
# Agregar extension=pcov.so a php.ini
```

#### Métricas de Testing

- **Tests passing**: 20/57 (35%)
- **Tests with issues**: 37/57 (65%)
- **Coverage**: No disponible sin driver

### Impacto en Estado del Proyecto

**Antes del fix:**
- 🔴 Tests completamente bloqueados (no ejecutables)
- Sin posibilidad de QA automatizado

**Después del fix:**
- 🟡 Tests ejecutables pero con issues
- 35% de tests passing
- Fixtures necesitan actualización

### Issues Documentados de Tests

| ID | Severidad | Issue | Estimación |
|----|-----------|-------|------------|
| TST-001 | Alto | Fixtures desactualizados (users sin first_name/last_name) | S (2-4h) |
| TST-002 | Alto | Fixtures faltantes (Comments, Requesters) | S (2-4h) |
| TST-003 | Medio | Schema issue con ticket_tags | M (1-2 días) |
| TST-004 | Bajo | Instalar driver de cobertura (pcov) | XS (<2h) |

---

## 10. Conclusiones Actualizadas

### Fortalezas del Proyecto

✅ **Buenas prácticas identificadas**:
- Uso de PHPStan nivel 5 (análisis estático configurado)
- PHPCS configurado con estándares CakePHP
- Estructura de proyecto organizada (Services, Controllers, Models)
- Separación de concerns con traits
- Tests existentes (aunque no ejecutables actualmente)

### Debilidades Principales

❌ **Issues críticos restantes**:
1. ~~Tests no ejecutables (migración broken)~~ ✅ **RESUELTO**
2. 455 errores PHPStan no revisados
3. Archivos excesivamente largos (anti-pattern)
4. 1156 violaciones de estándares de código
5. 🆕 37/57 tests con issues de fixtures

### Viabilidad de Producción

**Estado actual**: 🟡 **MEJORA SIGNIFICATIVA pero aún NO LISTO**

**Progreso**:
- ✅ Tests ahora ejecutables (bloqueante crítico resuelto)
- ✅ 20/57 tests passing (35%)
- ⚠️ Fixtures necesitan actualización
- ⚠️ 455 errores PHPStan sin revisar
- ⚠️ Archivos >1000 líneas dificultan mantenimiento

**Razones para no ir a producción aún**:
- 455 errores PHPStan pueden ocultar bugs críticos
- 65% de tests fallando por fixtures
- Archivos excesivamente complejos

**Tiempo estimado para estar listo**: 2-3 semanas de trabajo enfocado
- Fix fixtures: 1 día
- Revisión PHPStan: 1 semana
- Refactoring crítico: 2 semanas

---

**Fin del Diagnóstico Automatizado - Fase 1 (Actualizado)**

**Cambios aplicados**:
- ✅ 9 campos enum → string
- ✅ 112 índices renombrados
- ✅ 27 migraciones ejecutadas exitosamente
- ✅ Tests ejecutables (20/57 passing)

**Próximo paso**: Iniciar Fase 2 - Auditoría Manual de Services (Día 2)
