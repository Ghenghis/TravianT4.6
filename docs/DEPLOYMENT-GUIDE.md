# Deployment Guide - TravianT4.6

## Overview

This guide provides step-by-step instructions for deploying the TravianT4.6 production environment on Windows 11 with WSL2. By following this guide, you will provision a fully functional game server with AI-powered NPCs, dual-GPU LLM support, comprehensive monitoring, and automated backups.

**What This Guide Covers:**
- Windows 11/WSL2 environment setup
- Docker Compose deployment with all services
- Database initialization (PostgreSQL + MySQL)
- TLS/SSL certificate configuration
- Production monitoring and logging
- Post-deployment verification and testing

**Target Audience:** System administrators, DevOps engineers, or operators deploying to production

**Estimated Time:** 2-4 hours for first-time deployment

---

## Prerequisites

### Hardware Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| **OS** | Windows 11 Pro | Windows 11 Pro/Enterprise |
| **CPU** | 8 cores | 12+ cores (Ryzen 9 / Intel i9) |
| **RAM** | 32 GB | 64+ GB |
| **Storage** | 500 GB SSD | 1 TB NVMe SSD |
| **GPU 1** | NVIDIA RTX 3090 Ti | NVIDIA RTX 3090 Ti / 4090 |
| **GPU 2** | NVIDIA Tesla P40 | NVIDIA Tesla P40 / A100 |

### Software Requirements

- **Windows 11 Pro/Enterprise** (Home edition does not support WSL2 fully)
- **WSL2** installed and enabled
- **Docker Desktop for Windows** 4.25.0 or later
- **Git for Windows** (for repository cloning)
- **NVIDIA drivers** 535.xx or later (for GPU support)

**Validation Commands:**

```bash
# Check Windows version
winver

# Verify WSL2 installation
wsl --list --verbose

# Check Docker Desktop
docker --version
docker compose version

# Verify GPU drivers (run in WSL2)
nvidia-smi
```

---

## Environment Setup

### Step 1: Install WSL2

If not already installed, enable WSL2 on Windows 11:

```powershell
# Run as Administrator in PowerShell
wsl --install -d Ubuntu-22.04

# Set WSL2 as default
wsl --set-default-version 2

# Verify installation
wsl --list --verbose
```

**Expected Output:**
```
  NAME                   STATE           VERSION
* Ubuntu-22.04          Running         2
```

### Step 2: Configure Docker Desktop

1. Open **Docker Desktop** → Settings
2. Navigate to **Resources** → **WSL Integration**
3. Enable integration with **Ubuntu-22.04**
4. Navigate to **Resources** → **Advanced**
   - Memory: Allocate at least 16 GB (32 GB recommended)
   - CPUs: Allocate at least 8 CPUs
   - Swap: 4 GB minimum
5. Apply and restart Docker Desktop

**Validate Docker in WSL2:**

```bash
# Open Ubuntu-22.04 terminal
wsl -d Ubuntu-22.04

# Test Docker
docker run hello-world

# Should output: "Hello from Docker!"
```

### Step 3: Install NVIDIA Container Toolkit (GPU Support)

**Important:** This step enables Docker containers to access NVIDIA GPUs.

```bash
# Inside WSL2 Ubuntu terminal

# Add NVIDIA Container Toolkit repository
distribution=$(. /etc/os-release;echo $ID$VERSION_ID)
curl -fsSL https://nvidia.github.io/libnvidia-container/gpgkey | sudo gpg --dearmor -o /usr/share/keyrings/nvidia-container-toolkit-keyring.gpg
curl -s -L https://nvidia.github.io/libnvidia-container/$distribution/libnvidia-container.list | \
    sed 's#deb https://#deb [signed-by=/usr/share/keyrings/nvidia-container-toolkit-keyring.gpg] https://#g' | \
    sudo tee /etc/apt/sources.list.d/nvidia-container-toolkit.list

# Install toolkit
sudo apt-get update
sudo apt-get install -y nvidia-container-toolkit

# Configure Docker to use NVIDIA runtime
sudo nvidia-ctk runtime configure --runtime=docker

# Restart Docker (in Docker Desktop → Settings → Restart)
```

