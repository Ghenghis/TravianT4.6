# Troubleshooting Guide - TravianT4.6

## Overview

This guide provides symptom-based troubleshooting for common issues in the TravianT4.6 production environment. Each issue includes possible causes, validation commands, solutions, and escalation paths.

**How to Use This Guide:**
1. Find the symptom category matching your issue
2. Run validation commands to confirm the problem
3. Apply suggested solutions
4. Escalate to INCIDENT-RESPONSE.md if unresolved

**When to Escalate:**
- Issue persists after trying solutions
- P1/P2 severity (see INCIDENT-RESPONSE.md)
- Service degradation >15 minutes
- Data integrity concerns
- Security incident suspected

**Target Audience:** Operators, on-call engineers, support team

---

## Deployment Issues

### Symptom: Docker Compose Fails to Start

**Indicators:**
- `docker compose up -d` exits with error
- Services fail to start
- "port already in use" errors
- "cannot bind to port" errors

**Possible Causes:**
- Port conflicts with existing services
- Missing environment variables
- Insufficient disk space
- Docker daemon not running
- Invalid docker-compose.yml syntax

**Validation Commands:**

```bash
# Check Docker daemon
docker ps

# Check port conflicts
sudo netstat -tulpn | grep -E "80|443|3000|3306|5432|6379|9090"

# Check environment file
cat .env | grep -E "POSTGRES_PASSWORD|MYSQL_ROOT_PASSWORD|REDIS_PASSWORD"

# Check disk space
df -h

# Validate docker-compose.yml syntax
docker compose config
```

**Solutions:**

**If port conflicts:**
```bash
# Identify process using port
sudo lsof -i :80
sudo lsof -i :3306

# Stop conflicting process or change port in docker-compose.yml
```

**If missing environment variables:**
```bash
# Regenerate .env file
./scripts/secrets/generate-env.sh production
cp .env.production .env
```

**If insufficient disk space:**
```bash
# Clean Docker resources
docker system prune -a -f

# Remove old images
docker image prune -a -f

# Check space again
df -h
```

**If Docker daemon not running:**
```bash
# Start Docker Desktop (Windows)
# Or restart Docker service (Linux)
sudo systemctl restart docker
```

**Escalation Path:**
- If port conflicts persist: Contact infrastructure team
- If disk full despite cleanup: Escalate to P2 incident
- If Docker daemon issues: Check Docker Desktop logs

---

### Symptom: Services Show "Unhealthy" Status

**Indicators:**
- `docker compose ps` shows "unhealthy"
- Health check failures in logs
- Services restarting repeatedly

**Possible Causes:**
- Incorrect database credentials
- Services not fully initialized (need more time)
- Database connection exhausted
- Out of memory
- Network connectivity issues

**Validation Commands:**

```bash
# Check container health
docker compose ps

# Check health check logs
docker inspect travian_postgres | jq '.[0].State.Health'
docker inspect travian_mysql | jq '.[0].State.Health'

# Check container logs
docker compose logs postgres --tail=50
docker compose logs mysql --tail=50
docker compose logs redis --tail=50

# Check resource usage
docker stats --no-stream

# Test connectivity
docker exec travian_app ping -c 3 postgres
docker exec travian_app ping -c 3 mysql
```

**Solutions:**

**If database connection issues:**
```bash
# Verify credentials in .env
grep -E "POSTGRES_PASSWORD|MYSQL_ROOT_PASSWORD" .env

# Test PostgreSQL connection
docker exec travian_postgres psql -U postgres -c "SELECT 1;"

# Test MySQL connection
docker exec travian_mysql mysql -uroot -p$MYSQL_ROOT_PASSWORD -e "SELECT 1;"
```

**If services need more initialization time:**
```bash
# Wait 2-3 minutes for full startup
sleep 180
docker compose ps

# Check again
```

