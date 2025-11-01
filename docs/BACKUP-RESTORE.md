# Backup & Restore Documentation

## Table of Contents
1. [Overview](#overview)
2. [Backup Architecture](#backup-architecture)
3. [Automated Backup Schedule](#automated-backup-schedule)
4. [Manual Backup Procedures](#manual-backup-procedures)
5. [Restoration Procedures](#restoration-procedures)
6. [Testing Backups](#testing-backups)
7. [Disaster Recovery](#disaster-recovery)
8. [Troubleshooting](#troubleshooting)

---

## Overview

The Travian T4.6 backup system provides automated, reliable database backups for both PostgreSQL (global database + AI-NPC system) and MySQL (game world databases). The system implements a multi-tier retention policy to ensure data safety while managing storage efficiently.

### Key Features
- **Automated daily backups** (PostgreSQL at 2 AM, MySQL at 3 AM)
- **Multi-tier retention policy** (7 daily, 4 weekly, 12 monthly)
- **Compressed backups** (gzip) to save storage space
- **Backup verification** to ensure data integrity
- **Easy restoration scripts** for disaster recovery
- **Dockerized maintenance container** for isolation and portability

---

## Backup Architecture

### System Components

```
┌─────────────────────────────────────────────────────────┐
│          Maintenance Container (Alpine Linux)           │
│                                                          │
│  ┌────────────────┐         ┌──────────────────┐       │
│  │  Cron Daemon   │────────▶│  Backup Scripts  │       │
│  │                │         │                  │       │
│  │  Daily @ 2 AM  │         │ - PostgreSQL     │       │
│  │  Daily @ 3 AM  │         │ - MySQL          │       │
│  └────────────────┘         └──────────────────┘       │
│                                      │                  │
│                                      ▼                  │
│                          ┌──────────────────┐          │
│                          │  /backups Volume │          │
│                          │                  │          │
│                          │  ├─ postgres/    │          │
│                          │  │  ├─ daily/    │          │
│                          │  │  ├─ weekly/   │          │
│                          │  │  └─ monthly/  │          │
│                          │  └─ mysql/       │          │
│                          │     ├─ daily/    │          │
│                          │     ├─ weekly/   │          │
│                          │     └─ monthly/  │          │
│                          └──────────────────┘          │
└─────────────────────────────────────────────────────────┘
         │                                    │
         │                                    │
         ▼                                    ▼
┌──────────────────┐              ┌──────────────────┐
│  PostgreSQL DB   │              │     MySQL DB     │
│                  │              │                  │
│ - travian_global │              │ - travian_world1 │
│ - AI-NPC data    │              │ - travian_world2 │
└──────────────────┘              │ - ...            │
                                  └──────────────────┘
```

### Backup Types

| Type    | Frequency | Retention | Storage Path           |
|---------|-----------|-----------|------------------------|
| Daily   | Every day | 7 days    | `/backups/*/daily/`    |
| Weekly  | Sunday    | 4 weeks   | `/backups/*/weekly/`   |
| Monthly | 1st day   | 12 months | `/backups/*/monthly/`  |

### File Naming Convention

```bash
# PostgreSQL
postgres_daily_20251030_020000.sql.gz
postgres_weekly_20251030_020000.sql.gz
postgres_monthly_20251030_020000.sql.gz

# MySQL (per database)
mysql_travian_world1_daily_20251030_030000.sql.gz
mysql_travian_world1_weekly_20251030_030000.sql.gz
mysql_travian_world1_monthly_20251030_030000.sql.gz
```

---

## Automated Backup Schedule

### Enabling the Maintenance Container

The maintenance container runs as a Docker Compose profile to keep it separate from the main application stack.

#### Start with Main Stack

```bash
# Start all services including maintenance backups
docker-compose -f docker-compose.yml -f docker-compose.maintenance.yml --profile maintenance up -d
```

#### Start Separately

```bash
# Start only the maintenance container (requires main stack running)
docker-compose -f docker-compose.maintenance.yml --profile maintenance up -d
```

### Cron Schedule

The maintenance container runs two cron jobs:

| Job                | Schedule | Cron Expression      | Description                          |
|--------------------|----------|----------------------|--------------------------------------|
| PostgreSQL Backup  | 2:00 AM  | `0 2 * * *`          | Backs up global DB + AI-NPC data     |
| MySQL Backup       | 3:00 AM  | `0 3 * * *`          | Backs up all game world databases    |

### Customizing Backup Schedule

Edit `docker/maintenance/Dockerfile` to change cron schedules:

```dockerfile
# Example: Run PostgreSQL backup every 6 hours
RUN echo "0 */6 * * * /usr/local/bin/backup-postgres.sh >> /var/log/backup-postgres.log 2>&1" >> /etc/crontabs/root
```

Then rebuild the container:

```bash
docker-compose -f docker-compose.maintenance.yml build maintenance
docker-compose -f docker-compose.maintenance.yml --profile maintenance up -d
```

### Monitoring Automated Backups

#### Check backup logs

```bash
# PostgreSQL backup logs
docker exec travian_maintenance tail -f /var/log/backup-postgres.log

# MySQL backup logs
docker exec travian_maintenance tail -f /var/log/backup-mysql.log
```

#### View recent backups

```bash
# List all recent backups
docker exec travian_maintenance find /backups -type f -name "*.sql.gz" -mtime -7 -ls
```

#### Check cron status

```bash
# Verify cron jobs are configured
docker exec travian_maintenance crontab -l

# Check if cron is running
docker exec travian_maintenance ps aux | grep crond
```

---

## Manual Backup Procedures

### PostgreSQL Manual Backup

#### Using the backup script

```bash
# Execute backup script manually
docker exec travian_maintenance /usr/local/bin/backup-postgres.sh
```

#### Direct pg_dump command

```bash
# Backup from host machine
docker exec travian_postgres pg_dump -U travian travian_global | gzip > postgres_manual_$(date +%Y%m%d_%H%M%S).sql.gz

# Backup from within container
docker exec -it travian_maintenance sh
backup-postgres.sh
```

### MySQL Manual Backup

#### Using the backup script

```bash
# Execute backup script manually (backs up all databases)
docker exec travian_maintenance /usr/local/bin/backup-mysql.sh
```

#### Backup specific database

```bash
# Backup single database
docker exec travian_mysql mysqldump -u root -p${MYSQL_ROOT_PASSWORD} travian_world1 | gzip > mysql_world1_manual_$(date +%Y%m%d_%H%M%S).sql.gz
```

### Copying Backups to Host

```bash
# Copy PostgreSQL backup to host
docker cp travian_maintenance:/backups/postgres/daily/postgres_daily_20251030_020000.sql.gz ./backups/

# Copy all MySQL backups to host
docker cp travian_maintenance:/backups/mysql ./backups/
```

### External Storage (S3, Cloud)

#### AWS S3 Example

```bash
# Install AWS CLI in maintenance container (add to Dockerfile)
RUN apk add --no-cache aws-cli

# Upload to S3 (add to backup scripts)
aws s3 cp /backups/postgres/daily/ s3://your-bucket/postgres/daily/ --recursive
```

#### Google Cloud Storage Example

```bash
# Install gsutil
RUN apk add --no-cache python3 py3-pip && pip3 install gsutil

# Upload to GCS
gsutil -m cp -r /backups/postgres/daily/ gs://your-bucket/postgres/daily/
```

---

## Restoration Procedures

### PostgreSQL Restoration

#### Using the restore script

```bash
# 1. List available backups
docker exec travian_maintenance /usr/local/bin/restore-postgres.sh

# 2. Restore from specific backup
docker exec -it travian_maintenance /usr/local/bin/restore-postgres.sh /backups/postgres/daily/postgres_daily_20251030_020000.sql.gz
```

#### Manual restoration

```bash
# 1. Stop application containers
docker-compose stop php-fpm nginx

# 2. Drop and recreate database (DANGER!)
docker exec -it travian_postgres psql -U travian -d postgres -c "DROP DATABASE travian_global;"
docker exec -it travian_postgres psql -U travian -d postgres -c "CREATE DATABASE travian_global;"

# 3. Restore backup
docker exec -i travian_postgres psql -U travian -d travian_global < postgres_backup.sql

# Or with gzipped backup
gunzip -c postgres_backup.sql.gz | docker exec -i travian_postgres psql -U travian -d travian_global

# 4. Restart application
docker-compose start php-fpm nginx
```

### MySQL Restoration

#### Using the restore script

```bash
# 1. List available backups
docker exec travian_maintenance /usr/local/bin/restore-mysql.sh

# 2. Restore specific database
docker exec -it travian_maintenance /usr/local/bin/restore-mysql.sh \
  /backups/mysql/daily/mysql_travian_world1_daily_20251030_030000.sql.gz \
  travian_world1
```

#### Manual restoration

```bash
# 1. Stop application
docker-compose stop php-fpm nginx

# 2. Drop and recreate database
docker exec -it travian_mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "DROP DATABASE travian_world1;"
docker exec -it travian_mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "CREATE DATABASE travian_world1;"

# 3. Restore backup
gunzip -c mysql_backup.sql.gz | docker exec -i travian_mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} travian_world1

# 4. Restart application
docker-compose start php-fpm nginx
```

### Point-in-Time Recovery

For point-in-time recovery, you need to enable binary logging:

#### PostgreSQL WAL Archiving

Add to `docker-compose.yml`:

```yaml
postgres:
  command: >
    postgres
    -c wal_level=replica
    -c archive_mode=on
    -c archive_command='cp %p /backups/postgres/wal/%f'
```

#### MySQL Binary Logs

Add to `docker/mysql/my.cnf`:

```ini
[mysqld]
log-bin=/var/log/mysql/mysql-bin.log
expire_logs_days=7
```

---

## Testing Backups

### Verification Checklist

Regular backup testing ensures you can actually restore when needed.

#### Weekly Verification (Automated)

Create a test restoration script:

```bash
#!/bin/bash
# scripts/test-backup-restore.sh

set -euo pipefail

echo "=== Testing PostgreSQL Backup Restore ==="

# Get latest backup
LATEST_PG_BACKUP=$(find /backups/postgres -name "*.sql.gz" -type f | sort -r | head -1)

# Create test database
docker exec travian_postgres psql -U travian -d postgres -c "DROP DATABASE IF EXISTS test_restore;"
docker exec travian_postgres psql -U travian -d postgres -c "CREATE DATABASE test_restore;"

# Restore to test database
gunzip -c "$LATEST_PG_BACKUP" | docker exec -i travian_postgres psql -U travian -d test_restore

# Verify tables exist
TABLE_COUNT=$(docker exec travian_postgres psql -U travian -d test_restore -t -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public';")

if [ "$TABLE_COUNT" -gt 0 ]; then
    echo "✅ PostgreSQL backup verification successful ($TABLE_COUNT tables)"
else
    echo "❌ PostgreSQL backup verification failed"
    exit 1
fi

# Cleanup
docker exec travian_postgres psql -U travian -d postgres -c "DROP DATABASE test_restore;"

echo "=== PostgreSQL backup test completed ==="
```

#### Monthly Full Restoration Test

1. **Clone production environment**
   ```bash
   # Create separate test environment
   docker-compose -f docker-compose.test.yml up -d
   ```

2. **Restore latest backup**
   ```bash
   # Restore PostgreSQL
   docker exec test_maintenance restore-postgres.sh /backups/postgres/monthly/latest.sql.gz
   
   # Restore MySQL
   docker exec test_maintenance restore-mysql.sh /backups/mysql/monthly/latest.sql.gz travian_world1
   ```

3. **Verify application functionality**
   - Test login
   - Verify game world data
   - Check AI-NPC system
   - Confirm all features work

4. **Document results**
   - Record restoration time
   - Note any issues
   - Update runbooks

### Backup Integrity Checks

#### File Size Monitoring

```bash
# Alert if backup is suspiciously small
MIN_SIZE_MB=10
BACKUP_FILE="/backups/postgres/daily/latest.sql.gz"

SIZE_MB=$(du -m "$BACKUP_FILE" | cut -f1)

if [ "$SIZE_MB" -lt "$MIN_SIZE_MB" ]; then
    echo "⚠️ WARNING: Backup file is smaller than expected ($SIZE_MB MB)"
    # Send alert (email, Slack, etc.)
fi
```

#### Checksum Verification

```bash
# Generate checksums after backup
cd /backups/postgres/daily
sha256sum *.sql.gz > checksums.txt

# Verify before restoration
sha256sum -c checksums.txt
```

---

## Disaster Recovery

### Disaster Recovery Plan (DRP)

#### Recovery Time Objective (RTO)
- **Target:** 4 hours from disaster to full service restoration
- **Maximum acceptable:** 24 hours

#### Recovery Point Objective (RPO)
- **Target:** 1 hour of data loss maximum
- **Actual:** 24 hours (daily backups)

### Disaster Scenarios

#### Scenario 1: Database Corruption

**Symptoms:** Application errors, corrupted data, failed queries

**Recovery Steps:**
1. Stop application immediately
   ```bash
   docker-compose stop php-fpm nginx
   ```

2. Identify latest valid backup
   ```bash
   docker exec travian_maintenance ls -lh /backups/postgres/daily/
   ```

3. Restore from backup
   ```bash
   docker exec -it travian_maintenance restore-postgres.sh /backups/postgres/daily/postgres_daily_YYYYMMDD_HHMMSS.sql.gz
   ```

4. Verify restoration
   ```bash
   docker exec travian_postgres psql -U travian -d travian_global -c "SELECT COUNT(*) FROM users;"
   ```

5. Restart application
   ```bash
   docker-compose start php-fpm nginx
   ```

**Expected Time:** 30-60 minutes

#### Scenario 2: Complete Server Failure

**Symptoms:** Server unreachable, hardware failure

**Recovery Steps:**
1. Provision new server
2. Install Docker and dependencies
3. Clone repository
   ```bash
   git clone https://github.com/your-org/travian.git
   cd travian
   ```

4. Copy backups from external storage
   ```bash
   aws s3 sync s3://your-bucket/backups /backups/
   # or
   rsync -avz backup-server:/backups/ /backups/
   ```

5. Update `.env.production` with new settings
6. Start services
   ```bash
   docker-compose up -d
   ```

7. Restore databases
   ```bash
   docker exec -it travian_maintenance restore-postgres.sh /backups/postgres/latest.sql.gz
   docker exec -it travian_maintenance restore-mysql.sh /backups/mysql/latest.sql.gz travian_world1
   ```

8. Verify and test

**Expected Time:** 2-4 hours

#### Scenario 3: Accidental Data Deletion

**Symptoms:** Users report missing data

**Recovery Steps:**
1. Identify deletion timestamp
2. Find backup before deletion
3. Restore to temporary database
   ```bash
   docker exec travian_postgres psql -U travian -d postgres -c "CREATE DATABASE temp_restore;"
   gunzip -c backup.sql.gz | docker exec -i travian_postgres psql -U travian -d temp_restore
   ```

4. Extract deleted data
   ```bash
   docker exec travian_postgres psql -U travian -d temp_restore -c "SELECT * FROM deleted_table;" > recovered_data.sql
   ```

5. Import to production
   ```bash
   docker exec -i travian_postgres psql -U travian -d travian_global < recovered_data.sql
   ```

**Expected Time:** 1-2 hours

### Emergency Contacts

Document your emergency contacts:

| Role                    | Name           | Contact               |
|-------------------------|----------------|-----------------------|
| Database Administrator  | [Your Name]    | [Email/Phone]         |
| System Administrator    | [Your Name]    | [Email/Phone]         |
| Backup Service Provider | [Provider]     | [Support Email/Phone] |

### Offsite Backup Strategy

**Critical:** Always maintain offsite backups for disaster recovery.

#### Option 1: Cloud Storage (Recommended)

```bash
# AWS S3
aws s3 sync /backups/postgres s3://your-bucket/postgres --delete
aws s3 sync /backups/mysql s3://your-bucket/mysql --delete

# Google Cloud Storage
gsutil -m rsync -r -d /backups/postgres gs://your-bucket/postgres
gsutil -m rsync -r -d /backups/mysql gs://your-bucket/mysql
```

#### Option 2: Remote Server

```bash
# Rsync to remote server
rsync -avz --delete /backups/ backup-server:/remote/backups/

# Over SSH
rsync -avz -e "ssh -i ~/.ssh/backup_key" /backups/ user@backup-server:/backups/
```

#### Option 3: Multiple Locations

```bash
# Copy to multiple destinations
for DEST in s3://bucket1 s3://bucket2 user@server:/backups; do
    # Upload to each destination
done
```

---

## Troubleshooting

### Common Issues

#### Issue 1: Backup Script Fails

**Error:**
```
pg_dump: error: connection to server failed
```

**Diagnosis:**
```bash
# Check if PostgreSQL is running
docker ps | grep postgres

# Check PostgreSQL logs
docker logs travian_postgres

# Test connection
docker exec travian_maintenance psql -h postgres -U travian -d travian_global -c "SELECT 1;"
```

**Solution:**
```bash
# Restart PostgreSQL
docker-compose restart postgres

# Wait for healthy status
docker-compose ps postgres
```

#### Issue 2: Out of Disk Space

**Error:**
```
gzip: stdout: No space left on device
```

**Diagnosis:**
```bash
# Check disk usage
docker exec travian_maintenance df -h

# Check backup directory size
docker exec travian_maintenance du -sh /backups/*
```

**Solution:**
```bash
# Manual cleanup of old backups
docker exec travian_maintenance find /backups -name "*.sql.gz" -mtime +60 -delete

# Increase retention cleanup frequency
# Edit backup scripts to clean up more aggressively
```

#### Issue 3: Restore Fails with Encoding Errors

**Error:**
```
ERROR: invalid byte sequence for encoding "UTF8"
```

**Solution:**
```bash
# Restore with specific encoding
PGCLIENTENCODING=LATIN1 gunzip -c backup.sql.gz | docker exec -i travian_postgres psql -U travian -d travian_global

# Or recreate database with correct encoding
docker exec travian_postgres psql -U travian -d postgres -c "CREATE DATABASE travian_global ENCODING 'UTF8';"
```

#### Issue 4: Cron Jobs Not Running

**Diagnosis:**
```bash
# Check cron logs
docker logs travian_maintenance

# Verify crontab
docker exec travian_maintenance crontab -l

# Check cron daemon
docker exec travian_maintenance ps aux | grep crond
```

**Solution:**
```bash
# Restart maintenance container
docker-compose -f docker-compose.maintenance.yml restart maintenance

# Manually trigger backup to test
docker exec travian_maintenance /usr/local/bin/backup-postgres.sh
```

#### Issue 5: Permission Denied Errors

**Error:**
```
Permission denied: '/backups/postgres/daily'
```

**Solution:**
```bash
# Fix permissions in container
docker exec travian_maintenance chmod -R 755 /backups
docker exec travian_maintenance chown -R root:root /backups

# Recreate backup directories
docker exec travian_maintenance mkdir -p /backups/postgres/{daily,weekly,monthly}
docker exec travian_maintenance mkdir -p /backups/mysql/{daily,weekly,monthly}
```

### Debugging Tips

#### Enable Verbose Logging

Edit backup scripts and add:
```bash
set -x  # Enable debug mode
```

#### Test Backup Scripts Manually

```bash
# Run with environment variables
docker exec -e BACKUP_DIR=/backups/test travian_maintenance backup-postgres.sh
```

#### Verify Environment Variables

```bash
# Check all environment variables in container
docker exec travian_maintenance env | grep -E "(PG|MYSQL)"
```

#### Monitor Backup Progress

```bash
# Watch backup file size grow
watch -n 1 docker exec travian_maintenance ls -lh /backups/postgres/daily/
```

---

## Best Practices

### Security

1. **Encrypt backups**
   ```bash
   # Encrypt with GPG
   pg_dump ... | gzip | gpg --encrypt --recipient backup@example.com > backup.sql.gz.gpg
   ```

2. **Secure backup storage**
   - Use encrypted volumes
   - Restrict access with IAM policies
   - Enable MFA for cloud storage

3. **Rotate credentials**
   - Change database passwords regularly
   - Update in `.env.production` and restart containers

### Performance

1. **Off-peak backups**
   - Schedule during low-traffic hours (2-3 AM)

2. **Incremental backups** (advanced)
   - Use WAL archiving for PostgreSQL
   - Use binary logs for MySQL

3. **Compression**
   - Use `gzip -9` for maximum compression
   - Consider `zstd` for better performance

### Monitoring

1. **Backup alerts**
   ```bash
   # Send email on failure (add to scripts)
   if ! backup-postgres.sh; then
       echo "Backup failed" | mail -s "ALERT: Backup Failed" admin@example.com
   fi
   ```

2. **Size monitoring**
   - Track backup sizes over time
   - Alert on significant changes

3. **Dashboard integration**
   - Add backup metrics to Grafana
   - Monitor via Prometheus

---

## Maintenance Checklist

### Daily
- [ ] Verify automated backups completed
- [ ] Check backup logs for errors
- [ ] Monitor disk space

### Weekly
- [ ] Test restore on recent backup
- [ ] Verify backup file integrity
- [ ] Review backup sizes

### Monthly
- [ ] Full restoration test in staging
- [ ] Update disaster recovery documentation
- [ ] Review and update retention policies
- [ ] Test offsite backup retrieval

### Quarterly
- [ ] Disaster recovery drill
- [ ] Review and update RTO/RPO
- [ ] Audit backup access logs
- [ ] Update emergency contacts

---

## Additional Resources

- [PostgreSQL Backup Documentation](https://www.postgresql.org/docs/current/backup.html)
- [MySQL Backup Documentation](https://dev.mysql.com/doc/refman/8.0/en/backup-and-recovery.html)
- [Docker Volume Backup Best Practices](https://docs.docker.com/storage/volumes/#back-up-restore-or-migrate-data-volumes)

---

## Support

For backup and restore issues:
1. Check this documentation
2. Review logs: `/var/log/backup-*.log`
3. Test manually with scripts
4. Contact system administrator

**Last Updated:** October 30, 2025
