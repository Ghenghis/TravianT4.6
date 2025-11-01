#!/bin/bash
# Incident Log Collection Script
# Collects logs and system state for incident investigation

set -e

INCIDENT_ID="${1:-$(date +%Y%m%d-%H%M%S)}"
OUTPUT_DIR="./incident-${INCIDENT_ID}"
TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")

echo "==================================="
echo "Incident Log Collection Script"
echo "==================================="
echo "Incident ID: ${INCIDENT_ID}"
echo "Timestamp: ${TIMESTAMP}"
echo "Output Directory: ${OUTPUT_DIR}"
echo "==================================="

# Create output directory
mkdir -p "${OUTPUT_DIR}"

echo "[1/10] Collecting system information..."
{
    echo "=== System Information ==="
    echo "Timestamp: ${TIMESTAMP}"
    echo "Hostname: $(hostname)"
    echo "Uptime: $(uptime)"
    echo "Kernel: $(uname -r)"
    echo ""
    echo "=== Memory Usage ==="
    free -h
    echo ""
    echo "=== Disk Usage ==="
    df -h
    echo ""
    echo "=== Network Interfaces ==="
    ip addr show || ifconfig
} > "${OUTPUT_DIR}/system-info.txt" 2>&1

echo "[2/10] Collecting Docker container status..."
{
    echo "=== Docker Containers ==="
    docker ps -a
    echo ""
    echo "=== Docker Stats (snapshot) ==="
    timeout 5 docker stats --no-stream --no-trunc || echo "Stats collection timed out"
    echo ""
    echo "=== Docker Networks ==="
    docker network ls
    echo ""
    echo "=== Docker Volumes ==="
    docker volume ls
} > "${OUTPUT_DIR}/docker-status.txt" 2>&1

echo "[3/10] Collecting application logs..."
if [ -d "/var/log/travian" ]; then
    mkdir -p "${OUTPUT_DIR}/app-logs"
    cp -r /var/log/travian/* "${OUTPUT_DIR}/app-logs/" 2>/dev/null || echo "No travian logs found"
else
    echo "Directory /var/log/travian not found" > "${OUTPUT_DIR}/app-logs-error.txt"
fi

echo "[4/10] Collecting nginx logs..."
if [ -d "/var/log/nginx" ]; then
    mkdir -p "${OUTPUT_DIR}/nginx-logs"
    tail -1000 /var/log/nginx/access.log > "${OUTPUT_DIR}/nginx-logs/access.log" 2>/dev/null || echo "No nginx access log"
    tail -1000 /var/log/nginx/error.log > "${OUTPUT_DIR}/nginx-logs/error.log" 2>/dev/null || echo "No nginx error log"
else
    echo "Directory /var/log/nginx not found" > "${OUTPUT_DIR}/nginx-logs-error.txt"
fi

echo "[5/10] Collecting Docker container logs..."
mkdir -p "${OUTPUT_DIR}/container-logs"
for container in $(docker ps -a --format '{{.Names}}'); do
    echo "Collecting logs for: ${container}"
    docker logs --tail=1000 "${container}" > "${OUTPUT_DIR}/container-logs/${container}.log" 2>&1 || true
done

echo "[6/10] Collecting database audit trail..."
if [ -n "${DATABASE_URL}" ]; then
    {
        echo "=== Recent Critical Events ==="
        docker exec travian_postgres psql "${DATABASE_URL}" -c "
            SELECT * FROM audit_events 
            WHERE severity IN ('critical', 'error') 
            ORDER BY created_at DESC 
            LIMIT 100;
        " 2>&1 || echo "Failed to query audit_events table"
        
        echo ""
        echo "=== Failed Authentication Attempts (Last Hour) ==="
        docker exec travian_postgres psql "${DATABASE_URL}" -c "
            SELECT * FROM audit_events 
            WHERE event_type = 'authentication' 
            AND details->>'success' = 'false'
            AND created_at > NOW() - INTERVAL '1 hour'
            ORDER BY created_at DESC;
        " 2>&1 || echo "Failed to query authentication events"
    } > "${OUTPUT_DIR}/audit-trail.txt"
else
    echo "DATABASE_URL not set, skipping audit trail collection" > "${OUTPUT_DIR}/audit-trail-error.txt"
fi

echo "[7/10] Collecting database connection status..."
{
    echo "=== PostgreSQL Connections ==="
    docker exec travian_postgres psql "${DATABASE_URL}" -c "
        SELECT count(*), state 
        FROM pg_stat_activity 
        GROUP BY state;
    " 2>&1 || echo "Failed to query PostgreSQL connections"
    
    echo ""
    echo "=== MySQL Processes ==="
    docker exec travian_mysql mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "SHOW PROCESSLIST;" 2>&1 || echo "Failed to query MySQL processes"
} > "${OUTPUT_DIR}/database-connections.txt"

echo "[8/10] Collecting Grafana dashboard snapshots..."
{
    echo "=== Prometheus Targets ==="
    curl -s http://localhost:9090/api/v1/targets 2>&1 || echo "Prometheus not accessible"
    
    echo ""
    echo "=== Alert Status ==="
    curl -s http://localhost:9090/api/v1/alerts 2>&1 || echo "Prometheus alerts not accessible"
} > "${OUTPUT_DIR}/monitoring-status.txt"

echo "[9/10] Collecting recent security events..."
{
    echo "=== WAF Blocks (Last 100) ==="
    docker logs travian_waf 2>&1 | grep -i "block\|deny\|403" | tail -100 || echo "No WAF logs or container not found"
    
    echo ""
    echo "=== Failed Login Attempts ==="
    grep -r "401\|403\|failed.*login\|authentication.*failed" /var/log/travian/ 2>/dev/null | tail -100 || echo "No failed login logs found"
} > "${OUTPUT_DIR}/security-events.txt"

echo "[10/10] Creating summary..."
{
    echo "==================================="
    echo "Incident Log Collection Summary"
    echo "==================================="
    echo "Incident ID: ${INCIDENT_ID}"
    echo "Collection Time: ${TIMESTAMP}"
    echo "Collection Completed: $(date +"%Y-%m-%d %H:%M:%S")"
    echo ""
    echo "Files Collected:"
    ls -lh "${OUTPUT_DIR}"
    echo ""
    echo "Total Size:"
    du -sh "${OUTPUT_DIR}"
    echo ""
    echo "==================================="
    echo "Next Steps:"
    echo "1. Review collected logs in ${OUTPUT_DIR}/"
    echo "2. Create incident ticket with ID: ${INCIDENT_ID}"
    echo "3. Archive logs: tar -czf ${INCIDENT_ID}.tar.gz ${OUTPUT_DIR}/"
    echo "4. Document findings in incident response playbook"
    echo "==================================="
} > "${OUTPUT_DIR}/SUMMARY.txt"

cat "${OUTPUT_DIR}/SUMMARY.txt"

echo ""
echo "✅ Log collection complete!"
echo "📁 Logs saved to: ${OUTPUT_DIR}/"
echo ""
echo "To create archive:"
echo "  tar -czf ${INCIDENT_ID}.tar.gz ${OUTPUT_DIR}/"
echo ""

exit 0