**If out of memory:**
```bash
# Check memory limits
docker inspect travian_postgres | grep -i memory

# Increase memory limits in docker-compose.yml
# Restart services
docker compose restart postgres
```

**Escalation Path:**
- If unhealthy >5 minutes: Check detailed logs
- If memory issues persist: Escalate for capacity planning
- If database credentials wrong: Escalate to P2 (data access issue)

---

### Symptom: GPU Not Detected in Containers

**Indicators:**
- `nvidia-smi` fails in containers
- Ollama/vLLM fail to start
- "CUDA not found" errors
- LLM services use CPU instead of GPU

**Possible Causes:**
- NVIDIA Container Toolkit not installed
- Docker runtime not configured for GPU
- NVIDIA drivers not installed on host
- Incorrect GPU device mapping

**Validation Commands:**

```bash
# Verify GPUs visible on host (WSL2)
nvidia-smi

# Test GPU access from Docker
docker run --rm --gpus all nvidia/cuda:12.0.0-base-ubuntu22.04 nvidia-smi

# Check Ollama container
docker exec travian_ollama nvidia-smi

# Check vLLM container
docker exec travian_vllm nvidia-smi

# Verify GPU runtime config
docker info | grep -i runtime
```

**Solutions:**

**If NVIDIA Container Toolkit not installed:**
```bash
# Install toolkit (in WSL2 Ubuntu)
distribution=$(. /etc/os-release;echo $ID$VERSION_ID)
curl -fsSL https://nvidia.github.io/libnvidia-container/gpgkey | sudo gpg --dearmor -o /usr/share/keyrings/nvidia-container-toolkit-keyring.gpg

curl -s -L https://nvidia.github.io/libnvidia-container/$distribution/libnvidia-container.list | \
    sed 's#deb https://#deb [signed-by=/usr/share/keyrings/nvidia-container-toolkit-keyring.gpg] https://#g' | \
    sudo tee /etc/apt/sources.list.d/nvidia-container-toolkit.list

sudo apt-get update
sudo apt-get install -y nvidia-container-toolkit

# Configure Docker
sudo nvidia-ctk runtime configure --runtime=docker

# Restart Docker Desktop
```

**If driver issues:**
```bash
# Check driver version on Windows host
# Open NVIDIA Control Panel → Help → System Information
# Minimum required: 535.xx or later

# Update drivers if needed
# Download from https://www.nvidia.com/Download/index.aspx
```

**If GPU device mapping wrong:**
```bash
# Verify GPU devices
nvidia-smi -L

# Check docker-compose.yml for correct device mapping
# Ollama should use device 0 (RTX 3090 Ti)
# vLLM should use device 1 (Tesla P40)

# Example docker-compose.yml section:
# deploy:
#   resources:
#     reservations:
#       devices:
#         - driver: nvidia
#           device_ids: ['0']  # or ['1'] for vLLM
```

**Escalation Path:**
- If toolkit installation fails: Check WSL2 version and Docker Desktop compatibility
- If drivers not updating: Contact Windows admin or check hardware compatibility
- If GPU still not detected after fixes: Escalate to P2 incident

---

### Symptom: Port Conflicts

**Indicators:**
- "Address already in use" errors
- Services fail to bind to ports
- Cannot access services via browser

**Possible Causes:**
- Another service using required ports
- Previous deployment not cleaned up
- Firewall blocking ports
- Incorrect port mapping in docker-compose.yml

**Validation Commands:**

```bash
# Check which process is using ports
sudo netstat -tulpn | grep :80
sudo netstat -tulpn | grep :443
sudo netstat -tulpn | grep :3000
sudo netstat -tulpn | grep :3306
sudo netstat -tulpn | grep :5432

# List all Docker containers (including stopped)
docker ps -a

# Check firewall rules (if applicable)
sudo ufw status
```

**Solutions:**

**If previous deployment not cleaned:**
```bash
# Stop all containers
docker compose down
docker compose -f docker-compose.workers.yml down
docker compose -f docker-compose.monitoring.yml down

# Remove orphaned containers
docker container prune -f

# Restart deployment
docker compose up -d
```

