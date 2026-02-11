# 🗺️ MAPEO COMPLETO DEL PROYECTO - Mesa de Ayuda

**Fecha de generación**: 2026-01-08
**Propósito**: Auditoría pre-producción - Visión panorámica del sistema

---

## 📊 RESUMEN EJECUTIVO

| Categoría | Cantidad | Ubicación |
|-----------|----------|-----------|
| **Servicios** | 11 | `src/Service/` |
| **Traits de Servicios** | 5 | `src/Service/Traits/` |
| **Controladores Principales** | 8 | `src/Controller/` |
| **Controladores Admin** | 3 | `src/Controller/Admin/` |
| **Traits de Controladores** | 4 | `src/Controller/Traits/` |
| **Modelos (Tables)** | 17 | `src/Model/Table/` |
| **Entidades** | 17 | `src/Model/Entity/` |
| **Commands (CLI)** | 3 | `src/Command/` |
| **View Helpers** | 6 | `src/View/Helper/` |
| **View Cells** | 4 | `src/View/Cell/` |
| **Utilities** | 1 | `src/Utility/` |
| **Migraciones Activas** | 27 | `config/Migrations/` |
| **Migraciones Legacy** | 40+ | `config/Migrations/old/` |
| **Templates** | 81 | `templates/` |

---

## 🏗️ ARQUITECTURA DEL PROYECTO

### 1️⃣ CAPA DE SERVICIOS (Business Logic)

#### Servicios Principales (11)
```
src/Service/
├── GmailService.php              # Gmail OAuth2, fetching, parsing
├── TicketService.php             # Ticket lifecycle, email-to-ticket
├── ComprasService.php            # Purchase request workflows
├── PqrsService.php               # External PQRS management
├── EmailService.php              # Transactional email sending
├── WhatsappService.php           # WhatsApp notifications (Evolution API)
├── N8nService.php                # Webhook integration (AI tagging)
├── S3Service.php                 # AWS S3 file uploads/downloads
├── SlaManagementService.php      # SLA tracking and enforcement
├── StatisticsService.php         # Dashboard metrics and reporting
└── ResponseService.php           # Facade: Unified response handler (comment + status + files + notifications)
```

#### Traits de Servicios (5)
```
src/Service/Traits/
├── TicketSystemTrait.php         # Shared ticket/PQRS/compras logic
├── NotificationDispatcherTrait.php # Unified WhatsApp + email notifications
├── GenericAttachmentTrait.php    # File upload handling (S3/local)
├── StatisticsServiceTrait.php    # Common statistical calculations
└── EntityConversionTrait.php     # Entity-to-array with computed props
```

#### Renderer (1)
```
src/Service/Renderer/
└── NotificationRenderer.php      # Template rendering for notifications
```

---

### 2️⃣ CAPA DE CONTROLADORES (Presentation)

#### Controladores Principales (8)
```
src/Controller/
├── AppController.php             # Base controller (auth, settings, layout)
├── TicketsController.php         # Helpdesk ticket CRUD
├── ComprasController.php         # Purchase request CRUD
├── PqrsController.php            # External PQRS CRUD + public form
├── UsersController.php           # User management
├── PagesController.php           # Static pages
├── ErrorController.php           # Error handling
└── HealthController.php          # Health check endpoint
```

#### Controladores Admin (3)
```
src/Controller/Admin/
├── SettingsController.php        # System settings (Gmail, WhatsApp, n8n)
├── SlaManagementController.php   # SLA configuration
└── ConfigFilesController.php     # Config file upload/download
```

#### Traits de Controladores (4)
```
src/Controller/Traits/
├── ServiceInitializerTrait.php         # Service instantiation helper
├── ViewDataNormalizerTrait.php         # Data formatting for views
├── StatisticsControllerTrait.php       # Dashboard statistics logic
└── TicketSystemControllerTrait.php     # Shared CRUD operations
```

---

### 3️⃣ CAPA DE DATOS (Models & Entities)

#### Modelos / Tablas (17)
```
src/Model/Table/
├── OrganizationsTable.php
├── UsersTable.php
├── SystemSettingsTable.php
├── EmailTemplatesTable.php
├── TagsTable.php
│
├── TicketsTable.php              # Soporte Interno
├── TicketCommentsTable.php
├── AttachmentsTable.php
├── TicketHistoryTable.php
├── TicketTagsTable.php
├── TicketFollowersTable.php
│
├── ComprasTable.php              # Gestión de Compras
├── ComprasCommentsTable.php
├── ComprasAttachmentsTable.php
├── ComprasHistoryTable.php
│
├── PqrsTable.php                 # PQRS External
├── PqrsCommentsTable.php
├── PqrsAttachmentsTable.php
└── PqrsHistoryTable.php
```

#### Entidades (17) - Mirroring Tables
```
src/Model/Entity/
├── Organization.php, User.php, SystemSetting.php, EmailTemplate.php, Tag.php
├── Ticket.php, TicketComment.php, Attachment.php, TicketHistory.php, TicketTag.php, TicketFollower.php
├── Compra.php, ComprasComment.php, ComprasAttachment.php, ComprasHistory.php
└── Pqr.php, PqrsComment.php, PqrsAttachment.php, PqrsHistory.php
```

