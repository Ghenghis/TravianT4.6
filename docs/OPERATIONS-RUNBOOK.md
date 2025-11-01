# Operations Runbook - TravianT4.6

## Overview

This runbook provides day-to-day operational procedures for maintaining the TravianT4.6 production environment. Use this guide for routine tasks, scheduled maintenance, and standard operational procedures.

**Purpose:**
- Guide routine operations (daily, weekly, monthly)
- Define deployment procedures
- Document scaling operations
- Provide maintenance checklists

**When to Use This Runbook:**
- ✅ Daily health checks
- ✅ Scheduled maintenance
- ✅ Routine deployments
- ✅ Performance optimization
- ✅ Capacity planning

**When to Use INCIDENT-RESPONSE.md Instead:**
- ❌ Production incidents
- ❌ Service outages
- ❌ Security breaches
- ❌ Critical failures

**Target Audience:** On-call engineers, operators, DevOps team

---

## Daily Operations

### Morning Health Check (15 minutes)

Perform these checks at the start of each day:

#### 1. Check Monitoring Dashboards

```bash
# Open Grafana dashboards
# URL: http://localhost:3000

# Review these dashboards:
# - Database Health (PostgreSQL + MySQL metrics)
# - Worker Throughput (automation, AI, spawn scheduler)
# - LLM Latency (Ollama + vLLM performance)
# - System Resources (CPU, memory, disk)
```

**What to Look For:**
- ✅ All metrics within normal ranges
- ✅ No recent spikes in error rates
- ✅ Response times below thresholds (API: <400ms, LLM: <500ms)
- ✅ Worker queue depths below limits

**Action Items:**
- 🚨 If error rate >1%: Investigate logs
- 🚨 If response time >1000ms: Check database performance
- 🚨 If queue depth >500: Scale workers

#### 2. Review Logs for Errors

```bash
# Check for critical errors in last 24 hours
docker compose logs --since 24h | grep -i "error\|critical\|fatal" | head -20

# Check application errors
tail -100 /var/log/travian/app.log | jq 'select(.level=="error" or .level=="critical")'

# Check worker logs
docker compose -f docker-compose.workers.yml logs --since 24h | grep -i "error"
```

**Action Items:**
- Document any recurring errors
- Create tickets for non-critical issues
- Escalate critical errors to INCIDENT-RESPONSE.md

#### 3. Verify Backup Completion

```bash
# Check last PostgreSQL backup
ls -lth backups/postgres/daily/ | head -5

# Check last MySQL backup
ls -lth backups/mysql/daily/ | head -5

# Verify backup sizes are reasonable
du -sh backups/postgres/daily/* | tail -5
du -sh backups/mysql/daily/* | tail -5

# Check maintenance container logs
docker logs travian_maintenance --since 24h | grep backup
```

**Expected:**
- ✅ PostgreSQL backup from last night (2 AM)
- ✅ MySQL backup from last night (3 AM)
- ✅ File sizes consistent with previous backups
- ✅ No backup errors in logs

**Action Items:**
- 🚨 If backup missing: Check maintenance container status
- 🚨 If backup failed: Review logs and re-run manually
- 🚨 If backup size anomaly: Investigate database growth

#### 4. Check Disk Space

```bash
# Check disk usage
df -h

# Check Docker disk usage
docker system df

# Identify large directories
du -sh /var/lib/docker/* | sort -h | tail -10

# Check log file sizes
du -sh /var/log/travian/*
```

**Thresholds:**
- ⚠️ Warning: >70% disk usage
- 🚨 Critical: >85% disk usage

**Action Items:**
- If >70%: Review and clean old logs
- If >85%: Emergency cleanup (see Routine Maintenance)

#### 5. Monitor Worker Queue Depths

```bash
# Check Redis queue depths
docker exec travian_redis redis-cli -a $REDIS_PASSWORD --csv LLEN automation_queue
docker exec travian_redis redis-cli -a $REDIS_PASSWORD --csv LLEN ai_decision_queue
docker exec travian_redis redis-cli -a $REDIS_PASSWORD --csv LLEN spawn_queue

# Check worker process health
docker compose -f docker-compose.workers.yml ps

# View worker metrics in Grafana
# Dashboard: "Worker Throughput"
```

**Thresholds:**
- ✅ Normal: automation_queue <100, ai_decision_queue <500
- ⚠️ Warning: automation_queue >100, ai_decision_queue >500
- 🚨 Critical: automation_queue >500, ai_decision_queue >2000

**Action Items:**
- If queues growing: Consider scaling workers (see Scaling Procedures)
- If workers unhealthy: Restart worker containers

---

## Weekly Operations

### Monday Morning Review (30 minutes)

