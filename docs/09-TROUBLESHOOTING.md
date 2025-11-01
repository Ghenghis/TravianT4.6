# Troubleshooting Guide

## Overview

This guide covers common issues and solutions for TravianT4.6 deployment and operation.

## Docker Issues

### Container Won't Start

**Problem**: Container exits immediately after starting

```bash
# Check container logs
docker compose logs container_name

# Common issues and fixes:
```

**Solution 1**: Port already in use
```bash
# Find process using the port
netstat -tulpn | grep :80

# Kill the process
sudo kill -9 PID

# Or change port in docker-compose.yml
```

**Solution 2**: Configuration error
```bash
# Validate docker-compose.yml
docker compose config

# Check for syntax errors
```

**Solution 3**: Permission issues
```bash
# Fix file ownership
sudo chown -R 1000:1000 /var/www/travian

# Fix directory permissions
find /var/www/travian -type d -exec chmod 755 {} \;
find /var/www/travian -type f -exec chmod 644 {} \;
```

### Out of Memory Errors

**Problem**: Container killed due to OOM

```bash
# Check Docker memory limits
docker stats

# Increase memory limit in docker-compose.yml
services:
  php:
    deploy:
      resources:
        limits:
          memory: 4G  # Increase from 2G
```

**Solution**: Optimize PHP memory
```ini
# docker/php/php.ini
memory_limit = 1024M
```

### Docker Disk Space Full

**Problem**: No space left on device

```bash
# Check Docker disk usage
docker system df

# Clean up unused resources
docker system prune -a --volumes

# Remove stopped containers
docker container prune

# Remove unused images
docker image prune -a

# Remove unused volumes
docker volume prune
```

## Database Issues

### MySQL Connection Refused

**Problem**: Can't connect to MySQL

**Solution 1**: Check if MySQL is running
```bash
docker compose ps mysql
docker compose logs mysql

# Restart MySQL
docker compose restart mysql
```

**Solution 2**: Wrong credentials
```bash
# Verify environment variables
cat .env | grep MYSQL

# Test connection
docker compose exec mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "SELECT 1"
```

**Solution 3**: MySQL not fully initialized
```bash
# Wait for MySQL to be ready
docker compose logs -f mysql
# Wait for: "ready for connections"

# Health check
docker compose exec mysql mysqladmin ping -h localhost
```

### MySQL Slow Queries

**Problem**: Database queries taking too long

```bash
# Check slow query log
docker compose exec mysql cat /var/log/mysql/slow-query.log

# Identify slow queries
docker compose exec mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "
    SELECT * FROM sys.statements_with_full_table_scans LIMIT 10;
"
```

**Solution**: Add indexes
```sql
-- Example: Add index to activation table
ALTER TABLE activation ADD INDEX idx_email (email);
ALTER TABLE activation ADD INDEX idx_wid_name (wid, name);

-- Check query execution plan
EXPLAIN SELECT * FROM activation WHERE email = 'test@example.com';
```

**Solution**: Optimize MySQL configuration
```ini
# docker/mysql/conf.d/custom.cnf
[mysqld]
innodb_buffer_pool_size = 2G
query_cache_size = 256M
tmp_table_size = 64M
max_heap_table_size = 64M
```

### Database Corruption

**Problem**: Table corruption errors

```bash
# Check for corruption
docker compose exec mysql mysqlcheck -u root -p${MYSQL_ROOT_PASSWORD} --check --all-databases

# Repair tables
docker compose exec mysql mysqlcheck -u root -p${MYSQL_ROOT_PASSWORD} --repair --all-databases

# Optimize tables after repair
docker compose exec mysql mysqlcheck -u root -p${MYSQL_ROOT_PASSWORD} --optimize --all-databases
```

## Application Issues

### HTTP 500 Errors

**Problem**: Internal Server Error

**Solution 1**: Check PHP errors
```bash
# View PHP error logs
docker compose exec php tail -f /var/log/php/error.log

# Or application logs
tail -f storage/logs/error.log
```

**Solution 2**: Check Nginx errors
```bash
docker compose logs nginx | grep error
tail -f docker/nginx/logs/error.log
```

