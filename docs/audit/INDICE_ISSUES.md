# ÍNDICE DE ISSUES - Dónde encontrar cada uno

Creado: 2026-01-14
Total: 77 issues documentados

---

## BLOQUEADORES (2)

| Issue | Título | Archivo | Línea |
|-------|--------|---------|-------|
| BLK-001/SEC-001 | N8nService SSL Verification | PLAN_RESOLUCION_COMPLETO.md | ~150 |
| BLK-002/ARCH-005 | EmailService God Object | PLAN_RESOLUCION_COMPLETO.md | ~850 |

**Status**: BLK-001 ✅ IMPLEMENTADO (commit 674d9c2)

---

## ARQUITECTURA (15)

| Issue | Título | Archivo | Página |
|-------|--------|---------|--------|
| ARCH-001 | GmailService SRP violation | PLAN_RESOLUCION_COMPLETO.md | ~1800 |
| ARCH-002 | Query directa estática | PLAN_RESOLUCION_COMPLETO.md | ~2600 |
| ARCH-003 | S3Service no inyectado | PLAN_ISSUES_ADICIONALES.md | ~50 |
| ARCH-004 | DI Incompleta TicketService | PLAN_RESOLUCION_COMPLETO.md | ~3000 |
| ARCH-006 | EmailService dependencias | PLAN_ISSUES_ADICIONALES.md | ~150 |
| ARCH-007 | DI incompleta ResponseService | PLAN_ISSUES_ADICIONALES.md | ~200 |
| ARCH-008 | NotificationRenderer no inyectado | PLAN_ISSUES_ADICIONALES.md | ~250 |
| ARCH-009 | HTTP Client hardcoded WhatsApp | PLAN_ISSUES_ADICIONALES.md | ~300 |
| ARCH-010 | DI incompleta ComprasService | PLAN_ISSUES_ADICIONALES.md | ~350 |
| ARCH-011 | DI incompleta PqrsService | PLAN_ISSUES_ADICIONALES.md | ~400 |
| ARCH-012 | cURL hardcoded N8n | PLAN_ISSUES_ADICIONALES.md | ~450 |
| ARCH-014 | Dependencia Configure | PLAN_ISSUES_ADICIONALES.md | ~500 |
| ARCH-016 | Trait asume propiedades (ROOT CAUSE) | PLAN_RESOLUCION_COMPLETO.md | ~3500 |
| ARCH-017 | GenericAttachmentTrait crea S3Service | PLAN_ISSUES_ADICIONALES.md | ~550 |

**Nota**: ARCH-005 = BLK-002 (mismo issue, documentado como bloqueador)

---

## TRAITS (6)

| Issue | Título | Archivo |
|-------|--------|---------|
| TRAIT-001 | TicketSystemTrait grande (515 líneas) | Agente a7f77af (output) |
| TRAIT-002 | GenericAttachmentTrait → FileStorageService | Agente af469c1 (output) |
| TRAIT-003 | Hardcoded config ViewDataNormalizerTrait | PLAN_ISSUES_ADICIONALES.md |

**Nota**: 3 traits resueltos por ARCH-016

---

## CONTROLLERS (8)

| Issue | Título | Archivo |
|-------|--------|---------|
| CTRL-001 | DB queries en beforeFilter | PLAN_ISSUES_ADICIONALES.md |
| CTRL-002 | FormProtection disabled (CSRF) | PLAN_ISSUES_ADICIONALES.md |
| CTRL-003 | Direct queries en controller | PLAN_ISSUES_ADICIONALES.md |
| CTRL-004 | TicketSystemControllerTrait grande | Agente a3b05c8 (output) |
| CTRL-005 | PHPStan trait property errors | Agente a8df0ef (output) |
| CTRL-006 | StatisticsControllerTrait dependencies | Agente a8df0ef (output) |
| CTRL-007 | Long method StatisticsControllerTrait | Agente a8df0ef (output) |

---

## MODELS (4)

| Issue | Título | Archivo |
|-------|--------|---------|
| MODEL-001 | findWithFilters() duplicado | PLAN_RESOLUCION_COMPLETO.md |
| MODEL-002 | generateXXXNumber() duplicado | Agente a2c75f6 (output) |
| MODEL-003 | DocBlocks incompletos | PLAN_ISSUES_ADICIONALES.md |
| MODEL-004 | PHPStan propertyTag errors | PLAN_ISSUES_ADICIONALES.md |

---

## COMPLEJIDAD (6)

| Issue | Título | Archivo |
|-------|--------|---------|
| COM-001 | createMimeMessage largo | PLAN_ISSUES_SMELLS_COMPLEJIDAD.md |
| COM-002 | Recursión sin límite | PLAN_ISSUES_ADICIONALES.md |
| COM-003 | createFromEmail largo | PLAN_ISSUES_SMELLS_COMPLEJIDAD.md |
| COM-004 | EmailService duplicación | PLAN_ISSUES_SMELLS_COMPLEJIDAD.md |
| COM-005 | getSlaStatus complejidad | PLAN_ISSUES_SMELLS_COMPLEJIDAD.md |
| COM-006 | Métodos agregación | PLAN_ISSUES_SMELLS_COMPLEJIDAD.md |

**Nota**: COM-004 resuelto por BLK-002

---

## CODE SMELLS (10)

