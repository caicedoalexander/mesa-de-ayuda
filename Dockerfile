FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libicu-dev \
    nginx \
    supervisor \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Install application dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create necessary directories and set permissions
RUN mkdir -p logs tmp/cache tmp/sessions webroot/uploads/tickets webroot/uploads/compras webroot/uploads/pqrs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 logs tmp webroot/uploads

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Copy Nginx configuration
COPY docker/nginx/default.conf /etc/nginx/sites-available/default

# Copy supervisor configuration for running nginx + php-fpm + worker
RUN echo '[supervisord]\n\
nodaemon=true\n\
user=root\n\
\n\
[program:php-fpm]\n\
command=/usr/local/sbin/php-fpm -F\n\
autostart=true\n\
autorestart=true\n\
stdout_logfile=/var/www/html/logs/php-fpm.log\n\
stderr_logfile=/var/www/html/logs/php-fpm-error.log\n\
\n\
[program:nginx]\n\
command=/usr/sbin/nginx -g "daemon off;"\n\
autostart=true\n\
autorestart=true\n\
stdout_logfile=/var/www/html/logs/nginx.log\n\
stderr_logfile=/var/www/html/logs/nginx-error.log\n\
\n\
[program:gmail-worker]\n\
command=/usr/local/bin/php /var/www/html/bin/cake.php gmail_worker\n\
autostart=true\n\
autorestart=true\n\
stdout_logfile=/var/www/html/logs/worker.log\n\
stderr_logfile=/var/www/html/logs/worker-error.log\n\
user=www-data' > /etc/supervisor/conf.d/supervisord.conf

# Expose port 80
EXPOSE 80

# Start supervisor (runs nginx, php-fpm, and worker)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