**If another service using port:**
```bash
# Identify and stop conflicting service
sudo lsof -i :80
sudo kill <PID>

# Or change port in docker-compose.yml
# ports:
#   - "8080:80"  # Use port 8080 instead of 80
```

**Escalation Path:**
- If cannot stop conflicting service: Contact infrastructure team
- If firewall issues: Contact network admin

---

## Database Issues

### Symptom: Cannot Connect to PostgreSQL

**Indicators:**
- "Connection refused" errors
- "could not connect to server" errors
- Application cannot access database
- Database health check failing

**Possible Causes:**
- PostgreSQL not running
- Incorrect credentials
- Network connectivity issues
- PostgreSQL accepting connections on wrong interface
- Database corrupted

**Validation Commands:**

```bash
# Check if PostgreSQL container running
docker ps | grep postgres

# Check PostgreSQL logs
docker compose logs postgres --tail=50

# Test connection from host
docker exec travian_postgres psql -U postgres -c "SELECT 1;"

# Test from application container
docker exec travian_app psql -h postgres -U postgres -d travian_global -c "SELECT 1;"

# Check PostgreSQL listening
docker exec travian_postgres netstat -tuln | grep 5432

# Verify environment variables
docker exec travian_app env | grep DATABASE_URL
```

**Solutions:**

**If PostgreSQL not running:**
```bash
# Check why it stopped
docker compose logs postgres --tail=100

# Restart PostgreSQL
docker compose restart postgres

# Wait for healthy status
watch -n 2 'docker compose ps | grep postgres'
```

**If credential issues:**
```bash
# Verify password in .env
grep POSTGRES_PASSWORD .env

# Update DATABASE_URL if needed
# Format: postgresql://postgres:PASSWORD@postgres:5432/travian_global

# Restart services
docker compose restart postgres php-fpm
```

**If connection exhaustion:**
```bash
# Check active connections
docker exec travian_postgres psql -U postgres -c \
  "SELECT count(*) FROM pg_stat_activity;"

# Check max connections
docker exec travian_postgres psql -U postgres -c \
  "SHOW max_connections;"

# Kill idle connections
docker exec travian_postgres psql -U postgres -c \
  "SELECT pg_terminate_backend(pid) FROM pg_stat_activity 
   WHERE state = 'idle' AND state_change < NOW() - INTERVAL '5 minutes';"
```

**Escalation Path:**
- If PostgreSQL won't start: Check disk space and logs, escalate to P2
- If credentials correct but connection fails: Escalate to P2
- If database corrupted: Escalate to P1, prepare for restore from backup

---

### Symptom: Cannot Connect to MySQL

**Indicators:**
- "Access denied" errors
- "Can't connect to MySQL server" errors
- Game worlds not accessible

**Possible Causes:**
- MySQL not running
- Incorrect credentials
- Max connections exceeded
- MySQL crashed and needs recovery

**Validation Commands:**

```bash
# Check MySQL container
docker ps | grep mysql

# Check MySQL logs
docker compose logs mysql --tail=50

# Test connection
docker exec travian_mysql mysql -uroot -p$MYSQL_ROOT_PASSWORD -e "SELECT 1;"

# Check connections
docker exec travian_mysql mysql -uroot -p$MYSQL_ROOT_PASSWORD -e \
  "SHOW STATUS LIKE 'Threads_connected';"

# Check max connections
docker exec travian_mysql mysql -uroot -p$MYSQL_ROOT_PASSWORD -e \
  "SHOW VARIABLES LIKE 'max_connections';"
```

**Solutions:**

**If MySQL not running:**
```bash
# Check logs for crash reasons
docker compose logs mysql --tail=200

# Restart MySQL
docker compose restart mysql

# If won't start, check InnoDB recovery
docker compose logs mysql | grep -i innodb
```

