# TLS/SSL Setup Guide

## Overview

Dual-environment TLS configuration:
- **Development:** Self-signed certificates (HTTP on port 5000)
- **Production:** Let's Encrypt automated certificates (HTTPS on 443)

## Deployment Modes

### Development Mode

Start development stack with nginx exposed on port 5000:

```bash
# Explicit file loading
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# Or use convenience script
./scripts/dev.sh
```

This uses:
- `docker-compose.yml` (base configuration)
- `docker-compose.dev.yml` (development configuration - must be explicitly loaded)

Nginx exposed on port 5000 for direct access.

**Architecture:**
```
Client → Nginx (port 5000) → PHP-FPM
```

- Direct access to nginx on port 5000
- HTTP only (or HTTPS with self-signed certs)
- No WAF protection
- Convenient for local development

Access: http://localhost:5000

### Production Mode

Start production stack with WAF protection (no nginx port exposed):

```bash
# Production profile
docker compose -f docker-compose.yml --profile production up -d

# Or use convenience script
./scripts/prod.sh
```

This uses:
- `docker-compose.yml` ONLY (no dev file)
- `--profile production` enables WAF

Nginx NOT exposed to host (internal only). All traffic goes through WAF on ports 80/443.

**Architecture:**
```
Client → WAF:80/443 (ModSecurity + OWASP CRS) → Nginx:8080 (internal) → PHP-FPM
```

- All traffic goes through WAF first
- WAF performs TLS termination and proxies to nginx
- Nginx only accessible internally (no host port binding)
- Cannot bypass WAF

Access: http://your-domain.com (ports 80/443 via WAF)

**SECURITY:** Never load docker-compose.dev.yml in production. It exposes nginx port 5000, bypassing WAF protection.

### Port Mapping Summary

**Development:**
- Nginx: 5000 → 8080 (HTTP)

**Production:**
- WAF: 80 → 80 (HTTP redirect)
- WAF: 443 → 443 (HTTPS/TLS)
- Nginx: No host ports (internal only)

This ensures all production traffic is filtered through ModSecurity WAF before reaching the application.

## Development Setup

### Generate Self-Signed Certificates

```bash
./scripts/security/generate-dev-certs.sh
```

This creates:
- `certs/dev/ca.crt` - Certificate Authority
- `certs/dev/server.crt` - Server certificate
- `certs/dev/server.key` - Private key

### Start Development Server

```bash
# Explicit file loading
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# Or use convenience script
./scripts/dev.sh
```

Access via:
- HTTP: http://localhost:5000

## Production Setup

### Prerequisites

1. Domain pointing to your server
2. Email address for Let's Encrypt notifications

### Configuration

Update `.env.production`:

```bash
DOMAIN=your-domain.com
LETSENCRYPT_EMAIL=admin@your-domain.com
```

### Initial Certificate Issuance

```bash
# Start stack with production profile
docker compose --profile production up -d

# Request initial certificate
docker compose run --rm certbot
```

### Automated Renewal

Add to crontab on host:

```bash
# Renew certificates daily at 2 AM
0 2 * * * cd /path/to/project && docker compose --profile production run --rm certbot
```

Certificates renew automatically when within 30 days of expiry.

### Verification

```bash
./scripts/security/verify-certs.sh
```

## SSL Configuration

### WAF HTTPS Settings (Production Only)

In production mode, the WAF (Web Application Firewall) handles TLS termination:

- **Protocols:** TLSv1.2, TLSv1.3
- **Ciphers:** Modern, secure cipher suites
- **HSTS:** Enabled (1 year)
- **Session cache:** 10m
- **HTTP/2:** Enabled

The WAF then proxies requests to nginx over HTTP (internal network only).

### Security Headers

Applied by both WAF (production) and nginx (all modes):

- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `X-XSS-Protection`
- `Referrer-Policy`

In production, the WAF also adds:
- `Strict-Transport-Security`

## Troubleshooting

### Certificate Not Found

```bash
# Check certificate exists
docker compose exec certbot ls -la /etc/letsencrypt/live/

# Request new certificate
docker compose run --rm certbot
```

### Renewal Failed

```bash
# Check certbot logs
docker compose logs certbot

# Manual renewal
docker compose run --rm certbot certbot renew --dry-run
```

### Nginx Won't Start

```bash
# Check nginx config
docker compose exec nginx nginx -t

# Check certificate paths
docker compose exec nginx ls -la /etc/letsencrypt/live/
```

## Security Best Practices

1. **Always use HTTPS in production** - HTTP is automatically redirected to HTTPS
2. **Monitor certificate expiry** - Set up alerts for Let's Encrypt email notifications
3. **Test renewals regularly** - Use `certbot renew --dry-run` to verify the renewal process works
4. **Keep certbot updated** - Rebuild the certbot container periodically for security updates
5. **Backup certificates** - Include `/etc/letsencrypt` in your backup strategy

## Development Certificate Trust (Optional)

To avoid browser warnings when testing HTTPS locally:

### Linux

```bash
sudo cp ./certs/dev/ca.crt /usr/local/share/ca-certificates/travian-dev-ca.crt
sudo update-ca-certificates
```

### macOS

```bash
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain ./certs/dev/ca.crt
```

### Windows

1. Double-click `certs/dev/ca.crt`
2. Click "Install Certificate"
3. Select "Local Machine" → "Trusted Root Certification Authorities"

## Certificate Lifecycle

### Development Certificates
- **Validity:** 365 days
- **Renewal:** Re-run `./scripts/security/generate-dev-certs.sh`
- **Storage:** `certs/dev/` (not version controlled)

### Production Certificates
- **Validity:** 90 days (Let's Encrypt)
- **Auto-renewal:** Attempted daily via cron
- **Storage:** Docker volume `certbot_conf`
- **Backup:** Recommended for disaster recovery
