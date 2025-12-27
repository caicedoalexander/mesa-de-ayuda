# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **CakePHP 5.x** enterprise helpdesk system with three integrated modules:
- **Tickets (Soporte)**: Internal IT support ticket management
- **Compras**: Purchase requisition workflow system
- **PQRS**: External customer complaints/suggestions module

The system integrates with Gmail (email-to-ticket conversion), WhatsApp (Evolution API), and n8n (workflow automation with AI capabilities).

## Development Commands

### Essential Commands
```bash
# Install dependencies
composer install

# Database migrations (run in order)
bin/cake migrations migrate
bin/cake migrations seed

# Start development server
bin/cake server

# Run all quality checks (tests + phpcs + phpstan)
composer check
```

### Testing & Code Quality
```bash
# Run PHPUnit tests
composer test
# or with verbose output
bin/cake phpunit --colors=always

# Code style checking (PSR-12 via CakePHP standards)
composer cs-check

# Fix code style issues automatically
composer cs-fix

# Static analysis (PHPStan level 5)
composer stan
# or directly
vendor/bin/phpstan analyse
```

### Database Operations
```bash
# Run migrations
bin/cake migrations migrate

# Rollback last migration
bin/cake migrations rollback

# Create new migration
bin/cake bake migration MigrationName

# Run seeders
bin/cake migrations seed
```

### Custom Console Commands
```bash
# Import Gmail emails and create tickets
bin/cake import_gmail
bin/cake import_gmail --max 100 --query "is:unread"
bin/cake import_gmail --delay 2000

# Test email configuration
bin/cake test_email
```

### Bake (Code Generation)
```bash
# Generate controller
bin/cake bake controller ControllerName

# Generate model (entity + table)
bin/cake bake model ModelName

# Generate all (controller + model + templates)
bin/cake bake all ModelName
```

## Architecture & Code Organization

### Service Layer Architecture
Business logic is centralized in **service classes** (`src/Service/`), NOT in controllers. Controllers delegate to services:

- **TicketService**: Ticket lifecycle, email-to-ticket conversion, status changes
- **PqrsService**: PQRS management (mirrors ticket system for external users)
- **ComprasService**: Purchase requisition workflow
- **EmailService**: Transactional emails using templates from database
- **WhatsappService**: WhatsApp notifications via Evolution API
- **GmailService**: OAuth2 Gmail integration, attachment handling
- **N8nService**: Webhook integration for AI-powered tag classification
- **StatisticsService**: Dashboard metrics and analytics
- **ResponseService**: Unified comment/reply handling for tickets/PQRS

**Critical Pattern**: Controllers call services, services call other services. Keep controllers thin.

### Shared Logic via Traits

The codebase uses **traits extensively** to eliminate duplication between Tickets and PQRS modules:

#### Service Traits (`src/Service/Traits/`)
- **TicketSystemTrait**: Shared logic for status changes, assignments, priority changes
- **NotificationDispatcherTrait**: Email/WhatsApp notification orchestration
- **GenericAttachmentTrait**: File upload/download handling
- **StatisticsServiceTrait**: Common metrics calculations
- **SLAManagementTrait**: SLA calculation, breach detection, and recalculation logic (see SLA Management section)

#### Controller Traits (`src/Controller/Traits/`)
- **TicketSystemControllerTrait**: Actions like `assignEntity()`, `changeEntityStatus()`, `addEntityComment()`
- **StatisticsControllerTrait**: Dashboard statistics rendering
- **ViewDataNormalizerTrait**: Helper methods for view data preparation

**Usage Pattern**: Traits accept an `$entityType` parameter (`'ticket'`, `'pqrs'`, or `'compra'`) to switch behavior dynamically.

### Database Configuration

- **Database**: MySQL/MariaDB configured in `config/app_local.php` (gitignored)
- **Configuration Template**: Copy `config/app_local.example.php` to `config/app_local.php`
- **System Settings**: Stored in `system_settings` table (cached, accessed via `SettingsEncryptionTrait`)
- **Email Templates**: Dynamic templates in `email_templates` table with variable substitution

### Migrations & Seeding

Migrations are **timestamped and sequential**:
1. Core tables: `20251205000001_CreateOrganizations.php` through `20251205000015_CreatePqrsHistory.php`
2. Seeders: `20251205000016_SeedSystemSettings.php`, `SeedEmailTemplates.php`, `SeedTags.php`, `SeedAdminUser.php`
3. Schema changes: `20251206164107_AddChannelToTickets.php`, etc.

