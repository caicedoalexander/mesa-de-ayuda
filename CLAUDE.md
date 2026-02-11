# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Mesa de Ayuda** is an integrated corporate management system built on CakePHP 5.x (PHP 8.1+). It handles three main modules: internal support tickets (Helpdesk), purchasing/procurement (Compras), and external customer feedback (PQRS - Peticiones, Quejas, Reclamos, Sugerencias).

**Key Features:**
- Email-to-ticket conversion via Gmail API with OAuth2
- WhatsApp notifications via Evolution API
- n8n webhook integration for workflow automation
- AWS S3 for file storage with CloudFront CDN
- Background worker for automated email imports
- Docker-ready with production and development configurations

## Common Commands

### Development

```bash
# Start development server
bin/cake server

# Run tests
composer test
# OR
vendor/bin/phpunit --colors=always

# Run single test
vendor/bin/phpunit --filter testMethodName tests/TestCase/Path/To/TestFile.php

# Code style check
composer cs-check

# Code style fix
composer cs-fix

# Static analysis
composer stan
# OR
vendor/bin/phpstan analyse

# Run all checks (test + cs-check + stan)
composer check

# Clear cache
bin/cake cache clear_all
```

### Database

```bash
# Run migrations
bin/cake migrations migrate

# Rollback migration
bin/cake migrations rollback

# Check migration status
bin/cake migrations status

# Create new migration
bin/cake bake migration MigrationName

# Seed database
bin/cake migrations seed
```

### Background Workers

```bash
# Import Gmail manually (one-time)
bin/cake import_gmail

# Import with options
bin/cake import_gmail --max 100 --query "is:unread"

# Start Gmail worker (continuous)
# Note: In production, this runs via Supervisor in Docker
bin/cake gmail_worker
```

### Docker

```bash
# Development
docker-compose up -d --build
docker-compose logs -f
docker-compose down

# Production
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

# Execute commands in container
docker-compose exec web php bin/cake.php migrations migrate
docker-compose exec web bash

# Worker management
docker-compose logs -f worker
docker-compose restart worker
```

## Architecture

### Service Layer Pattern

Business logic lives in `src/Service/` classes, NOT in controllers. Controllers are thin and delegate to services.

**Key Services:**
- `TicketService` - Ticket lifecycle, email-to-ticket conversion, status management
- `GmailService` - Gmail API OAuth2, fetching emails, parsing attachments
- `WhatsappService` - Send notifications via Evolution API
- `N8nService` - Trigger n8n webhooks for automation workflows
- `EmailService` - Send transactional emails via SMTP
- `ComprasService` - Purchasing workflow and approvals
- `PqrsService` - External customer feedback management
- `S3Service` - AWS S3 file uploads with CloudFront
- `SlaManagementService` - SLA tracking and notifications

### Service Traits

Reusable functionality is extracted into traits in `src/Service/Traits/`:
- `NotificationDispatcherTrait` - Email and WhatsApp notifications
- `TicketSystemTrait` - Common ticket operations
- `GenericAttachmentTrait` - File attachment handling
- `EntityConversionTrait` - Entity to array conversion

### Configuration Management

**System settings are stored in the database** (`system_settings` table), NOT in config files:
- Access via `SystemSettingsTable`
- Sensitive values (OAuth tokens, API keys) are encrypted using `SettingsEncryptionTrait`
- Gmail refresh tokens, WhatsApp instance IDs, n8n webhook URLs are all in DB