**Solution 3**: Enable debug mode temporarily
```bash
# .env
APP_DEBUG=true

# Restart containers
docker compose restart php nginx
```

**Solution 4**: Check file permissions
```bash
# Make storage writable
chmod -R 775 storage/logs
chmod -R 775 storage/cache

# Fix ownership
chown -R 1000:1000 storage/
```

### Login Not Working

**Problem**: Users can't log in

**Solution 1**: Check database connection
```bash
# Test global database
docker compose exec mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} -D travian_global -e "SELECT * FROM gameServers"

# Test world database
docker compose exec mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} -D travian_testworld -e "SELECT COUNT(*) FROM users"
```

**Solution 2**: Verify game world configuration
```bash
# Check if connection.php exists
ls -la sections/servers/testworld/include/connection.php

# Verify database name matches
cat sections/servers/testworld/include/connection.php | grep database
```

**Solution 3**: Check session storage
```bash
# Test Redis
docker compose exec redis redis-cli ping

# Check session data
docker compose exec redis redis-cli KEYS "PHPREDIS_SESSION:*"
```

### Registration Failing

**Problem**: User registration not working

**Solution 1**: Check activation table
```sql
-- Check if table exists
SHOW TABLES LIKE 'activation';

-- Check table structure
DESCRIBE activation;

-- Test insert
INSERT INTO activation (wid, name, password, email, token, time) 
VALUES (1, 'test', SHA1('password'), 'test@test.com', MD5(RAND()), UNIX_TIMESTAMP());
```

**Solution 2**: Check email sending
```bash
# Test email configuration
docker compose exec php php scripts/test-email.php

# Check mail queue
docker compose exec mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} -D travian_global -e "SELECT * FROM mailserver WHERE status='pending'"
```

**Solution 3**: Check reCAPTCHA
```bash
# Verify reCAPTCHA keys
cat .env | grep RECAPTCHA

# Temporarily disable reCAPTCHA
# In RegisterCtrl.php, comment out reCAPTCHA validation
```

## Network Issues

### Can't Access Application

**Problem**: Can't reach application in browser

**Solution 1**: Check if services are running
```bash
docker compose ps
curl -I http://localhost
curl -I https://travian.yourdomain.com
```

**Solution 2**: Check firewall
```bash
# Verify firewall rules
sudo ufw status

# Allow ports if needed
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
```

**Solution 3**: Check DNS
```bash
# Test DNS resolution
nslookup travian.yourdomain.com
dig travian.yourdomain.com

# Test with IP directly
curl -I http://YOUR_SERVER_IP
```

**Solution 4**: Check Nginx configuration
```bash
# Test Nginx config
docker compose exec nginx nginx -t

# Reload Nginx
docker compose exec nginx nginx -s reload
```

### SSL Certificate Errors

**Problem**: SSL certificate not working

**Solution 1**: Verify certificate files
```bash
# Check if certificates exist
ls -la /etc/letsencrypt/live/travian.yourdomain.com/

# Check certificate expiry
sudo certbot certificates
```

**Solution 2**: Renew certificate
```bash
# Renew manually
sudo certbot renew

# Test renewal
sudo certbot renew --dry-run

# Restart Nginx
docker compose restart nginx
```

**Solution 3**: Fix permissions
```bash
# Allow Docker to read certificates
sudo chmod -R 755 /etc/letsencrypt/live/
sudo chmod -R 755 /etc/letsencrypt/archive/
```

## Performance Issues

### Slow Page Load Times

**Problem**: Application is slow

**Solution 1**: Enable OPcache
```bash
# Check if OPcache is enabled
docker compose exec php php -i | grep opcache.enable

# Verify OPcache settings
docker compose exec php php --ri opcache
```

**Solution 2**: Check database performance
```sql
-- Show processlist
SHOW FULL PROCESSLIST;

-- Find slow queries
SELECT * FROM sys.statements_with_runtimes_in_95th_percentile;

-- Check index usage
SELECT * FROM sys.schema_unused_indexes;
```

**Solution 3**: Monitor Redis
```bash
# Check Redis stats
docker compose exec redis redis-cli INFO stats

# Monitor Redis in real-time
docker compose exec redis redis-cli MONITOR
```

