# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Mesa de Ayuda** - An enterprise-grade integrated corporate management system built on CakePHP 5.x. This is a multi-module platform handling:
- **Tickets** (Helpdesk/Internal Support)
- **Compras** (Purchase Requests)
- **PQRS** (External Customer Requests)

The system integrates with Gmail API, n8n automation workflows, and WhatsApp Business (Evolution API) for comprehensive business process automation.

## Development Commands

### Essential Commands

```bash
# Install dependencies
composer install

# Development server (runs on http://localhost:8765)
bin/cake server

# Database migrations
bin/cake migrations migrate        # Apply pending migrations
bin/cake migrations seed          # Seed test/demo data

# Code quality checks
composer check                    # Run all checks (tests + linting + analysis)
composer test                     # Run PHPUnit tests
composer cs-check                 # Check code style (PHP CodeSniffer)
composer cs-fix                   # Auto-fix code style issues
composer stan                     # Run PHPStan static analysis (level 5)
```

### CLI Commands

```bash
# Import Gmail emails and convert to tickets
bin/cake import_gmail

# Test email configuration
bin/cake test_email
```

### Code Generation (Bake)

```bash
# Generate model classes
bin/cake bake model {ModelName}

# Generate controller
bin/cake bake controller {ControllerName}

# Generate template
bin/cake bake template {ControllerName}
```

## Architecture Overview

### Service Layer Pattern

Business logic is **not** in controllers. Controllers are thin request handlers that delegate to service classes:

- **TicketService** - Ticket lifecycle, creation, assignment, status changes
- **ComprasService** - Purchase request processing and workflow
- **PqrsService** - External customer request handling
- **SlaManagementService** - SLA calculation and management for all modules
- **GmailService** - Email-to-ticket conversion, Gmail API integration
- **WhatsappService** - WhatsApp notifications via Evolution API
- **N8nService** - n8n webhook integration, AI-powered tag classification
- **EmailService** - Email template rendering and sending
- **StatisticsService** - Analytics and reporting
- **ResponseService** - Standardized JSON response formatting

**Critical Rule**: When adding new features, create or extend services rather than putting logic in controllers.

### Controller Architecture

Controllers use traits for reusable logic:

- **ServiceInitializerTrait** - Lazy service injection pattern
- **StatisticsControllerTrait** - Statistics rendering logic
- **TicketSystemControllerTrait** - Common ticket operations
- **ViewDataNormalizerTrait** - View data preparation

Each controller action should:
1. Validate request
2. Call appropriate service method(s)
3. Handle response (redirect or render view)

### Model Relationships

The three main modules (Tickets, Compras, PQRS) follow parallel structures:

**Ticket Module:**
- Ticket (main entity)
- TicketComment
- TicketHistory (audit trail)
- TicketFollower
- TicketTag
- Attachment (polymorphic)

**Compras Module:**
- Compra (main entity)
- ComprasComment
- ComprasHistory
- ComprasAttachment

**PQRS Module:**
- Pqr (main entity)
- PqrsComment
- PqrsHistory
- PqrsAttachment

**Shared Entities:**
- User (with roles: Admin, Agent, Compras, Requester, Servicio Cliente)
- Organization (multi-tenancy)
- Tag (categorization)
- SystemSetting (configuration with encryption support)
- EmailTemplate (dynamic email content)

### Gmail Integration Architecture

The Gmail integration uses OAuth2 and implements intelligent email threading to prevent duplicate tickets:

**Core Components:**
1. **GmailService** - API authentication, email parsing, and filtering
2. **TicketService** - Email-to-ticket/comment conversion with threading logic
3. **ImportGmailCommand** - Automated email processing pipeline

**Email Threading (Added Jan 2026):**

The system automatically converts email replies to existing tickets into comments instead of creating duplicate tickets.

**Threading Logic (4-filter process):**
1. **Auto-reply detection** - Skips out-of-office and automated responses
2. **Notification detection** - Ignores responses to system notifications (prevents email loops)
3. **Duplicate check** - Prevents reprocessing same `gmail_message_id`
4. **Thread matching** - Checks `gmail_thread_id`:
   - If thread exists → Creates comment via `createCommentFromEmail()`
   - If new thread → Creates ticket via `createFromEmail()`

