#!/bin/bash
set -e

# ==========================================
# TRAVIAN T4.6 - DATABASE INITIALIZATION
# ==========================================
# Initializes PostgreSQL (global + AI-NPC) and MySQL (game worlds)
# Idempotent: Safe to run multiple times
# ==========================================

echo "========================================"
echo "Travian T4.6 - Database Initialization"
echo "========================================"

# ==========================================
# ENVIRONMENT VARIABLES
# ==========================================
POSTGRES_HOST="${PGHOST:-localhost}"
POSTGRES_PORT="${PGPORT:-5432}"
POSTGRES_USER="${PGUSER:-postgres}"
POSTGRES_PASSWORD="${PGPASSWORD}"
POSTGRES_DB="${PGDATABASE:-travian_global}"

MYSQL_HOST="${MYSQL_HOST:-localhost}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD}"
MYSQL_USER="${MYSQL_USER:-travian_user}"
MYSQL_PASSWORD="${MYSQL_PASSWORD}"

# Wait timeouts (seconds)
POSTGRES_TIMEOUT=60
MYSQL_TIMEOUT=60

# ==========================================
# FUNCTIONS
# ==========================================

# Wait for PostgreSQL to be ready
wait_for_postgres() {
    echo "[PostgreSQL] Waiting for database to be ready..."
    
    local timeout=$POSTGRES_TIMEOUT
    local elapsed=0
    
    while [ $elapsed -lt $timeout ]; do
        if pg_isready -h "$POSTGRES_HOST" -p "$POSTGRES_PORT" -U "$POSTGRES_USER" > /dev/null 2>&1; then
            echo "[PostgreSQL] Database is ready!"
            return 0
        fi
        echo "[PostgreSQL] Waiting... ($elapsed/$timeout seconds)"
        sleep 2
        elapsed=$((elapsed + 2))
    done
    
    echo "[PostgreSQL] ERROR: Timeout waiting for database!"
    return 1
}

# Wait for MySQL to be ready
wait_for_mysql() {
    echo "[MySQL] Waiting for database to be ready..."
    
    local timeout=$MYSQL_TIMEOUT
    local elapsed=0
    
    while [ $elapsed -lt $timeout ]; do
        if mysqladmin ping -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u root -p"$MYSQL_ROOT_PASSWORD" > /dev/null 2>&1; then
            echo "[MySQL] Database is ready!"
            return 0
        fi
        echo "[MySQL] Waiting... ($elapsed/$timeout seconds)"
        sleep 2
        elapsed=$((elapsed + 2))
    done
    
    echo "[MySQL] ERROR: Timeout waiting for database!"
    return 1
}

# Initialize PostgreSQL schema
init_postgres() {
    echo "[PostgreSQL] Initializing schema..."
    
    # Check if schema already exists
    local table_count=$(PGPASSWORD="$POSTGRES_PASSWORD" psql -h "$POSTGRES_HOST" -p "$POSTGRES_PORT" -U "$POSTGRES_USER" -d "$POSTGRES_DB" -t -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ('players', 'ai_configs', 'worlds');" 2>/dev/null | tr -d ' ')
    
    if [ "$table_count" = "3" ]; then
        echo "[PostgreSQL] Schema already initialized (found core tables)"
        return 0
    fi
    
    echo "[PostgreSQL] Applying schema from complete-automation-ai-system.sql..."
    
    if [ -f "database/schemas/complete-automation-ai-system.sql" ]; then
        PGPASSWORD="$POSTGRES_PASSWORD" psql -h "$POSTGRES_HOST" -p "$POSTGRES_PORT" -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f "database/schemas/complete-automation-ai-system.sql"
        echo "[PostgreSQL] Schema initialized successfully!"
    else
        echo "[PostgreSQL] ERROR: Schema file not found: database/schemas/complete-automation-ai-system.sql"
        return 1
    fi
}

# Initialize MySQL databases
init_mysql() {
    echo "[MySQL] Initializing game world databases..."
    
    # Create databases if they don't exist
    mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u root -p"$MYSQL_ROOT_PASSWORD" <<-EOSQL
        CREATE DATABASE IF NOT EXISTS \`travian_global\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE DATABASE IF NOT EXISTS \`travian_testworld\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE DATABASE IF NOT EXISTS \`travian_demo\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        
        FLUSH PRIVILEGES;
EOSQL
    
    echo "[MySQL] Databases created successfully!"
    
    # Create/update user permissions
    mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u root -p"$MYSQL_ROOT_PASSWORD" <<-EOSQL
        CREATE USER IF NOT EXISTS '$MYSQL_USER'@'%' IDENTIFIED BY '$MYSQL_PASSWORD';
        
        GRANT ALL PRIVILEGES ON travian_global.* TO '$MYSQL_USER'@'%';
        GRANT ALL PRIVILEGES ON travian_testworld.* TO '$MYSQL_USER'@'%';
        GRANT ALL PRIVILEGES ON travian_demo.* TO '$MYSQL_USER'@'%';
        
        FLUSH PRIVILEGES;
EOSQL
    
    echo "[MySQL] User permissions granted successfully!"
}

# Verify PostgreSQL tables
verify_postgres() {
    echo "[PostgreSQL] Verifying schema..."
    
    local tables=$(PGPASSWORD="$POSTGRES_PASSWORD" psql -h "$POSTGRES_HOST" -p "$POSTGRES_PORT" -U "$POSTGRES_USER" -d "$POSTGRES_DB" -t -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public';" 2>/dev/null | tr -d ' ')
    
    echo "[PostgreSQL] Found $tables tables"
    
    if [ "$tables" -gt "0" ]; then
        echo "[PostgreSQL] ✓ Schema verified successfully!"
        return 0
    else
        echo "[PostgreSQL] ✗ Schema verification failed!"
        return 1
    fi
}

# Verify MySQL databases
verify_mysql() {
    echo "[MySQL] Verifying databases..."
    
    local dbs=$(mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u root -p"$MYSQL_ROOT_PASSWORD" -e "SHOW DATABASES LIKE 'travian_%';" | grep -c "travian_" || echo "0")
    
    echo "[MySQL] Found $dbs game world databases"
    
    if [ "$dbs" -ge "3" ]; then
        echo "[MySQL] ✓ Databases verified successfully!"
        return 0
    else
        echo "[MySQL] ✗ Database verification failed!"
        return 1
    fi
}

# ==========================================
# MAIN EXECUTION
# ==========================================

echo ""
echo "Step 1: Waiting for databases to be ready..."
echo "--------------------------------------------"
wait_for_postgres || exit 1
wait_for_mysql || exit 1

echo ""
echo "Step 2: Initializing PostgreSQL..."
echo "--------------------------------------------"
init_postgres || exit 1

echo ""
echo "Step 3: Initializing MySQL..."
echo "--------------------------------------------"
init_mysql || exit 1

echo ""
echo "Step 4: Verifying databases..."
echo "--------------------------------------------"
verify_postgres || exit 1
verify_mysql || exit 1

echo ""
echo "========================================"
echo "✓ Database initialization completed!"
echo "========================================"
echo ""
echo "PostgreSQL: $POSTGRES_HOST:$POSTGRES_PORT/$POSTGRES_DB"
echo "MySQL: $MYSQL_HOST:$MYSQL_PORT (3 databases)"
echo ""
