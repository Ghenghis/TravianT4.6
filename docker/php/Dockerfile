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

# Set working directory
WORKDIR /var/www/html

# Create directories for logs
RUN mkdir -p /var/log/php /var/log/php-fpm && \
    chown -R www-data:www-data /var/log/php /var/log/php-fpm

# Expose port 9000
EXPOSE 9000

CMD ["php-fpm"]
