# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Mesa de Ayuda is a CakePHP 5.x enterprise platform integrating three critical business modules:
- **Soporte Interno (Helpdesk)**: Internal IT support ticketing system with Gmail-to-Ticket automation
- **Gestión de Compras**: Procurement and purchase request management with approval workflows
- **PQRS (External)**: Public-facing customer feedback system (Petitions, Complaints, Claims, Suggestions)

The system features deep integrations with Gmail (OAuth2), WhatsApp Business (Evolution API), n8n automation platform, and AWS S3 for file storage.

## Essential Commands

### Development
```bash
# Start development server
bin/cake server

# Clear all caches
bin/cake cache clear_all

# Run migrations
bin/cake migrations migrate

# Run database seeds
bin/cake migrations seed

# Import Gmail (manual trigger)
bin/cake import_gmail --max 50 --query "is:unread"

# Test email configuration
bin/cake test_email
```

### Docker Operations
```bash
# Development mode
docker-compose up -d --build

# Production mode
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

# View logs
docker-compose logs -f [service_name]  # web, worker, nginx

# Execute commands in container
docker-compose exec web php bin/cake.php migrations migrate
docker-compose exec web php bin/cake.php cache clear_all

# Restart Gmail worker
docker-compose restart worker
```

Refer to DOCKER.md for comprehensive Docker deployment instructions.

## Architecture

### Service Layer Pattern
Business logic is encapsulated in service classes (`src/Service/`), NOT in controllers or models. Services handle:
- Cross-cutting concerns (notifications, file uploads, external API calls)
- Complex workflows (ticket creation from email, status changes, approval flows)
- Integration with external systems (Gmail, WhatsApp, n8n, S3)

**Key Services:**
- `GmailService`: Gmail API OAuth2, email fetching, parsing, attachment downloads
- `TicketService`: Ticket lifecycle, email-to-ticket conversion, notifications
- `ComprasService`: Purchase request workflows, approvals
- `PqrsService`: External PQRS management
- `WhatsappService`: WhatsApp notifications via Evolution API
- `N8nService`: Webhook integration for AI-powered tag assignment
- `EmailService`: Transactional email sending
- `S3Service`: AWS S3 file uploads and downloads
- `SlaManagementService`: SLA tracking and enforcement
- `StatisticsService`: Dashboard metrics and reporting

### Service Traits for Code Reuse
Located in `src/Service/Traits/`, these traits eliminate code duplication:
- `TicketSystemTrait`: Shared ticket/PQRS/compras logic (status changes, assignments)
- `NotificationDispatcherTrait`: Unified WhatsApp and email notification dispatching
- `GenericAttachmentTrait`: File upload handling for all modules
- `StatisticsServiceTrait`: Common statistical calculations
- `EntityConversionTrait`: Entity-to-array conversions with computed properties

### Configuration Management
- **System Settings**: Stored in `system_settings` database table, cached for 1 hour
- **Encryption**: Sensitive settings (Gmail tokens, API keys) encrypted using `SettingsEncryptionTrait`
- **Access**: Settings loaded in `AppController::beforeFilter()` and passed to services to avoid redundant DB queries
- **Cache Key**: `system_settings` (uses `_cake_core_` cache config)

### Authentication & Authorization
- **Authentication**: CakePHP Authentication plugin with session-based auth
- **Roles**: `admin`, `agent`, `requester`, `compras`, `servicio_cliente`
- **Layout Routing**: `AppController::beforeFilter()` assigns layouts based on role
- **Access Control**: `AppController::redirectByRole()` enforces module-level permissions
- **Public Routes**: PQRS form (`/pqrs/formulario`) allows unauthenticated access

### Background Workers
The `GmailWorkerCommand` runs as a Docker container (`worker` service) executing scheduled Gmail imports:
- Reads interval from `system_settings.gmail_check_interval` (default: 5 minutes)
- Executes `ImportGmailCommand` to fetch unread emails and create tickets
- Uses Supervisor for process management and auto-restart
- Logs to `logs/` directory

### Database Migrations
67 migration files in `config/Migrations/` handle schema versioning. Always create migrations for schema changes:
```bash
bin/cake bake migration CreateNewTable field1:string field2:text
bin/cake migrations migrate
```

### File Structure Conventions
- `src/Controller/`: Thin controllers - delegate to services
- `src/Controller/Admin/`: Admin-only controllers (settings, user management)
- `src/Controller/Traits/`: Shared controller behaviors
- `src/Model/Table/`: CakePHP ORM table classes with associations and validation
- `src/Model/Entity/`: Entity classes (typically auto-generated)
- `src/Service/`: Business logic layer
- `src/Command/`: CLI commands (import jobs, workers)
- `src/Utility/`: Shared utilities and traits
- `templates/`: Server-rendered views (not API responses)
- `config/`: Application configuration, migrations, routes
- `tests/TestCase/`: PHPUnit tests mirroring `src/` structure

## Critical Development Patterns

### Services MUST Accept Optional Configuration
All services accept `?array $systemConfig = null` to avoid N+1 database queries:

