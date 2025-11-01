#!/bin/bash
# ==========================================
# PostgreSQL Backup Script
# ==========================================
# Backs up global database + AI-NPC system data
# Retention: 7 daily, 4 weekly, 12 monthly
# ==========================================

set -euo pipefail

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/backups/postgres}"
POSTGRES_HOST="${PGHOST:-postgres}"
POSTGRES_PORT="${PGPORT:-5432}"
POSTGRES_USER="${PGUSER:-travian}"
POSTGRES_DB="${PGDATABASE:-travian_global}"
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

# Backup filename
BACKUP_FILE="$BACKUP_DIR/$BACKUP_TYPE/postgres_${BACKUP_TYPE}_${DATE}.sql.gz"

echo "[$(date)] Starting PostgreSQL backup ($BACKUP_TYPE)..."

# Perform backup
if pg_dump -h "$POSTGRES_HOST" -p "$POSTGRES_PORT" -U "$POSTGRES_USER" -d "$POSTGRES_DB" | gzip > "$BACKUP_FILE"; then
    echo "[$(date)] ✅ Backup completed: $BACKUP_FILE"
    
    # Verify backup
    if [ -s "$BACKUP_FILE" ]; then
        SIZE=$(stat -f%z "$BACKUP_FILE" 2>/dev/null || stat -c%s "$BACKUP_FILE")
        echo "[$(date)] Backup size: $(numfmt --to=iec-i --suffix=B $SIZE)"
    else
        echo "[$(date)] ⚠️  WARNING: Backup file is empty!"
        exit 1
    fi
else
    echo "[$(date)] ❌ Backup failed!"
    exit 1
fi

# Apply retention policy
echo "[$(date)] Applying retention policy..."

# Keep 7 daily backups
find "$BACKUP_DIR/daily" -name "postgres_daily_*.sql.gz" -type f -mtime +7 -delete 2>/dev/null || true

# Keep 4 weekly backups
find "$BACKUP_DIR/weekly" -name "postgres_weekly_*.sql.gz" -type f -mtime +28 -delete 2>/dev/null || true

# Keep 12 monthly backups
find "$BACKUP_DIR/monthly" -name "postgres_monthly_*.sql.gz" -type f -mtime +365 -delete 2>/dev/null || true

echo "[$(date)] PostgreSQL backup completed successfully"
