#!/bin/bash
# ==========================================
# Docker Stack Integration Test
# ==========================================
# Validates all services are running correctly
# Exit code 0 = all tests passed
# Exit code 1 = one or more tests failed
# ==========================================

set -uo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Test counters
TESTS_PASSED=0
TESTS_FAILED=0

# Test result
test_result() {
    local test_name="$1"
    local result="$2"
    
    if [ "$result" -eq 0 ]; then
        echo -e "${GREEN}✅ PASS${NC}: $test_name"
        TESTS_PASSED=$((TESTS_PASSED + 1))
        return 0
    else
        echo -e "${RED}❌ FAIL${NC}: $test_name"
        TESTS_FAILED=$((TESTS_FAILED + 1))
        return 1
    fi
}

echo "=========================================="
echo "TRAVIAN T4.6 - DOCKER STACK INTEGRATION TEST"
echo "=========================================="
echo ""

# ==========================================
# TEST 1: Docker Compose Services Running
# ==========================================
echo "🔍 Testing Docker Compose services..."

test_service() {
    local service="$1"
    if docker compose ps "$service" | grep -q "Up"; then
        test_result "Service $service is running" 0
    else
        test_result "Service $service is running" 1
    fi
    return 0  # Continue to next test regardless of result
}

test_service "postgres"
test_service "mysql"
test_service "redis"
test_service "php-fpm"
test_service "nginx"

echo ""

# ==========================================
# TEST 2: PostgreSQL Connectivity
# ==========================================
echo "🔍 Testing PostgreSQL connectivity..."

if docker exec travian_postgres pg_isready -U travian >/dev/null 2>&1; then
    test_result "PostgreSQL is accepting connections" 0
else
    test_result "PostgreSQL is accepting connections" 1
fi

if docker exec travian_postgres psql -U travian -d travian_global -c "SELECT 1;" >/dev/null 2>&1; then
    test_result "PostgreSQL database query" 0
else
    test_result "PostgreSQL database query" 1
fi

echo ""

# ==========================================
# TEST 3: MySQL Connectivity
# ==========================================
echo "🔍 Testing MySQL connectivity..."

if docker exec travian_mysql mysqladmin -uroot -p"${MYSQL_ROOT_PASSWORD}" ping >/dev/null 2>&1; then
    test_result "MySQL is accepting connections" 0
else
    test_result "MySQL is accepting connections" 1
fi

if docker exec travian_mysql mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT 1;" >/dev/null 2>&1; then
    test_result "MySQL database query" 0
else
    test_result "MySQL database query" 1
fi

echo ""

# ==========================================
# TEST 4: Redis Connectivity
# ==========================================
echo "🔍 Testing Redis connectivity..."

if docker exec travian_redis redis-cli ping >/dev/null 2>&1; then
    test_result "Redis PING" 0
else
    test_result "Redis PING" 1
fi

if docker exec travian_redis redis-cli SET test_key test_value >/dev/null 2>&1 && \
   [ "$(docker exec travian_redis redis-cli GET test_key 2>/dev/null)" = "test_value" ]; then
    test_result "Redis SET/GET operations" 0
else
    test_result "Redis SET/GET operations" 1
fi

echo ""

# ==========================================
# TEST 5: Nginx + PHP-FPM
# ==========================================
echo "🔍 Testing Nginx + PHP-FPM..."

if curl -f -s http://localhost:5000 >/dev/null 2>&1; then
    test_result "Nginx HTTP response" 0
else
    test_result "Nginx HTTP response" 1
fi

if curl -f -s http://localhost:5000/nginx_status | grep -q "Active connections" 2>/dev/null; then
    test_result "Nginx stub_status endpoint" 0
else
    test_result "Nginx stub_status endpoint" 1
fi

echo ""

# ==========================================
# TEST 6: Health Checks
# ==========================================
echo "🔍 Testing health checks..."