**Security & Authorization:**
- Only recipients in original To/CC can comment via email (recipient validation)
- Unauthorized senders are rejected but email is marked read
- All email headers sanitized to prevent CRLF injection attacks
- Attachments validated against allowed file types before processing

**Auto-Reply Detection:**
Detects and filters automated responses using standard email headers:
- `Auto-Submitted: auto-replied`
- `X-Autoreply: yes`
- `X-Autorespond: yes`
- `Precedence: auto_reply` / `bulk` / `junk`

**Notification Detection (Enhanced Jan 2026):**
Multi-layered detection to prevent email loops from replies to system notifications:
1. **Custom header check** - `X-Mesa-Ayuda-Notification: true` (added to all outgoing notifications)
2. **Sender verification** - Detects if email is FROM system address (`gmail_user_email`)
3. **Subject pattern matching** - Identifies replies to notification subjects (`Re: [Ticket #`, `Re: [PQRS #`, `Re: [Compra #`)

Note: Custom headers don't propagate when users reply, so layers 2-3 are critical for reliability.

**Key Features:**
- Email attachments properly associated with comments
- Comments created via email do NOT trigger notifications (prevents infinite loops)
- Closed tickets can receive comments without reopening
- New users auto-created if sender is in original To/CC
- Console output shows: Created tickets, Comments added, Skipped, Errors

**Important Database Fields:**
- `tickets.gmail_thread_id` - Groups related emails (threading key)
- `tickets.gmail_message_id` - Unique message identifier (duplicate prevention)
- `tickets.email_to` - Original To recipients (JSON array, used for authorization)
- `tickets.email_cc` - Original CC recipients (JSON array, used for authorization)

**Important**: When modifying Gmail integration, test with `bin/cake import_gmail` command first.

### n8n Automation Integration

The system sends webhooks to n8n workflows for:
- **AI-powered tag classification** (automatic ticket categorization)
- **Custom workflow triggers** (status changes, assignments)
- **Notification routing**

Configuration is in SystemSettings table with keys prefixed `n8n_`.

**Note**: n8n settings are cached for 1 hour. Clear cache after config changes.

### WhatsApp Notifications

WhatsApp messages are sent through Evolution API integration:
- Real-time notifications for ticket/compras status changes
- Transaction messages (confirmations, updates)
- Configurable per user via SystemSettings

**Configuration keys**: `whatsapp_enabled`, `whatsapp_api_url`, `whatsapp_instance`

### Authentication & Authorization

- **Authentication**: CakePHP Authentication 3.0 with Session/Form authenticators
- **Credentials**: Email address (NOT username) + password
- **Authorization**: Role-based access control implemented per controller action
- **Roles**: Admin, Agent, Compras, Requester, Servicio Cliente

**Important**: The system uses `email` field for login, not `username`.

## Database Schema & Migrations

41 migrations define the complete schema. Key points:

- **All tables use AUTO_INCREMENT primary keys** (`id`)
- **Foreign keys** use CASCADE/SET_NULL appropriately
- **Unique constraints** on business identifiers (ticket_number, gmail_message_id)
- **Performance indexes** on frequently queried columns (status, priority, assignee_id, created)
- **Enum types** for constrained values (status, priority, role)
- **Timestamps** (created, modified) on all entities
- **SLA tracking** fields (first_response_at, resolved_at, first_response_sla_due, resolution_sla_due)

### SLA (Service Level Agreement) System

The system includes comprehensive SLA tracking across modules (added Dec 2024):

**PQRS Module SLA Fields:**
- `closed_at` - Fecha de cierre del PQRS
- `first_response_sla_due` - Deadline para primera respuesta (indexed)
- `resolution_sla_due` - Deadline para resolución (indexed)

**Compras Module SLA Fields:**
- `first_response_sla_due` - Deadline para primera respuesta (indexed)
- `resolution_sla_due` - Deadline para resolución (indexed)
- `sla_due_date` - Legacy field (deprecated, kept for backward compatibility)

