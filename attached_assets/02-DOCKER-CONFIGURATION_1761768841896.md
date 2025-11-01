# Docker Configuration

## Overview

This guide covers the complete Docker setup for TravianT4.6, including:
- Docker Compose configuration
- Nginx reverse proxy
- PHP-FPM containers
- MySQL database
- Redis cache
- Background workers
- Network configuration

## Directory Structure

Create the following structure in your project:

```
TravianT4.6/
├── docker/
│   ├── nginx/
│   │   ├── Dockerfile
│   │   ├── nginx.conf
│   │   └── conf.d/
│   │       └── default.conf
│   ├── php/
│   │   ├── Dockerfile
│   │   └── php.ini
│   ├── mysql/
│   │   └── init/
│   │       ├── 01-global-schema.sql
│   │       └── 02-world-schema.sql
│   └── redis/
│       └── redis.conf
├── docker-compose.yml
├── .env.example
├── .dockerignore
└── ... (application files)
```

## Step 1: Create Dockerfile for PHP-FPM

Create `docker/php/Dockerfile`:

```dockerfile
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libgd-dev \
    libmemcached-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_mysql \
    mysqli \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Create user matching host user (1000:1000)
RUN groupadd -g 1000 www && \
    useradd -u 1000 -g www -s /bin/bash -m www

# Change ownership
RUN chown -R www:www /var/www/html

# Switch to www user
USER www

# Expose port 9000
EXPOSE 9000

CMD ["php-fpm"]
```

## Step 2: Create PHP Configuration

Create `docker/php/php.ini`:

```ini
[PHP]
; Performance Settings
memory_limit = 512M
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
max_input_time = 300
max_input_vars = 5000

; Error Reporting (Development)
display_errors = Off
display_startup_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
log_errors = On
error_log = /var/log/php/error.log

; Timezone
date.timezone = UTC

; Session Settings
session.save_handler = redis
session.save_path = "tcp://redis:6379"
session.gc_maxlifetime = 86400
session.cookie_httponly = 1
session.cookie_secure = 1

; OPcache Settings (Production)
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1
```

## Step 3: Create Nginx Dockerfile

Create `docker/nginx/Dockerfile`:

```dockerfile
FROM nginx:alpine

# Copy Nginx configuration
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/ /etc/nginx/conf.d/

# Create log directory
RUN mkdir -p /var/log/nginx

# Set permissions
RUN chown -R nginx:nginx /var/log/nginx

EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]
```

## Step 4: Create Nginx Configuration

Create `docker/nginx/nginx.conf`:

```nginx
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 2048;
    use epoll;
    multi_accept on;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;

    # Performance settings
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 50M;

    # Gzip compression
    gzip on;
    gzip_disable "msie6";
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript 
               application/json application/javascript application/xml+rss 
               application/rss+xml font/truetype font/opentype 
               application/vnd.ms-fontobject image/svg+xml;

    # Include virtual host configs
    include /etc/nginx/conf.d/*.conf;
}
```

Create `docker/nginx/conf.d/default.conf`:

```nginx
# Main application server block
server {
    listen 80;
    server_name travian.local localhost;
    root /var/www/html/angularIndex/browser;
    index index.html;

    # Logging
    access_log /var/log/nginx/travian_access.log;
    error_log /var/log/nginx/travian_error.log;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # API routes (FastRoute backend)
    location /v1/ {
        try_files $uri /router.php$is_args$args;
        
        # PHP-FPM configuration
        fastcgi_pass php:9000;
        fastcgi_index router.php;
        fastcgi_param SCRIPT_FILENAME /var/www/html/router.php;
        include fastcgi_params;
        
        # Increase timeouts for long-running requests
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }

    # Static files with caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Angular SPA routing
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Deny access to sensitive files
    location ~* (\.php|\.git|\.env|composer\.json|composer\.lock)$ {
        deny all;
    }
}

# Game world servers (testworld, demo, etc.)
server {
    listen 80;
    server_name ~^(?<world>[^.]+)\.travian\.local$;
    root /var/www/html/sections/servers/$world/public;
    index index.php;

    access_log /var/log/nginx/${world}_access.log;
    error_log /var/log/nginx/${world}_error.log;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location ~ /\. {
        deny all;
    }
}
```

## Step 5: Create Docker Compose Configuration

