#!/bin/bash
# ==========================================
# MySQL Restore Script
# ==========================================
# Usage: ./restore-mysql.sh <backup_file> <database_name>
# ==========================================

set -euo pipefail

if [ $# -lt 2 ]; then
    echo "Usage: $0 <backup_file.sql.gz> <database_name>"
    echo ""
    echo "Available backups:"
    find /backups/mysql -name "*.sql.gz" -type f -printf "%T@ %p\n" | sort -rn | head -20 | cut -d' ' -f2
    exit 1
fi

BACKUP_FILE="$1"
DATABASE="$2"

if [ ! -f "$BACKUP_FILE" ]; then
    echo "❌ Backup file not found: $BACKUP_FILE"
    exit 1
fi

MYSQL_HOST="${MYSQL_HOST:-mysql}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASSWORD="${MYSQL_ROOT_PASSWORD}"

echo "⚠️  WARNING: This will restore the database from backup!"
echo "Database: $DATABASE"
echo "Backup file: $BACKUP_FILE"
read -p "Are you sure? (yes/no): " -r
if [[ ! $REPLY =~ ^yes$ ]]; then
    echo "Restore cancelled"
    exit 0
fi

echo "[$(date)] Starting MySQL restore..."

if gunzip -c "$BACKUP_FILE" | mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$DATABASE"; then
    echo "[$(date)] ✅ Restore completed successfully"
else
    echo "[$(date)] ❌ Restore failed!"
    exit 1
fi
