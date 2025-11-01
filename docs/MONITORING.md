# Travian T4.6 Monitoring Stack

## Table of Contents
- [Overview](#overview)
- [Architecture](#architecture)
- [Quick Start](#quick-start)
- [Metrics Collection](#metrics-collection)
- [Dashboards](#dashboards)
- [Alerts](#alerts)
- [Troubleshooting](#troubleshooting)
- [Scaling Recommendations](#scaling-recommendations)
- [API Reference](#api-reference)

---

## Overview

The Travian T4.6 monitoring stack provides comprehensive observability for all system components using **Prometheus** for metrics collection and **Grafana** for visualization. The stack monitors:

- **Databases**: PostgreSQL (global DB + AI-NPC) and MySQL (game worlds)
- **Caching**: Redis sessions and cache
- **Workers**: Automation, AI Decision, and Spawn Scheduler
- **LLM Backends**: Ollama and vLLM performance
- **System Resources**: CPU, memory, disk, network

### Key Features

✅ **Real-time Monitoring**: 15-second scrape intervals  
✅ **30-Day Retention**: Historical data for trend analysis  
✅ **Auto-provisioned Dashboards**: Pre-configured Grafana dashboards  
✅ **Smart Alerting**: Critical service alerts with configurable thresholds  
✅ **Multi-backend Support**: Monitor multiple LLM backends simultaneously  
✅ **Modular Deployment**: Deploy monitoring stack separately with Docker profiles  

---

## Architecture

### Components

```
┌─────────────────────────────────────────────────────────────┐
│                     Grafana (Port 3000)                     │
│                  Visualization & Dashboards                  │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                  Prometheus (Port 9090)                     │
│              Metrics Collection & Storage                    │
└─────────────────────┬───────────────────────────────────────┘
                      │
        ┌─────────────┼─────────────┐
        │             │             │
        ▼             ▼             ▼
┌──────────────┐ ┌──────────┐ ┌─────────────┐
│   Node       │ │ Database │ │   Redis     │
│  Exporter    │ │ Exporters│ │  Exporter   │
│  (Port 9100) │ │ (9187,   │ │ (Port 9121) │
│              │ │  9104)   │ │             │
└──────────────┘ └──────────┘ └─────────────┘

┌─────────────────────────────────────────────────────────────┐
│              Application Components                         │
│  PostgreSQL | MySQL | Redis | Workers | Ollama | vLLM      │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow

1. **Exporters** scrape metrics from services (databases, Redis, etc.)
2. **Prometheus** pulls metrics from exporters every 15 seconds
3. **Prometheus** evaluates alert rules every 15 seconds
4. **Grafana** queries Prometheus for dashboard visualizations
5. **Alerts** trigger when thresholds are exceeded

---

## Quick Start

### 1. Prerequisites

Ensure you have the following in `.env.production`:

```bash
# Grafana Credentials
GRAFANA_ADMIN_USER=admin
GRAFANA_ADMIN_PASSWORD=your_secure_password

# Database URLs
DATABASE_URL=postgresql://postgres:password@postgres:5432/travian_global
MYSQL_ROOT_PASSWORD=your_mysql_password
REDIS_PASSWORD=your_redis_password
```

### 2. Deploy Monitoring Stack

```bash
# Start monitoring services
docker compose --profile monitoring up -d

# Verify services are running
docker ps | grep -E "prometheus|grafana|exporter"
```

### 3. Access Dashboards

- **Grafana**: http://localhost:3000
  - Username: `admin` (or `$GRAFANA_ADMIN_USER`)
  - Password: `admin` (or `$GRAFANA_ADMIN_PASSWORD`)
  
- **Prometheus**: http://localhost:9090

### 4. Explore Pre-configured Dashboards

Navigate to **Dashboards → Travian T4.6** folder:

1. **Database Health** - PostgreSQL & MySQL metrics
2. **Worker Throughput** - Automation, AI, and Spawn Scheduler
3. **LLM Latency** - Ollama & vLLM performance

---

## Metrics Collection

### Exporters Overview

| Exporter | Port | Monitored Service | Key Metrics |
|----------|------|-------------------|-------------|
| **Node Exporter** | 9100 | Host System | CPU, Memory, Disk, Network |
| **PostgreSQL Exporter** | 9187 | PostgreSQL | Connections, Transactions, Cache Hit Ratio |
| **MySQL Exporter** | 9104 | MySQL | Queries, Connections, Replication |
| **Redis Exporter** | 9121 | Redis | Memory Usage, Commands, Keys |

### PostgreSQL Metrics

**Key Metrics Collected:**

- `pg_stat_database_xact_commit` - Transaction commits
- `pg_stat_database_xact_rollback` - Transaction rollbacks
- `pg_stat_activity_count` - Active connections
- `pg_stat_database_blks_hit` - Cache hits
- `pg_stat_database_blks_read` - Disk reads
- `pg_stat_database_tup_inserted` - Rows inserted
- `pg_stat_database_tup_updated` - Rows updated
- `pg_stat_database_tup_deleted` - Rows deleted

**Cache Hit Ratio Formula:**
```promql
rate(pg_stat_database_blks_hit[5m]) / 
(rate(pg_stat_database_blks_hit[5m]) + rate(pg_stat_database_blks_read[5m]))
```

**Optimal Cache Hit Ratio**: > 99%

### MySQL Metrics

**Key Metrics Collected:**

- `mysql_global_status_queries` - Total queries
- `mysql_global_status_threads_connected` - Active connections
- `mysql_global_status_commands_total{command="select"}` - SELECT queries
- `mysql_global_status_commands_total{command="insert"}` - INSERT queries
- `mysql_global_status_commands_total{command="update"}` - UPDATE queries
- `mysql_global_status_slow_queries` - Slow queries

### Redis Metrics

**Key Metrics Collected:**

- `redis_memory_used_bytes` - Memory usage
- `redis_commands_total` - Commands processed
- `redis_connected_clients` - Active connections
- `redis_evicted_keys_total` - Evicted keys
- `redis_keyspace_hits_total` - Cache hits
- `redis_keyspace_misses_total` - Cache misses

**Cache Hit Ratio Formula:**
```promql
rate(redis_keyspace_hits_total[5m]) / 
(rate(redis_keyspace_hits_total[5m]) + rate(redis_keyspace_misses_total[5m]))
```

### Worker Metrics

**Custom Application Metrics** (to be implemented in worker code):

```promql
# Worker Status
worker_automation_running
worker_ai_decision_running
worker_spawn_scheduler_running

# Throughput
rate(worker_automation_tasks_processed_total[5m])
rate(worker_ai_decision_tasks_processed_total[5m])
rate(worker_spawn_scheduler_tasks_processed_total[5m])

# Queue Depth
worker_automation_queue_length
worker_ai_decision_queue_length
worker_spawn_scheduler_queue_length

# Latency
histogram_quantile(0.95, rate(worker_automation_task_duration_ms_bucket[5m]))
histogram_quantile(0.95, rate(worker_ai_decision_task_duration_ms_bucket[5m]))

# Error Rate
rate(worker_automation_errors_total[5m])
rate(worker_ai_decision_errors_total[5m])
```

### LLM Metrics

**Custom Application Metrics** (to be implemented in LLM client code):

```promql
# Request Rate
rate(llm_requests_total{backend="ollama"}[5m])
rate(llm_requests_total{backend="vllm"}[5m])

# Latency
histogram_quantile(0.95, rate(llm_request_duration_ms_bucket{backend="ollama"}[5m]))
histogram_quantile(0.99, rate(llm_request_duration_ms_bucket{backend="vllm"}[5m]))

# Tokens Generated
llm_tokens_generated_total{backend="ollama"}
llm_tokens_generated_total{backend="vllm"}

# Error Rate
rate(llm_errors_total{backend="ollama"}[5m])
rate(llm_errors_total{backend="vllm"}[5m])

# GPU Utilization (if available)
llm_gpu_utilization_percent{backend="ollama"}
llm_gpu_memory_used_bytes{backend="vllm"}
```

---

## Dashboards

### 1. Database Health Dashboard

**Purpose**: Monitor PostgreSQL and MySQL performance and health.

**Panels:**

- **Status Indicators**: PostgreSQL/MySQL up/down status
- **Active Connections**: Gauge showing current connections vs. limits
- **Transaction Rate**: Line graph of commits/rollbacks per second
- **Query Rate**: MySQL SELECT/INSERT/UPDATE rates
- **Cache Hit Ratio**: PostgreSQL buffer cache efficiency
- **I/O Time**: Disk read/write latency
- **Row Operations**: INSERT/UPDATE/DELETE counts

**Key Insights:**

- ✅ **Green Status**: Both databases operational
- ⚠️ **Yellow**: Connections approaching limits (>80%)
- 🔴 **Red**: Database down or connections exhausted

### 2. Worker Throughput Dashboard

**Purpose**: Monitor background worker performance and queue depths.

**Panels:**

- **Worker Status**: Running/Stopped for each worker
- **Throughput**: Tasks processed per second
- **Queue Length**: Pending tasks in each queue
- **Task Duration**: P95/P99 latency for task processing
- **Error Rate**: Errors per second by worker
- **CPU Usage**: Worker CPU consumption

**Key Insights:**

- ✅ **Healthy**: Queue length < 100, P95 latency < 500ms
- ⚠️ **Warning**: Queue growing, P95 > 1000ms
- 🔴 **Critical**: Worker stopped or queue > 500

### 3. LLM Latency Dashboard

**Purpose**: Monitor Ollama and vLLM performance.

**Panels:**

- **LLM Status**: Ollama/vLLM up/down status
- **Request Rate**: Requests per second
- **Latency Distribution**: P50/P95/P99 response times
- **Tokens Generated**: Total tokens produced
- **Error Rate**: Failed requests per second
- **GPU Utilization**: GPU usage percentage (if available)
- **GPU Memory**: VRAM consumption

**Key Insights:**

- ✅ **Optimal**: P95 < 1000ms (Ollama), P95 < 500ms (vLLM)
- ⚠️ **Slow**: P95 > 2000ms
- 🔴 **Critical**: LLM down or P99 > 10000ms

**SLA Targets:**

- **Ollama (CPU)**: P95 < 3000ms, P99 < 5000ms
- **vLLM (GPU)**: P95 < 500ms, P99 < 1000ms

---

## Alerts

### Alert Configuration

Alerts are defined in `prometheus/alerts.yml` and evaluated every 30 seconds.

### Critical Alerts

#### PostgreSQLDown
- **Trigger**: PostgreSQL unreachable for > 1 minute
- **Severity**: Critical
- **Action**: 
  1. Check PostgreSQL container: `docker ps | grep postgres`
  2. View logs: `docker logs travian_postgres`
  3. Restart if needed: `docker compose restart postgres`

#### MySQLDown
- **Trigger**: MySQL unreachable for > 1 minute
- **Severity**: Critical
- **Action**: 
  1. Check MySQL container: `docker ps | grep mysql`
  2. View logs: `docker logs travian_mysql`
  3. Restart if needed: `docker compose restart mysql`

#### RedisDown
- **Trigger**: Redis unreachable for > 1 minute
- **Severity**: Critical
- **Action**: 
  1. Check Redis container: `docker ps | grep redis`
  2. View logs: `docker logs travian_redis`
  3. Restart if needed: `docker compose restart redis`

### Warning Alerts

#### OllamaDown
- **Trigger**: Ollama unreachable for > 5 minutes
- **Severity**: Warning
- **Action**: 
  1. Check if Ollama profile is enabled
  2. View logs: `docker logs travian_ollama`
  3. Verify model is loaded

#### HighCPUUsage
- **Trigger**: CPU usage > 80% for > 10 minutes
- **Severity**: Warning
- **Action**: 
  1. Identify top CPU processes: `docker stats`
  2. Scale workers if needed
  3. Optimize queries or cache settings

#### HighMemoryUsage
- **Trigger**: Memory usage > 90% for > 5 minutes
- **Severity**: Warning
- **Action**: 
  1. Check memory consumption: `docker stats`
  2. Clear Redis cache if safe
  3. Restart memory-intensive services

### Customizing Alerts

Edit `prometheus/alerts.yml`:

```yaml
- alert: CustomAlert
  expr: metric_name > threshold
  for: 5m
  labels:
    severity: warning
  annotations:
    summary: "Alert summary"
    description: "Detailed description"
```

**Reload Prometheus configuration:**
```bash
docker exec travian_prometheus kill -HUP 1
```

---

## Troubleshooting

### Common Issues

#### 1. Grafana Dashboards Not Loading

**Symptoms**: Empty dashboard list or "No data" errors

**Solutions:**

```bash
# Check Grafana logs
docker logs travian_grafana

# Verify Prometheus datasource
curl http://localhost:3000/api/datasources

# Restart Grafana
docker compose --profile monitoring restart grafana
```

#### 2. Prometheus Not Scraping Targets

**Symptoms**: Targets show as "Down" in Prometheus UI

**Solutions:**

```bash
# Check Prometheus targets
curl http://localhost:9090/api/v1/targets

# Verify network connectivity
docker exec travian_prometheus ping postgres-exporter

# Check exporter logs
docker logs travian_postgres_exporter
docker logs travian_mysql_exporter
```

#### 3. High Cardinality Warnings

**Symptoms**: Prometheus logs show "too many samples" warnings

**Solutions:**

- Reduce scrape interval in `prometheus.yml`
- Add relabel configs to drop unnecessary labels
- Increase Prometheus memory limits

#### 4. Missing Metrics

**Symptoms**: Specific metrics not appearing in Grafana

**Solutions:**

```bash
# Query Prometheus directly
curl 'http://localhost:9090/api/v1/query?query=metric_name'

# Check if metric is being scraped
curl http://localhost:9187/metrics | grep metric_name

# Verify exporter configuration
docker inspect travian_postgres_exporter
```

### Debug Mode

Enable Prometheus debug logging:

```yaml
# docker-compose.monitoring.yml
prometheus:
  command:
    - '--log.level=debug'
```

---

## Scaling Recommendations

### When to Scale

Monitor these key indicators:

| Metric | Threshold | Action |
|--------|-----------|--------|
| PostgreSQL Connections | > 80% max | Increase `max_connections` |
| MySQL Connections | > 80% max | Add read replicas |
| Redis Memory | > 90% | Increase memory or add nodes |
| Worker Queue Length | > 500 | Scale worker containers |
| LLM P95 Latency | > 2000ms | Add GPU or switch backend |

### Vertical Scaling

**Increase container resources:**

```yaml
# docker-compose.yml
postgres:
  deploy:
    resources:
      limits:
        cpus: '4'
        memory: 8G
      reservations:
        cpus: '2'
        memory: 4G
```

### Horizontal Scaling

**Add worker replicas:**

```bash
# Scale automation workers
docker compose up -d --scale worker-automation=3

# Scale AI decision workers
docker compose up -d --scale worker-ai-decision=2
```

**Add MySQL read replicas:**

```yaml
# docker-compose.yml
mysql-replica:
  image: mysql:8.0
  command: --server-id=2 --read-only=1
  environment:
    MYSQL_REPLICATION_MODE: slave
```

### Prometheus Scaling

**For large deployments (>1000 time series):**

1. **Increase retention time**:
   ```yaml
   - '--storage.tsdb.retention.time=90d'
   ```

2. **Enable remote write** (e.g., to Thanos, Cortex):
   ```yaml
   remote_write:
     - url: "http://thanos:10901/api/v1/receive"
   ```

3. **Add federation** for multiple Prometheus instances

### Grafana Scaling

**For high traffic:**

1. Use MySQL/PostgreSQL backend instead of SQLite
2. Enable caching
3. Load balance with HAProxy/Nginx

---

## API Reference

### Prometheus HTTP API

**Query current metrics:**
```bash
curl 'http://localhost:9090/api/v1/query?query=up'
```

**Query time range:**
```bash
curl 'http://localhost:9090/api/v1/query_range?query=up&start=2025-10-30T00:00:00Z&end=2025-10-30T23:59:59Z&step=15s'
```

**List all metrics:**
```bash
curl http://localhost:9090/api/v1/label/__name__/values
```

### Grafana HTTP API

**Get all dashboards:**
```bash
curl -u admin:admin http://localhost:3000/api/search?type=dash-db
```

**Create API key:**
```bash
curl -X POST -H "Content-Type: application/json" \
  -d '{"name":"apikey", "role": "Viewer"}' \
  -u admin:admin http://localhost:3000/api/auth/keys
```

### Exporter Endpoints

| Exporter | Metrics Endpoint |
|----------|------------------|
| Node Exporter | http://localhost:9100/metrics |
| PostgreSQL Exporter | http://localhost:9187/metrics |
| MySQL Exporter | http://localhost:9104/metrics |
| Redis Exporter | http://localhost:9121/metrics |

---

## Security Best Practices

### 1. Secure Grafana

```yaml
# grafana/grafana.ini
[security]
admin_password = ${GRAFANA_ADMIN_PASSWORD}
secret_key = ${GRAFANA_SECRET_KEY}
disable_gravatar = true

[auth.anonymous]
enabled = false
```

### 2. Restrict Prometheus Access

Use Nginx reverse proxy with basic auth:

```nginx
location /prometheus/ {
    auth_basic "Prometheus";
    auth_basic_user_file /etc/nginx/.htpasswd;
    proxy_pass http://prometheus:9090/;
}
```

### 3. Secure Exporters

Add network isolation:

```yaml
# docker-compose.monitoring.yml
networks:
  monitoring_internal:
    internal: true
```

### 4. Encrypt Data in Transit

Enable TLS for Grafana:

```yaml
[server]
protocol = https
cert_file = /etc/grafana/ssl/cert.pem
cert_key = /etc/grafana/ssl/key.pem
```

---

## Maintenance

### Daily Tasks

- ✅ Check Grafana dashboards for anomalies
- ✅ Review Prometheus alerts
- ✅ Verify all targets are up

### Weekly Tasks

- ✅ Review query performance in dashboards
- ✅ Check Prometheus storage usage
- ✅ Update Grafana plugins

### Monthly Tasks

- ✅ Review and update alert thresholds
- ✅ Cleanup old Prometheus data
- ✅ Backup Grafana dashboards

### Backup Commands

```bash
# Backup Grafana dashboards
docker exec travian_grafana grafana-cli admin export-dashboard > backup.json

# Backup Prometheus data
docker run --rm -v prometheus_data:/data -v $(pwd):/backup \
  alpine tar czf /backup/prometheus-backup.tar.gz /data

# Backup Grafana SQLite database
docker cp travian_grafana:/var/lib/grafana/grafana.db ./grafana-backup.db
```

---

## Additional Resources

- [Prometheus Documentation](https://prometheus.io/docs/)
- [Grafana Documentation](https://grafana.com/docs/)
- [PostgreSQL Exporter](https://github.com/prometheus-community/postgres_exporter)
- [MySQL Exporter](https://github.com/prometheus/mysqld_exporter)
- [Redis Exporter](https://github.com/oliver006/redis_exporter)
- [Node Exporter](https://github.com/prometheus/node_exporter)

---

## Support

For monitoring-related issues:

1. Check this documentation first
2. Review container logs: `docker logs <container_name>`
3. Verify configuration files in `prometheus/` and `grafana/`
4. Test metrics endpoints directly with curl
5. Consult Prometheus/Grafana community forums

**Monitoring Stack Version**: 1.0  
**Last Updated**: October 30, 2025  
**Maintained By**: Travian T4.6 DevOps Team
