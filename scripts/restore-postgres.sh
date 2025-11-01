#!/bin/bash
# ==========================================
# PostgreSQL Restore Script
# ==========================================
# Usage: ./restore-postgres.sh <backup_file>
# ==========================================

set -euo pipefail

if [ $# -eq 0 ]; then
    echo "Usage: $0 <backup_file.sql.gz>"
    echo ""
    echo "Available backups:"
    find /backups/postgres -name "*.sql.gz" -type f -printf "%T@ %p\n" | sort -rn | head -20 | cut -d' ' -f2
    exit 1
fi

BACKUP_FILE="$1"

if [ ! -f "$BACKUP_FILE" ]; then
    echo "❌ Backup file not found: $BACKUP_FILE"
    exit 1
fi

POSTGRES_HOST="${PGHOST:-postgres}"
POSTGRES_PORT="${PGPORT:-5432}"
POSTGRES_USER="${PGUSER:-travian}"
POSTGRES_DB="${PGDATABASE:-travian_global}"

echo "⚠️  WARNING: This will restore the database from backup!"
echo "Database: $POSTGRES_DB"
echo "Backup file: $BACKUP_FILE"
read -p "Are you sure? (yes/no): " -r
if [[ ! $REPLY =~ ^yes$ ]]; then
    echo "Restore cancelled"
    exit 0
fi

echo "[$(date)] Starting PostgreSQL restore..."

if gunzip -c "$BACKUP_FILE" | psql -h "$POSTGRES_HOST" -p "$POSTGRES_PORT" -U "$POSTGRES_USER" -d "$POSTGRES_DB"; then
    echo "[$(date)] ✅ Restore completed successfully"
else
    echo "[$(date)] ❌ Restore failed!"
    exit 1
fi