**Solution 4**: Check resource usage
```bash
# System resources
top
htop

# Docker stats
docker stats

# Disk I/O
iostat -x 1
```

### High CPU Usage

**Problem**: Server CPU at 100%

**Solution 1**: Identify the process
```bash
# Check top processes
top -o %CPU

# Check Docker container CPU
docker stats
```

**Solution 2**: Optimize PHP-FPM
```ini
# docker/php/www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

**Solution 3**: Add caching
```php
// Use Redis for caching
$redis = new Redis();
$redis->connect('redis', 6379);

$cacheKey = 'game_servers_list';
$cached = $redis->get($cacheKey);

if ($cached) {
    return json_decode($cached, true);
}

$data = fetchFromDatabase();
$redis->setex($cacheKey, 3600, json_encode($data));
```

## Deployment Issues

### Git Pull Fails

**Problem**: Can't pull latest changes

```bash
# Discard local changes
git stash

# Pull changes
git pull origin main

# Apply stashed changes
git stash pop

# Or reset completely (WARNING: loses local changes)
git fetch origin
git reset --hard origin/main
```

### Build Failures

**Problem**: Docker build fails

**Solution 1**: Clear build cache
```bash
# Build without cache
docker compose build --no-cache

# Remove all build cache
docker builder prune -a
```

**Solution 2**: Fix Dockerfile syntax
```bash
# Validate Dockerfile
docker run --rm -i hadolint/hadolint < docker/php/Dockerfile
```

### Migration Issues

**Problem**: Database migration fails

```bash
# Roll back last migration
docker compose exec php php artisan migrate:rollback

# Fresh migration (WARNING: drops all tables)
docker compose exec php php artisan migrate:fresh

# Repair migration table
docker compose exec mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} -D travian_global -e "
    DROP TABLE IF EXISTS migrations;
    CREATE TABLE migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255),
        batch INT
    );
"
```

## Email Issues

### Emails Not Sending

**Problem**: Email notifications not working

**Solution 1**: Test SMTP connection
```bash
# Test with telnet
telnet smtp.gmail.com 587

# Test with PHP
docker compose exec php php scripts/test-email.php
```

**Solution 2**: Check mail queue
```sql
-- Check pending emails
SELECT * FROM mailserver WHERE status='pending';

-- Check failed emails
SELECT * FROM mailserver WHERE status='failed' ORDER BY created_at DESC LIMIT 10;

-- Retry failed emails
UPDATE mailserver SET status='pending', attempts=0 WHERE status='failed';
```

**Solution 3**: Verify SMTP credentials
```bash
# Check environment variables
docker compose exec php env | grep SMTP

# Test with different SMTP provider
```

### Emails Going to Spam

**Problem**: Emails end up in spam folder

**Solution 1**: Set up SPF record
```
# Add to DNS
v=spf1 include:_spf.google.com ~all
```

**Solution 2**: Set up DKIM
```bash
# Generate DKIM keys
openssl genrsa -out dkim_private.pem 1024
openssl rsa -in dkim_private.pem -pubout -out dkim_public.pem

# Add public key to DNS TXT record
```

**Solution 3**: Set up DMARC
```
# Add to DNS TXT record
_dmarc.travian.com
v=DMARC1; p=quarantine; rua=mailto:dmarc@travian.com
```

## Recovery Procedures

### Complete System Recovery

**Problem**: Total system failure

```bash
# 1. Stop all containers
cd /var/www/travian
docker compose down

# 2. Restore from backup
tar -xzf /var/backups/travian/travian_backup_YYYYMMDD_HHMMSS.tar.gz -C /var/www/travian/

# 3. Restore database
docker compose up -d mysql
sleep 30
docker compose exec -T mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} < /var/backups/travian/mysql_YYYYMMDD_HHMMSS.sql

# 4. Start all services
docker compose up -d

# 5. Verify
docker compose ps
curl -I https://travian.yourdomain.com
```

### Emergency Maintenance Mode

**Problem**: Need to take site down temporarily

```bash
# 1. Enable maintenance in Nginx
cat > docker/nginx/conf.d/maintenance.conf << 'EOF'
server {
    listen 80 default_server;
    listen 443 ssl default_server;
    return 503;
}