**Rule**: Always create migrations for schema changes. Never modify existing migrations after deployment.

### Authentication & Authorization

- **Authentication**: CakePHP Authentication plugin (`cakephp/authentication`)
- **Role-based Access**: Users have roles (`admin`, `agente`, `usuario`, `compras`, `servicio_cliente`)
- **Role Redirects**: Controllers redirect users to their allowed module in `beforeFilter()`
  - `compras` role → `/compras`
  - `servicio_cliente` role → `/pqrs`
  - Others → `/tickets`

### Key Integrations

#### Gmail Integration
- **OAuth2 Flow**: Configured via Admin Settings (`/admin/settings`)
- **Client Secret**: Stored in `config/google/client_secret.json` (gitignored)
- **Tokens**: Encrypted in `system_settings` table using `SettingsEncryptionTrait`
- **Email Import**: Runs via `ImportGmailCommand` (can be automated with cron)

#### WhatsApp (Evolution API)
- **Configuration**: API URL, instance name, API key in `system_settings`
- **Message Templates**: HTML-based, rendered via `NotificationRenderer`
- **Use Cases**: Ticket assignments, status changes, new comments

#### n8n Automation
- **Webhook**: Sends ticket data to n8n on creation
- **AI Classification**: n8n returns suggested tags based on ticket content
- **Configuration**: `n8n_webhook_url`, `n8n_api_key` in system settings
- **Lazy Loading**: N8nService only instantiated when needed to reduce overhead

### Template System

- **Engine**: CakePHP templates (`.php` files) + Twig support available but not used
- **Structure**:
  - `templates/Tickets/`, `templates/Pqrs/`, `templates/Compras/` - Module views
  - `templates/Element/` - Reusable elements (especially `shared/` for ticket/PQRS commonalities)
  - `templates/cell/` - ViewCells for sidebar statistics
- **Shared Elements Pattern**: `Element/shared/` contains forms/displays reused across Tickets and PQRS (e.g., comments, attachments)

### Frontend Architecture

- **Stack**: Bootstrap 5, Vanilla JavaScript (no framework)
- **Philosophy**: Server-rendered views, progressive enhancement
- **AJAX**: Used for dynamic actions (comments, status changes) with JSON responses
- **Assets**: `webroot/css/` and `webroot/js/`

## Important Conventions

### Code Style
- **PSR-12** via CakePHP Code Sniffer
- **Strict Types**: All files use `declare(strict_types=1);`
- **Type Hints**: Always use parameter and return type hints
- **PHPDoc**: Required for complex methods, especially service classes

### Naming Conventions
- **Controllers**: Plural (`TicketsController`, `PqrsController`)
- **Tables**: Plural (`TicketsTable`, `PqrsTable`)
- **Entities**: Singular (`Ticket`, `Pqrs`)
- **Services**: Singular with `Service` suffix (`TicketService`)
- **Traits**: Descriptive with `Trait` suffix (`TicketSystemTrait`)

### Configuration Pattern
Services accept optional `$systemConfig` array in constructors to avoid redundant database queries. This config is cached at the AppController level and passed down:

```php
// In Controller
$systemConfig = Cache::remember('system_settings', ...);
$service = new TicketService($systemConfig);

// In Service
public function __construct(?array $systemConfig = null) {
    $this->config = $systemConfig ?? $this->loadConfigFromDb();
}
```

### Security Considerations
- **HTML Purifier**: Used selectively - email content is stored raw, purified on display
- **CSRF Protection**: Enabled by default via CakePHP FormProtection
- **Encryption**: Sensitive settings (API keys, OAuth tokens) encrypted via `SettingsEncryptionTrait`
- **File Uploads**: Validated extensions, stored outside webroot when possible

## Testing Guidelines

- **Test Location**: `tests/TestCase/` mirrors `src/` structure
- **Fixtures**: Define test data in `tests/Fixture/`
- **Run Single Test**: `bin/cake phpunit tests/TestCase/Service/TicketServiceTest.php`
- **Coverage**: Run `composer test` to generate coverage report

## Common Gotchas

1. **System Settings Cache**: After changing settings in Admin panel, cache is auto-cleared. Manual clear: `Cache::clear(false, '_cake_core_')`

2. **Trait Method Conflicts**: When using multiple traits, be aware of method name collisions. Use trait aliasing if needed.