**If connection limit reached:**
```bash
# Kill old connections
docker exec travian_mysql mysql -uroot -p$MYSQL_ROOT_PASSWORD -e \
  "SHOW PROCESSLIST;"

docker exec travian_mysql mysql -uroot -p$MYSQL_ROOT_PASSWORD -e \
  "KILL <process_id>;"

# Increase max connections (temporarily)
docker exec travian_mysql mysql -uroot -p$MYSQL_ROOT_PASSWORD -e \
  "SET GLOBAL max_connections = 200;"
```

**Escalation Path:**
- If MySQL corrupted: Escalate to P1, prepare restore
- If repeated crashes: Escalate to P2 for investigation

---

### Symptom: Slow Database Queries

**Indicators:**
- API response time >1000ms
- Grafana showing high query latency
- Database CPU/memory high
- Application timeouts

**Possible Causes:**
- Missing indexes
- Long-running queries blocking others
- Database needs vacuuming (PostgreSQL)
- Insufficient buffer pool (MySQL)
- Disk I/O bottleneck

**Validation Commands:**

```bash
# PostgreSQL: Check slow queries
docker exec travian_postgres psql -U postgres -d travian_global -c \
  "SELECT query, calls, total_exec_time/1000 as total_sec, mean_exec_time/1000 as mean_sec 
   FROM pg_stat_statements 
   ORDER BY mean_exec_time DESC LIMIT 10;"

# MySQL: Check slow queries
docker exec travian_mysql mysql -uroot -p$MYSQL_ROOT_PASSWORD -e \
  "SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;"

# Check active queries
# PostgreSQL
docker exec travian_postgres psql -U postgres -c \
  "SELECT pid, now() - pg_stat_activity.query_start AS duration, query 
   FROM pg_stat_activity 
   WHERE state = 'active' ORDER BY duration DESC;"

# MySQL
docker exec travian_mysql mysql -uroot -p$MYSQL_ROOT_PASSWORD -e \
  "SHOW FULL PROCESSLIST;"

# Check index usage
# PostgreSQL
docker exec travian_postgres psql -U postgres -d travian_global -c \
  "SELECT schemaname, tablename, indexname, idx_scan 
   FROM pg_stat_user_indexes 
   WHERE idx_scan = 0;"
```

**Solutions:**

**If missing indexes:**
```bash
# Add index to slow queries
# Example:
docker exec travian_postgres psql -U postgres -d travian_global -c \
  "CREATE INDEX CONCURRENTLY idx_players_world_id ON players(world_id);"

# Verify index created
docker exec travian_postgres psql -U postgres -d travian_global -c \
  "\d+ players"
```

**If database needs maintenance:**
```bash
# PostgreSQL: Vacuum and analyze
docker exec travian_postgres psql -U postgres -d travian_global -c \
  "VACUUM ANALYZE;"

# MySQL: Optimize tables
docker exec travian_mysql mysqloptimize -uroot -p$MYSQL_ROOT_PASSWORD \
  --optimize --all-databases
```

**If insufficient buffer pool:**
```bash
# MySQL: Increase InnoDB buffer pool
docker exec travian_mysql mysql -uroot -p$MYSQL_ROOT_PASSWORD -e \
  "SET GLOBAL innodb_buffer_pool_size = 4294967296;"  # 4GB

# PostgreSQL: Increase shared buffers (requires restart)
# Edit docker-compose.yml or postgresql.conf
```

**Escalation Path:**
- If performance doesn't improve: Escalate for capacity planning
- If disk I/O bottleneck: Escalate for infrastructure upgrade

---

## Worker Issues

### Symptom: Workers Not Running

**Indicators:**
- `docker ps` doesn't show worker containers
- Queue depths growing
- NPCs not making decisions
- Automation tasks not executing

**Possible Causes:**
- Workers not started
- Worker containers exited
- Worker processes crashed
- Supervisor not running

**Validation Commands:**