#### 1. Review Security Audit Logs

```bash
# Review failed authentication attempts
docker exec travian_postgres psql -U postgres -d travian_global -c \
  "SELECT * FROM audit_events WHERE event_type='authentication' 
   AND details->>'success'='false' 
   AND created_at > NOW() - INTERVAL '7 days' 
   ORDER BY created_at DESC LIMIT 50;"

# Review critical security events
docker exec travian_postgres psql -U postgres -d travian_global -c \
  "SELECT * FROM audit_events WHERE severity='critical' 
   AND created_at > NOW() - INTERVAL '7 days' 
   ORDER BY created_at DESC;"

# Check WAF blocking patterns
docker logs travian_waf --since 168h | grep "ModSecurity: Access denied" | wc -l
```

**Action Items:**
- Review patterns in failed logins
- Investigate repeated blocked IPs
- Update WAF rules if needed

#### 2. Check for Software Updates

```bash
# Check for Docker image updates
docker compose pull --dry-run

# Check for security updates in WSL2
sudo apt update
sudo apt list --upgradable

# Review NVIDIA driver updates
# (Check NVIDIA website for latest driver versions)
```

**Update Schedule:**
- Docker images: Review weekly, apply monthly
- OS packages: Apply security updates within 48 hours
- NVIDIA drivers: Apply quarterly or when issues arise

#### 3. Review Performance Metrics

```bash
# Generate performance report (last 7 days)
# Open Grafana and review:
# - Average API response time
# - Database query performance
# - LLM inference latency
# - Worker throughput

# Check slow query log (PostgreSQL)
docker exec travian_postgres psql -U postgres -d travian_global -c \
  "SELECT query, calls, total_exec_time/1000 as total_time_sec, 
   mean_exec_time/1000 as mean_time_sec 
   FROM pg_stat_statements 
   ORDER BY mean_exec_time DESC 
   LIMIT 20;"

# Check slow query log (MySQL)
docker exec travian_mysql mysql -uroot -p$MYSQL_ROOT_PASSWORD -e \
  "SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 20;"
```

**Action Items:**
- Identify and optimize slow queries
- Document performance trends
- Plan capacity upgrades if needed

#### 4. Analyze Error Trends

```bash
# Count errors by type (last 7 days)
tail -10000 /var/log/travian/app.log | \
  jq -r 'select(.level=="error") | .message' | \
  sort | uniq -c | sort -rn | head -10

# Check error rate trend
# View in Grafana: "Error Rate Over Time" panel
```

**Action Items:**
- Investigate recurring errors
- Create bug tickets for application errors
- Update documentation for known issues

---

## Monthly Operations

### First Monday of Month (1-2 hours)

#### 1. Rotate Credentials

```bash
# Rotate database passwords (PostgreSQL)
./scripts/secrets/rotate-credentials.sh postgres

# Rotate database passwords (MySQL)
./scripts/secrets/rotate-credentials.sh mysql

# Rotate Redis password
./scripts/secrets/rotate-credentials.sh redis

# Verify services after rotation
./scripts/test-stack.sh
```

**See:** [SECRETS-MANAGEMENT.md](SECRETS-MANAGEMENT.md) for detailed rotation procedures.

**Schedule:**
- Database passwords: Every 90 days
- API keys: Every 180 days
- JWT secrets: Every 365 days

#### 2. Review and Archive Old Backups

```bash
# List old backups (>90 days)
find backups/postgres/daily/ -type f -mtime +90 -ls
find backups/mysql/daily/ -type f -mtime +90 -ls

# Archive to cold storage (if configured)
# tar -czf backups/archive/postgres_$(date +%Y%m).tar.gz backups/postgres/monthly/

# Delete archived backups from daily/weekly folders
find backups/postgres/daily/ -type f -mtime +90 -delete
find backups/mysql/daily/ -type f -mtime +90 -delete

# Verify backup retention policy
ls -lh backups/postgres/daily/ | wc -l  # Should be ~7 files
ls -lh backups/postgres/weekly/ | wc -l # Should be ~4 files
ls -lh backups/postgres/monthly/ | wc -l # Should be ~12 files
```

#### 3. Update Documentation

```bash
# Review and update docs/ directory
# - Update API-REFERENCE.md with new endpoints
# - Update ARCHITECTURE.md with system changes
# - Update TROUBLESHOOTING.md with new known issues

# Update replit.md (project memory)
# - Document architectural changes
# - Update dependency versions
# - Record lessons learned

# Commit documentation changes
git add docs/
git commit -m "docs: monthly documentation update"
```

#### 4. Security Review