**Environment variables** (`.env`) are for deployment-specific config:
- Database credentials (`DB_HOST`, `DB_USERNAME`, etc.)
- Security salt (`SECURITY_SALT`)
- Debug mode (`DEBUG`)
- Worker enabled flag (`WORKER_ENABLED`)
- S3 credentials (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`)

### Background Processing

The **Gmail worker** (`src/Command/GmailWorkerCommand.php`) runs continuously via Supervisor in Docker:
1. Reads `gmail_check_interval` from `system_settings` (default: 5 minutes)
2. Executes `ImportGmailCommand` to fetch unread emails
3. Creates tickets from emails via `TicketService->createTicketFromEmail()`
4. Marks emails as read in Gmail
5. Sleeps until next interval

**In Docker:** Worker runs as separate container (`worker` service) with automatic restarts.

### Email Threading

Email threads are tracked using:
- `gmail_message_id` - Unique Gmail message ID
- `gmail_thread_id` - Gmail conversation thread ID
- New emails in same thread are added as comments to existing tickets

### Authentication

Uses CakePHP Authentication plugin:
- Session-based authentication
- Identity is `User` entity
- Login route: `/users/login`
- Unauthenticated users redirect to login
- Admin routes require authentication (prefix: `/admin`)

### Admin Panel

Admin-only features accessed via `/admin` prefix:
- `/admin/settings` - Configure Gmail OAuth, WhatsApp, n8n
- `/admin/sla-management` - SLA rules configuration
- `/admin/config-files` - View/edit config files (use with caution)

**OAuth Flow for Gmail:**
1. Upload `client_secret.json` via `/admin/settings`
2. Click "Authorize Gmail Access" → Redirects to Google
3. After authorization, refresh token is stored encrypted in DB
4. Worker can now fetch emails automatically

## Important Patterns

### When Creating Tickets

Always use `TicketService->createTicketFromEmail()` or similar service methods. This ensures:
- Notifications are sent (email + WhatsApp)
- History is logged
- n8n webhooks are triggered
- Attachments are properly linked

### When Updating Ticket Status

Use `TicketService->updateTicketStatus()` to ensure:
- Status transitions are validated
- History records are created
- Followers are notified
- SLA timers are updated

### File Uploads

**S3 is used for production**, local filesystem for development:
- Upload via `S3Service->uploadFile()` when `AWS_S3_ENABLED=true`
- Files are stored with unique names to prevent collisions
- CloudFront URLs returned for serving files
- Fallback to local `webroot/uploads/` when S3 disabled

### Attachment Handling

Different modules have separate attachment tables:
- `attachments` - Ticket attachments
- `compras_attachments` - Purchase order attachments
- `pqrs_attachments` - PQRS attachments

All use `GenericAttachmentTrait` for common operations.

## Database Schema

Key tables:
- `tickets` - Main ticket records with status, priority, assignee
- `ticket_comments` - Ticket conversation history
- `ticket_history` - Audit log of all ticket changes
- `ticket_followers` - Users subscribed to ticket updates
- `compras` - Purchase requisitions and approvals
- `pqrs` - External customer feedback submissions
- `users` - System users (agents, admins)
- `organizations` - Customer organizations
- `system_settings` - Application configuration (key-value pairs)
- `email_templates` - Customizable email templates with placeholders

## Integration Details

### Gmail API

- **OAuth2 flow required** - Cannot use app passwords
- Requires HTTPS in production (OAuth redirect URI must be HTTPS)
- Refresh token stored encrypted in `system_settings.gmail_refresh_token`
- Client secret JSON path in `system_settings.gmail_client_secret_path`
- Import runs every N minutes (configurable via `gmail_check_interval`)

### WhatsApp (Evolution API)

- Instance-based architecture (one instance = one WhatsApp number)
- Requires Evolution API server (external dependency)
- Settings: `whatsapp_instance_id`, `whatsapp_api_url`, `whatsapp_api_key`
- Used for ticket notifications and status updates

### n8n Automation

- Webhooks triggered on ticket creation/updates
- Webhook URL stored in `system_settings.n8n_webhook_url`
- Sends ticket data as JSON payload
- Used for advanced automation (Slack notifications, JIRA sync, etc.)

## Testing

Tests use **Fixtures** for database state:
- Fixtures defined in `tests/Fixture/`
- Test cases in `tests/TestCase/`
- Follow CakePHP conventions: `FooBarTableTest.php` for `FooBarTable` tests

**Coverage:**
- Service layer tests should mock external APIs (Gmail, WhatsApp, n8n)
- Controller tests should use integration testing approach
- Use `IntegrationTestTrait` for controller tests

## Deployment Considerations

### Docker Production

- Uses **all-in-one container** (Nginx + PHP-FPM in single image)
- Separate **worker container** for background jobs
- Supervisor manages both PHP-FPM and Nginx processes
- Health check endpoint: `/health`
- Logs mounted to `./logs/` on host

### Easypanel Deployment

- Single Dockerfile deployment
- Must configure **HTTPS domain** for Gmail OAuth
- Set `TRUST_PROXY=true` to detect HTTPS from reverse proxy
- Worker disabled by default (`autostart=false` in Supervisor)
- Enable worker after configuring Gmail via admin panel

### Required Environment Variables

```bash
# Database (external)
DB_HOST=your-db-host
DB_DATABASE=mesadeayuda
DB_USERNAME=your-user
DB_PASSWORD=your-password

# Security (generate with: php -r "echo bin2hex(random_bytes(32));")
SECURITY_SALT=your-64-char-hex-string

# HTTPS detection (when behind reverse proxy)
TRUST_PROXY=true
```

## Code Quality Standards

- **Strict types enabled** - All PHP files use `declare(strict_types=1);`
- **PSR-12 coding standard** - Enforced by `phpcs`
- **PHPStan level 5** - Static analysis with CakePHP-specific ignores
- **CakePHP conventions** - Follow CakePHP naming and structure
- **Service layer for logic** - Keep controllers thin
- **Type hints required** - Use strict typing for parameters and return values

## Security Notes

- **Never commit** `.env`, `config/app_local.php`, or `client_secret.json`
- OAuth tokens are encrypted at rest using `SECURITY_SALT`
- File uploads are sanitized and validated
- SQL injection prevented via CakePHP ORM
- XSS prevention via HTMLPurifier for user content
- CSRF protection enabled by default

## Common Gotchas

1. **Gmail OAuth requires HTTPS** - Local development uses `http://localhost` exception, production needs real HTTPS
2. **Worker won't start without DB settings** - Database must be configured before worker runs
3. **Migrations must run before first use** - Empty DB will fail, run `migrations migrate` first
4. **System settings are in DB** - Don't look for config files, use admin panel
5. **S3 must be enabled explicitly** - Set `AWS_S3_ENABLED=true` and configure credentials
6. **Docker port is 80 inside container** - But exposed on `APP_PORT` (default 8765) on host