**Verify GPU Access:**

```bash
# Test GPU access from Docker
docker run --rm --gpus all nvidia/cuda:12.0.0-base-ubuntu22.04 nvidia-smi

# Should display GPU information for both RTX 3090 Ti and Tesla P40
```

---

## Repository Setup

### Step 1: Clone Repository

```bash
# Navigate to your projects directory
cd /home/$USER/projects

# Clone repository
git clone https://github.com/your-org/TravianT4.6.git
cd TravianT4.6

# Verify directory structure
ls -la
```

**Expected Structure:**
```
sections/       # PHP backend API
angularIndex/   # Angular frontend
docker/         # Docker configurations
database/       # SQL schemas
scripts/        # Deployment scripts
docs/           # Documentation
grafana/        # Monitoring dashboards
prometheus/     # Monitoring config
```

### Step 2: Configure Environment Variables

```bash
# Generate production .env file
./scripts/secrets/generate-env.sh production

# This creates .env.production with secure random secrets
# Edit the file to customize:
nano .env.production
```

**Required Configuration:**

| Variable | Description | Example |
|----------|-------------|---------|
| `DOMAIN` | Your production domain | `game.example.com` |
| `LETSENCRYPT_EMAIL` | Email for SSL cert notifications | `admin@example.com` |
| `POSTGRES_PASSWORD` | PostgreSQL root password | (auto-generated) |
| `MYSQL_ROOT_PASSWORD` | MySQL root password | (auto-generated) |
| `REDIS_PASSWORD` | Redis password | (auto-generated) |
| `GRAFANA_ADMIN_PASSWORD` | Grafana admin password | (auto-generated) |

**Validate Configuration:**

```bash
# Run validation script
./scripts/secrets/validate-env.sh .env.production

# Should output: "✅ All required variables are set"
```

### Step 3: Copy Environment File

```bash
# Copy to .env for Docker Compose
cp .env.production .env

# Verify
cat .env | head -20
```

---

## Docker Compose Deployment

### Step 1: Deploy Core Services

The core stack includes: Nginx, PHP-FPM, PostgreSQL, MySQL, Redis, Ollama, vLLM.

```bash
# Pull latest images
docker compose pull

# Start core services
docker compose up -d

# Verify services are starting
docker compose ps
```

**Expected Output:**
```
NAME                    STATUS          PORTS
travian_nginx           Up 10 seconds   0.0.0.0:80->80/tcp, 0.0.0.0:443->443/tcp
travian_app             Up 10 seconds   
travian_postgres        Up (healthy)    5432/tcp
travian_mysql           Up (healthy)    3306/tcp
travian_redis           Up (healthy)    6379/tcp
travian_ollama          Up 10 seconds   11434/tcp
travian_vllm            Up 10 seconds   8000/tcp
```

**Wait for Health Checks:**

```bash
# Monitor health status (wait until all show "healthy")
watch -n 2 'docker compose ps'

# Should take 1-2 minutes for all services to become healthy
```

### Step 2: Deploy Background Workers

```bash
# Start worker services
docker compose -f docker-compose.workers.yml up -d

# Verify workers
docker compose -f docker-compose.workers.yml ps
```

**Expected Workers:**
- `travian_automation_worker` - Handles scheduled automation tasks
- `travian_ai_decision_worker` - Processes AI decision requests
- `travian_spawn_scheduler_worker` - Manages NPC spawning batches

### Step 3: Deploy Monitoring Stack (Optional but Recommended)

```bash
# Start monitoring services
docker compose --profile monitoring up -d

# Verify monitoring
docker ps | grep -E "prometheus|grafana|exporter"
```

**Access Monitoring:**
- **Grafana**: http://localhost:3000 (admin / `$GRAFANA_ADMIN_PASSWORD`)
- **Prometheus**: http://localhost:9090

