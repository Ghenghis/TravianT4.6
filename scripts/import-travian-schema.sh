#!/bin/bash

set -e

MYSQL_HOST="${MYSQL_HOST:-mysql}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD}"
SCHEMA_FILE="main_script/include/schema/T4.4.sql"

echo "======================================"
echo "Travian Schema Import Script"
echo "======================================"
echo ""

if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
    echo "ERROR: MYSQL_ROOT_PASSWORD environment variable is not set"
    exit 1
fi

if [ ! -f "$SCHEMA_FILE" ]; then
    echo "ERROR: Schema file not found at $SCHEMA_FILE"
    exit 1
fi

echo "Waiting for MySQL to be ready..."
until mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1" > /dev/null 2>&1; do
    echo "  MySQL is not ready yet, waiting..."
    sleep 2
done
echo "✓ MySQL is ready"
echo ""

echo "Importing schema into travian_testworld..."
mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u root -p"$MYSQL_ROOT_PASSWORD" travian_testworld < "$SCHEMA_FILE"
echo "✓ Schema imported successfully into travian_testworld"
echo ""

echo "Importing schema into travian_demo..."
mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u root -p"$MYSQL_ROOT_PASSWORD" travian_demo < "$SCHEMA_FILE"
echo "✓ Schema imported successfully into travian_demo"
echo ""

echo "Verifying database tables..."
TESTWORLD_TABLES=$(mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u root -p"$MYSQL_ROOT_PASSWORD" -D travian_testworld -e "SHOW TABLES" | wc -l)
DEMO_TABLES=$(mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u root -p"$MYSQL_ROOT_PASSWORD" -D travian_demo -e "SHOW TABLES" | wc -l)

echo "  travian_testworld: $TESTWORLD_TABLES tables"
echo "  travian_demo: $DEMO_TABLES tables"
echo ""

echo "======================================"
echo "Schema import completed successfully!"
echo "======================================"
