# Easypanel Deployment Guide

This guide explains how to deploy Mesa de Ayuda on Easypanel, a modern Docker-based PaaS platform.

## Table of Contents
- [Why Easypanel?](#why-easypanel)
- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Step-by-Step Deployment](#step-by-step-deployment)
- [Database Setup](#database-setup)
- [Environment Configuration](#environment-configuration)
- [Post-Deployment Setup](#post-deployment-setup)
- [Gmail Worker Configuration](#gmail-worker-configuration)
- [Monitoring & Logs](#monitoring--logs)
- [Domain & SSL Setup](#domain--ssl-setup)
- [Updating the Application](#updating-the-application)
- [Troubleshooting](#troubleshooting)
- [Performance Optimization](#performance-optimization)

---

## Why Easypanel?

Easypanel simplifies deployment with:
- **Single Container Architecture**: All services (Nginx, PHP-FPM, Worker) in one container managed by Supervisor
- **Automatic SSL**: Free SSL certificates via Let's Encrypt
- **Built-in Proxy**: Automatic HTTPS proxy and load balancing
- **Easy Scaling**: Horizontal and vertical scaling with one click
- **Integrated Monitoring**: Built-in logs, metrics, and health checks
- **GitHub Integration**: Automatic deployments on git push

This architecture is optimized for Easypanel's container model and reduces resource usage compared to multi-container Docker Compose setups.

---

## Prerequisites

1. **Easypanel Account**: Install Easypanel on your VPS ([Installation Guide](https://easypanel.io/docs))
2. **External MySQL Database**:
   - MySQL 8.0+ or MariaDB 10.5+
   - Create database: `mesadeayuda`
   - Accessible from Easypanel server (same VPS or external managed database)
3. **Git Repository**: This project pushed to GitHub/GitLab/Bitbucket
4. **Domain Name** (optional but recommended): For SSL and professional URLs

**Recommended VPS Specs:**
- **Minimum**: 2 vCPU, 2GB RAM, 20GB SSD
- **Recommended**: 2 vCPU, 4GB RAM, 40GB SSD
- **Production**: 4 vCPU, 8GB RAM, 80GB SSD

---

## Quick Start

```bash
# 1. Create MySQL database (on your database server)
mysql -u root -p
CREATE DATABASE mesadeayuda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mesadeayuda'@'%' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON mesadeayuda.* TO 'mesadeayuda'@'%';
FLUSH PRIVILEGES;
EXIT;

# 2. Generate security salt
php -r "echo bin2hex(random_bytes(32));"
# Save this for environment configuration

# 3. In Easypanel: Create App → From Source → Configure environment variables
# 4. Deploy and run migrations (see Post-Deployment Setup)
```

---

## Step-by-Step Deployment

### 1. Create New Application in Easypanel

1. Access your Easypanel dashboard
2. Click **"Create"** → **"App"**
3. Choose **"From Source"** (GitHub/GitLab)
4. Select or connect your Git repository
5. **Application Name**: `mesa-de-ayuda` (or your preference)
6. **Branch**: `main` (or your deployment branch)

### 2. Configure Build Settings

**Build Method**: `Dockerfile`

**Dockerfile Path**: `./Dockerfile` (default)

**Build Context**: `.` (root directory)

**Port Mapping**:
- Container Port: `80`
- Protocol: `HTTP`

### 3. Configure Environment Variables

Click **"Environment"** tab and add these variables:

#### Required Variables

```env
# Database Configuration
DB_HOST=your-database-host.com
DB_PORT=3306
DB_DATABASE=mesadeayuda
DB_USERNAME=mesadeayuda
DB_PASSWORD=your-secure-database-password

# Security (IMPORTANT: Generate unique value)
SECURITY_SALT=generated-64-character-hex-string

# Application Settings
APP_ENV=production
DEBUG=false

# Proxy Configuration (REQUIRED for Easypanel)
TRUST_PROXY=true
FULL_BASE_URL=https://yourdomain.com
```

#### Optional Variables

```env
# Worker Configuration
WORKER_ENABLED=true

# AWS S3 File Storage (if using S3)
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_REGION=us-east-1
AWS_S3_BUCKET=your-bucket-name
AWS_S3_ENABLED=true
AWS_CLOUDFRONT_URL=https://your-cloudfront-url
```

**Security Salt Generation:**
```bash
# Run this command to generate SECURITY_SALT
php -r "echo bin2hex(random_bytes(32));"

# Or use OpenSSL
openssl rand -hex 32
```

### 4. Deploy Application

1. Click **"Deploy"**
2. Easypanel will:
   - Pull code from Git
   - Build Docker image using Dockerfile
   - Start container with Supervisor managing all services
3. Wait for build to complete (~3-5 minutes)
4. Check **"Logs"** tab for build progress

---

## Database Setup

### Option 1: Local MySQL on Same Server

If running MySQL on the same VPS as Easypanel:

```bash
# SSH into your server
ssh user@your-server.com

# Install MySQL (if not installed)
sudo apt update
sudo apt install mysql-server

# Secure installation
sudo mysql_secure_installation

# Create database and user
sudo mysql
CREATE DATABASE mesadeayuda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mesadeayuda'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON mesadeayuda.* TO 'mesadeayuda'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**Environment Variable:**
```env
DB_HOST=host.docker.internal  # Or actual IP: 172.17.0.1
```

### Option 2: Managed Database (Recommended for Production)

Use managed databases from:
- **DigitalOcean Managed Databases**
- **AWS RDS**
- **PlanetScale**
- **Supabase** (PostgreSQL - requires adapter changes)

**Advantages:**
- Automatic backups
- High availability
- Monitoring included
- Scalable storage

**Environment Variable:**
```env
DB_HOST=your-managed-db-host.com
DB_PORT=3306
```

### Run Database Migrations

After first deployment, execute migrations inside the container:

```bash
# In Easypanel: Go to your app → "Terminal" tab
# Or use Easypanel CLI
easypanel exec mesa-de-ayuda -- php bin/cake.php migrations migrate

# Run database seeds (optional - creates default data)
easypanel exec mesa-de-ayuda -- php bin/cake.php migrations seed
```

**Verify Migration Success:**
```bash
easypanel exec mesa-de-ayuda -- php bin/cake.php migrations status
```

---

## Environment Configuration

### Critical Environment Variables Explained

#### `TRUST_PROXY=true`
**REQUIRED** when behind Easypanel's reverse proxy. Allows CakePHP to:
- Detect HTTPS from `X-Forwarded-Proto` header
- Use correct base URL for redirects
- Generate proper asset URLs

Without this, all URLs will be `http://` even with SSL enabled.

#### `FULL_BASE_URL=https://yourdomain.com`
Forces application to use this URL for:
- Email links (password resets, notifications)
- WhatsApp notification links
- Generated asset URLs

**Set this to your actual domain after configuring DNS.**

#### `WORKER_ENABLED=true`
Controls Gmail worker process:
- `true`: Supervisor starts `gmail-worker` process automatically
- `false`: Worker disabled (manual email import only)

Default: `true` (recommended)

### Environment Variables Priority

Configuration is loaded in this order (later overrides earlier):
1. `config/app.php` - Default CakePHP configuration
2. `config/app_local.php` - Generated from `app_local.example.php` during build
3. Environment variables - **Highest priority**

All `DB_*`, `SECURITY_SALT`, and custom variables come from environment.

---

## Post-Deployment Setup

### 1. Create Admin User

Access the container terminal and create the first admin user:

```bash
# In Easypanel Terminal tab
php bin/cake.php bake seed AdminUserSeed

# Or manually via MySQL
mysql -h DB_HOST -u DB_USERNAME -p DB_DATABASE
INSERT INTO users (email, password, first_name, last_name, role, organization_id, is_active, created, modified)
VALUES (
    'admin@example.com',
    '$2y$10$hashed_password_here',  -- Use CakePHP to hash: Security::hash('password', 'bcrypt')
    'Admin',
    'User',
    'admin',
    1,
    1,
    NOW(),
    NOW()
);
```

**Better approach - Use CakePHP Console:**
```bash
php -r "echo password_hash('your-password', PASSWORD_BCRYPT);"
# Copy hash and insert manually
```

### 2. Access Admin Panel

1. Navigate to: `https://yourdomain.com/admin/settings`
2. Login with admin credentials
3. Configure system settings:
   - **System Title**: Your organization name
   - **Email Settings**: SMTP or Gmail
   - **WhatsApp Integration**: Evolution API credentials
   - **n8n Integration**: Webhook URL (if using automation)

### 3. Configure Gmail OAuth (Email-to-Ticket)

**Step 1: Google Cloud Console**
1. Create project: https://console.cloud.google.com
2. Enable Gmail API
3. Create OAuth 2.0 credentials (Web Application)
4. Add authorized redirect URI: `https://yourdomain.com/admin/settings/gmail-callback`
5. Download `credentials.json`

**Step 2: Upload Credentials to Container**
```bash
# In Easypanel Terminal
mkdir -p config/google
# Upload credentials.json using file upload or paste content:
cat > config/google/credentials.json << 'EOF'
{
  "web": {
    "client_id": "your-client-id",
    "client_secret": "your-client-secret",
    ...
  }
}
EOF
```

**Step 3: Authorize Gmail Access**
1. Go to `/admin/settings`
2. Click **"Configure Gmail"**
3. Click **"Authorize Gmail Access"**
4. Follow Google OAuth flow
5. Refresh token is encrypted and saved to database

**Step 4: Start Gmail Worker**
```bash
# Worker starts automatically if WORKER_ENABLED=true
# To manually control:
supervisorctl status gmail-worker
supervisorctl start gmail-worker
supervisorctl restart gmail-worker
```

---

## Gmail Worker Configuration

The Gmail worker automatically imports emails and creates tickets.

### Architecture

The Dockerfile configures Supervisor to manage three processes:
1. **php-fpm** (Priority 1): PHP FastCGI Process Manager
2. **nginx** (Priority 2): Web server
3. **gmail-worker** (Priority 3): Background email import job

All run in a single container for Easypanel efficiency.

### Worker Process Details

**Command:** `/usr/local/bin/php /var/www/html/bin/cake.php gmail_worker`

**Configuration:**
- `autostart=false`: Requires manual start or `WORKER_ENABLED=true`
- `autorestart=true`: Restarts on failure
- `startretries=3`: Retries 3 times before giving up

**Log Locations:**
- `logs/worker.log`: Standard output (info, warnings)
- `logs/worker-error.log`: Error output (failures, exceptions)

### Manual Worker Management

```bash
# Inside Easypanel Terminal

# Start worker manually (one-time)
/usr/local/bin/start-worker

# Using supervisorctl directly
supervisorctl start gmail-worker
supervisorctl stop gmail-worker
supervisorctl restart gmail-worker
supervisorctl status gmail-worker

# View real-time logs
tail -f logs/worker.log
tail -f logs/worker-error.log
```

### Configuring Import Interval

1. Access `/admin/settings`
2. Find **"Gmail Check Interval"** setting
3. Set interval in minutes (default: 5)
4. Save settings
5. Restart worker: `supervisorctl restart gmail-worker`

The worker reads this value from `system_settings` table on each iteration.

### Troubleshooting Worker

**Worker not starting:**
```bash
# Check supervisor logs
tail -f logs/supervisord.log

# Check worker error log
tail -f logs/worker-error.log

# Manually test Gmail import
php bin/cake.php import_gmail --max 10
```

**Worker starts but doesn't import:**
- Verify Gmail OAuth is configured (refresh token exists in `system_settings`)
- Check `gmail_enabled` setting is `true`
- Check Gmail credentials file exists: `ls -la config/google/credentials.json`
- Test Gmail connection: `php bin/cake.php import_gmail --max 1`

---

## Monitoring & Logs

### Access Logs in Easypanel

**Web UI:**
1. Go to your app in Easypanel
2. Click **"Logs"** tab
3. View real-time container output

**Log Files (inside container):**
```bash
# Application logs
tail -f logs/error.log
tail -f logs/debug.log
tail -f logs/cli-error.log

# Web server logs
tail -f logs/nginx-access.log
tail -f logs/nginx-error.log

# PHP-FPM logs
tail -f logs/php-fpm.log
tail -f logs/php-fpm-error.log

# Worker logs
tail -f logs/worker.log
tail -f logs/worker-error.log

# Supervisor logs
tail -f logs/supervisord.log
```

### Health Check Endpoint

The application includes a health check at `/health` that verifies:
- Nginx is responding
- PHP-FPM is processing requests
- Database connectivity

**Docker Health Check Configuration:**
- Interval: 30 seconds
- Timeout: 10 seconds
- Start Period: 60 seconds (allows time for initialization)
- Retries: 3

**Test Health Check:**
```bash
curl http://localhost/health
# Expected: HTTP 200 with JSON response
```

**Easypanel Monitoring:**
- Easypanel automatically monitors health check
- Restarts container if health check fails 3 times
- View health status in app dashboard

### Performance Monitoring

**CPU and Memory:**
```bash
# Inside container
ps aux
top

# From Easypanel CLI
easypanel stats mesa-de-ayuda
```

**Database Queries:**
- Enable debug mode temporarily: `DEBUG=true`
- View SQL logs in `logs/debug.log`
- Disable after debugging: `DEBUG=false`

**Cache Status:**
```bash
# Check cache directory
du -sh tmp/cache/*

# Clear cache
php bin/cake.php cache clear_all
```

---

## Domain & SSL Setup

### 1. Configure Domain in Easypanel

1. Go to your app → **"Domains"** tab
2. Click **"Add Domain"**
3. Enter your domain: `yourdomain.com`
4. Enable **"Auto SSL"** (Let's Encrypt)
5. Save

### 2. DNS Configuration

Point your domain to Easypanel server:

**A Record:**
```
Type: A
Name: @ (or subdomain)
Value: YOUR_EASYPANEL_SERVER_IP
TTL: 3600
```

**CNAME (if using subdomain):**
```
Type: CNAME
Name: helpdesk
Value: your-easypanel-domain.com
TTL: 3600
```

### 3. Update Environment Variables

After domain is configured:
```env
FULL_BASE_URL=https://yourdomain.com
```

Redeploy or restart the container for changes to take effect.

### 4. Verify SSL

```bash
# Test HTTPS
curl -I https://yourdomain.com

# Check SSL certificate
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com
```

---

## Updating the Application

### Automatic Deployment (Recommended)

**Setup GitHub Webhook:**
1. In Easypanel: App → **"Settings"** → **"Build"**
2. Enable **"Auto Deploy"**
3. Copy webhook URL
4. In GitHub: Repository → **"Settings"** → **"Webhooks"** → **"Add Webhook"**
5. Paste webhook URL
6. Select **"Just the push event"**
7. Save

**Now:** Every push to main branch automatically triggers rebuild and deploy.

### Manual Deployment

**Option 1: Easypanel UI**
1. Go to your app
2. Click **"Rebuild"**
3. Wait for build to complete
4. Container automatically restarts

**Option 2: CLI**
```bash
easypanel rebuild mesa-de-ayuda
```

### Running Migrations After Update

```bash
# After deployment, run migrations
easypanel exec mesa-de-ayuda -- php bin/cake.php migrations migrate

# Clear cache
easypanel exec mesa-de-ayuda -- php bin/cake.php cache clear_all

# Restart worker (if code changes affect it)
easypanel exec mesa-de-ayuda -- supervisorctl restart gmail-worker
```

### Zero-Downtime Deployment Strategy

Easypanel doesn't support blue-green deployment natively, but you can minimize downtime:

1. **Run migrations before deploy** (if backward compatible)
2. **Use database migrations carefully** (avoid breaking changes)
3. **Scale to 2 instances** during deploy (Easypanel Pro)
4. **Test staging environment first**

---

## Troubleshooting

### Container Won't Start

**Check logs:**
```bash
easypanel logs mesa-de-ayuda
```

**Common issues:**
- **Missing environment variables**: Verify all required vars are set
- **Database connection failed**: Check `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`
- **Invalid SECURITY_SALT**: Must be 64-character hex string
- **Port conflict**: Ensure port 80 is not used by another app

**Fix and rebuild:**
```bash
easypanel rebuild mesa-de-ayuda --no-cache
```

### 500 Internal Server Error

**Check PHP errors:**
```bash
easypanel exec mesa-de-ayuda -- tail -f logs/error.log
easypanel exec mesa-de-ayuda -- tail -f logs/php-fpm-error.log
```

**Common causes:**
- **Missing permissions**: `chown -R www-data:www-data logs tmp webroot/uploads`
- **Cache corruption**: `php bin/cake.php cache clear_all`
- **Database not migrated**: `php bin/cake.php migrations migrate`

### Database Connection Errors

**Test connectivity from container:**
```bash
easypanel exec mesa-de-ayuda -- php bin/cake.php migrations status
```

**If fails:**
1. Verify database is accessible from Easypanel server
2. Check firewall rules (MySQL port 3306)
3. Verify credentials are correct
4. Check `DB_HOST` uses correct IP/domain

**Local MySQL fix:**
```bash
# Allow connections from Docker network
mysql -u root -p
GRANT ALL PRIVILEGES ON mesadeayuda.* TO 'mesadeayuda'@'172.%' IDENTIFIED BY 'password';
FLUSH PRIVILEGES;
```

### File Upload Errors

**Check permissions:**
```bash
easypanel exec mesa-de-ayuda -- ls -la webroot/uploads/
easypanel exec mesa-de-ayuda -- chown -R www-data:www-data webroot/uploads/
easypanel exec mesa-de-ayuda -- chmod -R 775 webroot/uploads/
```

**If using S3:**
- Verify AWS credentials in environment variables
- Check bucket permissions (public read for downloads)
- Test S3 connectivity: `aws s3 ls s3://your-bucket-name/`

### Gmail Worker Not Importing

**Verify worker is running:**
```bash
easypanel exec mesa-de-ayuda -- supervisorctl status gmail-worker
```

**Start worker:**
```bash
easypanel exec mesa-de-ayuda -- supervisorctl start gmail-worker
```

**Test manual import:**
```bash
easypanel exec mesa-de-ayuda -- php bin/cake.php import_gmail --max 5
```

**Check Gmail OAuth:**
1. Go to `/admin/settings`
2. Verify **"Gmail Refresh Token"** is set
3. Re-authorize if needed
4. Check `config/google/credentials.json` exists

### Performance Issues

**Increase PHP memory limit:**
```bash
# Edit docker/php/php.ini
memory_limit = 512M

# Rebuild container
easypanel rebuild mesa-de-ayuda
```

**Enable OPcache:**
```bash
# Already enabled in docker/php/php.ini
# Verify:
easypanel exec mesa-de-ayuda -- php -i | grep opcache
```

**Database optimization:**
```bash
# Add indexes for slow queries
mysql -h DB_HOST -u DB_USERNAME -p DB_DATABASE

# Analyze slow query log
tail -f logs/debug.log | grep "slow query"
```

---

## Performance Optimization

### Container Resources

**In Easypanel UI:**
1. App → **"Resources"**
2. Set resource limits:
   - **CPU**: 1-2 cores
   - **Memory**: 2-4GB (minimum 1GB)
   - **Swap**: 1GB

### PHP-FPM Tuning

Edit `docker/php/php.ini` and rebuild:
```ini
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
post_max_size = 100M
upload_max_filesize = 100M

opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # Disable in production
opcache.revalidate_freq=0
```

### Nginx Tuning

Edit `docker/nginx/easypanel.conf` and rebuild:
```nginx
client_max_body_size 200M;
client_body_buffer_size 128k;
fastcgi_buffers 16 16k;
fastcgi_buffer_size 32k;
```

### Database Optimization

**Enable query caching in CakePHP:**
```php
// config/app_local.php
'Cache' => [
    'default' => [
        'className' => 'File',
        'duration' => '+1 hour',
    ],
],
```

**Add database indexes:**
```sql
CREATE INDEX idx_tickets_status ON tickets(status);
CREATE INDEX idx_tickets_assigned_to ON tickets(assigned_to_id);
CREATE INDEX idx_tickets_created ON tickets(created);
```

### CDN for Static Assets

**Use CloudFront with S3:**
1. Upload static assets to S3
2. Create CloudFront distribution
3. Set `AWS_CLOUDFRONT_URL` environment variable
4. Assets served from CDN instead of container

---

## Backup Strategy

### Database Backups

**Automated backup script (on database server):**
```bash
#!/bin/bash
# backup.sh
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -h localhost -u mesadeayuda -p'password' mesadeayuda > backup_$DATE.sql
gzip backup_$DATE.sql

# Keep only last 7 days
find . -name "backup_*.sql.gz" -mtime +7 -delete
```

**Schedule with cron:**
```bash
crontab -e
# Add: Daily backup at 2 AM
0 2 * * * /path/to/backup.sh
```

### File Uploads Backup

**If using local storage (not S3):**
```bash
# Create persistent volume in Easypanel
# Map container path /var/www/html/webroot/uploads to host volume

# Backup from host
tar -czf uploads-backup-$(date +%Y%m%d).tar.gz /easypanel/volumes/mesa-de-ayuda/uploads/
```

**If using S3:** Backups handled by AWS (enable versioning)

### Configuration Backup

**Export environment variables:**
```bash
# In Easypanel: App → Environment → Export
# Save to secure location
```

**Backup Google OAuth credentials:**
```bash
# Copy from container
easypanel exec mesa-de-ayuda -- cat config/google/credentials.json > credentials-backup.json
```

---

## Migration from Docker Compose to Easypanel

If migrating from existing Docker Compose setup:

**1. Export data from Docker Compose:**
```bash
# Database dump
docker-compose exec web mysqldump -u root -p mesadeayuda > backup.sql

# File uploads (if not using S3)
docker cp $(docker-compose ps -q web):/var/www/html/webroot/uploads ./uploads-backup
```

**2. Import to Easypanel database:**
```bash
mysql -h EASYPANEL_DB_HOST -u mesadeayuda -p mesadeayuda < backup.sql
```

**3. Upload files to S3 (recommended) or container:**
```bash
# Option A: Upload to S3
aws s3 sync ./uploads-backup s3://your-bucket/uploads/

# Option B: Copy to container (not persistent across rebuilds)
# Use Easypanel file upload or:
tar -czf uploads.tar.gz uploads-backup/
# Upload via Easypanel terminal and extract
```

**4. Update environment variables in Easypanel**

**5. Deploy application**

**6. Verify migration:**
- Test login
- Check ticket data
- Verify file downloads work
- Test Gmail import worker

---

## Security Best Practices

1. **Use strong SECURITY_SALT**: Generate with `openssl rand -hex 32`
2. **Secure database credentials**: Use complex passwords (20+ characters)
3. **Enable Easypanel SSL**: Always use HTTPS in production
4. **Limit SSH access**: Use SSH keys, disable password auth
5. **Regular updates**: Rebuild container monthly with latest base images
6. **Monitor logs**: Set up log rotation and monitoring alerts
7. **Backup regularly**: Automate database and file backups
8. **Use managed database**: For production workloads
9. **Environment secrets**: Never commit `.env` or credentials to Git
10. **Rate limiting**: Configure Nginx rate limiting for public endpoints

**Nginx rate limiting example:**
```nginx
# Add to docker/nginx/easypanel.conf
limit_req_zone $binary_remote_addr zone=pqrs_limit:10m rate=10r/m;

location /pqrs/formulario {
    limit_req zone=pqrs_limit burst=5 nodelay;
    try_files $uri $uri/ /index.php?$args;
}
```

---

## Support & Resources

- **Easypanel Documentation**: https://easypanel.io/docs
- **CakePHP 5 Book**: https://book.cakephp.org/5/
- **Application Docs**: See `README.md` and `CLAUDE.md`
- **Docker Guide**: See `DOCKER.md` for multi-container setup comparison

**For Easypanel-specific issues:**
- Check Easypanel Community: https://discord.gg/easypanel
- Review Easypanel GitHub: https://github.com/easypanel-io

**For Application issues:**
- Check application logs: `logs/error.log`
- Review CLAUDE.md for architecture details
- Test locally with `bin/cake server` before deploying