```bash
# Check worker containers
docker compose -f docker-compose.workers.yml ps

# Check if workers exited
docker ps -a | grep worker

# Check worker logs
docker compose -f docker-compose.workers.yml logs --tail=50

# Check supervisor status (inside worker container)
docker exec travian_automation_worker supervisorctl status
docker exec travian_ai_decision_worker supervisorctl status
docker exec travian_spawn_scheduler_worker supervisorctl status
```

**Solutions:**

**If workers not started:**
```bash
# Start worker stack
docker compose -f docker-compose.workers.yml up -d

# Verify started
docker compose -f docker-compose.workers.yml ps
```

**If workers crashed:**
```bash
# Check crash logs
docker compose -f docker-compose.workers.yml logs automation-worker --tail=200
docker compose -f docker-compose.workers.yml logs ai-decision-worker --tail=200

# Restart workers
docker compose -f docker-compose.workers.yml restart

# If crashes persist, check for code errors
```

**If supervisor not running:**
```bash
# Restart worker container
docker compose -f docker-compose.workers.yml restart automation-worker

# Check supervisor inside container
docker exec travian_automation_worker ps aux | grep supervisor
```

**Escalation Path:**
- If workers repeatedly crash: Check application logs, escalate to P2
- If cannot restart workers: Escalate to P2

---

### Symptom: Queue Backlog Growing

**Indicators:**
- Redis queue depths >1000
- Worker metrics showing low throughput
- NPCs delayed in making decisions
- Automation tasks pending for hours

**Possible Causes:**
- Workers overwhelmed (need scaling)
- Worker processing slow (database latency)
- LLM inference slow
- Queue consumer bugs

**Validation Commands:**

```bash
# Check queue depths
docker exec travian_redis redis-cli -a $REDIS_PASSWORD LLEN automation_queue
docker exec travian_redis redis-cli -a $REDIS_PASSWORD LLEN ai_decision_queue
docker exec travian_redis redis-cli -a $REDIS_PASSWORD LLEN spawn_queue

# Check worker throughput (Grafana)
# Dashboard: "Worker Throughput"

# Check worker resource usage
docker stats travian_automation_worker
docker stats travian_ai_decision_worker

# Check worker health
./scripts/healthcheck/worker-healthcheck.php automation 700
```

**Solutions:**

**If workers need scaling:**
```bash
# Scale up automation workers
docker compose -f docker-compose.workers.yml up -d --scale automation-worker=3

# Scale up AI decision workers
docker compose -f docker-compose.workers.yml up -d --scale ai-decision-worker=5

# Monitor queue depths
watch -n 5 'docker exec travian_redis redis-cli -a $REDIS_PASSWORD LLEN ai_decision_queue'
```

**If database latency causing slowdown:**
```bash
# Optimize database (see Database Issues)
# Check query performance
# Add missing indexes
```

**If LLM inference slow:**
```bash
# Check LLM health
curl http://localhost:11434/api/health  # Ollama
curl http://localhost:8000/health  # vLLM

# Check GPU utilization
nvidia-smi

# See LLM-OPERATIONS.md for performance tuning
```

**Escalation Path:**
- If scaling doesn't help: Investigate worker code for bottlenecks
- If queue depths >5000: Escalate to P2

---

## LLM Issues

### Symptom: Cannot Connect to Ollama

**Indicators:**
- "Connection refused" to Ollama
- AI decisions failing
- Workers showing LLM errors
- Ollama container not running

**Possible Causes:**
- Ollama container not started
- Ollama crashed
- GPU not available
- Model not loaded

**Validation Commands:**

```bash
# Check Ollama container
docker ps | grep ollama

# Check Ollama logs
docker compose logs ollama --tail=50

# Test Ollama API
curl http://localhost:11434/api/health

# Check GPU access
docker exec travian_ollama nvidia-smi

# List loaded models
curl http://localhost:11434/api/tags
```

**Solutions:**

