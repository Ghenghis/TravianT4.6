FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libpq-dev \
    libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# Configure GD with JPEG and Freetype support
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    pdo_pgsql \
    gd \
    mbstring \
    xml \
    zip \
    opcache \
    intl \
    bcmath \
    pcntl \
    sockets

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Create non-root user
RUN groupadd -r appuser && useradd -r -g appuser -u 1001 appuser

# Create directories for logs
RUN mkdir -p /var/log/php /var/log/php-fpm && \
    chown -R appuser:appuser /var/log/php /var/log/php-fpm

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY --chown=appuser:appuser . /var/www/html

# Make health check executable
RUN chmod +x /var/www/html/scripts/healthcheck/php-fpm-healthcheck.php

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R appuser:appuser /var/www/html

# Switch to non-root user
USER appuser

EXPOSE 9000

CMD ["php-fpm"]