3. **Entity Type Parameter**: Methods in traits require `$entityType` ('ticket', 'pqrs', 'compra'). Always pass the correct string.

4. **Gmail Token Refresh**: Access tokens expire. The system auto-refreshes, but if authentication fails, re-authorize via Admin Settings.

5. **Migration Order**: Seed migrations depend on table migrations. Always run in timestamp order.

6. **Service Circular Dependencies**: Avoid circular service instantiation. Use lazy loading (see N8nService pattern).

## Module-Specific Notes

### Tickets Module
- **Email-to-Ticket**: Primary input method, converts Gmail messages to tickets
- **Thread Tracking**: Uses `gmail_message_id` and `gmail_thread_id` to link conversations
- **Channels**: Tickets have a `channel` field ('email', 'web', 'phone', etc.)

### PQRS Module
- **Public Access**: Designed for external users (customers)
- **Shared Codebase**: Reuses 90% of Ticket logic via traits
- **Recipient Tracking**: PQRS have `recipient_names` and `recipient_emails` fields for multi-recipient handling

### Compras Module
- **Approval Workflow**: Multi-stage approval process
- **Similar Pattern**: Uses same trait-based architecture as Tickets/PQRS
- **Attachments**: Purchase requisitions support file attachments

## SLA (Service Level Agreement) Management

### Overview
The system includes a comprehensive SLA management system for both PQRS and Compras modules, tracking **two critical metrics** per entity:
- **First Response Time SLA**: Time until first agent response
- **Resolution Time SLA**: Time until resolved/closed

### SLA Configuration
- **Location**: Admin Panel → `/admin/settings/sla`
- **PQRS**: Type-based SLA (4 types × 2 metrics = 8 configurations)
  - Petición: 2 days first response, 5 days resolution (default)
  - Queja: 1 day first response, 3 days resolution (default)
  - Reclamo: 1 day first response, 3 days resolution (default)
  - Sugerencia: 3 days first response, 7 days resolution (default)
- **Compras**: Single SLA (2 metrics)
  - First Response: 1 day (default)
  - Resolution: 3 days (default)
- **Storage**: `system_settings` table with keys like `sla_pqrs_peticion_first_response_days`
- **Calculation**: 24/7 calendar days (not business hours)

### Architecture

#### SLAManagementTrait (`src/Service/Traits/SLAManagementTrait.php`)
Core business logic shared between PqrsService and ComprasService:
- `calculatePqrsSLA(string $type, ?DateTime $created)` - Type-specific calculation for PQRS
- `calculateComprasSLA(?DateTime $created)` - Single calculation for Compras
- `isFirstResponseSLABreached(Entity $entity)` - Check first response breach
- `isResolutionSLABreached(Entity $entity)` - Check resolution breach
- `getBreachedSLAEntities(string $module, array $closedStatuses, string $slaType)` - Query breached entities
- `recalculateSLAForEntity(Entity $entity)` - Recalculate after config changes

#### SLAHelper (`src/View/Helper/SLAHelper.php`)
Shared visualization helper with traffic light system:
- **Traffic Light Logic**:
  - 🟢 GREEN (ok): > 50% time remaining
  - 🟡 YELLOW (warning): 25-50% time remaining
  - 🔴 RED (critical): < 25% time remaining
  - 🔴 RED (breached): Past deadline
  - ⚪ GRAY (completed): Entity closed
- **Methods**:
  - `getSlaStatus($entity, $slaField, $closedStatuses)` - Calculate SLA status
  - `slaBadge($entity, $slaField, $closedStatuses, $showPercentage)` - Display badge
  - `slaIcon($entity, $slaField, $closedStatuses)` - Simple icon with tooltip
  - `slaIndicator($entity, $slaField, $closedStatuses, $showProgressBar)` - Detailed indicator

#### PqrsHelper & ComprasHelper
Both helpers delegate to SLAHelper for consistent visualization:

**PqrsHelper** (`src/View/Helper/PqrsHelper.php`):
```php
public array $helpers = ['SLA'];

// Wrapper methods for convenience
$this->Pqrs->firstResponseSlaBadge($pqr, $showPercentage = false);
$this->Pqrs->resolutionSlaBadge($pqr, $showPercentage = false);
$this->Pqrs->firstResponseSlaIcon($pqr);
$this->Pqrs->resolutionSlaIcon($pqr);
$this->Pqrs->firstResponseSlaIndicator($pqr, $showProgressBar = false);
$this->Pqrs->resolutionSlaIndicator($pqr, $showProgressBar = false);
```