### Step 4: Deploy Logging Stack (Optional)

```bash
# Start logging services
docker compose --profile logging up -d

# Verify Loki and Promtail
docker ps | grep -E "loki|promtail"
```

**Access Logging:**
- **Loki**: http://localhost:3100 (via Grafana datasource)

### Step 5: Deploy Maintenance/Backup Container (Optional)

```bash
# Start maintenance container for automated backups
docker compose --profile maintenance up -d

# Verify
docker ps | grep maintenance
```

---

## Database Initialization

### Step 1: Initialize Databases

The initialization script creates all required databases and tables:

```bash
# Run database initialization
./scripts/init-databases.sh

# This script:
# 1. Creates travian_global database (PostgreSQL)
# 2. Creates AI-NPC tables (PostgreSQL)
# 3. Creates game world databases (MySQL)
# 4. Applies all schemas
```

**Expected Output:**
```
✅ PostgreSQL: travian_global database created
✅ PostgreSQL: AI-NPC tables created
✅ MySQL: travian_testworld database created
✅ MySQL: Game tables created
✅ Database initialization complete
```

### Step 2: Verify PostgreSQL

```bash
# Connect to PostgreSQL
docker exec -it travian_postgres psql -U postgres -d travian_global

# Verify tables
\dt

# Should show AI-NPC tables:
# - players
# - villages
# - ai_configs
# - npc_actions
# - decision_logs
# - llm_decision_metrics

# Exit
\q
```

### Step 3: Verify MySQL

```bash
# Connect to MySQL
docker exec -it travian_mysql mysql -uroot -p$MYSQL_ROOT_PASSWORD

# Show databases
SHOW DATABASES;

# Should include:
# - travian_global
# - travian_testworld
# - travian_demo

# Verify tables in test world
USE travian_testworld;
SHOW TABLES;

# Should show game tables (users, villages, buildings, etc.)

# Exit
exit
```

---

## TLS/SSL Setup

### Option 1: Let's Encrypt (Production)

**Prerequisites:**
- Domain name pointing to your server's public IP
- Ports 80 and 443 open in firewall
- DNS A record configured

```bash
# Ensure DOMAIN is set in .env
grep DOMAIN .env

# Start certbot service
docker compose run --rm certbot certonly \
  --webroot \
  --webroot-path=/var/www/certbot \
  -d $DOMAIN \
  --email $LETSENCRYPT_EMAIL \
  --agree-tos \
  --no-eff-email

# Certificate will be stored in ./certs/prod/
```

**Verify Certificate:**

```bash
# Check certificate files
ls -la certs/prod/live/$DOMAIN/

# Should show:
# - fullchain.pem
# - privkey.pem
```

**Restart Nginx to Apply:**

```bash
docker compose restart nginx
```

### Option 2: Self-Signed Certificate (Development/Testing)

```bash
# Generate self-signed certificate
./scripts/security/generate-dev-certs.sh

# Certificate will be in ./certs/dev/
ls -la certs/dev/
```

**Note:** Self-signed certificates will trigger browser warnings. Use Let's Encrypt for production.

---

## Verification

### Step 1: Run Stack Test Script

```bash
# Comprehensive health check
./scripts/test-stack.sh

# This script tests:
# - All container health
# - Database connectivity
# - Redis connectivity
# - GPU availability
# - API endpoints
# - Worker processes
```

**Expected Output:**
```
✅ Nginx: Healthy
✅ PHP-FPM: Healthy
✅ PostgreSQL: Healthy
✅ MySQL: Healthy
✅ Redis: Healthy
✅ Ollama: Healthy (GPU 0: RTX 3090 Ti)
✅ vLLM: Healthy (GPU 1: Tesla P40)
✅ Workers: All running
✅ API: Responding
✅ All tests passed!
```

### Step 2: Verify Services Manually

**Check Container Status:**

```bash
# All containers should be "Up" or "Up (healthy)"
docker compose ps
docker compose -f docker-compose.workers.yml ps
```