```bash
# Run comprehensive security audit
./scripts/security/run-security-audit.sh

# Scan for dependency vulnerabilities
./scripts/security/scan-dependencies.sh

# Verify security hardening
./scripts/security/verify-hardening.sh

# Review and address findings
# See: SECURITY-AUDIT.md for remediation steps
```

**Action Items:**
- Address critical vulnerabilities within 48 hours
- Plan remediation for high-severity findings
- Document accepted risks

---

## Deployment Procedures

### Standard Deployment Workflow

#### Pre-Deployment Checklist

- [ ] Code reviewed and approved
- [ ] Tests passing (unit + integration)
- [ ] Changelog updated
- [ ] Backup completed recently
- [ ] Deployment window communicated
- [ ] Rollback plan documented

#### Deployment Steps

```bash
# 1. Pull latest code
cd /home/$USER/projects/TravianT4.6
git fetch origin
git checkout main
git pull origin main

# 2. Backup current state
./scripts/backup-postgres.sh
./scripts/backup-mysql.sh

# 3. Stop services
docker compose down
docker compose -f docker-compose.workers.yml down

# 4. Pull new images (if using pre-built)
docker compose pull

# 5. Build new images (if building locally)
docker compose build

# 6. Start services
docker compose up -d
docker compose -f docker-compose.workers.yml up -d

# 7. Wait for health checks
watch -n 2 'docker compose ps'
# Wait until all show "healthy" status

# 8. Run smoke tests
./scripts/test-stack.sh

# 9. Verify application
curl http://localhost/v1/health
curl http://localhost/v1/token

# 10. Monitor logs for errors
docker compose logs -f --tail=50
```

**Post-Deployment:**
- Monitor Grafana dashboards for 30 minutes
- Watch for error rate spikes
- Verify worker throughput normal
- Document deployment in changelog

#### Rollback Procedure

If deployment fails or issues arise:

```bash
# 1. Stop current deployment
docker compose down
docker compose -f docker-compose.workers.yml down

# 2. Revert to previous version
git checkout <previous-commit-hash>

# 3. Restore from backup (if database changes)
./scripts/restore-postgres.sh backups/postgres/daily/<backup-file>
./scripts/restore-mysql.sh backups/mysql/daily/<backup-file>

# 4. Redeploy previous version
docker compose up -d
docker compose -f docker-compose.workers.yml up -d

# 5. Verify rollback successful
./scripts/test-stack.sh
```

---

## Scaling Procedures

### Horizontal Scaling: Add Worker Instances

**Scenario:** Queue depths increasing, workers at capacity

```bash
# Scale up automation workers
docker compose -f docker-compose.workers.yml up -d --scale automation-worker=3

# Scale up AI decision workers
docker compose -f docker-compose.workers.yml up -d --scale ai-decision-worker=5

# Verify scaled instances
docker compose -f docker-compose.workers.yml ps

# Monitor queue depths
watch -n 5 'docker exec travian_redis redis-cli -a $REDIS_PASSWORD LLEN ai_decision_queue'
```

**Scaling Limits:**
- Automation workers: 1-5 instances
- AI decision workers: 1-10 instances
- Spawn scheduler: 1 instance (do not scale)

### Vertical Scaling: Increase Resources

**Scenario:** Services hitting memory/CPU limits

```bash
# Edit docker-compose.yml to increase limits
nano docker-compose.yml

# Example: Increase PHP-FPM memory
# Change: mem_limit: 1536M
# To: mem_limit: 2048M

# Restart service
docker compose up -d php-fpm

# Verify new limits
docker stats travian_app
```

### Database Scaling

**PostgreSQL:**
```bash
# Increase shared_buffers and effective_cache_size
docker exec travian_postgres psql -U postgres -c \
  "ALTER SYSTEM SET shared_buffers = '4GB';"

docker exec travian_postgres psql -U postgres -c \
  "ALTER SYSTEM SET effective_cache_size = '12GB';"

# Restart PostgreSQL
docker compose restart postgres
```

**MySQL:**
```bash
# Increase InnoDB buffer pool
docker exec travian_mysql mysql -uroot -p$MYSQL_ROOT_PASSWORD -e \
  "SET GLOBAL innodb_buffer_pool_size = 4294967296;"

# Restart MySQL for persistent change
docker compose restart mysql
```

### Redis Scaling

```bash
# Increase max memory
docker exec travian_redis redis-cli -a $REDIS_PASSWORD CONFIG SET maxmemory 2gb

# Make persistent
docker exec travian_redis redis-cli -a $REDIS_PASSWORD CONFIG REWRITE

# Restart Redis
docker compose restart redis
```

---

## Routine Maintenance

### Database Vacuuming (Weekly)

