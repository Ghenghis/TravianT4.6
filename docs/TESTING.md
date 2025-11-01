# Integration Testing

## Overview

The Travian T4.6 Docker stack includes a comprehensive integration test script that validates all services are working correctly together. This document describes how to run tests, what gets tested, and how to integrate testing into CI/CD pipelines.

## Running Integration Tests

### Quick Start

```bash
# Run integration tests
./scripts/test-stack.sh
```

### Prerequisites

- Docker and Docker Compose must be installed
- All required services must be running via `docker compose up -d`
- Environment variables must be properly configured (`.env.production`)

## What Gets Tested

### 1. Docker Services

Verifies all core services are running:
- PostgreSQL (global database)
- MySQL (game world databases)
- Redis (caching & sessions)
- PHP-FPM (application runtime)
- Nginx (web server)

### 2. Database Connectivity

Tests database connections and basic queries:

**PostgreSQL:**
- Connection readiness check
- Database query execution

**MySQL:**
- Connection ping test
- Database query execution

### 3. Redis Caching Layer

Tests Redis functionality:
- PING command response
- SET/GET operations

### 4. Web Server

Validates Nginx and PHP-FPM integration:
- HTTP response (200 OK)
- Nginx stub_status endpoint

### 5. Health Checks

Validates Docker health checks for all services:
- PostgreSQL health status
- MySQL health status
- Redis health status
- PHP-FPM health status
- Nginx health status

### 6. Worker Metrics

Tests worker monitoring endpoints:
- Worker metrics endpoint availability
- Prometheus format validation

### 7. Monitoring Stack (Optional)

Tests Prometheus and Grafana when monitoring profile is active:

**Prometheus:**
- Health endpoint check (`/-/healthy`)

**Grafana:**
- API health check (`/api/health`)

### 8. LLM Services (Optional)

Tests AI backends when LLM profiles are active:

**Ollama:**
- API availability check (`/api/tags`)

**vLLM:**
- Health endpoint check (`/health`)

### 9. Backup Scripts

Validates backup infrastructure:
- Script file existence
- Script executability permissions

## Test Output

### Successful Test Run

```
==========================================
TRAVIAN T4.6 - DOCKER STACK INTEGRATION TEST
==========================================

🔍 Testing Docker Compose services...
✅ PASS: Service postgres is running
✅ PASS: Service mysql is running
✅ PASS: Service redis is running
✅ PASS: Service php-fpm is running
✅ PASS: Service nginx is running

🔍 Testing PostgreSQL connectivity...
✅ PASS: PostgreSQL is accepting connections
✅ PASS: PostgreSQL database query

...

==========================================
TEST SUMMARY
==========================================
Tests Passed: 18
Tests Failed: 0

✅ ALL TESTS PASSED
```

### Failed Test Run

```
🔍 Testing PostgreSQL connectivity...
❌ FAIL: PostgreSQL is accepting connections
❌ FAIL: PostgreSQL database query

...

==========================================
TEST SUMMARY
==========================================
Tests Passed: 12
Tests Failed: 6

❌ SOME TESTS FAILED
```

## Exit Codes

- `0`: All tests passed successfully
- `1`: One or more tests failed

These exit codes make the script suitable for CI/CD integration.

## CI/CD Integration

### GitHub Actions Example

Create `.github/workflows/test.yml`:

```yaml
name: Integration Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v2
      
      - name: Create environment file
        run: |
          cp .env.example .env.production
          # Add your environment variable setup here
      
      - name: Start Docker stack
        run: docker compose up -d
      
      - name: Wait for services to be ready
        run: sleep 30
      
      - name: Run integration tests
        run: ./scripts/test-stack.sh
      
      - name: Show logs on failure
        if: failure()
        run: docker compose logs
      
      - name: Cleanup
        if: always()
        run: docker compose down -v
```

### GitLab CI Example

Create `.gitlab-ci.yml`:

```yaml
stages:
  - test

integration_tests:
  stage: test
  image: docker:latest
  services:
    - docker:dind
  before_script:
    - apk add --no-cache docker-compose
  script:
    - cp .env.example .env.production
    - docker compose up -d
    - sleep 30
    - ./scripts/test-stack.sh
  after_script:
    - docker compose logs
    - docker compose down -v
  only:
    - main
    - develop
```

## Troubleshooting

### Common Issues

#### Test Failures

**Symptom:** Tests fail even though services appear to be running

**Solutions:**
1. Check Docker logs for specific services:
   ```bash
   docker compose logs <service_name>
   ```

2. Verify environment variables:
   ```bash
   docker compose config
   ```

3. Check service health status:
   ```bash
   docker compose ps
   ```

#### Timeout Errors

**Symptom:** Tests fail due to services not being ready

**Solutions:**
1. Increase wait time between service start and tests
2. Check system resources (CPU, memory, disk space)
3. Verify network connectivity between containers

#### Connection Refused

**Symptom:** Cannot connect to service ports

**Solutions:**
1. Verify port mappings in `docker-compose.yml`
2. Check firewall rules
3. Ensure services are bound to correct interfaces

#### Health Check Warnings

**Symptom:** Services show as running but health checks fail

**Solutions:**
1. Review health check scripts in `scripts/healthcheck/`
2. Verify health check intervals and timeouts
3. Check service-specific logs

### Debug Mode

Run tests with verbose output:

```bash
bash -x ./scripts/test-stack.sh
```

### Manual Service Testing

Test individual services manually:

```bash
# PostgreSQL
docker exec travian_postgres pg_isready -U travian

# MySQL
docker exec travian_mysql mysqladmin -uroot -p"${MYSQL_ROOT_PASSWORD}" ping

# Redis
docker exec travian_redis redis-cli ping

# Nginx
curl -s http://localhost:5000
```

## Best Practices

### Development Workflow

1. **Before committing code:**
   ```bash
   docker compose up -d
   ./scripts/test-stack.sh
   ```

2. **After making infrastructure changes:**
   ```bash
   docker compose down -v
   docker compose up -d
   sleep 30
   ./scripts/test-stack.sh
   ```

3. **Before deployment:**
   ```bash
   # Test with production-like configuration
   docker compose --profile monitoring up -d
   ./scripts/test-stack.sh
   ```

### Test Coverage

The integration tests cover:
- ✅ Service availability
- ✅ Database connectivity
- ✅ Caching functionality
- ✅ Web server responses
- ✅ Health monitoring
- ✅ Backup infrastructure

The tests do NOT cover:
- ❌ Application logic
- ❌ Unit tests
- ❌ Performance/load testing
- ❌ Security scanning

### Continuous Improvement

Add new tests when:
- Adding new services to the stack
- Implementing new features requiring external services
- Discovering gaps in test coverage
- Experiencing production issues that weren't caught by tests

## Related Documentation

- [Health Checks](HEALTH-CHECKS.md) - Detailed health check configuration
- [Monitoring & Maintenance](08-MONITORING-MAINTENANCE.md) - Monitoring setup
- [Backup & Restore](BACKUP-RESTORE.md) - Backup procedures
- [Production Deployment](06-PRODUCTION-DEPLOYMENT.md) - Deployment guide
- [Troubleshooting](09-TROUBLESHOOTING.md) - Common issues and solutions