**Check Logs for Errors:**

```bash
# View recent logs
docker compose logs --tail=50

# Check specific service
docker compose logs nginx --tail=20
docker compose logs travian_app --tail=20
```

### Step 3: Test API Endpoints

```bash
# Test health endpoint
curl http://localhost/v1/health

# Expected: {"status":"ok","timestamp":"..."}

# Test CSRF token endpoint
curl http://localhost/v1/token

# Expected: {"success":true,"data":{"csrf_token":"..."}}
```

### Step 4: Verify Grafana Dashboards

1. Open http://localhost:3000
2. Login with admin credentials
3. Navigate to **Dashboards** → **Travian T4.6**
4. Verify dashboards load:
   - **Database Health**
   - **Worker Throughput**
   - **LLM Latency**

### Step 5: Verify GPU Access

```bash
# Check GPU allocation
./scripts/verify-gpu.sh

# Expected:
# GPU 0: RTX 3090 Ti → Ollama
# GPU 1: Tesla P40 → vLLM
```

---

## Post-Deployment

### Step 1: Configure Monitoring Alerts

Edit Prometheus alert rules:

```bash
nano prometheus/alerts.yml

# Configure alert thresholds for:
# - High error rate
# - Database connection failures
# - Worker queue backlog
# - High memory usage
# - Disk space warnings
```

Restart Prometheus to apply:

```bash
docker compose restart prometheus
```

### Step 2: Set Up Backup Schedules

Backups run automatically via the maintenance container. Verify schedule:

```bash
# Check cron jobs in maintenance container
docker exec travian_maintenance crontab -l

# Expected:
# 0 2 * * * /scripts/backup-postgres.sh
# 0 3 * * * /scripts/backup-mysql.sh
```

**Manual Backup Test:**

```bash
# Test PostgreSQL backup
./scripts/backup-postgres.sh

# Test MySQL backup
./scripts/backup-mysql.sh

# Verify backups created
ls -lh backups/postgres/daily/
ls -lh backups/mysql/daily/
```

**See:** [BACKUP-RESTORE.md](BACKUP-RESTORE.md) for full backup documentation.

### Step 3: Review Security Settings

Run security audit:

```bash
# Run comprehensive security audit
./scripts/security/run-security-audit.sh

# Verify CORS/CSRF configuration
./scripts/security/verify-cors-csrf.sh

# Check for dependency vulnerabilities
./scripts/security/scan-dependencies.sh
```

**See:** [SECURITY-HARDENING.md](SECURITY-HARDENING.md) for security best practices.

### Step 4: Configure Log Retention

Edit Loki configuration:

```bash
nano loki/loki-config.yml

# Adjust retention periods:
# - retention_period: 30d (default)
```

Restart Loki:

```bash
docker compose restart loki
```

**See:** [LOGGING.md](LOGGING.md) for logging documentation.

---

## Rollback Procedures

If deployment fails or issues arise, use these rollback procedures:

### Quick Rollback (Stop All Services)

```bash
# Stop all services immediately
docker compose down
docker compose -f docker-compose.workers.yml down
docker compose -f docker-compose.monitoring.yml down
docker compose -f docker-compose.logging.yml down

# Services are now stopped, databases are preserved
```

### Rollback to Previous Version

```bash
# Stop current deployment
docker compose down

# Checkout previous version
git log --oneline -10  # Find previous commit
git checkout <previous-commit-hash>

# Restore previous .env if needed
cp .env.production.backup .env

# Redeploy
docker compose up -d
```

### Restore from Backup

If database corruption occurs:

```bash
# Stop services
docker compose down

# Restore PostgreSQL
./scripts/restore-postgres.sh backups/postgres/daily/<backup-file>

# Restore MySQL
./scripts/restore-mysql.sh backups/mysql/daily/<backup-file>

# Restart services
docker compose up -d
```

**See:** [BACKUP-RESTORE.md](BACKUP-RESTORE.md) for detailed restore procedures.

### Emergency Maintenance Mode