**PostgreSQL:**
```bash
# Analyze and vacuum
docker exec travian_postgres psql -U postgres -d travian_global -c "VACUUM ANALYZE;"

# Check bloat
docker exec travian_postgres psql -U postgres -d travian_global -c \
  "SELECT schemaname, tablename, 
   pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
   FROM pg_tables 
   WHERE schemaname NOT IN ('pg_catalog', 'information_schema') 
   ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC 
   LIMIT 10;"
```

**MySQL:**
```bash
# Optimize tables
docker exec travian_mysql mysqlcheck -uroot -p$MYSQL_ROOT_PASSWORD --optimize --all-databases

# Analyze tables
docker exec travian_mysql mysqlcheck -uroot -p$MYSQL_ROOT_PASSWORD --analyze --all-databases
```

### Log Rotation Verification

```bash
# Verify logrotate is running
ls -lh /var/log/travian/

# Check log file sizes
du -sh /var/log/travian/*

# Force rotation if needed
docker exec travian_app logrotate -f /etc/logrotate.conf
```

### Cache Clearing

```bash
# Clear Redis cache (preserve sessions)
docker exec travian_redis redis-cli -a $REDIS_PASSWORD --scan --pattern "cache:*" | \
  xargs docker exec travian_redis redis-cli -a $REDIS_PASSWORD DEL

# Verify cache cleared
docker exec travian_redis redis-cli -a $REDIS_PASSWORD INFO stats | grep keys
```

### Container Cleanup

```bash
# Remove stopped containers
docker container prune -f

# Remove unused images
docker image prune -a -f

# Remove unused volumes (careful!)
docker volume prune -f

# Remove unused networks
docker network prune -f

# Check disk space reclaimed
docker system df
```

---

## Monitoring & Alerting

### Dashboard Review Checklist

Daily review of these Grafana panels:

**Database Health Dashboard:**
- [ ] PostgreSQL connections: <150/200
- [ ] MySQL connections: <100/150
- [ ] Cache hit ratio: >85%
- [ ] Query latency: <50ms (95th percentile)

**Worker Throughput Dashboard:**
- [ ] Automation queue depth: <100
- [ ] AI decision queue depth: <500
- [ ] Processing rate: >60 jobs/minute
- [ ] Worker error rate: <1%

**LLM Latency Dashboard:**
- [ ] Ollama latency: <200ms (95th percentile)
- [ ] vLLM latency: <500ms (95th percentile)
- [ ] GPU utilization: 40-80%
- [ ] LLM error rate: <0.1%

**System Resources Dashboard:**
- [ ] CPU usage: <70%
- [ ] Memory usage: <80%
- [ ] Disk usage: <70%
- [ ] Network throughput: Normal

### Alert Acknowledgment Procedures

When Grafana alert fires:

1. **Acknowledge Alert** in Grafana
2. **Assess Severity** using INCIDENT-RESPONSE.md matrix
3. **Investigate** using TROUBLESHOOTING.md
4. **Take Action** or escalate
5. **Document Resolution** in alert notes

### Escalation Criteria

Escalate to INCIDENT-RESPONSE.md if:
- P1/P2 severity incident
- Service degradation >15 minutes
- Data integrity concerns
- Security incident suspected

---

## Secret Rotation

### Scheduled Rotation (Monthly)

```bash
# Rotate PostgreSQL password
./scripts/secrets/rotate-credentials.sh postgres

# Rotate MySQL password
./scripts/secrets/rotate-credentials.sh mysql

# Rotate Redis password
./scripts/secrets/rotate-credentials.sh redis

# Verify services after rotation
./scripts/test-stack.sh
```

### Emergency Rotation

If credentials compromised:

```bash
# Immediate rotation
./scripts/secrets/rotate-credentials.sh <service> --force

# Update audit log
docker exec travian_postgres psql -U postgres -d travian_global -c \
  "INSERT INTO audit_events (event_type, severity, details) 
   VALUES ('credential_rotation', 'critical', 
   '{\"service\": \"<service>\", \"reason\": \"emergency\"}');"

# Review access logs
docker logs travian_<service> --since 24h | grep -i auth
```

**See:** [SECRETS-MANAGEMENT.md](SECRETS-MANAGEMENT.md)

---

## See Also

- [INCIDENT-RESPONSE.md](INCIDENT-RESPONSE.md) - For production incidents
- [DEPLOYMENT-GUIDE.md](DEPLOYMENT-GUIDE.md) - For initial deployment
- [MONITORING.md](MONITORING.md) - For monitoring setup
- [BACKUP-RESTORE.md](BACKUP-RESTORE.md) - For backup procedures
- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - For troubleshooting issues
- [SECRETS-MANAGEMENT.md](SECRETS-MANAGEMENT.md) - For credential management
