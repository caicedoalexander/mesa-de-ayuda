# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Mesa de Ayuda** is an integrated corporate management system built on **CakePHP 5.x** that manages three core business workflows:
1. **Tickets** - Internal helpdesk for employee support
2. **PQRS** - External public complaints/requests system (Peticiones, Quejas, Reclamos, Sugerencias)
3. **Compras** - Purchase requisitions and procurement workflow

The system integrates with Gmail (OAuth), WhatsApp (Evolution API), and n8n for workflow automation.

## Development Commands

### Running the Application
```bash
bin/cake server                    # Start development server (http://localhost:8080)
```

### Database Migrations
```bash
bin/cake migrations migrate        # Run pending migrations
bin/cake migrations rollback       # Rollback last migration
bin/cake migrations status         # Check migration status
bin/cake migrations seed           # Run database seeders
```

### Code Quality & Testing
```bash
composer check                     # Run all checks (tests + cs-check + stan)
composer test                      # Run PHPUnit tests
composer cs-check                  # Check code style (PHPCS)
composer cs-fix                    # Auto-fix code style issues (PHPCBF)
composer stan                      # Run PHPStan static analysis (level 5)

# Run specific test file
vendor/bin/phpunit tests/TestCase/Model/Table/TicketsTableTest.php
```

### Code Generation (Bake)
```bash
bin/cake bake model Tickets        # Generate model + table + entity + tests
bin/cake bake controller Tickets   # Generate controller
bin/cake bake template Tickets     # Generate view templates
```

## Architecture Overview

### Service Layer Pattern

The system uses a **service-oriented architecture** to encapsulate business logic outside controllers. Services are the primary place to implement complex workflows.

**Key Services** (`src/Service/`):
- `TicketService` - Ticket lifecycle management
- `PqrsService` - PQRS management
- `ComprasService` - Purchase requisition management
- `ResponseService` - **Unified comment/response handler** for all entities (delegates to entity-specific services)
- `EmailService` - Gmail OAuth integration
- `WhatsappService` - WhatsApp notifications via Evolution API
- `GmailService` - Email-to-ticket conversion, thread management
- `N8nService` - n8n webhook automation
- `StatisticsService` - Analytics and reporting

**Service Initialization**:
Controllers use `ServiceInitializerTrait` to lazily initialize services with cached system config:
```php
use App\Controller\Traits\ServiceInitializerTrait;

class TicketsController extends AppController {
    use ServiceInitializerTrait;

    private TicketService $ticketService;

    public function initialize(): void {
        parent::initialize();
        $this->initializeServices(); // Sets up all service properties
    }
}
```

### Shared Trait Architecture

To eliminate code duplication across the three entity types (Tickets, PQRS, Compras), the system heavily uses **traits**:

**Controller Traits** (`src/Controller/Traits/`):
- `TicketSystemControllerTrait` - Shared controller actions (assign, changeStatus, changePriority, addComment, downloadAttachment)
- `StatisticsControllerTrait` - Statistics page rendering
- `ViewDataNormalizerTrait` - Normalizes entity data for consistent view rendering
- `ServiceInitializerTrait` - Lazy service initialization with system config

**Service Traits** (`src/Service/Traits/`):
- `TicketSystemTrait` - Shared service logic (changeStatus, changePriority, assign, recordHistory)
- `StatisticsServiceTrait` - Analytics calculations
- `NotificationDispatcherTrait` - Send email/WhatsApp notifications
- `GenericAttachmentTrait` - File upload/download handling
- `EntityConversionTrait` - Convert tickets to purchase requisitions

**Usage Pattern**:
```php
// Controller uses trait to handle common actions
class TicketsController extends AppController {
    use TicketSystemControllerTrait;

    public function assign($id = null) {
        // Trait method handles generic logic
        return $this->assignEntity('ticket', (int)$id, $this->request->getData('agent_id'));
    }
}
```

### Template Element Reusability

Templates extensively use **shared elements** (`templates/Element/shared/`) to avoid duplication:
- `entity_header.php` - Header with title, status, priority badges
- `comments_list.php` - Comment thread display
- `reply_editor.php` - Reply form with attachments and email recipient selection
- `statistics/*` - Chart and metric components
- `bulk_actions_bar.php` / `bulk_modals.php` - Bulk operation UI

Entity-specific elements are in `templates/Element/{tickets,pqrs,compras}/`.

### Database Schema Design

All three entity types follow a **consistent pattern**:
- Main entity table (tickets, pqrs, compras)
- Comments table (ticket_comments, pqrs_comments, compras_comments)
- Attachments table (attachments, pqrs_attachments, compras_attachments)
- History table (ticket_history, pqrs_history, compras_history)