**ComprasHelper** (`src/View/Helper/ComprasHelper.php`):
```php
public array $helpers = ['SLA'];

// Wrapper methods with field parameter
$this->Compras->getSlaStatus($compra, $slaField = 'resolution_sla_due');
$this->Compras->slaBadge($compra, $slaField = 'resolution_sla_due', $showPercentage = false);
$this->Compras->slaIcon($compra, $slaField = 'resolution_sla_due');
$this->Compras->slaIndicator($compra, $slaField = 'resolution_sla_due', $showProgressBar = false);
```

### Database Schema

#### PQRS Table Fields
- `first_response_sla_due` (datetime, indexed) - First response deadline
- `resolution_sla_due` (datetime, indexed) - Resolution deadline
- `closed_at` (datetime) - Closure timestamp

#### Compras Table Fields
- `first_response_sla_due` (datetime, indexed) - First response deadline
- `resolution_sla_due` (datetime, indexed) - Resolution deadline
- `sla_due_date` (datetime, deprecated) - Old field kept for backward compatibility

### Service Layer Integration

#### PqrsService
```php
// Auto-calculates SLA on creation based on type
$pqrs = $pqrsService->createFromForm($formData, $files);

// Recalculate after config changes
$pqrsService->recalculateSLA($pqrsId);

// Get breached entities
$breached = $pqrsService->getBreachedFirstResponseSLA();
$breached = $pqrsService->getBreachedResolutionSLA();
```

#### ComprasService
```php
// Auto-calculates SLA on creation from ticket
$compra = $comprasService->createFromTicket($ticket, $data);

// Check if SLA breached (checks BOTH SLAs)
$breached = $comprasService->isSLABreached($compra);

// Old method (deprecated, returns resolution SLA only)
$slaDate = $comprasService->calculateSLA($compra);

// Recalculate after config changes
$comprasService->recalculateSLA($compraId);
```

### View Integration

#### PQRS Views
**Index View** (`templates/Pqrs/index.php`):
- Two SLA columns added after "Asignado a":
  - "1ra Resp. SLA" - Shows `firstResponseSlaIcon()`
  - "Resolución SLA" - Shows `resolutionSlaIcon()`

**Detail View** (`templates/element/pqrs/left_sidebar.php`):
- SLA section displays both metrics with badges
- Shows due dates formatted as `d/m/Y H:i`
- Admin-only "Recalcular SLA" button

#### Compras Views
**Index View** (`templates/Compras/index.php`):
- Two SLA columns (replaced single "SLA" column):
  - "1ra Resp. SLA" - Shows `slaIcon($compra, 'first_response_sla_due')`
  - "Resolución SLA" - Shows `slaIcon($compra, 'resolution_sla_due')`

**Detail View** (`templates/element/compras/left_sidebar.php`):
- Dedicated SLA section with both metrics
- Badges with percentage/time remaining
- Admin-only "Recalcular SLA" button

### Controller Actions

Both PqrsController and ComprasController include:

```php
/**
 * Recalculate SLA for an entity
 * @param string|null $id Entity ID
 */
public function recalculateSla($id = null)
{
    $this->request->allowMethod(['post', 'get']);

    try {
        $this->pqrsService->recalculateSLA((int)$id); // or comprasService
        $this->Flash->success(__('SLA recalculado exitosamente.'));
    } catch (\Exception $e) {
        $this->Flash->error(__('Error al recalcular SLA: {0}', $e->getMessage()));
    }

    return $this->redirect(['action' => 'view', $id]);
}
```

### Statistics Integration

**StatisticsService** (`src/Service/StatisticsService.php`) includes SLA metrics:

#### PQRS Statistics
```php
$stats = $statisticsService->getPqrsStats($filters);
$stats['sla_metrics'] = [
    'first_response_breached' => 5,      // Count past first response deadline
    'first_response_at_risk' => 3,       // Count < 24h until deadline
    'first_response_compliance' => 92.5, // % compliance
    'resolution_breached' => 2,          // Count past resolution deadline
    'resolution_at_risk' => 4,           // Count < 24h until deadline
    'resolution_compliance' => 96.0,     // % compliance
    'active_count' => 40,                // Total active PQRS
];
```