for service in postgres mysql redis php-fpm nginx; do
    if docker inspect "travian_$service" | grep -q '"Health"' 2>/dev/null; then
        if docker inspect "travian_$service" | grep -q '"Status": "healthy"' 2>/dev/null; then
            test_result "Health check: $service" 0
        else
            test_result "Health check: $service" 1
        fi
    else
        echo -e "${YELLOW}⚠️  SKIP${NC}: Health check: $service (no healthcheck defined)"
    fi
done

echo ""

# ==========================================
# TEST 7: Worker Metrics
# ==========================================
echo "🔍 Testing worker metrics..."

if curl -f -s http://localhost:5000/metrics/workers | grep -q "worker_status" 2>/dev/null; then
    test_result "Worker metrics endpoint" 0
else
    test_result "Worker metrics endpoint" 1
fi

echo ""

# ==========================================
# TEST 8: Monitoring Stack (if running)
# ==========================================
echo "🔍 Testing monitoring stack..."

if docker ps | grep -q "travian_prometheus" 2>/dev/null; then
    if curl -f -s http://localhost:9090/-/healthy >/dev/null 2>&1; then
        test_result "Prometheus health" 0
    else
        test_result "Prometheus health" 1
    fi
else
    echo -e "${YELLOW}⚠️  SKIP${NC}: Prometheus (monitoring profile not running)"
fi

if docker ps | grep -q "travian_grafana" 2>/dev/null; then
    if curl -f -s http://localhost:3000/api/health >/dev/null 2>&1; then
        test_result "Grafana health" 0
    else
        test_result "Grafana health" 1
    fi
else
    echo -e "${YELLOW}⚠️  SKIP${NC}: Grafana (monitoring profile not running)"
fi

echo ""

# ==========================================
# TEST 9: LLM Services (if running)
# ==========================================
echo "🔍 Testing LLM services..."

if docker ps | grep -q "travian_ollama" 2>/dev/null; then
    if curl -f -s http://localhost:11434/api/tags >/dev/null 2>&1; then
        test_result "Ollama API" 0
    else
        test_result "Ollama API" 1
    fi
else
    echo -e "${YELLOW}⚠️  SKIP${NC}: Ollama (LLM profile not running)"
fi

if docker ps | grep -q "travian_vllm" 2>/dev/null; then
    if curl -f -s http://localhost:8000/health >/dev/null 2>&1; then
        test_result "vLLM API" 0
    else
        test_result "vLLM API" 1
    fi
else
    echo -e "${YELLOW}⚠️  SKIP${NC}: vLLM (LLM profile not running)"
fi

echo ""

# ==========================================
# TEST 10: Backup Scripts
# ==========================================
echo "🔍 Testing backup scripts..."

if [ -f scripts/backup-postgres.sh ]; then
    test_result "Backup script exists: backup-postgres.sh" 0
else
    test_result "Backup script exists: backup-postgres.sh" 1
fi

if [ -f scripts/backup-mysql.sh ]; then
    test_result "Backup script exists: backup-mysql.sh" 0
else
    test_result "Backup script exists: backup-mysql.sh" 1
fi

if [ -x scripts/backup-postgres.sh ]; then
    test_result "Backup script executable: backup-postgres.sh" 0
else
    test_result "Backup script executable: backup-postgres.sh" 1
fi

if [ -x scripts/backup-mysql.sh ]; then
    test_result "Backup script executable: backup-mysql.sh" 0
else
    test_result "Backup script executable: backup-mysql.sh" 1
fi

echo ""

# ==========================================
# TEST SUMMARY
# ==========================================
echo "=========================================="
echo "TEST SUMMARY"
echo "=========================================="
echo -e "Tests Passed: ${GREEN}$TESTS_PASSED${NC}"
echo -e "Tests Failed: ${RED}$TESTS_FAILED${NC}"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}✅ ALL TESTS PASSED${NC}"
    exit 0
else
    echo -e "${RED}❌ SOME TESTS FAILED${NC}"
    exit 1
fi