| Issue | Título | Archivo |
|-------|--------|---------|
| SMELL-001 | Magic strings headers | PLAN_ISSUES_SMELLS_COMPLEJIDAD.md |
| SMELL-002 | file_exists inconsistente | PLAN_ISSUES_ADICIONALES.md |
| SMELL-003 | Magic strings status/channel | PLAN_ISSUES_SMELLS_COMPLEJIDAD.md |
| SMELL-004 | Método no usado | PLAN_ISSUES_SMELLS_COMPLEJIDAD.md |
| SMELL-005 | Magic strings templates | PLAN_ISSUES_SMELLS_COMPLEJIDAD.md |
| SMELL-006 | Parsing email duplicado | PLAN_ISSUES_SMELLS_COMPLEJIDAD.md |
| SMELL-007 | Debug logging en prod | PLAN_ISSUES_SMELLS_COMPLEJIDAD.md |

**Nota**: 3 smells adicionales en PLAN_ISSUES_LOW_FINALES.md

---

## SEGURIDAD (2)

| Issue | Título | Archivo | Status |
|-------|--------|---------|--------|
| SEC-001 | N8nService SSL | PLAN_RESOLUCION_COMPLETO.md | ✅ DONE |
| SEC-002 | AWS credentials desde Configure | PLAN_ISSUES_ADICIONALES.md | Pending |

**Nota**: SEC-001 = BLK-001 (mismo issue)

---

## ISSUES LOW (40)

**Todos en**: `PLAN_ISSUES_LOW_FINALES.md`

Agrupados por categoría:

### Magic Strings y Constantes (8 issues)
- MS-001 a MS-008: MIME types, status values, config keys, etc.

### Métodos No Usados (3 issues)
- UNUSED-001 a UNUSED-003: Código muerto

### Documentación (4 issues)
- DOC-001 a DOC-004: PHPDoc faltante, comentarios

### Validaciones (3 issues)
- VAL-001 a VAL-003: Input validation, sanitization

### Configuración (3 issues)
- CFG-001 a CFG-003: Hardcoded config, environment

### Refactoring Menor (4 issues)
- REF-001 a REF-004: Extract method, simplificaciones

### Optimizaciones (8 issues)
- OPT-001 a OPT-008: Cache, queries, performance

### Testing (7 issues)
- TEST-001 a TEST-007: Cobertura, fixtures, mocking

---

## BÚSQUEDA RÁPIDA

### Por Severidad
```bash
# Bloqueadores (2)
grep -n "BLK-" docs/audit/PLAN_RESOLUCION_COMPLETO.md

# Críticos (9)
grep -n "ARCH-001\|ARCH-016\|MODEL-001\|TRAIT-001\|TRAIT-002" docs/audit/PLAN_*.md

# Medios (28)
grep -n "ARCH-\|CTRL-\|COM-" docs/audit/PLAN_*.md

# Bajos (40)
cat docs/audit/PLAN_ISSUES_LOW_FINALES.md
```

### Por Archivo Afectado
```bash
# EmailService
grep -n "EmailService\|BLK-002" docs/audit/PLAN_RESOLUCION_COMPLETO.md

# GmailService
grep -n "GmailService\|ARCH-001" docs/audit/PLAN_RESOLUCION_COMPLETO.md

# N8nService
grep -n "N8nService\|BLK-001" docs/audit/PLAN_RESOLUCION_COMPLETO.md

# Tables (Models)
grep -n "MODEL-001\|findWithFilters" docs/audit/PLAN_RESOLUCION_COMPLETO.md
```

---

## OUTPUTS DE AGENTES

6 agentes generaron documentación adicional:

```
.claude/task_outputs/
├── agent_a4a1d00_output.md  → MODEL-001
├── agent_af469c1_output.md  → TRAIT-002
├── agent_a2c75f6_output.md  → MODEL-002
├── agent_a3b05c8_output.md  → CTRL-004
├── agent_a7f77af_output.md  → TRAIT-001
├── agent_ab6e03a_output.md  → ARCH issues restantes
└── agent_a8df0ef_output.md  → CTRL issues restantes
```

Para leer outputs:
```bash
ls -lh .claude/task_outputs/
cat .claude/task_outputs/agent_a7f77af_output.md
```

---

## ARCHIVOS DE SOPORTE

| Archivo | Propósito |
|---------|-----------|
| LISTO_PARA_IMPLEMENTAR.md | **START HERE** - Overview + Primer paso |
| RESUMEN_EJECUTIVO_FINAL.md | Métricas, KPIs, beneficios |
| ROADMAP_PRODUCCION.md | Priorización por fases |
| PROGRESO_ACTUAL.md | Tracking de progreso |
| ESTADO_FINAL_Y_SIGUIENTE_PASO.md | 4 opciones de implementación |
| AUDITORIA_CALIDAD_CODIGO.md | Audit original (7,300 líneas) |

---

## RESUMEN EJECUTIVO

```
Total issues:        77/77 (100%)
Documentados:        77 (100%)
Implementados:       1 (BLK-001) ✅
Pendientes:          76

Distribución de archivos:
- PLAN_RESOLUCION_COMPLETO.md:         7 issues críticos
- PLAN_ISSUES_ADICIONALES.md:         21 issues
- PLAN_ISSUES_SMELLS_COMPLEJIDAD.md:  16 issues
- PLAN_ISSUES_LOW_FINALES.md:         25 issues LOW
- Agentes (outputs):                   8 issues

Total documentación: ~20,000 líneas
Total código solución: ~11,500 líneas
```

---

**Generado**: 2026-01-14
**Proyecto**: Mesa de Ayuda
**Status**: Documentación completa, implementación en progreso