#### Compras Statistics
```php
$stats = $statisticsService->getComprasStats($filters);
$stats['sla_metrics'] = [
    'first_response_breached' => 3,
    'first_response_at_risk' => 2,
    'first_response_compliance' => 94.0,
    'resolution_breached' => 1,
    'resolution_at_risk' => 5,
    'resolution_compliance' => 97.5,
    'active_count' => 30,

    // Legacy fields (backward compatibility)
    'breached_count' => 1,        // Maps to resolution_breached
    'at_risk_count' => 5,         // Maps to resolution_at_risk
    'compliance_rate' => 97.5,    // Maps to resolution_compliance
];
```

### Admin Usage

1. **Configure SLA**: Navigate to Admin Settings → SLA Configuration
2. **Edit Values**: Change days for each type/metric
3. **Save**: Changes apply to new entities immediately
4. **Recalculate**: Use "Recalcular SLA" button on individual entities to update existing ones

### Migrations

Run migrations in order:
```bash
bin/cake migrations migrate
```

Migrations created:
1. `20251227150226_AddSlaFieldsToPqrs` - Adds 3 fields to pqrs table
2. `20251227150341_AddSlaFieldsToCompras` - Adds 2 fields to compras table
3. `20251227150434_MigrateComprasSlaData` - Migrates existing Compras data
4. `20251227150559_SeedSlaSettings` - Seeds 10 SLA configuration settings

### Important Notes

- **Type Safety**: All SLA calculations use strict typing with `DateTimeInterface` for compatibility
  - ⚠️ **CRITICAL**: Helper properties must use typed declarations: `public array $helpers = ['SLA'];`
  - SLA methods accept `?DateTimeInterface` to support both native `DateTime` and `Cake\I18n\DateTime`
- **Backward Compatibility**: Old `sla_due_date` field preserved in Compras
  - Legacy `calculateSLA()` method still works but returns only resolution SLA
  - Statistics include legacy fields (`breached_count`, `at_risk_count`, `compliance_rate`)
- **Cache**: SLA settings cached in `_cake_core_` cache, cleared on save
- **Closed Statuses**: SLA ignored for closed entities:
  - PQRS: `resuelto`, `cerrado`
  - Compras: `completado`, `rechazado`, `convertido`
- **Nullable Fields**: All SLA fields are nullable (won't break if not set)
- **Migration Order**: Run migrations sequentially - they depend on each other
- **Admin Navigation**: SLA config accessible via `/admin/settings/sla` or Settings → SLA button

### Common Issues & Troubleshooting

**Type Error: "must be of type ?array, false given"**
- **Cause**: `TicketSystemTrait` calling `addComment()` with wrong parameters
- **Fix**: Ensure `addComment()` calls don't pass extra `$isPqrs` parameter
- **Correct**: `$this->addComment($id, $userId, $comment, 'internal', true, false);`
- **Incorrect**: `$this->addComment($id, $userId, $comment, 'internal', true, false, $isPqrs);`

**Type Error: "must be of type ?DateTime, Cake\I18n\DateTime given"**
- **Cause**: Type hint mismatch between native `DateTime` and CakePHP's `DateTime`
- **Fix**: Use `?DateTimeInterface` type hint in method signatures
- **File**: `src/Service/Traits/SLAManagementTrait.php`

**Fatal Error: "Type of Helper::$helpers must be array"**
- **Cause**: Missing type declaration on `$helpers` property in Helper classes
- **Fix**: Use `public array $helpers = ['SLA'];` (not `public $helpers`)
- **Files**: `PqrsHelper.php`, `ComprasHelper.php`

**SLA Not Calculating**
- Check that migrations ran successfully
- Verify SLA settings exist in `system_settings` table
- Clear cache: `bin/cake cache clear _cake_core_`
- Check entity has `created` timestamp

**SLA Shows N/A or null**
- Verify entity has SLA due dates set
- Check entity status (closed entities don't show SLA)
- For PQRS: ensure `type` field is set correctly
- Recalculate SLA via admin button

## External Dependencies

- **PHP**: 8.5+
- **CakePHP**: 5.2.x
- **Database**: MySQL/MariaDB
- **Google APIs**: `google/apiclient` for Gmail OAuth2
- **HTMLPurifier**: `ezyang/htmlpurifier` for XSS protection
- **Mobile Detection**: `mobiledetect/mobiledetectlib` for responsive behavior
