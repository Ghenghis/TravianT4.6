# Production Deployment

## Overview

This guide covers deploying TravianT4.6 to a production server with enterprise-grade configuration, including:
- VPS/Cloud server setup
- SSL certificates
- Domain configuration
- Load balancing (optional)
- Monitoring and backups

## Step 1: Server Requirements

### Minimum Production Server Specs

- **OS**: Ubuntu 22.04 LTS Server
- **CPU**: 4 cores (8+ recommended)
- **RAM**: 16GB minimum (32GB+ recommended)
- **Storage**: 100GB SSD (500GB+ recommended)
- **Network**: 1Gbps connection
- **IP**: Static IP address

### Recommended Cloud Providers

- **DigitalOcean**: Droplet ($48/month for 8GB RAM)
- **Linode**: Shared CPU ($36/month for 8GB RAM)
- **AWS EC2**: t3.large ($60-70/month)
- **Google Cloud**: e2-standard-4 ($120/month)
- **Vultr**: Cloud Compute ($48/month for 8GB RAM)

## Step 2: Initial Server Setup

### Connect to Server

```bash
# SSH into your server
ssh root@your-server-ip

# Or with key
ssh -i ~/.ssh/your-key.pem root@your-server-ip
```

### Update System

```bash
# Update package list
apt update && apt upgrade -y

# Install essential tools
apt install -y curl wget git vim ufw fail2ban
```

### Create Application User

```bash
# Create user
adduser travian

# Add to sudo group
usermod -aG sudo travian

# Setup SSH for user
mkdir -p /home/travian/.ssh
cp ~/.ssh/authorized_keys /home/travian/.ssh/
chown -R travian:travian /home/travian/.ssh
chmod 700 /home/travian/.ssh
chmod 600 /home/travian/.ssh/authorized_keys

# Switch to new user
su - travian
```

### Configure Firewall

```bash
# Enable UFW
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Allow SSH
sudo ufw allow 22/tcp

# Allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Enable firewall
sudo ufw enable

# Check status
sudo ufw status
```

## Step 3: Install Docker on Ubuntu

### Install Docker Engine

```bash
# Remove old versions
sudo apt-get remove docker docker-engine docker.io containerd runc

# Install dependencies
sudo apt-get install -y \
    ca-certificates \
    curl \
    gnupg \
    lsb-release

# Add Docker's official GPG key
sudo mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg

# Set up repository
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Add user to docker group
sudo usermod -aG docker travian

# Log out and back in for group changes to take effect
exit
ssh travian@your-server-ip

# Verify installation
docker --version
docker compose version
```

### Configure Docker

```bash
# Create daemon config
sudo mkdir -p /etc/docker
sudo nano /etc/docker/daemon.json
```

Add:

```json
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  },
  "storage-driver": "overlay2"
}
```

Restart Docker:

```bash
sudo systemctl restart docker
sudo systemctl enable docker
```

## Step 4: Deploy Application

### Clone Repository

```bash
# Create app directory
sudo mkdir -p /var/www/travian
sudo chown travian:travian /var/www/travian
cd /var/www/travian

# Clone from GitHub
git clone https://github.com/YOUR_ORG/travian-t4.6.git .

# Checkout main branch
git checkout main
```

### Configure Environment

```bash
# Copy environment file
cp .env.example .env

# Edit with production values
nano .env
```

Update with production values:

```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://travian.yourdomain.com

MYSQL_ROOT_PASSWORD=<strong-password-here>
DB_PASSWORD=<strong-password-here>

# ... other values
```

### Build and Start

```bash
# Build images
docker compose build --no-cache

# Start services
docker compose up -d

# Check status
docker compose ps

# View logs
docker compose logs -f
```

## Step 5: Domain and DNS Setup

### Configure DNS Records

Add these records to your domain DNS:

```
Type    Name              Value                TTL
A       @                 your-server-ip       3600
A       www               your-server-ip       3600
CNAME   testworld         travian.yourdomain.com
CNAME   demo              travian.yourdomain.com
CNAME   *.yourdomain.com  travian.yourdomain.com
```

### Verify DNS Propagation

```bash
# Check DNS
dig travian.yourdomain.com
dig testworld.travian.yourdomain.com

# Or use online tools
# https://dnschecker.org
```

## Step 6: SSL Certificate Setup

### Install Certbot

```bash
# Install snapd
sudo apt install snapd
sudo snap install core
sudo snap refresh core

# Install certbot
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/bin/certbot
```

### Obtain SSL Certificate

```bash
# Stop nginx temporarily
docker compose stop nginx

# Get certificate (replace with your domain)
sudo certbot certonly --standalone \
  -d travian.yourdomain.com \
  -d www.travian.yourdomain.com \
  -d testworld.travian.yourdomain.com \
  -d demo.travian.yourdomain.com \
  --email your-email@example.com \
  --agree-tos \
  --non-interactive

# Certificates saved to:
# /etc/letsencrypt/live/travian.yourdomain.com/
```

### Configure Nginx for SSL

Update `docker/nginx/conf.d/default.conf`:

```nginx
# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name travian.yourdomain.com www.travian.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

# HTTPS server
server {
    listen 443 ssl http2;
    server_name travian.yourdomain.com www.travian.yourdomain.com;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/travian.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/travian.yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    # HSTS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # Other security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    root /var/www/html/angularIndex/browser;
    index index.html;
    
    # ... rest of configuration
}

# Game worlds with SSL
server {
    listen 443 ssl http2;
    server_name ~^(?<world>[^.]+)\.travian\.yourdomain\.com$;
    
    ssl_certificate /etc/letsencrypt/live/travian.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/travian.yourdomain.com/privkey.pem;
    # ... SSL config same as above
    
    # ... rest of game world config
}
```

### Mount SSL Certificates in Docker

Update `docker-compose.yml`:

```yaml
services:
  nginx:
    # ...
    volumes:
      - ./:/var/www/html:ro
      - ./docker/nginx/conf.d:/etc/nginx/conf.d:ro
      - /etc/letsencrypt:/etc/letsencrypt:ro  # Add this
      - nginx_logs:/var/log/nginx
```

### Restart Services

```bash
docker compose down
docker compose up -d

# Verify HTTPS
curl -I https://travian.yourdomain.com
```

### Auto-Renewal Setup

```bash
# Test renewal
sudo certbot renew --dry-run

# Create renewal hook
sudo nano /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh
```

Add:

```bash
#!/bin/bash
cd /var/www/travian
docker compose restart nginx
```

Make executable:

```bash
sudo chmod +x /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh
```

## Step 7: Production Optimization

### Enable OPcache

In `docker/php/php.ini`:

```ini
[opcache]
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0  # Disable in production
opcache.revalidate_freq=0
opcache.fast_shutdown=1
```

### MySQL Optimization

Create `docker/mysql/conf.d/production.cnf`:

```ini
[mysqld]
# Connection Settings
max_connections = 500
connect_timeout = 10
wait_timeout = 600
max_allowed_packet = 64M

# Buffer Settings
innodb_buffer_pool_size = 4G       # 50-70% of available RAM
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Query Cache (MySQL 5.7)
query_cache_type = 1
query_cache_size = 256M
query_cache_limit = 2M

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 2
log_queries_not_using_indexes = 1

# Performance
innodb_io_capacity = 2000
innodb_read_io_threads = 4
innodb_write_io_threads = 4
```

### Restart Services

```bash
docker compose restart
```

## Step 8: Automated Backups

### Create Backup Script

Create `/var/www/travian/scripts/production-backup.sh`:

```bash
#!/bin/bash

BACKUP_DIR="/var/backups/travian"
DATE=$(date +"%Y%m%d_%H%M%S")
RETENTION_DAYS=30

# Create backup directory
mkdir -p ${BACKUP_DIR}

# Backup MySQL databases
echo "Backing up databases..."
docker compose exec -T mysql mysqldump -u root -p${MYSQL_ROOT_PASSWORD} \
    --all-databases --single-transaction --quick --lock-tables=false \
    | gzip > "${BACKUP_DIR}/mysql_${DATE}.sql.gz"

# Backup application files
echo "Backing up application files..."
tar -czf "${BACKUP_DIR}/app_${DATE}.tar.gz" \
    --exclude='./docker/mysql/data' \
    --exclude='./storage/logs' \
    --exclude='./node_modules' \
    --exclude='./vendor' \
    -C /var/www/travian .

# Upload to cloud storage (optional - AWS S3 example)
if command -v aws &> /dev/null; then
    echo "Uploading to S3..."
    aws s3 cp "${BACKUP_DIR}/mysql_${DATE}.sql.gz" s3://your-backup-bucket/travian/
    aws s3 cp "${BACKUP_DIR}/app_${DATE}.tar.gz" s3://your-backup-bucket/travian/
fi

# Remove old backups
echo "Cleaning old backups..."
find ${BACKUP_DIR} -name "*.sql.gz" -mtime +${RETENTION_DAYS} -delete
find ${BACKUP_DIR} -name "*.tar.gz" -mtime +${RETENTION_DAYS} -delete

echo "Backup completed: ${DATE}"
```

Make executable:

```bash
chmod +x scripts/production-backup.sh
```

### Schedule with Cron

```bash
# Edit crontab
crontab -e
```

Add:

```cron
# Backup every day at 2 AM
0 2 * * * /var/www/travian/scripts/production-backup.sh >> /var/log/travian-backup.log 2>&1

# Restart task worker daily at 3 AM
0 3 * * * cd /var/www/travian && docker compose restart task_worker
```

## Step 9: Monitoring Setup

### Install Monitoring Stack (Optional)

Create `docker-compose.monitoring.yml`:

```yaml
version: '3.8'

services:
  prometheus:
    image: prom/prometheus:latest
    ports:
      - "9090:9090"
    volumes:
      - ./monitoring/prometheus.yml:/etc/prometheus/prometheus.yml
      - prometheus_data:/prometheus
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
    networks:
      - travian_network

  grafana:
    image: grafana/grafana:latest
    ports:
      - "3000:3000"
    volumes:
      - grafana_data:/var/lib/grafana
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin
    networks:
      - travian_network

  node_exporter:
    image: prom/node-exporter:latest
    ports:
      - "9100:9100"
    networks:
      - travian_network

volumes:
  prometheus_data:
  grafana_data:

networks:
  travian_network:
    external: true
```

Start monitoring:

```bash
docker compose -f docker-compose.monitoring.yml up -d
```

## Step 10: Deployment Checklist

### Pre-Deployment

- [ ] DNS records configured and propagated
- [ ] SSL certificates obtained and configured
- [ ] All environment variables set correctly
- [ ] Firewall rules configured
- [ ] Backup system tested
- [ ] Database migrations tested
- [ ] All services running correctly

### Post-Deployment

- [ ] Application accessible via HTTPS
- [ ] User registration working
- [ ] Login system functional
- [ ] Game worlds accessible
- [ ] Email notifications sending
- [ ] Background workers running
- [ ] Monitoring dashboards accessible
- [ ] Backups scheduled and working

### Performance Testing

```bash
# Test response time
curl -w "@curl-format.txt" -o /dev/null -s https://travian.yourdomain.com

# Load testing with Apache Bench
ab -n 1000 -c 10 https://travian.yourdomain.com/

# Or use wrk
wrk -t12 -c400 -d30s https://travian.yourdomain.com/
```

## Next Steps

Continue to [07-SECURITY-HARDENING.md](07-SECURITY-HARDENING.md) for security best practices and hardening.