---

### 4️⃣ COMANDOS CLI & WORKERS

```
src/Command/
├── GmailWorkerCommand.php        # Background worker (scheduled Gmail imports)
├── ImportGmailCommand.php        # Manual Gmail import execution
└── TestEmailCommand.php          # Email configuration tester
```

**Background Worker**: `GmailWorkerCommand` runs in Docker container, executes `ImportGmailCommand` on schedule.

---

### 5️⃣ CAPA DE VISTA (View Layer)

#### View Helpers (6)
```
src/View/Helper/
├── TimeHumanHelper.php           # Human-readable timestamps
├── TicketHelper.php              # Ticket-specific formatting
├── PqrsHelper.php                # PQRS-specific formatting
├── ComprasHelper.php             # Compras-specific formatting
├── UserHelper.php                # User display utilities
└── StatusHelper.php              # Status badge rendering
```

#### View Cells (4) - Sidebar Components
```
src/View/Cell/
├── TicketsSidebarCell.php
├── ComprasSidebarCell.php
├── PqrsSidebarCell.php
└── UsersSidebarCell.php
```

#### Custom Views (2)
```
src/View/
├── AppView.php                   # Base view class
└── AjaxView.php                  # AJAX response handler
```

#### Templates (81 files)
```
templates/
├── layout/                       # Base layouts (admin, agent, default)
├── element/                      # Reusable components
├── Tickets/                      # Ticket module views
├── Compras/                      # Compras module views
├── Pqrs/                         # PQRS module views
├── Users/                        # User management views
└── Admin/                        # Admin panel views
```

---

### 6️⃣ CONFIGURACIÓN & MIGRACIONES

#### Archivos de Configuración
```
config/
├── app.php                       # Main application config
├── app_local.php                 # Local environment config (gitignored)
├── app_local.example.php         # Template for local config
├── bootstrap.php                 # Bootstrap initialization
├── paths.php                     # Directory paths
├── plugins.php                   # Plugin loading
└── routes.php                    # Route definitions
```

#### Migraciones (27 activas + 40+ legacy)
```
config/Migrations/
├── 20260105000001_CreateOrganizations.php
├── 20260105000002_CreateUsers.php
├── 20260105000003_CreateSystemSettings.php
├── 20260105000004_CreateEmailTemplates.php
├── 20260105000005_CreateTags.php
├── 20260105000006_CreateTickets.php
├── ... (21 more active migrations)
└── old/                          # Legacy migrations (archived)
```

---

### 7️⃣ UTILITIES & TRAITS

```
src/Utility/
└── SettingsEncryptionTrait.php   # Encrypt/decrypt sensitive settings
```

```
src/Console/
└── Installer.php                 # Post-install setup script
```

---

---

## 📚 DOCUMENTACIÓN DEL PROYECTO

```
Root Documentation:
├── README.md                     # Project overview
├── CLAUDE.md                     # Claude Code instructions (patterns, architecture)
├── DOCKER.md                     # Docker deployment guide
├── EASYPANEL.md                  # Production deployment (Easypanel)
└── AUDITORIA_MAPEO.md           # This file (audit roadmap)
```

---

## 🔍 PUNTOS CRÍTICOS PARA AUDITORÍA

### ✅ Servicios Analizados
- **ResponseService.php**: Facade pattern - coordina Tickets/PQRS/Compras para respuestas unificadas
  - **PREGUNTA**: ¿Es necesario o los controllers pueden llamar directamente a servicios específicos?

### ⚠️ Duplicación Potencial
- ¿3 módulos (Tickets/Compras/PQRS) comparten suficiente código via traits?
- ¿Hay lógica duplicada entre Controllers que debería estar en `TicketSystemControllerTrait`?

### ⚠️ Migraciones Legacy
- 40+ migraciones en carpeta `config/Migrations/old/` - ¿Se pueden eliminar permanentemente?
- Migraciones activas (27) vs legacy (40+) - Requiere limpieza

### ⚠️ Docker & Deployment
- `docker-compose.yml` (development)
- `docker-compose.prod.yml` (production)
- Worker container con Supervisor para `GmailWorkerCommand`

### ⚠️ Configuración
- `app_local.php` contiene configuración sensible (verificar .gitignore)

---

## 📋 PRÓXIMOS PASOS

### Fase 1: Auditoría de Servicios
- [ ] Revisar cada servicio línea por línea
- [ ] Verificar que todos acepten `?array $systemConfig = null`
- [ ] Identificar código duplicado
- [ ] Validar manejo de errores

### Fase 2: Auditoría de Traits
- [ ] Verificar consistencia de uso
- [ ] Identificar oportunidades de consolidación

### Fase 3: Auditoría de Controladores
- [ ] Verificar que sean "thin controllers"
- [ ] Mover lógica de negocio a servicios si aplica

### Fase 4: Auditoría de Modelos
- [ ] Revisar validaciones
- [ ] Optimizar queries y asociaciones

### Fase 5: Auditoría de Seguridad
- [ ] Revisar autenticación/autorización
- [ ] Validar sanitización de inputs
- [ ] Verificar encriptación de datos sensibles

---

**Generado automáticamente para auditoría pre-producción**
