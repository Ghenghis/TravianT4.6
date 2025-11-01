# Health Check System

## Overview
All services have Docker health checks to ensure system reliability.

## Health Check Matrix

| Service | Method | Interval | Timeout | Retries | Start Period |
|---------|--------|----------|---------|---------|--------------|
| PostgreSQL | pg_isready | 10s | 5s | 5 | 10s |
| MySQL | mysqladmin ping | 10s | 5s | 5 | 10s |
| Redis | redis-cli AUTH PING | 10s | 5s | 5 | 10s |
| PHP-FPM | PHP socket test | 30s | 10s | 3 | 10s |
| Nginx | HTTP curl | 30s | 10s | 3 | 10s |
| Ollama | HTTP API | 60s | 10s | 3 | 60s |
| vLLM | HTTP API | 60s | 10s | 3 | 120s |
| Automation Worker | Heartbeat | 60s | 10s | 3 | 30s |
| AI Decision Worker | Heartbeat | 60s | 10s | 3 | 30s |
| Spawn Scheduler | Heartbeat | 60s | 10s | 3 | 60s |

## Health Check Scripts

### Database Health Checks
- **PostgreSQL:** Built-in `pg_isready` command
- **MySQL:** Built-in `mysqladmin ping` command
- **Redis:** `redis-cli` with AUTH support

### Application Health Checks
- **PHP-FPM:** Custom PHP script (`scripts/healthcheck/php-fpm-healthcheck.php`) - Tests FastCGI socket connection on port 9000
- **Nginx:** Custom bash script (`scripts/healthcheck/nginx-healthcheck.sh`) - HTTP probe to `/` endpoint
- **Ollama:** Custom bash script (`scripts/healthcheck/ollama-healthcheck.sh`) - HTTP API probe to `/api/tags`
- **vLLM:** Custom bash script (`scripts/healthcheck/vllm-healthcheck.sh`) - HTTP API probe to `/health` with fallback to `/v1/models`

### Worker Health Checks
- **Method:** Redis heartbeat TTL
- **Location:** `cli/workers/*.php`
- **Thresholds:** 700s (automation/AI), 2000s (spawn scheduler)

## Checking Service Health

### Docker Compose
```bash
# Check all services
docker compose ps

# Check specific service
docker compose ps postgres

# Expected output:
NAME                  STATUS
travian_postgres      Up 5 minutes (healthy)
```

### Manual Health Check
```bash
# PostgreSQL
docker exec travian_postgres pg_isready -U postgres

# MySQL
docker exec travian_mysql mysqladmin ping -h localhost -u root -p[PASSWORD]

# Redis
docker exec travian_redis redis-cli -a [PASSWORD] PING

# PHP-FPM
docker exec travian_app php /var/www/html/scripts/healthcheck/php-fpm-healthcheck.php

# Nginx
docker exec travian_nginx curl -f http://localhost/

# Ollama
docker exec travian_ollama curl -f http://localhost:11434/api/tags

# vLLM
docker exec travian_vllm curl -f http://localhost:8000/health

# Workers
docker exec travian_automation_worker php /var/www/html/cli/workers/automation-worker.php --health-check
```

## Troubleshooting

### Service Marked as Unhealthy
1. Check logs: `docker compose logs [service_name]`
2. Run manual health check
3. Verify dependencies are healthy
4. Check resource usage (CPU/memory)

### Health Check Timeout
- Increase `timeout` value in docker-compose.yml
- Check if service is under heavy load
- Verify network connectivity

### Health Check Never Passes
- Check `start_period` - may need to be longer
- Verify service is starting correctly
- Check for application errors in logs

## Exit Codes
- **0:** Healthy
- **1:** Unhealthy
- **Other:** Error (treated as unhealthy)