**SLA Configuration (SystemSettings):**

PQRS has different SLA targets by request type:
- **Petición**: 2 days first response, 5 days resolution
- **Queja**: 1 day first response, 3 days resolution
- **Reclamo**: 1 day first response, 3 days resolution
- **Sugerencia**: 3 days first response, 7 days resolution

Compras has uniform SLA targets:
- **First Response**: 1 day
- **Resolution**: 3 days

**Configuration keys in SystemSettings:**
```
sla_pqrs_{tipo}_first_response_days    # Petición, Queja, Reclamo, Sugerencia
sla_pqrs_{tipo}_resolution_days
sla_compras_first_response_days
sla_compras_resolution_days
```

**Important Notes:**
- SLA deadlines are calculated automatically when creating new Compras/PQRS
- Existing Compras records were migrated: `sla_due_date` → `resolution_sla_due`
- SLA settings are cached for 1 hour (clear cache after modifications)
- Indexes on `*_sla_due` fields enable efficient breach queries

**Related Migrations:**
1. `20251227150226_AddSlaFieldsToPqrs.php` - SLA fields for PQRS
2. `20251227150341_AddSlaFieldsToCompras.php` - SLA fields for Compras
3. `20251227150434_MigrateComprasSlaData.php` - Data migration for existing records
4. `20251227150559_SeedSlaSettings.php` - Default SLA configuration

### Creating New Migrations

```bash
# Create migration
bin/cake bake migration {MigrationName}

# Example: Adding a field
bin/cake bake migration AddFieldToTable field:type

# Apply migrations
bin/cake migrations migrate
```

**Convention**: Use descriptive migration names (e.g., `AddEmailThreadingToTickets`, `CreateComprasModule`)

## Frontend Architecture

### JavaScript Modules

Modern ES6-style modules in `webroot/js/`:

- **bulk-actions-module.js** - Bulk operations (select all, mass actions)
- **entity-history-lazy.js** - Lazy-loaded audit trail
- **modern-statistics.js** - Statistics dashboard
- **statistics-animations.js** - Chart animations
- **email-recipients.js** - Email recipient management
- **select2-init.js** - Enhanced dropdown initialization
- **loading-spinner.js** - Loading state management
- **flash-messages.js** - Toast notifications
- **marquee.js** - Scrolling announcements

**Pattern**: Modules are loaded on-demand per page. Do not bundle into single file.

### CSS Structure

- **Bootstrap 5** - Primary framework (do NOT add Bootstrap 4 or other versions)
- **Custom CSS** in `webroot/css/`:
  - `styles.css` - Global styles
  - `modern-statistics.css` - Statistics dashboard
  - `bulk-actions.css` - Bulk action UI

**Important**: Avoid inline styles. Use utility classes or dedicated CSS files.

### Templates (Twig)

Templates follow CakePHP conventions with Twig syntax:

```
templates/
├── layout/           # Layouts (admin, agent, compras, default)
├── Tickets/          # Ticket module views
├── Compras/          # Compras module views
├── Pqrs/             # PQRS module views
├── Element/          # Reusable partials
├── cell/             # Cell components (sidebars, widgets)
└── email/            # Email templates
```

**Template inheritance**: Most templates extend a layout. Use `{% extends 'layout/name.twig' %}`.

## Routing Conventions

### Public Routes (No Authentication)

```
GET  /                           - Home (redirects to tickets)
GET  /health                     - Docker health check
GET  /pqrs/formulario            - Public PQRS submission form
GET  /pqrs/success/{pqrsNumber}  - PQRS confirmation page
```

### Authenticated Routes

```
# Tickets
GET    /tickets                    - Ticket list
GET    /tickets/view/{id}          - Ticket details
POST   /tickets/add                - Create ticket
POST   /tickets/add-comment/{id}   - Add comment
POST   /tickets/assign/{id}        - Assign ticket
POST   /tickets/change-status/{id} - Update status
GET    /tickets/convert-to-compra/{id} - Convert to purchase request

# Compras
GET    /compras                    - Purchase list
GET    /compras/view/{id}          - Purchase details
POST   /compras/add-comment/{id}   - Add comment
POST   /compras/assign/{id}        - Assign purchase
POST   /compras/change-status/{id} - Update status
POST   /compras/change-priority/{id} - Update priority
GET    /compras/download/{id}      - Download attachment

# Admin
GET    /admin/settings             - System configuration
```