```php
// GOOD: Pass system config once to all services
$systemConfig = Cache::remember('system_settings', ...);
$ticketService = new TicketService($systemConfig);
$emailService = new EmailService($systemConfig);

// BAD: Each service queries database separately
$ticketService = new TicketService();  // Queries DB
$emailService = new EmailService();    // Queries DB again
```

### Notification Dispatching Pattern
Use `NotificationDispatcherTrait` for consistent notification behavior:

```php
use App\Service\Traits\NotificationDispatcherTrait;

// In service class
$this->dispatchNotifications(
    $entity,           // Ticket, PQRS, or Compra entity
    'nuevo_ticket',    // Email template key
    $extraData,        // Additional template variables
    $moduleName        // 'tickets', 'pqrs', or 'compras'
);
```

This trait handles:
- Loading email templates from database
- Sending email via EmailService
- Sending WhatsApp notification via WhatsappService
- Graceful degradation if services are unavailable

### Settings Encryption
Use `SettingsEncryptionTrait` when handling sensitive settings:

```php
use App\Utility\SettingsEncryptionTrait;

// Automatically encrypts values containing sensitive keywords
$encrypted = $this->encryptIfNeeded('gmail_refresh_token', $value);

// Automatically decrypts when loading
$config = $this->processSettings($rawSettingsArray);
```

Sensitive keywords: `token`, `secret`, `password`, `key`, `api_key`

### Attachment Handling
Use `GenericAttachmentTrait` for all file uploads:

```php
use App\Service\Traits\GenericAttachmentTrait;

// In service
$this->saveAttachments($request, $entityId, 'tickets');  // or 'pqrs', 'compras'
```

This trait:
- Handles S3 uploads when configured
- Falls back to local storage
- Creates database records in appropriate `*_attachments` table
- Generates secure download URLs

### Gmail Import Flow
1. `GmailWorkerCommand` runs on schedule (Docker container)
2. Calls `ImportGmailCommand::execute()`
3. `GmailService::getMessages()` fetches unread emails
4. `TicketService::createFromEmail()` processes each email:
   - Parses sender, subject, body
   - Sanitizes HTML with HTMLPurifier
   - Downloads attachments to S3/local
   - Creates ticket entity
   - Dispatches notifications (WhatsApp + Email)
   - Marks email as read

### n8n Integration
When enabled, the system sends webhook payloads to n8n for AI-powered operations:
- **Tag Assignment**: Analyzes ticket content and suggests tags
- **Priority Detection**: Determines urgency based on keywords
- **Auto-Classification**: Routes tickets to correct team

Configuration in `system_settings`: `n8n_enabled`, `n8n_webhook_url`, `n8n_webhook_secret`

## Environment Configuration

### Required Environment Variables
```env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=mesadeayuda
DB_USERNAME=root
DB_PASSWORD=secret

# Security
SECURITY_SALT=your-64-char-random-salt-here

# Optional
DEBUG=false
APP_ENV=production
WORKER_ENABLED=true
```

### Configuration Files
- `config/app_local.php`: Local environment config (gitignored)
- `config/app_local.example.php`: Template for local configuration
- `.env`: Environment variables (Docker)
- `.env.docker.example`: Template for Docker environment

## External Integrations Setup

### Gmail OAuth2
1. Create Google Cloud project and OAuth2 credentials
2. Download `credentials.json` to `config/google/`
3. Access `/admin/settings` and configure Gmail section
4. Authorize access (generates refresh token, encrypted in database)
5. Configure `gmail_check_interval` (minutes)

### WhatsApp (Evolution API)
Configure in Admin Settings (`/admin/settings`):
- `whatsapp_enabled`: Enable/disable notifications
- `whatsapp_instance`: Evolution API instance name
- `whatsapp_api_url`: Evolution API base URL
- `whatsapp_api_key`: API authentication key

### n8n Automation
Configure in Admin Settings:
- `n8n_enabled`: Enable/disable webhooks
- `n8n_webhook_url`: n8n webhook endpoint
- `n8n_webhook_secret`: Webhook authentication secret

### AWS S3 File Storage
Configure in `config/app_local.php`:
```php
'S3' => [
    'enabled' => true,
    'bucket' => 'your-bucket-name',
    'region' => 'us-east-1',
    'credentials' => [
        'key' => 'YOUR_ACCESS_KEY',
        'secret' => 'YOUR_SECRET_KEY',
    ],
],
```

## Common Pitfalls

1. **Don't query `system_settings` directly in loops**: Use cached config or pass to services
2. **Don't put business logic in controllers**: Create/extend service classes
3. **Don't forget to encrypt sensitive settings**: Use `SettingsEncryptionTrait`
4. **Don't create manual SQL queries**: Use CakePHP ORM query builder
5. **Don't modify core files in vendor/**: Extend via inheritance or plugins
6. **Always run migrations after pulling**: Schema changes are frequent
7. **Clear cache after settings changes**: `bin/cake cache clear_all`
8. **Use strict types**: All files MUST have `declare(strict_types=1);`

## Testing

Tests are located in `tests/TestCase/` mirroring `src/` structure:
- Service tests verify business logic without database dependencies
- Controller tests use fixtures for integration testing
- Run full test suite with `composer test`

PHPStan is configured at level 5 with CakePHP-specific ignores (see `phpstan.neon`).
