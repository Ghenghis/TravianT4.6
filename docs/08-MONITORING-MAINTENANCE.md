# Monitoring and Maintenance

## Overview

This guide covers monitoring, logging, performance tuning, and regular maintenance tasks for TravianT4.6.

## Step 1: Application Monitoring

### Install Monitoring Stack

Create `docker-compose.monitoring.yml`:

```yaml
version: '3.8'

services:
  prometheus:
    image: prom/prometheus:latest
    container_name: travian_prometheus
    ports:
      - "9090:9090"
    volumes:
      - ./monitoring/prometheus.yml:/etc/prometheus/prometheus.yml:ro
      - prometheus_data:/prometheus
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.path=/prometheus'
      - '--web.console.libraries=/etc/prometheus/console_libraries'
      - '--web.console.templates=/etc/prometheus/consoles'
    networks:
      - travian_network
    restart: unless-stopped

  grafana:
    image: grafana/grafana:latest
    container_name: travian_grafana
    ports:
      - "3000:3000"
    volumes:
      - grafana_data:/var/lib/grafana
      - ./monitoring/grafana/dashboards:/etc/grafana/provisioning/dashboards:ro
      - ./monitoring/grafana/datasources:/etc/grafana/provisioning/datasources:ro
    environment:
      - GF_SECURITY_ADMIN_USER=admin
      - GF_SECURITY_ADMIN_PASSWORD=${GRAFANA_PASSWORD}
      - GF_INSTALL_PLUGINS=grafana-clock-panel,grafana-simple-json-datasource
    networks:
      - travian_network
    restart: unless-stopped
    depends_on:
      - prometheus

  node_exporter:
    image: prom/node-exporter:latest
    container_name: travian_node_exporter
    ports:
      - "9100:9100"
    volumes:
      - /proc:/host/proc:ro
      - /sys:/host/sys:ro
      - /:/rootfs:ro
    command:
      - '--path.procfs=/host/proc'
      - '--path.sysfs=/host/sys'
      - '--collector.filesystem.mount-points-exclude=^/(sys|proc|dev|host|etc)($$|/)'
    networks:
      - travian_network
    restart: unless-stopped

  mysql_exporter:
    image: prom/mysqld-exporter:latest
    container_name: travian_mysql_exporter
    ports:
      - "9104:9104"
    environment:
      - DATA_SOURCE_NAME=exporter:${MYSQL_EXPORTER_PASSWORD}@(mysql:3306)/
    networks:
      - travian_network
    restart: unless-stopped
    depends_on:
      - mysql

  redis_exporter:
    image: oliver006/redis_exporter:latest
    container_name: travian_redis_exporter
    ports:
      - "9121:9121"
    environment:
      - REDIS_ADDR=redis:6379
    networks:
      - travian_network
    restart: unless-stopped
    depends_on:
      - redis

  alertmanager:
    image: prom/alertmanager:latest
    container_name: travian_alertmanager
    ports:
      - "9093:9093"
    volumes:
      - ./monitoring/alertmanager.yml:/etc/alertmanager/alertmanager.yml:ro
      - alertmanager_data:/alertmanager
    command:
      - '--config.file=/etc/alertmanager/alertmanager.yml'
      - '--storage.path=/alertmanager'
    networks:
      - travian_network
    restart: unless-stopped

volumes:
  prometheus_data:
  grafana_data:
  alertmanager_data:

networks:
  travian_network:
    external: true
```

### Configure Prometheus

Create `monitoring/prometheus.yml`:

```yaml
global:
  scrape_interval: 15s
  evaluation_interval: 15s
  external_labels:
    monitor: 'travian-monitor'

alerting:
  alertmanagers:
    - static_configs:
        - targets: ['alertmanager:9093']

rule_files:
  - '/etc/prometheus/alert.rules.yml'

scrape_configs:
  # Prometheus itself
  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']

  # Node Exporter
  - job_name: 'node'
    static_configs:
      - targets: ['node_exporter:9100']

  # MySQL Exporter
  - job_name: 'mysql'
    static_configs:
      - targets: ['mysql_exporter:9104']

  # Redis Exporter
  - job_name: 'redis'
    static_configs:
      - targets: ['redis_exporter:9121']

  # Nginx Exporter (optional - requires nginx-prometheus-exporter)
  - job_name: 'nginx'
    static_configs:
      - targets: ['nginx_exporter:9113']

  # Application metrics (if implemented)
  - job_name: 'travian_app'
    metrics_path: '/metrics'
    static_configs:
      - targets: ['php:9000']
```