**Pattern**: Use named routes in templates. Avoid hardcoded URLs.

## Configuration Files

### Environment-Specific Configuration

- **config/app.php** - Base configuration (committed to git)
- **config/app_local.php** - Local overrides (NOT in git, contains secrets)
- **config/app_local.example.php** - Template for local config

**Critical**: NEVER commit `app_local.php`. It contains database credentials and API keys.

### API Credentials

Store in SystemSettings table or config/app_local.php:

```php
// Gmail OAuth2
'google_client_id' => env('GOOGLE_CLIENT_ID'),
'google_client_secret' => env('GOOGLE_CLIENT_SECRET'),

// WhatsApp Evolution API
'whatsapp_api_url' => env('WHATSAPP_API_URL'),
'whatsapp_instance' => env('WHATSAPP_INSTANCE'),

// n8n Webhooks
'n8n_webhook_url' => env('N8N_WEBHOOK_URL'),
```

## Testing

### Running Tests

```bash
# All tests
composer test

# Specific test file
vendor/bin/phpunit tests/TestCase/Controller/TicketsControllerTest.php

# Single test method
vendor/bin/phpunit --filter testIndex tests/TestCase/Controller/TicketsControllerTest.php
```

### Test Structure

```
tests/
├── TestCase/
│   ├── Controller/    # Controller integration tests
│   ├── Model/         # Entity and Table unit tests
│   └── Service/       # Service layer tests
└── Fixture/           # Test data fixtures
```

**Convention**: Test files mirror source structure (e.g., `TicketService.php` → `TicketServiceTest.php`)

## Code Quality Standards

- **PHPStan Level**: 5 (strict type checking enabled)
- **CakePHP CodeSniffer**: CakePHP coding standards enforced
- **Type Hints**: Required on all public methods (params and return types)
- **Docblocks**: Required for all classes and public methods

### Before Committing

```bash
# Always run before committing
composer check

# This runs:
# 1. composer test      - PHPUnit tests
# 2. composer cs-check  - Code style validation
# 3. composer stan      - Static analysis
```

**Rule**: All checks must pass before creating a pull request.

## Common Patterns & Conventions

### Adding a New Service

1. Create service class in `src/Service/`
2. Add service initialization to relevant controller using `ServiceInitializerTrait`
3. Add type hints and docblocks
4. Write tests in `tests/TestCase/Service/`

Example:

```php
namespace App\Service;

class MyNewService
{
    /**
     * @param \App\Model\Entity\Ticket $ticket The ticket entity
     * @return bool Success status
     */
    public function processTicket(\App\Model\Entity\Ticket $ticket): bool
    {
        // Business logic here
        return true;
    }
}
```

### Adding New Migrations

When adding fields or tables:

1. Create migration: `bin/cake bake migration DescriptiveName`
2. Edit migration file in `config/Migrations/`
3. Test locally: `bin/cake migrations migrate`
4. **Always add rollback logic** (down() method)

### Working with System Settings

Settings are stored in the database with optional encryption:

```php
// In controller/service
$this->loadModel('SystemSettings');
$value = $this->SystemSettings->getSetting('setting_key', 'default_value');

// Encrypted settings (for sensitive data)
$apiKey = $this->SystemSettings->getSetting('api_key'); // Auto-decrypted
```

**Important**: Settings are cached for 1 hour. Clear cache after changes.

### Working with SLA Management

The system includes a centralized SLA Management service that handles Service Level Agreement calculations for PQRS and Compras modules.

**Admin Interface:**
Navigate to Admin → Settings → Gestión SLA (`/admin/sla-management`) to configure SLA targets.

**SLA Configuration:**

PQRS has type-specific SLA targets:
```php
// Get SLA settings for specific PQRS type
$slaService = new SlaManagementService();
$settings = $slaService->getPqrsSlaSettings('queja');
// Returns: ['first_response_days' => 1, 'resolution_days' => 3]
```