error_page 503 @maintenance;
location @maintenance {
    root /var/www/html;
    rewrite ^(.*)$ /maintenance.html break;
}
EOF

# 2. Create maintenance page
cat > angularIndex/browser/maintenance.html << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <title>Maintenance</title>
</head>
<body>
    <h1>We'll be back soon!</h1>
    <p>Sorry for the inconvenience. We're performing maintenance.</p>
</body>
</html>
EOF

# 3. Reload Nginx
docker compose restart nginx

# 4. Disable maintenance when done
rm docker/nginx/conf.d/maintenance.conf
docker compose restart nginx
```

## Diagnostic Commands

### Quick Health Check

```bash
#!/bin/bash
# Save as scripts/quick-health-check.sh

echo "=== Quick Health Check ==="

# Docker
echo -n "Docker: "
docker info > /dev/null 2>&1 && echo "✓" || echo "✗"

# Containers
echo -n "Containers Running: "
docker compose ps | grep "Up" | wc -l

# MySQL
echo -n "MySQL: "
docker compose exec mysql mysqladmin ping -h localhost 2>&1 | grep -q "alive" && echo "✓" || echo "✗"

# Redis
echo -n "Redis: "
docker compose exec redis redis-cli ping 2>&1 | grep -q "PONG" && echo "✓" || echo "✗"

# Web Server
echo -n "Web Server (HTTP): "
curl -s -o /dev/null -w "%{http_code}" http://localhost | grep -q "200\|301\|302" && echo "✓" || echo "✗"

# Disk Space
echo "Disk Usage: $(df -h / | tail -1 | awk '{print $5}')"

# Memory
echo "Memory Usage: $(free -m | awk 'NR==2{printf "%.2f%%", $3*100/$2}')"
```

### Full System Diagnostic

Create `scripts/full-diagnostic.sh`:

```bash
#!/bin/bash
# Comprehensive diagnostic script

OUTPUT="/tmp/travian-diagnostic-$(date +%Y%m%d-%H%M%S).log"

echo "Generating diagnostic report..."
echo "Output: $OUTPUT"

{
    echo "=== Travian System Diagnostic ==="
    echo "Generated: $(date)"
    echo ""
    
    echo "## System Information"
    uname -a
    echo ""
    
    echo "## Docker Version"
    docker --version
    docker compose version
    echo ""
    
    echo "## Running Containers"
    docker compose ps
    echo ""
    
    echo "## Container Logs (last 50 lines each)"
    for container in $(docker compose ps -q); do
        echo "### Container: $(docker inspect $container | jq -r '.[0].Name')"
        docker logs --tail 50 $container
        echo ""
    done
    
    echo "## System Resources"
    free -h
    df -h
    echo ""
    
    echo "## Network Connections"
    netstat -tulpn | grep -E ':(80|443|3306|6379|9000)'
    echo ""
    
    echo "## Environment Variables"
    env | grep -v PASSWORD | grep -v SECRET | sort
    echo ""
    
    echo "## Git Status"
    git status
    git log --oneline -5
    echo ""
    
} > "$OUTPUT" 2>&1

echo "Diagnostic complete. Review: $OUTPUT"
echo "To share with support, run: cat $OUTPUT"
```

## Getting Help

### Before Asking for Help

1. Check this troubleshooting guide
2. Run diagnostic script
3. Check application logs
4. Search GitHub issues
5. Review recent changes

### Information to Provide

When reporting issues, include:

```bash
# System info
uname -a
docker --version

# Error logs
docker compose logs --tail=100 > logs.txt

# Container status
docker compose ps

# Diagnostic report
./scripts/full-diagnostic.sh
```

### Support Resources

- **Documentation**: `/docs` folder
- **GitHub Issues**: Create detailed issue report
- **Community Forum**: Ask community for help
- **Professional Support**: Contact your support provider

## Conclusion

This troubleshooting guide covers the most common issues. For complex problems:

1. Collect diagnostic information
2. Check recent changes
3. Review logs systematically
4. Test in isolation
5. Document the solution

Remember: Always backup before making major changes!
