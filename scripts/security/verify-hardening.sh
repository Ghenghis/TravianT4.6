#!/usr/bin/env bash
set -euo pipefail

# Verify container hardening is properly applied
# This script checks that all security measures are in place

echo "🔍 Verifying container security hardening..."
echo "============================================="
echo ""

ERRORS=0
WARNINGS=0

# Helper function to check and report
check_pass() {
    echo "✅ $1"
}

check_fail() {
    echo "❌ $1"
    ERRORS=$((ERRORS + 1))
}

check_warn() {
    echo "⚠️  $1"
    WARNINGS=$((WARNINGS + 1))
}

# Check if Docker is running
if ! docker info &> /dev/null; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
fi

# 1. Check Network Segmentation
echo "1. Checking network segmentation..."
echo "-----------------------------------"

if docker network ls | grep -q "travian_edge_public"; then
    check_pass "edge_public network exists"
else
    check_fail "edge_public network not found"
fi

if docker network ls | grep -q "travian_app_core"; then
    check_pass "app_core network exists"
else
    check_fail "app_core network not found"
fi

if docker network ls | grep -q "travian_data_services"; then
    check_pass "data_services network exists"
else
    check_fail "data_services network not found"
fi

if docker network ls | grep -q "travian_llm_gpu"; then
    check_pass "llm_gpu network exists"
else
    check_fail "llm_gpu network not found"
fi

# Check that data_services and llm_gpu are internal
if docker network inspect travian_data_services 2>/dev/null | grep -q '"Internal": true'; then
    check_pass "data_services network is internal (no external access)"
else
    check_warn "data_services network should be internal"
fi

if docker network inspect travian_llm_gpu 2>/dev/null | grep -q '"Internal": true'; then
    check_pass "llm_gpu network is internal (no external access)"
else
    check_warn "llm_gpu network should be internal"
fi

echo ""

# 2. Check Non-Root Users (only if containers are running)
echo "2. Checking non-root users..."
echo "-----------------------------"

if docker ps --format '{{.Names}}' | grep -q "travian_app"; then
    if docker exec travian_app id 2>/dev/null | grep -q "uid=1001"; then
        check_pass "php-fpm running as non-root (UID 1001)"
    else
        check_fail "php-fpm not running as non-root user"
    fi
else
    check_warn "php-fpm container not running (skipping user check)"
fi

if docker ps --format '{{.Names}}' | grep -q "travian_nginx"; then
    if docker exec travian_nginx id 2>/dev/null | grep -q "uid=1001"; then
        check_pass "nginx running as non-root (UID 1001)"
    else
        check_fail "nginx not running as non-root user"
    fi
else
    check_warn "nginx container not running (skipping user check)"
fi

echo ""

# 3. Check Resource Limits
echo "3. Checking resource limits..."
echo "------------------------------"

if docker ps --format '{{.Names}}' | grep -q "travian_app"; then
    MEMORY_LIMIT=$(docker inspect travian_app 2>/dev/null | jq -r '.[0].HostConfig.Memory' || echo "0")
    if [ "$MEMORY_LIMIT" != "0" ] && [ "$MEMORY_LIMIT" != "null" ]; then
        check_pass "php-fpm has memory limit: $((MEMORY_LIMIT / 1024 / 1024))Mi"
    else
        check_warn "php-fpm has no memory limit"
    fi
else
    check_warn "php-fpm container not running (skipping resource check)"
fi

if docker ps --format '{{.Names}}' | grep -q "travian_nginx"; then
    MEMORY_LIMIT=$(docker inspect travian_nginx 2>/dev/null | jq -r '.[0].HostConfig.Memory' || echo "0")
    if [ "$MEMORY_LIMIT" != "0" ] && [ "$MEMORY_LIMIT" != "null" ]; then
        check_pass "nginx has memory limit: $((MEMORY_LIMIT / 1024 / 1024))Mi"
    else
        check_warn "nginx has no memory limit"
    fi
else
    check_warn "nginx container not running (skipping resource check)"
fi

echo ""

# 4. Check Read-Only Filesystems
echo "4. Checking read-only filesystems..."
echo "------------------------------------"

if docker ps --format '{{.Names}}' | grep -q "travian_nginx"; then
    READONLY=$(docker inspect travian_nginx 2>/dev/null | jq -r '.[0].HostConfig.ReadonlyRootfs' || echo "false")
    if [ "$READONLY" == "true" ]; then
        check_pass "nginx has read-only rootfs"
    else
        check_warn "nginx rootfs is writable (should be read-only for security)"
    fi
else
    check_warn "nginx container not running (skipping read-only check)"
fi

echo ""

# 5. Check Port Exposure
echo "5. Checking port exposure..."
echo "----------------------------"

EXPOSED_PORTS=$(docker ps --format '{{.Names}} {{.Ports}}' 2>/dev/null | grep -E "travian_(postgres|mysql|redis|ollama|vllm)" || true)

if [ -z "$EXPOSED_PORTS" ]; then
    check_pass "Internal services (postgres, mysql, redis, ollama, vllm) have no exposed ports"
else
    check_fail "Internal services should not expose ports to host:"
    echo "$EXPOSED_PORTS" | while read line; do
        echo "   $line"
    done
fi

echo ""

# 6. Check Security Options
echo "6. Checking security options..."
echo "--------------------------------"

if docker ps --format '{{.Names}}' | grep -q "travian_app"; then
    SECOPT=$(docker inspect travian_app 2>/dev/null | jq -r '.[0].HostConfig.SecurityOpt[]?' || echo "")
    if echo "$SECOPT" | grep -q "no-new-privileges"; then
        check_pass "php-fpm has no-new-privileges security option"
    else
        check_warn "php-fpm should have no-new-privileges security option"
    fi
else
    check_warn "php-fpm container not running (skipping security options check)"
fi

echo ""

# Summary
echo "============================================="
echo "Security Hardening Verification Summary"
echo "============================================="
echo ""

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo "✅ All security hardening checks passed!"
    echo ""
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo "⚠️  All critical checks passed, but there are $WARNINGS warnings"
    echo "   Review the warnings above and consider addressing them."
    echo ""
    exit 0
else
    echo "❌ Security hardening verification failed with $ERRORS errors and $WARNINGS warnings"
    echo "   Please review and fix the issues above."
    echo ""
    exit 1
fi
