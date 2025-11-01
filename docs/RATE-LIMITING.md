# Rate Limiting & WAF Configuration

## Rate Limiting Policies

### Tiered Rate Limits

| Endpoint | Rate Limit | Burst | Purpose |
|----------|-----------|-------|---------|
| Global | 300 req/min | 600 | Prevent general abuse |
| /v1/* (API) | 120 req/min | 120 | Protect API resources |
| /v1/auth/* | 30 req/min | 30 | Prevent brute force |
| /health | Unlimited | - | Health checks |
| /nginx_status | Unlimited | - | Monitoring (internal only) |
| /metrics/workers | Unlimited | - | Prometheus metrics |

### How It Works

Nginx uses `limit_req_zone` with shared memory to track request rates per IP address:

```nginx
limit_req_zone $binary_remote_addr zone=global:10m rate=300r/m;
limit_req_zone $binary_remote_addr zone=api:10m rate=120r/m;
limit_req_zone $binary_remote_addr zone=auth:10m rate=30r/m;
```

**Shared Memory Zones:**
- `10m` = 10 megabytes of shared memory
- Stores ~160,000 IP addresses
- Automatically evicts old entries when full

**Rate Calculation:**
- `300r/m` = 300 requests per minute
- Equivalent to ~5 requests per second
- Enforced using a leaky bucket algorithm

### Response Codes

- **200:** Request allowed
- **429:** Too Many Requests (rate limit exceeded)
- **503:** Service Temporarily Unavailable (burst exceeded)

## Configuration Files

### Development (`docker/nginx/conf.d/dev-http.conf`)
- HTTP-only configuration
- Rate limiting enabled
- Accessed via port 5000

### Production (`docker/nginx/conf.d/prod-https.conf`)
- HTTPS with automatic HTTP redirect
- Enhanced rate limiting
- Security headers enabled
- Accessed via port 443

## Monitoring Rate Limits

### Nginx Status

Access nginx status (internal networks only):

```bash
curl http://localhost:5000/nginx_status
```

Output:
```
Active connections: 291
server accepts handled requests
 16630948 16630948 31070465
Reading: 6 Writing: 179 Waiting: 106
```

### Prometheus Metrics

Rate limiting metrics exported to Prometheus via nginx exporters:
- `nginx_http_requests_total` - Total HTTP requests
- `nginx_http_request_duration_seconds` - Request latency
- `nginx_connections_active` - Active connections

### Access Logs

Rate limit violations are logged to nginx access logs:

```
192.168.1.1 - - [30/Oct/2025:12:00:00 +0000] "POST /v1/auth/login HTTP/1.1" 429 0
```

View logs:
```bash
docker compose logs nginx | grep "429"
```

## Tuning Rate Limits

### Adjust Global Limits

Edit `docker/nginx/conf.d/dev-http.conf` or `prod-https.conf`:

```nginx
# Increase global rate limit to 500 requests/min
limit_req_zone $binary_remote_addr zone=global:10m rate=500r/m;
```

### Adjust Burst Values

```nginx
# Allow bursts of up to 1000 requests before rejecting
limit_req zone=global burst=1000 nodelay;
```

**Burst Explanation:**
- `burst=600` allows temporary spikes up to 600 requests
- `nodelay` processes burst requests immediately
- Without `nodelay`, excess requests are delayed (smoothed)

### Whitelist IPs

Add IP whitelisting for trusted sources:

```nginx
geo $limit {
    default 1;
    10.0.0.0/8 0;           # Internal network
    172.16.0.0/12 0;        # Docker networks
    192.168.1.100 0;        # Trusted monitoring server
}

map $limit $limit_key {
    0 "";                    # No rate limiting
    1 $binary_remote_addr;   # Apply rate limiting
}

limit_req_zone $limit_key zone=global:10m rate=300r/m;
```

### Per-User Rate Limiting (Advanced)

For authenticated users, rate limit by user ID instead of IP:

```nginx
# In your application, set X-User-ID header
map $http_x_user_id $limit_key {
    ""      $binary_remote_addr;  # Use IP if no user ID
    default $http_x_user_id;      # Use user ID if authenticated
}

limit_req_zone $limit_key zone=api:10m rate=120r/m;
```

## DDoS Protection

### Layer 7 (Application Layer) Protection

Rate limiting provides protection against:
- **HTTP flood attacks** - Overwhelming server with requests
- **Slowloris attacks** - Slow HTTP requests to exhaust connections
- **Brute force attempts** - Password guessing on /v1/auth/

### Connection Limits

Limit concurrent connections per IP (add to nginx.conf):

```nginx
http {
    limit_conn_zone $binary_remote_addr zone=addr:10m;
    
    server {
        limit_conn addr 10;  # Max 10 concurrent connections per IP
    }
}
```

### Request Size Limits

Already configured in server blocks:

```nginx
client_max_body_size 64M;
```

### Additional Protection Layers

For production deployments, consider:

1. **Cloudflare** - Upstream DDoS protection, CDN, WAF
   - Blocks attacks before reaching your server
   - Free tier available with basic DDoS protection

2. **Fail2ban** - IP banning based on log patterns
   - Automatically blocks IPs after repeated violations
   - Integrates with nginx access logs

3. **ModSecurity WAF** - Web Application Firewall
   - OWASP Core Rule Set for common attacks
   - SQL injection, XSS protection

4. **Hardware Load Balancer** - Enterprise deployments
   - Dedicated DDoS mitigation hardware

## Rate Limit Testing

### Test Global Rate Limit

```bash
# Send 400 requests rapidly (exceeds 300 req/min limit)
for i in {1..400}; do 
    curl -s -o /dev/null -w "%{http_code}\n" http://localhost:5000/ &
done | grep -c "429"
```

Expected: ~100 requests return HTTP 429

### Test Auth Rate Limit

```bash
# Send 50 login attempts (exceeds 30 req/min limit)
for i in {1..50}; do 
    curl -s -o /dev/null -w "%{http_code}\n" \
    -X POST http://localhost:5000/v1/auth/login &
done | grep -c "429"
```

Expected: ~20 requests return HTTP 429

### Load Testing Tool (Apache Bench)

```bash
# 1000 requests, 100 concurrent connections
ab -n 1000 -c 100 http://localhost:5000/

# Review results:
# - Requests per second
# - Failed requests (429 responses)
# - Connection times
```

## Troubleshooting

### Too Many 429 Errors (Legitimate Traffic)

**Problem:** Legitimate users hitting rate limits

**Solutions:**
1. Increase rate limits for affected endpoints
2. Increase burst values to allow temporary spikes
3. Whitelist trusted IPs (monitoring, internal services)
4. Implement per-user rate limiting for authenticated users

### Rate Limiting Not Working

**Check configuration:**
```bash
docker compose exec nginx nginx -t
```

**Verify zones are defined:**
```bash
docker compose exec nginx cat /etc/nginx/conf.d/dev-http.conf | grep limit_req_zone
```

**Check logs for errors:**
```bash
docker compose logs nginx | grep -i "limit"
```

### Monitoring Endpoints Self-Limiting

**Problem:** Prometheus scraping triggers rate limits

**Solution:** Ensure monitoring endpoints have `limit_req off`:

```nginx
location /metrics/workers {
    limit_req off;  # Disable rate limiting for metrics
    # ... rest of config
}
```

### Different Limits for Dev vs Prod

**Development:** Uses `dev-http.conf`
- Lower security overhead
- Same rate limits (for testing)

**Production:** Uses `prod-https.conf`
- HTTPS enforced
- Additional security headers
- Separate rate limit zones (prevents dev/prod conflicts)

## Best Practices

1. **Start conservative** - Begin with low limits, increase based on monitoring
2. **Monitor metrics** - Track 429 responses to tune appropriately
3. **Whitelist trusted IPs** - Internal services, monitoring, CDN origin servers
4. **Log violations** - Analyze patterns to detect attacks
5. **Test before production** - Load test to verify limits don't block legitimate traffic
6. **Document changes** - Track rate limit adjustments and reasoning
7. **Layer defenses** - Combine rate limiting with WAF, CDN, and firewall rules

## Rate Limit Tuning Examples

### High-Traffic Public API
```nginx
limit_req_zone $binary_remote_addr zone=global:20m rate=1000r/m;
limit_req_zone $binary_remote_addr zone=api:20m rate=500r/m;
```

### Sensitive Authentication Endpoints
```nginx
limit_req_zone $binary_remote_addr zone=auth:10m rate=10r/m;
limit_req zone=auth burst=5 nodelay;
```

### Internal Microservices
```nginx
# Whitelist internal network, no rate limits
geo $is_internal {
    default 0;
    172.16.0.0/12 1;
}

map $is_internal $limit_key {
    1 "";                    # Internal: no limit
    0 $binary_remote_addr;   # External: rate limit
}
```

## WAF (Web Application Firewall)

### ModSecurity with OWASP CRS

Production deployment includes ModSecurity WAF with OWASP Core Rule Set for protection against:
- SQL Injection
- Cross-Site Scripting (XSS)
- Remote File Inclusion
- Command Injection
- Session Fixation
- And 100+ other attack vectors

### WAF Configuration

WAF service sits in front of nginx in production:

```
Client → WAF (ModSecurity) → Nginx → PHP-FPM
```

### Paranoia Levels

- **Paranoia 1** (default): Balanced protection, minimal false positives
- **Paranoia 2**: Stricter rules, may cause false positives
- **Paranoia 3-4**: Maximum protection, requires extensive tuning

Adjust in docker-compose.yml:

```yaml
waf:
  environment:
    - PARANOIA=2  # Increase for stricter protection
```

### Monitoring WAF

View blocked requests:

```bash
docker compose logs waf | grep "ModSecurity"
```

### Tuning WAF

Disable specific rules causing false positives:

```bash
# Add to WAF container environment
- MODSEC_RULE_EXCLUSION=rule_id_to_disable
```

### Testing WAF

```bash
# Test SQL injection protection
curl "http://localhost/?id=1' OR '1'='1"

# Test XSS protection
curl "http://localhost/?search=<script>alert('xss')</script>"
```

Both should return 403 Forbidden.

## Future Enhancements

Potential improvements for advanced use cases:

1. **Geographic rate limiting** - Different limits per region
2. **Dynamic rate limiting** - Adjust based on server load
3. **Redis-based rate limiting** - Distributed rate limiting across multiple nginx instances
4. **Custom 429 response** - Branded rate limit exceeded page
5. **Rate limit headers** - Return `X-RateLimit-*` headers to clients