Compras has uniform SLA targets:
```php
$settings = $slaService->getComprasSlaSettings();
// Returns: ['first_response_days' => 1, 'resolution_days' => 3]
```

**Automatic SLA Calculation:**

SLA deadlines are calculated automatically when creating PQRS or Compras:

```php
// PQRS - SLA calculated based on type
$pqrsService = new PqrsService();
$pqrs = $pqrsService->createFromForm($formData, $files);
// $pqrs->first_response_sla_due and $pqrs->resolution_sla_due are auto-set

// Compras - SLA calculated from settings
$comprasService = new ComprasService();
$compra = $comprasService->createFromTicket($ticket, $data);
// $compra->first_response_sla_due and $compra->resolution_sla_due are auto-set
```

**Checking SLA Status:**

```php
// For PQRS
$slaStatus = $pqrsService->getSlaStatus($pqrs);
// Returns: [
//   'first_response' => ['status' => 'met|breached|on_track|approaching', 'class' => 'success|danger|info|warning', 'label' => '...'],
//   'resolution' => [...]
// ]

// For Compras
$slaStatus = $comprasService->getSlaStatus($compra);

// Check if SLA is breached
$isBreached = $comprasService->isResolutionSLABreached($compra);
$isFirstResponseBreached = $comprasService->isFirstResponseSLABreached($compra);
```

**Finding Breached SLAs:**

```php
// Get all Compras with breached SLA
$breachedCompras = $comprasService->getBreachedSLACompras();

// Get all PQRS with breached SLA
$breachedPqrs = $pqrsService->getBreachedSLAPqrs();
```

**Important Notes:**
- SLA settings are cached for 1 hour
- After updating SLA configuration in admin, cache is automatically cleared
- SLA deadlines are calculated based on creation date
- Closed/completed entities do not trigger SLA breach status
- For PQRS: Each type (petición, queja, reclamo, sugerencia) has different SLA targets
- For Compras: All purchase requests use the same SLA targets

### File Uploads

Attachments are stored in `webroot/uploads/`:

```
webroot/uploads/
├── tickets/        # Ticket attachments
├── compras/        # Purchase attachments
└── pqrs/          # PQRS attachments
```

Use `GenericAttachmentTrait` for common file handling operations.

## Troubleshooting

### Gmail Integration Issues

1. Check OAuth2 credentials in SystemSettings
2. Verify token refresh: `bin/cake import_gmail`
3. Check logs in `logs/` directory
4. Test email sending: `bin/cake test_email`

### WhatsApp Notifications Not Sending

1. Verify Evolution API is running
2. Check `whatsapp_enabled` setting
3. Verify instance is connected in Evolution API dashboard
4. Check logs for webhook errors

### n8n Webhooks Not Firing

1. Verify `n8n_webhook_url` in SystemSettings
2. Check n8n workflow is active
3. Test webhook manually with curl
4. Check timeout settings (default: 10 seconds)

### Database Connection Errors

1. Verify `config/app_local.php` credentials
2. Check database server is running
3. Verify migrations are up to date: `bin/cake migrations status`

## Development Workflow

1. **Create feature branch**: `git checkout -b feature/description`
2. **Make changes** following code standards
3. **Run quality checks**: `composer check`
4. **Test manually** using development server
5. **Commit with descriptive message**
6. **Push and create pull request**

**Commit Message Convention**: Use descriptive commits that explain the "why", not just the "what".

## Important Notes

- **Multi-tenancy**: The system supports multiple organizations. Always filter by `organization_id` where applicable.
- **Soft Deletes**: Use history tables for audit trails. Avoid hard deletes on core entities.
- **Email Threading**: When working with Gmail integration, preserve `gmail_thread_id` and `gmail_message_id` relationships.
- **Role-Based Access**: Always check user role before authorizing actions (implemented in AppController).
- **File Security**: Uploaded files should be validated and sanitized (HTMLPurifier is included).
- **Caching**: System settings and n8n configuration are cached. Clear cache when updating settings.