```bash
# Create maintenance flag
touch /var/www/html/maintenance.flag

# This triggers maintenance page on frontend
# Backend APIs remain accessible for diagnostics
```

---

## Troubleshooting

### Common Issues

#### Issue: Docker Compose fails to start

**Symptoms:** Services fail to start, exit immediately

**Diagnosis:**

```bash
# Check logs
docker compose logs

# Check for port conflicts
sudo netstat -tulpn | grep -E "80|443|3000|3306|5432|6379"
```

**Solutions:**
- Ensure no other services using required ports
- Check .env file is configured correctly
- Verify Docker has sufficient resources allocated

#### Issue: Services show "unhealthy" status

**Symptoms:** `docker compose ps` shows "unhealthy" for services

**Diagnosis:**

```bash
# Check health check logs
docker inspect travian_postgres | grep -A 10 "Health"
docker inspect travian_mysql | grep -A 10 "Health"

# Check service logs
docker compose logs postgres
docker compose logs mysql
```

**Solutions:**
- Wait 1-2 minutes for services to fully initialize
- Verify database passwords in .env match configuration
- Check disk space: `df -h`

#### Issue: GPU not detected in containers

**Symptoms:** Ollama/vLLM fail to start, `nvidia-smi` not working

**Diagnosis:**

```bash
# Verify GPU access from WSL2
nvidia-smi

# Test Docker GPU runtime
docker run --rm --gpus all nvidia/cuda:12.0.0-base-ubuntu22.04 nvidia-smi
```

**Solutions:**
- Reinstall NVIDIA Container Toolkit (see Step 3 in Environment Setup)
- Restart Docker Desktop
- Update NVIDIA drivers on Windows host

#### Issue: Cannot access Grafana/API

**Symptoms:** Cannot reach http://localhost:3000 or http://localhost/v1/

**Diagnosis:**

```bash
# Check if ports are listening
sudo netstat -tulpn | grep -E "80|3000"

# Check Nginx logs
docker compose logs nginx

# Check firewall (Windows)
# Open Windows Firewall → Allow ports 80, 443, 3000
```

**Solutions:**
- Ensure Docker Desktop is running
- Verify WSL2 integration is enabled
- Check Windows Firewall settings
- Restart Nginx: `docker compose restart nginx`

### Additional Resources

- **Full Troubleshooting Guide:** [TROUBLESHOOTING.md](TROUBLESHOOTING.md)
- **Incident Response:** [INCIDENT-RESPONSE.md](INCIDENT-RESPONSE.md)
- **Operations Guide:** [OPERATIONS-RUNBOOK.md](OPERATIONS-RUNBOOK.md)

---

## Next Steps

After successful deployment:

1. **Review Monitoring:** Familiarize yourself with Grafana dashboards
2. **Set Up Alerts:** Configure alert notifications (email, Slack, etc.)
3. **Test Backups:** Run a test restore to verify backup integrity
4. **Security Hardening:** Review [SECURITY-HARDENING.md](SECURITY-HARDENING.md)
5. **Developer Onboarding:** If setting up development environment, see [DEVELOPER-ONBOARDING.md](DEVELOPER-ONBOARDING.md)
6. **Operations Training:** Review [OPERATIONS-RUNBOOK.md](OPERATIONS-RUNBOOK.md) for day-to-day tasks

---

## See Also

- [OPERATIONS-RUNBOOK.md](OPERATIONS-RUNBOOK.md) - Day-to-day operational procedures
- [BACKUP-RESTORE.md](BACKUP-RESTORE.md) - Backup and restore procedures
- [MONITORING.md](MONITORING.md) - Monitoring stack documentation
- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - Symptom-based troubleshooting
- [INCIDENT-RESPONSE.md](INCIDENT-RESPONSE.md) - Incident response playbook
- [SECURITY-HARDENING.md](SECURITY-HARDENING.md) - Security best practices
- [ARCHITECTURE.md](ARCHITECTURE.md) - System architecture overview