**If Ollama not running:**
```bash
# Start Ollama
docker compose restart ollama

# Wait for startup
sleep 10

# Verify health
curl http://localhost:11434/api/health
```

**If model not loaded:**
```bash
# Pull model
docker exec travian_ollama ollama pull gemma:2b

# List models
docker exec travian_ollama ollama list

# Test inference
docker exec travian_ollama ollama run gemma:2b "Hello"
```

**If GPU issues:**
```bash
# Verify GPU assigned correctly (device 0)
docker inspect travian_ollama | grep -i gpu

# Check GPU utilization
nvidia-smi
```

**Escalation Path:**
- If Ollama won't start: Check GPU setup, escalate to P2
- If model loading fails: Check disk space and GPU memory

**See Also:** [LLM-OPERATIONS.md](LLM-OPERATIONS.md)

---

### Symptom: GPU Out of Memory

**Indicators:**
- "CUDA out of memory" errors
- LLM inference failing
- GPU showing 100% memory usage
- Containers crashing with OOM

**Possible Causes:**
- Model too large for GPU
- Multiple models loaded
- Batch size too large
- Memory leak

**Validation Commands:**

```bash
# Check GPU memory usage
nvidia-smi

# Check which processes using GPU
nvidia-smi pmon -c 1

# Check model sizes
docker exec travian_ollama ollama list

# Check container memory
docker stats travian_ollama
docker stats travian_vllm
```

**Solutions:**

**If model too large:**
```bash
# Switch to smaller model
# Ollama: Use gemma:2b instead of gemma:7b
# vLLM: Use llama2:7b instead of llama2:13b

# Unload large model
docker exec travian_ollama ollama rm <large-model>

# Load smaller model
docker exec travian_ollama ollama pull gemma:2b
```

**If multiple models loaded:**
```bash
# List loaded models
docker exec travian_ollama ollama list

# Remove unused models
docker exec travian_ollama ollama rm <unused-model>
```

**If memory leak:**
```bash
# Restart LLM containers
docker compose restart ollama vllm

# Monitor memory over time
watch -n 5 nvidia-smi
```

**Escalation Path:**
- If GPU memory issues persist: Escalate for GPU upgrade
- If critical production impact: Escalate to P1

**See Also:** [LLM-OPERATIONS.md](LLM-OPERATIONS.md)

---

## Networking Issues

### Symptom: Cannot Reach API

**Indicators:**
- HTTP 502/503 errors
- Cannot access http://localhost/v1/
- Nginx showing errors
- Frontend cannot reach backend

**Possible Causes:**
- Nginx not running
- PHP-FPM not running
- Network misconfiguration
- Firewall blocking
- Port not mapped correctly

**Validation Commands:**

```bash
# Check Nginx
docker ps | grep nginx
docker compose logs nginx --tail=20

# Check PHP-FPM
docker ps | grep php-fpm
docker compose logs php-fpm --tail=20

# Test from host
curl http://localhost/v1/health

# Test from inside Nginx container
docker exec travian_nginx curl http://php-fpm:9000

# Check port mappings
docker ps | grep nginx
```

**Solutions:**

**If Nginx not running:**
```bash
# Restart Nginx
docker compose restart nginx

# Check logs for errors
docker compose logs nginx --tail=50
```

**If PHP-FPM not responding:**
```bash
# Restart PHP-FPM
docker compose restart php-fpm

# Check PHP-FPM status
docker exec travian_app php-fpm -t
```

**If firewall blocking:**
```bash
# Check Windows Firewall
# Open Windows Defender Firewall → Allow an app
# Ensure Docker Desktop allowed

# Or add rule
# netsh advfirewall firewall add rule name="Docker API" dir=in action=allow protocol=TCP localport=80
```

**Escalation Path:**
- If networking completely broken: Escalate to P1
- If only specific endpoints affected: Check application logs

---

### Symptom: CORS Errors