### Configure Alert Rules

Create `monitoring/alert.rules.yml`:

```yaml
groups:
  - name: travian_alerts
    interval: 30s
    rules:
      # Server Alerts
      - alert: HighCPUUsage
        expr: 100 - (avg by(instance) (rate(node_cpu_seconds_total{mode="idle"}[5m])) * 100) > 80
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High CPU usage detected"
          description: "CPU usage is above 80% for 5 minutes"

      - alert: HighMemoryUsage
        expr: (node_memory_MemTotal_bytes - node_memory_MemAvailable_bytes) / node_memory_MemTotal_bytes * 100 > 90
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "High memory usage detected"
          description: "Memory usage is above 90%"

      - alert: DiskSpaceLow
        expr: (node_filesystem_avail_bytes{mountpoint="/"} / node_filesystem_size_bytes{mountpoint="/"}) * 100 < 10
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Low disk space"
          description: "Disk space is below 10%"

      # Database Alerts
      - alert: MySQLDown
        expr: mysql_up == 0
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "MySQL is down"
          description: "MySQL database is not responding"

      - alert: MySQLSlowQueries
        expr: rate(mysql_global_status_slow_queries[5m]) > 10
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High number of slow queries"
          description: "More than 10 slow queries per second"

      # Redis Alerts
      - alert: RedisDown
        expr: redis_up == 0
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "Redis is down"
          description: "Redis cache is not responding"

      - alert: RedisMemoryHigh
        expr: redis_memory_used_bytes / redis_memory_max_bytes * 100 > 90
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Redis memory usage high"
          description: "Redis is using over 90% of allocated memory"

      # Application Alerts
      - alert: HighErrorRate
        expr: rate(nginx_http_requests_total{status=~"5.."}[5m]) > 10
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "High error rate detected"
          description: "More than 10 500-errors per second"

      - alert: HighResponseTime
        expr: nginx_http_request_duration_seconds{quantile="0.99"} > 2
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High response time"
          description: "99th percentile response time is above 2 seconds"
```

### Configure Alertmanager

Create `monitoring/alertmanager.yml`:

```yaml
global:
  resolve_timeout: 5m
  smtp_from: 'alerts@travian.com'
  smtp_smarthost: 'smtp.gmail.com:587'
  smtp_auth_username: 'your-email@gmail.com'
  smtp_auth_password: 'your-app-password'

route:
  group_by: ['alertname', 'cluster', 'service']
  group_wait: 10s
  group_interval: 10s
  repeat_interval: 12h
  receiver: 'default'
  routes:
    - match:
        severity: critical
      receiver: 'critical'
      continue: true
    - match:
        severity: warning
      receiver: 'warning'

receivers:
  - name: 'default'
    email_configs:
      - to: 'admin@travian.com'
        headers:
          Subject: 'Travian Alert: {{ .GroupLabels.alertname }}'

  - name: 'critical'
    email_configs:
      - to: 'admin@travian.com,team@travian.com'
        headers:
          Subject: 'CRITICAL: {{ .GroupLabels.alertname }}'
    # Optional: Slack webhook
    slack_configs:
      - api_url: 'https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK'
        channel: '#alerts'
        title: 'Critical Alert'
        text: '{{ range .Alerts }}{{ .Annotations.description }}{{ end }}'

  - name: 'warning'
    email_configs:
      - to: 'admin@travian.com'
        headers:
          Subject: 'WARNING: {{ .GroupLabels.alertname }}'

inhibit_rules:
  - source_match:
      severity: 'critical'
    target_match:
      severity: 'warning'
    equal: ['alertname', 'instance']
```

### Start Monitoring Stack

```bash
docker-compose -f docker-compose.monitoring.yml up -d

# Access Grafana
# http://your-server-ip:3000
# Default: admin / admin
```

## Step 2: Logging Configuration

### Centralized Logging with ELK Stack (Optional)

Create `docker-compose.logging.yml`:

```yaml
version: '3.8'

services:
  elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:8.10.0
    container_name: travian_elasticsearch
    environment:
      - discovery.type=single-node
      - "ES_JAVA_OPTS=-Xms512m -Xmx512m"
      - xpack.security.enabled=false
    ports:
      - "9200:9200"
    volumes:
      - elasticsearch_data:/usr/share/elasticsearch/data
    networks:
      - travian_network

  logstash:
    image: docker.elastic.co/logstash/logstash:8.10.0
    container_name: travian_logstash
    volumes:
      - ./logging/logstash.conf:/usr/share/logstash/pipeline/logstash.conf:ro
    ports:
      - "5000:5000"
    networks:
      - travian_network
    depends_on:
      - elasticsearch

  kibana:
    image: docker.elastic.co/kibana/kibana:8.10.0
    container_name: travian_kibana
    ports:
      - "5601:5601"
    environment:
      - ELASTICSEARCH_HOSTS=http://elasticsearch:9200
    networks:
      - travian_network
    depends_on:
      - elasticsearch

volumes:
  elasticsearch_data:

networks:
  travian_network:
    external: true
```

### Application Log Rotation

Create `/etc/logrotate.d/travian`:

```
/var/www/travian/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 travian travian
    sharedscripts
    postrotate
        docker compose -f /var/www/travian/docker-compose.yml exec php php -r "opcache_reset();"
    endscript
}

/var/www/travian/docker/nginx/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        docker compose -f /var/www/travian/docker-compose.yml exec nginx nginx -s reopen
    endscript
}
```

## Step 3: Performance Monitoring

### Create Performance Monitoring Script

Create `scripts/performance-check.sh`:

```bash
#!/bin/bash

echo "=== Travian Performance Report ===\"
echo "Generated: $(date)"
echo ""

# System Resources
echo "## System Resources"
echo "CPU Usage: $(top -bn1 | grep "Cpu(s)" | awk '{print $2}')%"
echo "Memory Usage: $(free -m | awk 'NR==2{printf "%.2f%%", $3*100/$2}')"
echo "Disk Usage: $(df -h / | awk 'NR==2{print $5}')"
echo ""

# Docker Containers
echo "## Docker Containers"
docker stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}"
echo ""

# MySQL
echo "## MySQL Status"
docker compose exec mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "
    SHOW STATUS LIKE 'Threads_connected';
    SHOW STATUS LIKE 'Questions';
    SHOW STATUS LIKE 'Slow_queries';
    SHOW PROCESSLIST;
"
echo ""

# Redis
echo "## Redis Status"
docker compose exec redis redis-cli INFO | grep -E "used_memory_human|connected_clients|total_commands_processed"
echo ""

# Nginx
echo "## Nginx Status"
echo "Active Connections: $(docker compose exec nginx cat /var/log/nginx/access.log | wc -l)"
echo "Requests (last hour): $(docker compose exec nginx tail -1000 /var/log/nginx/access.log | grep "$(date -d '1 hour ago' +'%d/%b/%Y:%H')" | wc -l)"
echo ""

# Application
echo "## Application Status"
echo "Total Users: $(docker compose exec mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} -D travian_global -sN -e "SELECT COUNT(*) FROM activation")"
echo "Active Sessions: $(docker compose exec redis redis-cli DBSIZE)"
```

Make executable and schedule:

```bash
chmod +x scripts/performance-check.sh

# Add to crontab
crontab -e
```

Add:

```cron
# Performance report every 6 hours
0 */6 * * * /var/www/travian/scripts/performance-check.sh >> /var/log/travian-performance.log 2>&1
```

## Step 4: Database Maintenance

### Create Database Maintenance Script

Create `scripts/db-maintenance.sh`:

```bash
#!/bin/bash

echo "Starting database maintenance: $(date)"

# Optimize tables
echo "Optimizing tables..."
docker compose exec mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "
    USE travian_global;
    OPTIMIZE TABLE gameServers, activation, configurations, banIP, email_blacklist, mailserver, passwordRecovery;
    
    USE travian_testworld;
    OPTIMIZE TABLE users, villages, alliances, marketplace;
"

# Analyze tables for query optimization
echo "Analyzing tables..."
docker compose exec mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "
    ANALYZE TABLE travian_global.gameServers;
    ANALYZE TABLE travian_testworld.users;
    ANALYZE TABLE travian_testworld.villages;
"

# Clean up old data
echo "Cleaning old data..."
docker compose exec mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "
    -- Delete old password recovery tokens (older than 24 hours)
    DELETE FROM travian_global.passwordRecovery WHERE created_at < NOW() - INTERVAL 24 HOUR;
    
    -- Delete sent emails (older than 30 days)
    DELETE FROM travian_global.mailserver WHERE status='sent' AND sent_at < NOW() - INTERVAL 30 DAY;
    
    -- Delete old IP logs (older than 90 days)
    DELETE FROM travian_testworld.log_ip WHERE time < UNIX_TIMESTAMP(NOW() - INTERVAL 90 DAY);
"

# Check for table corruption
echo "Checking table integrity..."
docker compose exec mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "
    CHECK TABLE travian_global.gameServers;
    CHECK TABLE travian_testworld.users;
"

echo "Database maintenance completed: $(date)"
```

Schedule:

```cron
# Database maintenance weekly on Sunday at 3 AM
0 3 * * 0 /var/www/travian/scripts/db-maintenance.sh >> /var/log/travian-db-maintenance.log 2>&1
```

## Step 5: Regular Maintenance Tasks

### Daily Tasks

```bash
#!/bin/bash
# Daily maintenance script

# Check disk space
DISK_USAGE=$(df -h / | tail -1 | awk '{print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 85 ]; then
    echo "WARNING: Disk usage is at ${DISK_USAGE}%" | mail -s "Disk Space Warning" admin@travian.com
fi

# Check failed containers
FAILED=$(docker compose ps | grep -c "Exit")
if [ $FAILED -gt 0 ]; then
    echo "WARNING: $FAILED container(s) failed" | mail -s "Container Failure" admin@travian.com
    docker compose up -d
fi

# Clear old logs
find /var/www/travian/storage/logs -name "*.log" -mtime +30 -delete

# Update Docker images (optional)
# docker compose pull
```

### Weekly Tasks

```bash
#!/bin/bash
# Weekly maintenance script

# Full backup
/var/www/travian/scripts/production-backup.sh

# Security updates
apt update
apt upgrade -y

# Docker cleanup
docker system prune -af --volumes --filter "until=720h"

# Generate weekly report
/var/www/travian/scripts/weekly-report.sh
```

### Monthly Tasks

```bash
#!/bin/bash
# Monthly maintenance script

# SSL certificate renewal check
certbot renew --dry-run

# Full system audit
/var/www/travian/scripts/security-audit.sh

# Performance review
/var/www/travian/scripts/performance-review.sh

# Database backup verification
/var/www/travian/scripts/verify-backups.sh
```

## Step 6: Health Checks

### Create Health Check Endpoint

Create `sections/api/include/Api/Ctrl/HealthCtrl.php`:

```php
<?php
namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Database\DB;
use Redis;

class HealthCtrl extends ApiAbstractCtrl
{
    public function check()
    {
        $health = [
            'status' => 'ok',
            'timestamp' => time(),
            'checks' => []
        ];
        
        // Database check
        try {
            $db = DB::getInstance();
            $db->query("SELECT 1");
            $health['checks']['database'] = 'ok';
        } catch (\Exception $e) {
            $health['checks']['database'] = 'error';
            $health['status'] = 'error';
        }
        
        // Redis check
        try {
            $redis = new Redis();
            $redis->connect(getenv('REDIS_HOST'), 6379);
            $redis->ping();
            $health['checks']['redis'] = 'ok';
        } catch (\Exception $e) {
            $health['checks']['redis'] = 'error';
            $health['status'] = 'error';
        }
        
        // Disk space check
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $diskPercent = ($diskFree / $diskTotal) * 100;
        $health['checks']['disk_space'] = $diskPercent > 10 ? 'ok' : 'warning';
        
        $this->response = $health;
    }
}
```

### External Monitoring

Use external services like:
- **UptimeRobot** (free): https://uptimerobot.com
- **Pingdom**: https://www.pingdom.com
- **StatusCake**: https://www.statuscake.com

Configure to monitor:
- https://travian.yourdomain.com
- https://travian.yourdomain.com/v1/health/check

## Next Steps

Continue to [09-TROUBLESHOOTING.md](09-TROUBLESHOOTING.md) for troubleshooting common issues and solutions.
