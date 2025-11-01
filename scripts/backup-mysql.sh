#!/bin/bash
# ==========================================
# MySQL Backup Script
# ==========================================
# Backs up all game world databases
# Retention: 7 daily, 4 weekly, 12 monthly
# ==========================================

set -euo pipefail

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/backups/mysql}"
MYSQL_HOST="${MYSQL_HOST:-mysql}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASSWORD="${MYSQL_ROOT_PASSWORD}"
DATE=$(date +%Y%m%d_%H%M%S)
DAY_OF_WEEK=$(date +%u)
DAY_OF_MONTH=$(date +%d)

# Backup type (daily, weekly, monthly)
BACKUP_TYPE="daily"
if [ "$DAY_OF_WEEK" -eq 7 ]; then
    BACKUP_TYPE="weekly"
fi
if [ "$DAY_OF_MONTH" -eq "01" ]; then
    BACKUP_TYPE="monthly"
fi

# Create all directories (daily, weekly, monthly)
mkdir -p "$BACKUP_DIR/daily" "$BACKUP_DIR/weekly" "$BACKUP_DIR/monthly"

echo "[$(date)] Starting MySQL backup ($BACKUP_TYPE)..."

# Get list of databases (exclude system databases)
DATABASES=$(mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SHOW DATABASES;" | grep -Ev "(Database|information_schema|performance_schema|mysql|sys)")

# Track failures
FAILED_BACKUPS=0

# Backup each database
for DB in $DATABASES; do
    BACKUP_FILE="$BACKUP_DIR/$BACKUP_TYPE/mysql_${DB}_${BACKUP_TYPE}_${DATE}.sql.gz"
    
    echo "[$(date)] Backing up database: $DB"
    
    if mysqldump -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" \
        --single-transaction --quick --lock-tables=false "$DB" | gzip > "$BACKUP_FILE"; then
        
        SIZE=$(stat -f%z "$BACKUP_FILE" 2>/dev/null || stat -c%s "$BACKUP_FILE")
        echo "[$(date)] ✅ Backup completed: $BACKUP_FILE ($(numfmt --to=iec-i --suffix=B $SIZE))"
    else
        echo "[$(date)] ❌ Backup failed for database: $DB"
        FAILED_BACKUPS=$((FAILED_BACKUPS + 1))
    fi
done

# Exit with error if any backups failed
if [ $FAILED_BACKUPS -gt 0 ]; then
    echo "[$(date)] ❌ $FAILED_BACKUPS database backup(s) failed!"
    exit 1
fi

# Apply retention policy
echo "[$(date)] Applying retention policy..."

find "$BACKUP_DIR/daily" -name "mysql_*_daily_*.sql.gz" -type f -mtime +7 -delete 2>/dev/null || true
find "$BACKUP_DIR/weekly" -name "mysql_*_weekly_*.sql.gz" -type f -mtime +28 -delete 2>/dev/null || true
find "$BACKUP_DIR/monthly" -name "mysql_*_monthly_*.sql.gz" -type f -mtime +365 -delete 2>/dev/null || true

echo "[$(date)] MySQL backup completed successfully"
