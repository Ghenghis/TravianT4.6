# Logging Infrastructure Documentation

## Overview

TravianT4.6 uses centralized structured logging with Loki, Promtail, and JSON-formatted logs.

## Architecture

```
[PHP App] → JSON logs → /var/log/travian/app.log
                            ↓
[Nginx] → access/error logs → /var/log/nginx/
                            ↓
                     [Promtail] → [Loki] → [Grafana]
```

## Log Levels

| Level | Usage | Examples |
|-------|-------|----------|
| **DEBUG** | Detailed debugging | Variable values, execution flow |
| **INFO** | Normal operations | Request start/end, successful actions |
| **WARNING** | Unexpected but handled | Failed login attempts, rate limits |
| **ERROR** | Errors requiring attention | Database errors, API failures |
| **CRITICAL** | System-critical failures | Data corruption, security breaches |

## Structured Logging

All application logs are JSON-formatted with standard fields:

```json
{
  "level": "info",
  "message": "REQUEST_START",
  "context": {
    "request_id": "a1b2c3d4e5f6...",
    "user_id": 123,
    "session_id": "abc123",
    "ip": "192.168.1.100",
    "timestamp": "2025-10-30T12:00:00+00:00",
    "method": "POST",
    "uri": "/v1/auth/login"
  }
}
```

## Usage

### PHP Application

```php
use App\Logging\Logger;

// Basic logging
Logger::logWithContext('info', 'User action', [
    'action' => 'update_profile',
    'user_id' => 123
]);

// Security event
Logger::logSecurityEvent('failed_login', [
    'username' => 'john@example.com',
    'reason' => 'invalid_password'
]);

// Custom logger
$logger = Logger::getInstance();
$logger->info('Processing batch', ['batch_id' => 456]);
```

### Correlation IDs

Request IDs are automatically generated and propagated:

1. Nginx adds `X-Request-ID` header (if not present)
2. PHP reads `$_SERVER['HTTP_X_REQUEST_ID']`
3. All logs include `request_id` in context
4. Use same ID in audit trail and external calls

## Log Retention

| Type | Hot Storage | Warm Storage | Cold Storage | Total |
|------|-------------|--------------|--------------|-------|
| Application Logs | 30 days (Loki) | 180 days (S3) | - | 210 days |
| Nginx Access | 30 days | 90 days | - | 120 days |
| Audit Events | 365 days (DB) | - | Archive | 365+ days |
| Security Logs | 90 days | 270 days | - | 360 days |

## Querying Logs

### Loki (Grafana)

**By request ID:**
```logql
{job="travian_app"} |= "a1b2c3d4e5f6"
```

**Failed authentications:**
```logql
{job="travian_app",level="warning"} | json | event_type="authentication" | success="false"
```

**Error rate:**
```logql
rate({job="travian_app",level="error"}[5m])
```

### Audit Trail (SQL)

**Recent critical events:**
```sql
SELECT * FROM audit_events 
WHERE severity = 'critical' 
ORDER BY created_at DESC 
LIMIT 100;
```

**User actions:**
```sql
SELECT * FROM audit_events 
WHERE actor_id = '123' 
AND created_at > NOW() - INTERVAL '24 hours'
ORDER BY created_at DESC;
```

**Failed logins:**
```sql
SELECT * FROM audit_events 
WHERE event_type = 'authentication' 
AND details->>'success' = 'false'
AND created_at > NOW() - INTERVAL '1 hour';
```

## Deployment

### Start Logging Stack

```bash
# Start with logging profile
docker compose -f docker-compose.yml \
               -f docker-compose.logging.yml \
               --profile logging \
               up -d

# Verify
curl http://localhost:3100/ready  # Loki
curl http://localhost:9080/ready  # Promtail
```

### View Logs in Grafana

1. Navigate to Grafana: http://localhost:3000
2. Go to Explore
3. Select Loki datasource
4. Run LogQL query
5. View results

## Monitoring

### Log Volume

Monitor log ingestion rates in Grafana:

```promql
rate(loki_distributor_lines_received_total[5m])
```

### Error Rates

```logql
sum(rate({job="travian_app",level="error"}[5m])) by (level)
```

### Top Error Messages

```logql
topk(10, sum by (message) (count_over_time({job="travian_app",level="error"}[24h])))
```

## Troubleshooting

### Logs not appearing in Loki

1. Check Promtail status:
   ```bash
   docker logs travian_promtail
   ```

2. Verify log file exists:
   ```bash
   ls -la /var/log/travian/app.log
   ```

3. Check Promtail positions:
   ```bash
   cat /tmp/positions.yaml
   ```

4. Test Loki connection:
   ```bash
   curl http://localhost:3100/ready
   ```

### High disk usage

1. Check retention settings in loki-config.yml
2. Verify log rotation working:
   ```bash
   ls -lh /var/log/travian/
   ```

3. Clean old logs manually:
   ```bash
   find /var/log/travian/ -name "*.log.*" -mtime +30 -delete
   ```

## Best Practices

1. **Always include request_id** for correlation
2. **Use appropriate log levels** - Don't log everything as ERROR
3. **Include context** - User ID, session ID, IP address
4. **Sanitize sensitive data** - No passwords, tokens, credit cards
5. **Use structured logging** - JSON format for machine parsing
6. **Set meaningful messages** - Use constants like "REQUEST_START"
7. **Log exceptions** - Include stack traces for errors
8. **Performance** - Log after response sent (async)

## Security Considerations

**Never log:**
- Passwords (plaintext or hashed)
- API keys or tokens
- Credit card numbers
- Personal identification numbers
- Social security numbers

**Always log:**
- Authentication attempts
- Authorization failures
- Data modifications
- Admin actions
- Security events

## Migration Guide

### From Legacy Logging

```php
// OLD (unstructured)
error_log("User login failed for user ID: " . $userId);

// NEW (structured)
Logger::logWithContext('warning', 'LOGIN_FAILED', [
    'user_id' => $userId,
    'reason' => 'invalid_password'
]);
```

### Adding Correlation IDs

```php
// Add to existing code
if (!isset($_SERVER['HTTP_X_REQUEST_ID'])) {
    $_SERVER['HTTP_X_REQUEST_ID'] = bin2hex(random_bytes(16));
}
```

---

**Last Updated:** 2025-10-30
**Owner:** Infrastructure Team
**Review:** Quarterly