Create `docker-compose.yml`:

```yaml
version: '3.8'

services:
  # Nginx Web Server
  nginx:
    build:
      context: .
      dockerfile: docker/nginx/Dockerfile
    container_name: travian_nginx
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/var/www/html:ro
      - ./docker/nginx/conf.d:/etc/nginx/conf.d:ro
      - nginx_logs:/var/log/nginx
    depends_on:
      - php
    networks:
      - travian_network
    restart: unless-stopped

  # PHP-FPM Application
  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: travian_php
    volumes:
      - ./:/var/www/html
      - php_logs:/var/log/php
    environment:
      - DB_HOST=${DB_HOST}
      - DB_PORT=${DB_PORT}
      - DB_DATABASE=${DB_DATABASE}
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - REDIS_HOST=redis
      - REDIS_PORT=6379
    depends_on:
      - mysql
      - redis
    networks:
      - travian_network
    restart: unless-stopped

  # MySQL Database
  mysql:
    image: mysql:8.0
    container_name: travian_mysql
    ports:
      - "3306:3306"
    environment:
      - MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
      - MYSQL_DATABASE=${MYSQL_DATABASE}
      - MYSQL_USER=${MYSQL_USER}
      - MYSQL_PASSWORD=${MYSQL_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/init:/docker-entrypoint-initdb.d:ro
      - ./docker/mysql/conf.d:/etc/mysql/conf.d:ro
    command: --default-authentication-plugin=mysql_native_password
              --character-set-server=utf8mb4
              --collation-server=utf8mb4_unicode_ci
              --max_connections=500
              --innodb_buffer_pool_size=1G
    networks:
      - travian_network
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  # Redis Cache
  redis:
    image: redis:7-alpine
    container_name: travian_redis
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
      - ./docker/redis/redis.conf:/usr/local/etc/redis/redis.conf:ro
    command: redis-server /usr/local/etc/redis/redis.conf
    networks:
      - travian_network
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 3s
      retries: 3

  # Task Worker (Background Jobs)
  task_worker:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: travian_task_worker
    command: php /var/www/html/TaskWorker/worker.php
    volumes:
      - ./:/var/www/html
    environment:
      - DB_HOST=${DB_HOST}
      - DB_PORT=${DB_PORT}
      - DB_DATABASE=${DB_DATABASE}
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - REDIS_HOST=redis
    depends_on:
      - mysql
      - redis
    networks:
      - travian_network
    restart: unless-stopped

  # Mail Notification Service
  mail_service:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: travian_mail
    command: php /var/www/html/mailNotify/notify.php
    volumes:
      - ./:/var/www/html
    environment:
      - SMTP_HOST=${SMTP_HOST}
      - SMTP_PORT=${SMTP_PORT}
      - SMTP_USERNAME=${SMTP_USERNAME}
      - SMTP_PASSWORD=${SMTP_PASSWORD}
      - SENDINBLUE_API_KEY=${SENDINBLUE_API_KEY}
    depends_on:
      - mysql
    networks:
      - travian_network
    restart: unless-stopped

  # PhpMyAdmin (Development Only)
  phpmyadmin:
    image: phpmyadmin/phpmyadmin:latest
    container_name: travian_phpmyadmin
    ports:
      - "8080:80"
    environment:
      - PMA_HOST=mysql
      - PMA_PORT=3306
      - PMA_USER=root
      - PMA_PASSWORD=${MYSQL_ROOT_PASSWORD}
    depends_on:
      - mysql
    networks:
      - travian_network
    profiles:
      - dev

networks:
  travian_network:
    driver: bridge

volumes:
  mysql_data:
    driver: local
  redis_data:
    driver: local
  nginx_logs:
    driver: local
  php_logs:
    driver: local
```

## Step 6: Create Environment Variables File

Create `.env.example`:

```bash
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://travian.yourdomain.com

# MySQL Database
MYSQL_ROOT_PASSWORD=secure_root_password_here
MYSQL_DATABASE=travian_global
MYSQL_USER=travian_user
MYSQL_PASSWORD=secure_password_here
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=travian_global
DB_USERNAME=travian_user
DB_PASSWORD=secure_password_here

# Redis
REDIS_HOST=redis
REDIS_PORT=6379

# Email SMTP
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_ENCRYPTION=tls
SMTP_FROM_ADDRESS=noreply@travian.com
SMTP_FROM_NAME=Travian

# SendinBlue API (optional)
SENDINBLUE_API_KEY=your-sendinblue-api-key

# reCAPTCHA
RECAPTCHA_SITE_KEY=your-recaptcha-site-key
RECAPTCHA_SECRET_KEY=your-recaptcha-secret-key

# Game Configuration
GAME_SPEED=100
ROUND_LENGTH=365

# Security
SESSION_LIFETIME=86400
SECURE_HASH_SALT=your-random-salt-here
```

Copy to `.env` and customize:

```bash
cp .env.example .env
```

## Step 7: Create .dockerignore

Create `.dockerignore`:

```
.git
.gitignore
.env
.env.example
node_modules
vendor
*.md
docs/
.vscode
.idea
*.log
.DS_Store
Thumbs.db
```

## Step 8: Create Redis Configuration

Create `docker/redis/redis.conf`:

```conf
# Redis configuration for Travian

# Network
bind 0.0.0.0
protected-mode yes
port 6379

# General
daemonize no
pidfile /var/run/redis_6379.pid
loglevel notice
databases 16

# Persistence
save 900 1
save 300 10
save 60 10000
stop-writes-on-bgsave-error yes
rdbcompression yes
dbfilename dump.rdb
dir /data

# Memory
maxmemory 256mb
maxmemory-policy allkeys-lru

# Append Only File
appendonly yes
appendfilename "appendonly.aof"
appendfsync everysec
```

## Step 9: Build and Start Containers

### Build Images

```bash
# Build all images
docker-compose build

# Or build specific service
docker-compose build php
```

### Start Containers

```bash
# Start all services
docker-compose up -d

# Check running containers
docker-compose ps

# View logs
docker-compose logs -f

# View specific service logs
docker-compose logs -f php
```

### Stop Containers

```bash
# Stop all services
docker-compose down

# Stop and remove volumes (WARNING: deletes data)
docker-compose down -v
```

## Step 10: Verify Installation

### Check Container Status

```bash
# All containers should be running
docker-compose ps
```

Expected output:
```
NAME                   SERVICE       STATUS       PORTS
travian_nginx          nginx         running      80/tcp, 443/tcp
travian_php            php           running      9000/tcp
travian_mysql          mysql         running      3306/tcp, 33060/tcp
travian_redis          redis         running      6379/tcp
travian_task_worker    task_worker   running
travian_mail           mail_service  running
```

### Test Services

```bash
# Test Nginx
curl http://localhost
# Should return HTML

# Test MySQL
docker-compose exec mysql mysql -u root -p -e "SELECT VERSION();"

# Test Redis
docker-compose exec redis redis-cli ping
# Should return PONG

# Test PHP
docker-compose exec php php -v
```

### Access Services

- **Application**: http://localhost
- **PhpMyAdmin** (dev only): http://localhost:8080
- **MySQL**: localhost:3306
- **Redis**: localhost:6379

## Maintenance Commands

### View Logs

```bash
# All services
docker-compose logs -f

# Specific service with timestamps
docker-compose logs -f --timestamps php

# Last 100 lines
docker-compose logs --tail=100 nginx
```

### Execute Commands in Containers

```bash
# PHP container bash
docker-compose exec php bash

# Run Composer
docker-compose exec php composer install

# MySQL console
docker-compose exec mysql mysql -u root -p

# Redis console
docker-compose exec redis redis-cli
```

### Restart Services

```bash
# Restart all
docker-compose restart

# Restart specific service
docker-compose restart php
```

### Update Images

```bash
# Pull latest images
docker-compose pull

# Rebuild and restart
docker-compose up -d --build
```

## Troubleshooting

### Port Already in Use

```bash
# Find process using port 80
netstat -ano | findstr :80

# Kill process (Windows PowerShell as Admin)
taskkill /PID <process_id> /F
```

### Container Won't Start

```bash
# Check logs
docker-compose logs container_name

# Remove and recreate
docker-compose rm -f container_name
docker-compose up -d container_name
```

### Permission Issues

```bash
# Fix file permissions (in WSL)
sudo chown -R 1000:1000 /path/to/project
```

## Next Steps

Continue to [03-DATABASE-SETUP.md](03-DATABASE-SETUP.md) for database configuration and schema import.
