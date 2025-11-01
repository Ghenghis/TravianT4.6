# TRAVIAN T4.6 - SECRETS MANAGEMENT STRATEGY

**Version:** 1.0.0  
**Last Updated:** October 30, 2025  
**Owner:** DevOps/Security Team  
**Classification:** INTERNAL - SECURITY CRITICAL

---

## Table of Contents

1. [Overview](#overview)
2. [Secrets Architecture](#secrets-architecture)
3. [Secret Types & Classification](#secret-types--classification)
4. [Storage Locations](#storage-locations)
5. [Generation Procedures](#generation-procedures)
6. [Rotation Procedures](#rotation-procedures)
7. [Emergency Response](#emergency-response)
8. [Best Practices](#best-practices)
9. [Compliance & Audit](#compliance--audit)
10. [Troubleshooting](#troubleshooting)
11. [References](#references)

---

## Overview

### Purpose

This document establishes the comprehensive secrets management strategy for the Travian T4.6 game server infrastructure. It defines how secrets (passwords, API keys, tokens, certificates) are:

- **Generated** with cryptographically secure methods
- **Stored** in secure locations with appropriate access controls
- **Rotated** on a regular schedule with zero-downtime procedures
- **Monitored** for leaks and unauthorized access
- **Revoked** in case of compromise

### Scope

This strategy covers all secrets used in the Travian infrastructure including:

- Database credentials (PostgreSQL, MySQL, Redis)
- API keys (Brevo, OpenAI, reCAPTCHA, AWS)
- Encryption keys (JWT secrets, session keys)
- Service credentials (Grafana, Prometheus)
- SSL/TLS certificates
- SSH keys and access tokens

### Security Requirements

**Classification Levels:**

- 🔴 **CRITICAL** - Database passwords, encryption keys (rotate every 90 days)
- 🟠 **HIGH** - API keys with billing/data access (rotate every 180 days)
- 🟡 **MEDIUM** - Service credentials, monitoring (rotate every 365 days)
- 🟢 **LOW** - Non-production credentials (rotate as needed)

---

## Secrets Architecture

### Multi-Layer Security Model

```
┌─────────────────────────────────────────────────────────────┐
│                    APPLICATION LAYER                         │
│  PHP-FPM, Workers, API Services                             │
│  ↓ Reads from                                                │
├─────────────────────────────────────────────────────────────┤
│                   ENVIRONMENT LAYER                          │
│  .env.production (600 permissions, encrypted volume)        │
│  ↓ Loaded into                                               │
├─────────────────────────────────────────────────────────────┤
│                  DOCKER SECRETS LAYER                        │
│  Docker Swarm Secrets / Compose Secrets                     │
│  ↓ Mounted as                                                │
├─────────────────────────────────────────────────────────────┤
│                   STORAGE LAYER                              │
│  Encrypted Docker Volumes (AES-256)                         │
│  ↓ Optional backup to                                        │
├─────────────────────────────────────────────────────────────┤
│                  EXTERNAL VAULT (Optional)                   │
│  HashiCorp Vault, AWS Secrets Manager, Azure Key Vault     │
└─────────────────────────────────────────────────────────────┘
```

### Current Implementation

**Primary Storage:** Environment variables in `.env.production`
- **Location:** `/var/www/html/.env.production`
- **Permissions:** `600` (owner read/write only)
- **Encryption:** Docker volume encryption enabled
- **Access Control:** Limited to `www-data` user in PHP-FPM container

**Secondary Storage:** Docker Compose environment variables
- **Purpose:** Bootstrap configuration
- **Source:** `.env.production` loaded by Docker Compose
- **Scope:** Available to all containers in the stack

**Future Enhancement:** External Secrets Vault
- **Options:** HashiCorp Vault, AWS Secrets Manager, Azure Key Vault
- **Benefits:** Centralized management, audit trails, dynamic secrets
- **Timeline:** Phase 2 implementation (Q1 2026)

---

## Secret Types & Classification

### 1. Database Credentials (🔴 CRITICAL)

#### PostgreSQL (Global Database + AI-NPC System)

**Secrets:**
- `POSTGRES_PASSWORD` - Superuser password
- `PGPASSWORD` - Application connection password
- `DATABASE_URL` - Full connection string

**Classification:** 🔴 CRITICAL  
**Rotation Schedule:** Every 90 days minimum  
**Length:** 64 characters minimum  
**Format:** Base64-encoded random bytes  

**Generation Command:**
```bash
openssl rand -base64 96 | tr -d '\n' | head -c 64
```

**Rotation Script:**
```bash
./scripts/secrets/rotate-credentials.sh postgres
```

#### MySQL (Game World Databases)

**Secrets:**
- `MYSQL_ROOT_PASSWORD` - MySQL root password
- `MYSQL_PASSWORD` - Application user password

**Classification:** 🔴 CRITICAL  
**Rotation Schedule:** Every 90 days minimum  
**Length:** 64 characters minimum  

**Rotation Script:**
```bash
./scripts/secrets/rotate-credentials.sh mysql
```

#### Redis (Caching & Sessions)

**Secrets:**
- `REDIS_PASSWORD` - Redis authentication password

**Classification:** 🔴 CRITICAL  
**Rotation Schedule:** Every 90 days minimum  
**Length:** 64 characters minimum  

**Special Considerations:**
- Session invalidation upon rotation
- Cache clear may be required

**Rotation Script:**
```bash
./scripts/secrets/rotate-credentials.sh redis
```

---

### 2. API Keys (🟠 HIGH)

#### Brevo (Email Service)

**Secrets:**
- `BREVO_API_KEY` - API access key (starts with `xkeysib-`)
- `BREVO_SMTP_KEY` - SMTP authentication key

**Classification:** 🟠 HIGH  
**Rotation Schedule:** Every 180 days or when compromised  
**Obtain From:** https://app.brevo.com/settings/keys/api  

**Validation:**
```bash
curl -X GET "https://api.brevo.com/v3/account" \
  -H "api-key: ${BREVO_API_KEY}"
```

**Security Notes:**
- Monitor for unexpected email volume
- Enable IP whitelisting in Brevo dashboard
- Set rate limits to prevent abuse

#### OpenAI API

**Secrets:**
- `OPENAI_API_KEY` - OpenAI API key (starts with `sk-`)

**Classification:** 🟠 HIGH  
**Rotation Schedule:** Every 180 days or when compromised  
**Obtain From:** https://platform.openai.com/api-keys  

**Security Notes:**
- **COST CRITICAL:** Monitor usage to prevent unexpected billing
- Set spending limits in OpenAI dashboard
- Use separate keys for development/production
- Track usage per key

**Cost Monitoring:**
```bash
curl https://api.openai.com/v1/usage \
  -H "Authorization: Bearer ${OPENAI_API_KEY}"
```

#### Google reCAPTCHA

**Secrets:**
- `RECAPTCHA_PUBLIC_KEY` - Site key (starts with `6L`)
- `RECAPTCHA_PRIVATE_KEY` - Secret key (starts with `6L`)

**Classification:** 🟠 HIGH  
**Rotation Schedule:** Every 180 days or when compromised  
**Obtain From:** https://www.google.com/recaptcha/admin  

**Security Notes:**
- Bind to specific domains
- Monitor verification success rates
- Enable security preferences (challenge difficulty)

---

### 3. Encryption Keys (🔴 CRITICAL)

#### JWT Secret

**Secrets:**
- `JWT_SECRET` - JSON Web Token signing key

**Classification:** 🔴 CRITICAL  
**Rotation Schedule:** Every 365 days or immediately if compromised  
**Length:** 64 characters minimum (recommended)  

**Generation:**
```bash
openssl rand -base64 96 | tr -d '\n' | head -c 64
```

**⚠️ CRITICAL WARNING:**
Rotating JWT secret **invalidates ALL existing user sessions**. Users must re-authenticate.

**Rotation Procedure:**
1. Announce maintenance window to users
2. Run rotation script during low-traffic period
3. Monitor authentication endpoints for errors
4. Verify new tokens are issued correctly

**Rotation Script:**
```bash
./scripts/secrets/rotate-credentials.sh jwt
```

---

### 4. AWS Credentials (🔴 CRITICAL - if using S3 backups)

**Secrets:**
- `AWS_ACCESS_KEY_ID` - 20-character access key
- `AWS_SECRET_ACCESS_KEY` - 40-character secret key

**Classification:** 🔴 CRITICAL  
**Rotation Schedule:** Every 90 days minimum  

**IAM Best Practices:**
```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:ListBucket"
      ],
      "Resource": [
        "arn:aws:s3:::your-backup-bucket",
        "arn:aws:s3:::your-backup-bucket/*"
      ]
    }
  ]
}
```

**Security Requirements:**
- Use dedicated IAM user for backups only
- Enable MFA for IAM user
- Restrict to backup bucket only
- Enable CloudTrail logging
- Configure bucket encryption (AES-256 or KMS)

---

### 5. Monitoring Credentials (🟡 MEDIUM)

#### Grafana

**Secrets:**
- `GRAFANA_ADMIN_USER` - Admin username (change from default!)
- `GRAFANA_ADMIN_PASSWORD` - Admin password

**Classification:** 🟡 MEDIUM  
**Rotation Schedule:** Every 90 days minimum  
**Length:** 32 characters minimum  

**Security Best Practices:**
- Change default `admin` username
- Enable OAuth integration (Google, GitHub)
- Restrict dashboard editing permissions
- Enable audit logging

#### Prometheus

**No authentication by default - SECURE YOUR DEPLOYMENT!**

**Recommended Security:**
- Use nginx reverse proxy with basic auth
- Bind to internal network only
- Enable TLS encryption
- Use Prometheus Operator RBAC

---

## Storage Locations

### Primary Storage: Environment Files

**File:** `.env.production`  
**Location:** `/var/www/html/.env.production`  
**Permissions:** `600` (owner read/write only)  
**Owner:** `www-data:www-data`  

**Security Checklist:**
- ✅ File permissions set to 600
- ✅ Listed in `.gitignore`
- ✅ Not committed to version control
- ✅ Encrypted volume mount
- ✅ Regular backups to secure location
- ✅ Backup encryption enabled

**Validation:**
```bash
# Check permissions
ls -la .env.production
# Expected: -rw------- 1 www-data www-data

# Verify not in git
git ls-files | grep .env.production
# Expected: (no output)

# Validate configuration
./scripts/secrets/validate-env.sh .env.production
```

---

### Template Storage: Version Control

**File:** `.env.production.example`  
**Location:** `/var/www/html/.env.production.example`  
**Permissions:** `644` (world-readable)  
**Purpose:** Template for generating actual .env files  

**⚠️ CRITICAL RULES:**
- **NEVER** contain real secrets
- Use `CHANGE_ME_*` placeholders only
- Include security annotations
- Document rotation schedules
- Safe to commit to version control

---

### Docker Secrets (Optional - Swarm Mode)

**Location:** `/run/secrets/`  
**Mounted:** Read-only in containers  
**Permissions:** `400` (owner read-only)  

**Example Usage:**
```yaml
services:
  php-fpm:
    secrets:
      - postgres_password
      - mysql_password

secrets:
  postgres_password:
    file: ./secrets/postgres_password.txt
  mysql_password:
    file: ./secrets/mysql_password.txt
```

---

### Backup Storage

**Local Backups:**
- **Location:** `/var/backups/travian/secrets/`
- **Encryption:** GPG encrypted (`gpg -c`)
- **Permissions:** `600`
- **Retention:** 90 days

**Remote Backups (S3):**
- **Bucket:** `s3://your-backup-bucket/secrets/`
- **Encryption:** SSE-S3 or SSE-KMS
- **Versioning:** Enabled
- **Lifecycle:** 90-day retention, then Glacier

**Backup Command:**
```bash
# Encrypt and backup .env.production
gpg --symmetric --cipher-algo AES256 .env.production
aws s3 cp .env.production.gpg s3://your-backup-bucket/secrets/env-$(date +%Y%m%d).gpg
```

---

## Generation Procedures

### Automated Generation

**Primary Tool:** `./scripts/secrets/generate-env.sh`

**Features:**
- Generates cryptographically secure random passwords
- Prompts for user-specific values (domain, API keys)
- Validates input format
- Sets secure file permissions
- Creates backup of existing file
- Logs all actions

**Usage:**
```bash
# Generate production environment
./scripts/secrets/generate-env.sh production

# Generate development environment
./scripts/secrets/generate-env.sh development

# Generate staging environment
./scripts/secrets/generate-env.sh staging
```

**Workflow:**
1. Checks if `.env.production.example` exists
2. Prompts for confirmation if `.env` already exists
3. Creates backup: `.env.production.backup.YYYYMMDD_HHMMSS`
4. Generates secure passwords using `openssl rand`
5. Prompts for user-specific values (domain, API keys)
6. Replaces placeholders in template
7. Sets file permissions to `600`
8. Validates generated configuration
9. Creates audit log

---

### Manual Generation

**For Database Passwords (64 characters):**
```bash
openssl rand -base64 96 | tr -d '\n' | head -c 64
```

**For API Keys (variable length):**
```bash
# 32 characters
openssl rand -base64 48 | tr -d '\n' | head -c 32

# 64 characters
openssl rand -base64 96 | tr -d '\n' | head -c 64

# Hexadecimal (64 chars)
openssl rand -hex 32
```

**For Alphanumeric Tokens:**
```bash
cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 64 | head -n 1
```

---

### Password Requirements

**Minimum Complexity:**

| Secret Type | Min Length | Charset | Example |
|------------|-----------|---------|---------|
| Database Password | 64 chars | Base64 | `aB3...xYz==` |
| JWT Secret | 64 chars | Base64 | `aB3...xYz==` |
| API Key | 32 chars | Alphanumeric | `a1B2c3D4...` |
| Service Password | 32 chars | Base64 | `aB3...xYz==` |

**Prohibited Patterns:**
- ❌ Dictionary words
- ❌ Sequential characters (`123456`, `abcdef`)
- ❌ Repeated characters (`aaaaaa`, `111111`)
- ❌ Common passwords (`password`, `admin`)
- ❌ Personal information (names, dates)

---

## Rotation Procedures

### General Rotation Workflow

**Pre-Rotation Checklist:**
- [ ] Announce maintenance window to users
- [ ] Backup current `.env.production` file
- [ ] Verify backup systems are functioning
- [ ] Check disk space for logs
- [ ] Schedule during low-traffic period
- [ ] Prepare rollback procedure

**Rotation Steps:**
1. **Backup Current Configuration**
   ```bash
   cp .env.production .env.production.backup.$(date +%Y%m%d_%H%M%S)
   ```

2. **Run Rotation Script**
   ```bash
   ./scripts/secrets/rotate-credentials.sh [service]
   ```

3. **Verify New Credentials**
   ```bash
   ./scripts/secrets/validate-env.sh .env.production
   ```

4. **Test Service Connectivity**
   ```bash
   docker-compose exec php-fpm php -r "echo 'Testing...'; exit(0);"
   ```

5. **Monitor for Errors**
   ```bash
   docker-compose logs -f --tail=100
   ```

6. **Update External Systems**
   - Backup scripts
   - Monitoring tools
   - Documentation

**Post-Rotation Checklist:**
- [ ] Verify all services running correctly
- [ ] Check application logs for errors
- [ ] Test user authentication
- [ ] Verify database connections
- [ ] Update backup systems
- [ ] Document rotation in change log
- [ ] Archive old credentials securely

---

### PostgreSQL Rotation (Zero-Downtime)

**Strategy:** Blue-Green Credential Rotation

**Procedure:**
```bash
./scripts/secrets/rotate-credentials.sh postgres
```

**Manual Steps:**

**Step 1: Create Temporary Admin User**
```sql
CREATE USER postgres_new WITH PASSWORD 'NEW_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON DATABASE travian_global TO postgres_new;
```

**Step 2: Update Application Configuration**
```bash
# Update .env.production
sed -i 's/POSTGRES_PASSWORD=.*/POSTGRES_PASSWORD=NEW_PASSWORD_HERE/' .env.production
```

**Step 3: Rolling Restart**
```bash
# Restart application containers one at a time
docker-compose restart php-fpm
sleep 5
docker-compose restart worker-automation
sleep 5
docker-compose restart worker-ai-decision
```

**Step 4: Verify Connectivity**
```bash
docker exec travian_app php -r "
  \$conn = pg_connect(getenv('DATABASE_URL'));
  if (\$conn) {
    echo 'PostgreSQL connection successful\n';
  } else {
    echo 'PostgreSQL connection failed\n';
  }
"
```

**Step 5: Update Actual User Password**
```sql
ALTER USER postgres WITH PASSWORD 'NEW_PASSWORD_HERE';
```

**Step 6: Cleanup Temporary User**
```sql
DROP USER IF EXISTS postgres_new;
```

**Rollback Procedure (if needed):**
```bash
# Restore backup
cp .env.production.backup.YYYYMMDD_HHMMSS .env.production

# Restart services
docker-compose restart php-fpm
```

---

### MySQL Rotation (Zero-Downtime)

**Procedure:**
```bash
./scripts/secrets/rotate-credentials.sh mysql
```

**Manual Steps:**

**Step 1: Create Temporary Admin**
```sql
CREATE USER 'temp_admin'@'%' IDENTIFIED BY 'NEW_ROOT_PASSWORD';
GRANT ALL PRIVILEGES ON *.* TO 'temp_admin'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
```

**Step 2: Update Root Password**
```sql
ALTER USER 'root'@'localhost' IDENTIFIED BY 'NEW_ROOT_PASSWORD';
ALTER USER 'root'@'%' IDENTIFIED BY 'NEW_ROOT_PASSWORD';
FLUSH PRIVILEGES;
```

**Step 3: Update Application User**
```sql
ALTER USER 'travian_user'@'%' IDENTIFIED BY 'NEW_USER_PASSWORD';
FLUSH PRIVILEGES;
```

**Step 4: Update Configuration**
```bash
# Update .env.production
sed -i 's/MYSQL_ROOT_PASSWORD=.*/MYSQL_ROOT_PASSWORD=NEW_ROOT_PASSWORD/' .env.production
sed -i 's/MYSQL_PASSWORD=.*/MYSQL_PASSWORD=NEW_USER_PASSWORD/' .env.production
```

**Step 5: Rolling Restart**
```bash
docker-compose restart php-fpm
```

**Step 6: Cleanup**
```sql
DROP USER 'temp_admin'@'%';
FLUSH PRIVILEGES;
```

---

### Redis Rotation (TRUE Zero-Downtime with ACL)

**Automated Procedure:**
```bash
./scripts/secrets/rotate-credentials.sh redis
```

**Strategy:** ACL-based overlapping credentials (both old and new passwords valid during transition)

**Requirements:**
- **Redis 6.0+** for ACL support (recommended for production)
- **Redis 5.x or earlier** will fallback to requirepass (brief downtime < 1 second)

---

#### Method 1: ACL-Based Rotation (TRUE Zero-Downtime)

**Prerequisites:**
- Redis 6.0+ with ACL support enabled
- No rejected connections during rotation

**Manual Steps:**

**Step 1: Verify ACL Availability**
```bash
docker compose exec redis redis-cli -a "${REDIS_PASSWORD}" ACL WHOAMI
# Expected: "default" (confirms ACL support)
```

**Step 2: Add New Password (Keep Old Password Valid)**
```bash
# Generate new password
NEW_PASSWORD=$(openssl rand -base64 96 | tr -d '\n' | head -c 64)

# Add new password while keeping old password valid (OVERLAPPING)
docker compose exec redis redis-cli -a "${REDIS_PASSWORD}" \
  ACL SETUSER default ">${REDIS_PASSWORD}" ">${NEW_PASSWORD}" ~* "&*" +@all on
```

**Step 3: Verify BOTH Passwords Work**
```bash
# Test old password (should still work)
docker compose exec redis redis-cli -a "${REDIS_PASSWORD}" PING
# Expected: PONG

# Test new password (should now work)
docker compose exec redis redis-cli -a "${NEW_PASSWORD}" PING
# Expected: PONG

# ✅ OVERLAPPING CREDENTIALS ACTIVE - ZERO DOWNTIME
```

**Step 4: Persist ACL Configuration**
```bash
docker compose exec redis redis-cli -a "${NEW_PASSWORD}" ACL SAVE
# or
docker compose exec redis redis-cli -a "${NEW_PASSWORD}" CONFIG REWRITE
```

**Step 5: Update .env.production**
```bash
sed -i "s/REDIS_PASSWORD=.*/REDIS_PASSWORD=${NEW_PASSWORD}/" .env.production
```

**Step 6: Restart Application (Loads New Password)**
```bash
docker compose restart php-fpm
sleep 5
# Application now uses new password, but old password still valid
```

**Step 7: Remove Old Password**
```bash
# Remove old password (only new password valid now)
docker compose exec redis redis-cli -a "${NEW_PASSWORD}" \
  ACL SETUSER default ">${NEW_PASSWORD}" ~* "&*" +@all on
```

**Step 8: Persist Final Configuration**
```bash
docker compose exec redis redis-cli -a "${NEW_PASSWORD}" ACL SAVE
```

**Step 9: Final Verification**
```bash
# New password should work
docker compose exec redis redis-cli -a "${NEW_PASSWORD}" PING
# Expected: PONG

# Old password should fail
docker compose exec redis redis-cli -a "${OLD_PASSWORD}" PING
# Expected: (error) NOAUTH Authentication required
```

**✅ Result:** TRUE zero-downtime achieved - no rejected connections during rotation

**ACL Command Explanation:**
```bash
ACL SETUSER default      # Modify the "default" user
  >"PASSWORD1"           # Add password 1
  >"PASSWORD2"           # Add password 2 (overlapping)
  ~*                     # Access to all keys
  &*                     # Access to all pub/sub channels
  +@all                  # All commands allowed
  on                     # User enabled
```

---

#### Method 2: Requirepass Fallback (Brief Downtime < 1 Second)

**Use Case:** Redis 5.x or earlier without ACL support

**⚠️ DOWNTIME WARNING:**
- **CONFIG SET requirepass** immediately invalidates old password
- New connections fail until app restarts with new password
- **Duration:** Typically < 1 second in practice
- **Impact:** Sessions may be invalidated, some users may need to re-login

**Manual Steps:**

**Step 1: Update Redis Password (Live)**
```bash
# ⚠️ Old password immediately invalidated by this command
docker compose exec redis redis-cli -a "${OLD_PASSWORD}" \
  CONFIG SET requirepass "NEW_PASSWORD"
```

**Step 2: Persist Configuration**
```bash
docker compose exec redis redis-cli -a "NEW_PASSWORD" CONFIG REWRITE
```

**Step 3: Verify New Password**
```bash
docker compose exec redis redis-cli -a "NEW_PASSWORD" PING
# Expected: PONG
```

**Step 4: Update .env.production**
```bash
sed -i 's/REDIS_PASSWORD=.*/REDIS_PASSWORD=NEW_PASSWORD/' .env.production
```

**Step 5: Restart Application**
```bash
docker compose restart php-fpm
sleep 3
```

**Mitigation Strategies (for requirepass):**
1. **Schedule rotations during low-traffic periods** (e.g., 2-4 AM)
2. **Use connection pooling** to minimize new connections during rotation
3. **Monitor connection errors** and retry failed connections
4. **Consider upgrading to Redis 6.0+** for ACL support

---

#### Comparison: ACL vs Requirepass

| Feature | ACL (Redis 6.0+) | Requirepass (Redis 5.x) |
|---------|------------------|-------------------------|
| **Downtime** | TRUE zero downtime | < 1 second downtime |
| **Overlapping Credentials** | ✅ Yes (multiple passwords) | ❌ No (single password) |
| **Connection Failures** | ✅ None | ⚠️ Brief window |
| **Session Impact** | ✅ No impact | ⚠️ May invalidate sessions |
| **Production Ready** | ✅ Recommended | ⚠️ Use with caution |
| **Complexity** | Medium | Low |

**Recommendation:** **Upgrade to Redis 6.0+ for production environments** to enable true zero-downtime rotation with ACL overlapping credentials.

---

### JWT Secret Rotation (User Re-Authentication Required)

**Procedure:**
```bash
./scripts/secrets/rotate-credentials.sh jwt
```

**Manual Steps:**

**Step 1: Announce Maintenance**
```bash
# Send notification to users
# Update status page
# Schedule during low-traffic period
```

**Step 2: Generate New Secret**
```bash
NEW_SECRET=$(openssl rand -base64 96 | tr -d '\n' | head -c 64)
```

**Step 3: Update Configuration**
```bash
sed -i "s/JWT_SECRET=.*/JWT_SECRET=${NEW_SECRET}/" .env.production
```

**Step 4: Restart Services**
```bash
docker-compose restart php-fpm
```

**Step 5: Monitor Authentication**
```bash
docker-compose logs -f php-fpm | grep -i "jwt\|auth"
```

**⚠️ CRITICAL IMPACT:**
- All existing JWT tokens invalidated
- All users forced to re-authenticate
- API clients must re-authenticate

---

### API Key Rotation

**Brevo API Key:**

**Step 1: Create New Key**
1. Login to Brevo dashboard: https://app.brevo.com
2. Navigate to Settings → API Keys
3. Create new API key with descriptive name
4. Copy new key (displayed only once)

**Step 2: Update Configuration**
```bash
sed -i 's/BREVO_API_KEY=.*/BREVO_API_KEY=NEW_KEY_HERE/' .env.production
```

**Step 3: Test Email Delivery**
```bash
docker-compose exec php-fpm php -r "
  // Test email sending with new key
  // Verify delivery success
"
```

**Step 4: Revoke Old Key**
1. Return to Brevo dashboard
2. Delete old API key
3. Verify application still functional

**OpenAI API Key:**

**Step 1: Create New Key**
1. Login: https://platform.openai.com/api-keys
2. Create new secret key
3. Copy key (shown only once)

**Step 2: Update Configuration**
```bash
sed -i 's/OPENAI_API_KEY=.*/OPENAI_API_KEY=NEW_KEY_HERE/' .env.production
```

**Step 3: Test API Access**
```bash
curl https://api.openai.com/v1/models \
  -H "Authorization: Bearer ${OPENAI_API_KEY}"
```

**Step 4: Revoke Old Key**
1. Return to OpenAI dashboard
2. Delete old key
3. Monitor usage to confirm migration

---

## Emergency Response

### Leak Detection & Response

**Immediate Actions (Within 1 Hour):**

1. **Confirm the Leak**
   - Verify which secrets were exposed
   - Determine exposure duration
   - Identify access scope (public/private)

2. **Contain the Breach**
   ```bash
   # Run audit script
   ./scripts/secrets/audit-secrets.sh
   
   # Check git history
   git log --all --full-history -- ".env*"
   
   # Search for commits with secrets
   git log -p | grep -i "password\|secret\|key" | head -50
   ```

3. **Rotate Compromised Credentials**
   ```bash
   # Rotate ALL potentially compromised secrets
   ./scripts/secrets/rotate-credentials.sh all
   ```

4. **Revoke API Keys**
   - Brevo: Delete key from dashboard
   - OpenAI: Revoke key immediately
   - AWS: Deactivate access key
   - reCAPTCHA: Regenerate site keys

---

### Git History Cleanup

**If secrets were committed to git:**

**⚠️ DESTRUCTIVE OPERATION - COORDINATE WITH TEAM**

**Option 1: BFG Repo-Cleaner (Recommended)**
```bash
# Install BFG
wget https://repo1.maven.org/maven2/com/madgag/bfg/1.14.0/bfg-1.14.0.jar

# Remove .env files from history
java -jar bfg-1.14.0.jar --delete-files .env

# Clean up
git reflog expire --expire=now --all
git gc --prune=now --aggressive

# Force push (coordinate with team!)
git push --force --all
```

**Option 2: git filter-branch**
```bash
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch .env .env.production .env.local" \
  --prune-empty --tag-name-filter cat -- --all

git push --force --all
```

**Post-Cleanup:**
- Notify all team members to re-clone repository
- Rotate ALL secrets that were in git history
- Update CI/CD pipelines
- Document incident

---

### GitHub Leak Response

**If leaked to GitHub:**

1. **Remove from Repository**
   ```bash
   git rm .env.production
   git commit -m "Remove leaked secrets"
   git push
   ```

2. **Clean History** (see above)

3. **Rotate Immediately**
   ```bash
   ./scripts/secrets/rotate-credentials.sh all
   ```

4. **Monitor for Abuse**
   - Check database access logs
   - Monitor API usage (OpenAI, Brevo)
   - Review AWS CloudTrail
   - Check for unauthorized logins

5. **Enable GitHub Secret Scanning**
   - Settings → Security → Secret scanning
   - Review detected secrets
   - Enable push protection

---

### Incident Severity Levels

**🔴 CRITICAL (P0) - Immediate Action Required:**
- Database passwords exposed publicly
- AWS credentials leaked
- Production secrets in public repository
- Active exploitation detected

**Response Time:** < 15 minutes  
**Rotation:** Immediate, all affected credentials

**🟠 HIGH (P1) - Urgent Action Needed:**
- API keys exposed in logs
- Secrets in private repository (team access)
- Non-production database passwords exposed

**Response Time:** < 1 hour  
**Rotation:** Within 4 hours

**🟡 MEDIUM (P2) - Prompt Action Needed:**
- Secrets in development environment
- Weak passwords detected
- Expired credentials still active

**Response Time:** < 24 hours  
**Rotation:** Within 7 days

**🟢 LOW (P3) - Routine Maintenance:**
- Scheduled rotation
- Best practice improvements
- Documentation updates

**Response Time:** < 7 days  
**Rotation:** Per schedule

---

### Communication Templates

**Critical Leak Notification (Internal):**
```
SUBJECT: [SECURITY INCIDENT] Credential Leak Detected

SEVERITY: P0 - CRITICAL
DETECTED: YYYY-MM-DD HH:MM UTC
STATUS: Active Response

AFFECTED SYSTEMS:
- PostgreSQL production database
- [Other affected systems]

ACTIONS TAKEN:
1. Credentials rotated immediately
2. Access logs reviewed
3. Monitoring for suspicious activity

NEXT STEPS:
- Complete rotation of all credentials
- Review access patterns
- Incident report by EOD

CONTACT: security@example.com
```

**User Notification (External - if required):**
```
SUBJECT: Scheduled Maintenance - Password Reset Required

Dear Travian Players,

We are performing scheduled security maintenance on our servers. 
As a precautionary measure, all user sessions have been invalidated.

Please log in again with your existing credentials.

Timeline:
- Start: YYYY-MM-DD HH:MM UTC
- Expected Duration: 30 minutes
- Status: https://status.example.com

We apologize for any inconvenience.

Travian Team
```

---

## Best Practices

### Development Workflow

**1. Local Development**
```bash
# Use .env.local for local development
cp .env.production.example .env.local

# Generate development secrets (shorter, clearly marked)
./scripts/secrets/generate-env.sh development

# Never commit .env.local
git status | grep .env.local  # Should see nothing
```

**2. Environment Separation**

| Environment | File | Secrets | Purpose |
|-------------|------|---------|---------|
| Production | `.env.production` | Real, strong | Live system |
| Staging | `.env.staging` | Real, medium | Pre-production testing |
| Development | `.env.development` | Fake, weak | Local development |
| Testing | `.env.test` | Mock values | Automated tests |

**3. Git Hygiene**
```bash
# Before every commit
git status | grep -E '\.env$|\.env\..*$'  # Should be empty

# Pre-commit hook (.git/hooks/pre-commit)
#!/bin/bash
if git diff --cached --name-only | grep -qE '\.env$|\.env\..*$'; then
  echo "ERROR: Attempting to commit .env file!"
  echo "This is prohibited for security reasons."
  exit 1
fi
```

---

### Access Control

**Principle of Least Privilege:**

| Role | PostgreSQL | MySQL | Redis | API Keys | Rotate |
|------|-----------|-------|-------|----------|--------|
| Admin | Full | Full | Full | Read/Write | Yes |
| Developer | Read | Read | Read | Read-only | No |
| Support | No Access | Read | No Access | No Access | No |
| Automated | App User | App User | Read/Write | No Access | No |

**SSH Key Management:**
```bash
# Generate deployment key
ssh-keygen -t ed25519 -C "deployment@travian" -f deploy_key

# Set restrictive permissions
chmod 600 deploy_key
chmod 644 deploy_key.pub

# Use with ssh-agent
eval "$(ssh-agent -s)"
ssh-add deploy_key
```

---

### Monitoring & Alerting

**Metrics to Monitor:**

1. **Failed Authentication Attempts**
   ```bash
   # PostgreSQL
   SELECT * FROM pg_stat_database WHERE numbackends > 100;
   
   # MySQL
   SHOW STATUS WHERE Variable_name = 'Aborted_connects';
   ```

2. **API Usage Anomalies**
   - OpenAI: Unusual token consumption
   - Brevo: Spike in email volume
   - AWS S3: Unexpected data transfer

3. **File System Monitoring**
   ```bash
   # Monitor .env file access
   auditctl -w /var/www/html/.env.production -p rwa -k env_access
   
   # View audit logs
   ausearch -k env_access
   ```

**Alerting Rules (Prometheus):**
```yaml
groups:
  - name: secrets
    rules:
      - alert: HighFailedAuthRate
        expr: rate(auth_failures_total[5m]) > 10
        annotations:
          summary: "High authentication failure rate detected"
          
      - alert: EnvFileModified
        expr: changes(node_filefd_allocated{file=".env.production"}[5m]) > 0
        annotations:
          summary: ".env.production file was modified"
```

---

### Backup & Recovery

**Backup Strategy:**

**Daily Encrypted Backups:**
```bash
#!/bin/bash
# scripts/backup-secrets.sh

BACKUP_DIR="/var/backups/travian/secrets"
DATE=$(date +%Y%m%d_%H%M%S)

# Create encrypted backup
gpg --symmetric --cipher-algo AES256 \
  --passphrase-file /root/.backup-passphrase \
  .env.production

# Move to backup directory
mv .env.production.gpg "$BACKUP_DIR/env-${DATE}.gpg"

# Upload to S3 (optional)
aws s3 cp "$BACKUP_DIR/env-${DATE}.gpg" \
  s3://your-backup-bucket/secrets/ \
  --storage-class GLACIER

# Cleanup old backups (keep 90 days)
find "$BACKUP_DIR" -name "env-*.gpg" -mtime +90 -delete
```

**Recovery Procedure:**
```bash
# List available backups
aws s3 ls s3://your-backup-bucket/secrets/

# Download backup
aws s3 cp s3://your-backup-bucket/secrets/env-20251030_120000.gpg /tmp/

# Decrypt
gpg --decrypt --passphrase-file /root/.backup-passphrase \
  /tmp/env-20251030_120000.gpg > .env.production

# Verify
./scripts/secrets/validate-env.sh .env.production

# Restart services
docker-compose restart
```

---

### Security Hardening

**File System Security:**
```bash
# Set immutable flag (prevents accidental deletion)
chattr +i .env.production

# To modify, remove immutable flag first
chattr -i .env.production

# Edit...
# Then re-apply
chattr +i .env.production
```

**Docker Security:**
```yaml
# docker-compose.yml
services:
  php-fpm:
    # Use secrets instead of environment variables (Swarm mode)
    secrets:
      - postgres_password
      - mysql_password
    
    # Drop all capabilities except required
    cap_drop:
      - ALL
    cap_add:
      - NET_BIND_SERVICE
    
    # Read-only root filesystem
    read_only: true
    tmpfs:
      - /tmp
      - /var/run
```

**Environment Variable Security:**
```php
// Don't log environment variables
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// Clear sensitive env vars after use
putenv('POSTGRES_PASSWORD');
unset($_ENV['POSTGRES_PASSWORD']);

// Use PHP-FPM's clear_env setting
// php-fpm.conf:
clear_env = yes
```

---

## Compliance & Audit

### Audit Trail Requirements

**Log All Secret Access:**
```bash
# Enable audit logging
auditctl -w /var/www/html/.env.production -p rwa -k secrets_access

# View audit logs
ausearch -k secrets_access -ts recent

# Generate audit report
aureport -k secrets_access --start today
```

**Rotation Audit Log:**
```
/var/log/travian/secrets-rotation.log

Format:
[TIMESTAMP] [USER] [ACTION] [SECRET_TYPE] [STATUS] [NOTES]

Example:
[2025-10-30 14:30:00] admin ROTATE postgres SUCCESS "Scheduled 90-day rotation"
[2025-10-30 14:35:00] admin ROTATE mysql SUCCESS "Scheduled 90-day rotation"
[2025-10-30 15:00:00] admin ROTATE jwt FAILED "Service restart failed"
```

---

### Compliance Frameworks

**GDPR Compliance:**
- ✅ Encrypt personal data (database passwords protect user data)
- ✅ Maintain audit trail of access
- ✅ Breach notification procedures
- ✅ Data retention policies (90-day backup retention)

**PCI DSS (if accepting payments):**
- ✅ Requirement 3: Protect stored cardholder data
- ✅ Requirement 7: Restrict access by business need-to-know
- ✅ Requirement 8: Identify and authenticate access
- ✅ Requirement 10: Track and monitor all access

**SOC 2 Type II:**
- ✅ CC6.1: Logical and physical access controls
- ✅ CC6.6: Encryption of sensitive data
- ✅ CC7.2: System monitoring
- ✅ A1.2: Confidentiality controls

---

### Regular Audit Schedule

**Weekly:**
- [ ] Run `./scripts/secrets/audit-secrets.sh`
- [ ] Review failed authentication logs
- [ ] Check `.gitignore` compliance

**Monthly:**
- [ ] Review access logs
- [ ] Validate backup encryption
- [ ] Test recovery procedures
- [ ] Update documentation

**Quarterly:**
- [ ] Rotate database passwords (every 90 days)
- [ ] Rotate service credentials
- [ ] Security training for team
- [ ] Penetration testing

**Annually:**
- [ ] Comprehensive security audit
- [ ] Rotate all API keys
- [ ] Review and update policies
- [ ] Disaster recovery drill

---

## Troubleshooting

### Common Issues

**1. Application Can't Connect to Database After Rotation**

**Symptoms:**
- Application errors: "Connection refused"
- Logs show authentication failures

**Solution:**
```bash
# Verify password is updated in .env
grep POSTGRES_PASSWORD .env.production

# Test database connection
docker exec travian_postgres psql -U postgres -d travian_global -c "SELECT 1;"

# Restart application
docker-compose restart php-fpm

# Check logs
docker-compose logs php-fpm | tail -50
```

---

**2. JWT Tokens Not Working After Rotation**

**Symptoms:**
- Users getting "Invalid token" errors
- API requests failing with 401

**Solution:**
```bash
# This is EXPECTED behavior - JWT secret rotation invalidates all tokens

# Verify new secret is loaded
docker-compose exec php-fpm php -r "echo getenv('JWT_SECRET');"

# Users must re-authenticate
# Send notification to users
```

---

**3. Backup Restore Fails**

**Symptoms:**
- Cannot decrypt backup file
- Wrong passphrase error

**Solution:**
```bash
# Verify backup file integrity
file env-20251030_120000.gpg
# Expected: GPG encrypted data

# Try decryption with verbose output
gpg --decrypt --verbose env-20251030_120000.gpg

# If passphrase file is wrong, use manual passphrase
gpg --decrypt env-20251030_120000.gpg > .env.production

# Verify restored file
./scripts/secrets/validate-env.sh .env.production
```

---

**4. Scripts Failing with "Permission Denied"**

**Symptoms:**
- `bash: ./script.sh: Permission denied`

**Solution:**
```bash
# Make scripts executable
chmod +x scripts/secrets/*.sh

# Verify permissions
ls -la scripts/secrets/
# Expected: -rwxr-xr-x

# Run script
./scripts/secrets/generate-env.sh
```

---

### Emergency Contacts

**Security Incidents:**
- **Primary:** security@example.com
- **On-Call:** +1-555-0100
- **Escalation:** cto@example.com

**External Resources:**
- **GitHub Security:** https://github.com/security
- **AWS Support:** https://console.aws.amazon.com/support/
- **Have I Been Pwned:** https://haveibeenpwned.com/

---

## References

### Documentation

**Internal:**
- [Deployment Guide](./DEPLOYMENT.md)
- [Architecture Overview](./ARCHITECTURE.md)
- [Disaster Recovery](./DISASTER-RECOVERY.md)

**External:**
- [OWASP Secrets Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_CheatSheet.html)
- [NIST Password Guidelines](https://pages.nist.gov/800-63-3/)
- [Docker Secrets Documentation](https://docs.docker.com/engine/swarm/secrets/)

---

### Tools

**Secrets Management:**
- [HashiCorp Vault](https://www.vaultproject.io/)
- [AWS Secrets Manager](https://aws.amazon.com/secrets-manager/)
- [Azure Key Vault](https://azure.microsoft.com/en-us/services/key-vault/)

**Scanning & Detection:**
- [git-secrets](https://github.com/awslabs/git-secrets)
- [truffleHog](https://github.com/trufflesecurity/trufflehog)
- [GitGuardian](https://www.gitguardian.com/)

**Encryption:**
- [OpenSSL](https://www.openssl.org/)
- [GnuPG](https://gnupg.org/)
- [Age](https://github.com/FiloSottile/age)

---

## Changelog

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0.0 | 2025-10-30 | DevOps Team | Initial comprehensive secrets management strategy |

---

## Approval

**Reviewed By:**
- [ ] Security Team Lead
- [ ] DevOps Manager
- [ ] CTO

**Approved By:**
- [ ] CISO
- [ ] Legal Counsel (for compliance)

**Next Review Date:** 2026-01-30

---

**Document Classification:** INTERNAL - SECURITY CRITICAL  
**Distribution:** DevOps, Security, Engineering Leadership Only  
**Retention:** Permanent (update annually)

---

*End of Secrets Management Strategy Document*