**Indicators:**
- Browser console shows CORS errors
- "Access-Control-Allow-Origin" errors
- Frontend cannot call API
- Preflight requests failing

**Possible Causes:**
- CORS middleware not configured
- Incorrect allowed origins
- Missing CORS headers
- Browser security policy

**Validation Commands:**

```bash
# Check CORS configuration in PHP
grep -r "Access-Control-Allow-Origin" sections/api/

# Test CORS with curl
curl -I http://localhost/v1/health \
  -H "Origin: http://localhost:4200"

# Check browser network tab
# Look for OPTIONS preflight request
```

**Solutions:**

**If CORS not configured:**
```bash
# Add CORS headers to Nginx
nano docker/nginx/conf.d/default.conf

# Add these headers:
# add_header 'Access-Control-Allow-Origin' '*' always;
# add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, OPTIONS' always;
# add_header 'Access-Control-Allow-Headers' 'Content-Type, X-CSRF-Token' always;

# Restart Nginx
docker compose restart nginx
```

**Escalation Path:**
- If CORS errors persist: Check application CORS middleware

**See Also:** [CORS-CSRF-SETUP.md](CORS-CSRF-SETUP.md)

---

## Performance Issues

### Symptom: API Slow

**Indicators:**
- Response time >1000ms
- Users reporting slow page loads
- Grafana showing high latency
- Timeouts occurring

**Possible Causes:**
- Database queries slow
- No caching
- High CPU/memory usage
- Network latency

**Validation Commands:**

```bash
# Check API latency in Grafana
# Dashboard: "API Performance"

# Test specific endpoint
time curl http://localhost/v1/server/worlds

# Check database query performance
# See Database Issues section

# Check Redis cache hit rate
docker exec travian_redis redis-cli -a $REDIS_PASSWORD INFO stats | grep keyspace_hits
```

**Solutions:**

**If database slow:**
```bash
# See "Database Issues" → "Slow Queries" section
# Add indexes, optimize queries
```

**If caching not working:**
```bash
# Check Redis connectivity
docker exec travian_app redis-cli -h redis -a $REDIS_PASSWORD PING

# Clear and rebuild cache
docker exec travian_redis redis-cli -a $REDIS_PASSWORD FLUSHDB
```

**If high resource usage:**
```bash
# Scale PHP-FPM workers
# Edit docker-compose.yml
# Increase pm.max_children

docker compose restart php-fpm
```

**Escalation Path:**
- If performance degraded >30 min: Escalate to P2

---

## Diagnostic Commands

### Quick Reference

**Docker:**
```bash
# View all containers
docker ps -a

# View logs
docker compose logs <service> --tail=50

# Container stats
docker stats --no-stream

# System info
docker system df
docker info
```

**Database:**
```bash
# PostgreSQL
docker exec travian_postgres psql -U postgres -d travian_global -c "SELECT version();"

# MySQL
docker exec travian_mysql mysql -uroot -p$MYSQL_ROOT_PASSWORD -e "STATUS;"
```

**Logs:**
```bash
# Application logs
tail -50 /var/log/travian/app.log | jq .

# Search for errors
grep -i error /var/log/travian/app.log | tail -20
```

**Resources:**
```bash
# Disk space
df -h

# Memory
free -h

# CPU
top -bn1 | head -20

# GPU
nvidia-smi
```

---

## See Also

- [INCIDENT-RESPONSE.md](INCIDENT-RESPONSE.md) - For P1/P2 incidents
- [OPERATIONS-RUNBOOK.md](OPERATIONS-RUNBOOK.md) - For routine tasks
- [MONITORING.md](MONITORING.md) - For monitoring setup
- [LOGGING.md](LOGGING.md) - For log queries
- [LLM-OPERATIONS.md](LLM-OPERATIONS.md) - For LLM troubleshooting
- [DEPLOYMENT-GUIDE.md](DEPLOYMENT-GUIDE.md) - For deployment issues