**Common Fields**:
- `status` - Workflow state (nuevo, en_progreso, resuelto, cerrado, rechazado, etc.)
- `priority` - urgente, alta, media, baja
- `channel` - Entry point (email, whatsapp, web, phone)
- `assigned_to` - Foreign key to users table
- `email_to`, `email_cc`, `email_from` - Email recipient tracking for replies

### Authentication & Authorization

**Roles** (defined in users.role enum):
- `admin` - Full system access
- `agent` - Can manage tickets and PQRS
- `requester` - Can create and view own tickets
- `compras` - Can manage purchase requisitions
- `servicio_cliente` - Customer service for PQRS

**Role-based redirects**:
Controllers use `AppController::redirectByRole()` to ensure users land on appropriate modules:
```php
public function beforeFilter(\Cake\Event\EventInterface $event) {
    parent::beforeFilter($event);
    return $this->redirectByRole(['admin', 'agent', 'requester'], 'tickets');
}
```

**Layouts**:
- `templates/layout/admin.php`
- `templates/layout/agent.php`
- `templates/layout/compras.php`
- `templates/layout/servicio_cliente.php`

### External Integration Points

**Gmail OAuth Flow**:
1. Admin configures Gmail OAuth credentials in system settings
2. `GmailService` handles token refresh and API calls
3. Email-to-ticket conversion via `TicketService::createFromEmail()`

**WhatsApp Notifications**:
- Uses Evolution API (configured in system_settings)
- `WhatsappService` sends transactional messages on status changes

**n8n Webhooks**:
- Bidirectional communication for workflow automation
- Tickets/PQRS can trigger n8n workflows
- n8n can update entity status via webhooks

### Frontend JavaScript Modules

Key JS files (`webroot/js/`):
- `bulk-actions-module.js` - Bulk select/update UI
- `entity-history-lazy.js` - Lazy-load activity timeline
- `modern-statistics.js` - Chart rendering (Chart.js)
- `email-recipients.js` - Dynamic To/CC field management
- `flash-messages.js` - Toast notifications

## Configuration Files

### System Settings
Settings are stored in `system_settings` table and cached. Sensitive values (API keys, OAuth tokens) are encrypted using `SettingsEncryptionTrait`.

Access in controllers/services via:
```php
$systemConfig = \Cake\Cache\Cache::read('system_settings', '_cake_core_');
```

### Environment Variables
Required in `config/app_local.php` or `.env`:
- Database credentials (DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD)
- Security salt and cipher seed
- Debug mode toggle

## Important Patterns & Conventions

### Entity Type Parameter
Many shared methods use `$type` parameter to distinguish entities:
```php
// Valid values: 'ticket', 'pqrs', 'compra'
$responseService->processResponse($type, $entityId, $userId, $data, $files);
```

### Status Change Flow
Always use service methods to change status (never update entities directly):
```php
$success = $ticketService->changeStatus($ticket, 'resuelto', $userId, $comment, true);
```
This ensures:
- History tracking
- Timestamp updates (resolved_at, closed_at)
- Notifications sent
- Cache invalidation

### Attachment Handling
File uploads go through `GenericAttachmentTrait::handleAttachments()`:
- Sanitizes filenames
- Stores in `webroot/files/{tickets,pqrs,compras}/{id}/`
- Records in appropriate attachments table

### Email Recipient Tracking
When replying to entities, additional recipients are tracked:
```php
// Frontend sends JSON arrays via email-recipients.js
'email_to' => '["user1@example.com","user2@example.com"]'
'email_cc' => '["cc@example.com"]'

// Backend decodes and stores in comment record
```

## Testing Approach

Tests follow CakePHP conventions:
- Table tests in `tests/TestCase/Model/Table/`
- Controller tests in `tests/TestCase/Controller/`
- Use fixtures for test data

**Current test coverage is minimal**. When adding tests:
1. Create fixtures in `tests/Fixture/`
2. Use `IntegrationTestTrait` for controller tests
3. Mock external services (EmailService, WhatsappService, N8nService)

## Common Pitfalls

1. **Don't bypass service layer** - Controllers should delegate to services, not contain business logic
2. **Cache invalidation** - When updating system_settings, clear cache: `Cache::delete('system_settings', '_cake_core_')`
3. **Entity type consistency** - Use singular 'ticket'/'pqrs'/'compra' not plural in code
4. **Service instantiation** - Always pass `$systemConfig` to service constructors to avoid redundant DB queries
5. **HTML sanitization** - User input is sanitized via HTMLPurifier in services before storage

## Code Style

- **PHP 8.1+** with strict types (`declare(strict_types=1);`)
- **PSR-12** via CakePHP Code Sniffer
- **PHPStan level 5** static analysis
- Type hints required on all method signatures
- Use `FrozenTime` (CakePHP Chronos) for datetime handling, not native DateTime
