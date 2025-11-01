# Security Hardening

This document describes the security hardening measures implemented in the Travian Docker infrastructure to ensure production-grade security.

## Network Segmentation

### Network Architecture

The infrastructure uses a 4-tier Docker network design to isolate services based on their security requirements and communication patterns:

- **edge_public**: Public-facing services (nginx, grafana)
- **app_core**: Application layer (php-fpm, workers)
- **data_services**: Database layer (postgres, mysql, redis) - **INTERNAL ONLY**
- **llm_gpu**: LLM services (ollama, vllm) - **INTERNAL ONLY**

### Network Membership Matrix

The following table shows which services are connected to which networks:

| Service | edge_public | app_core | data_services | llm_gpu |
|---------|-------------|----------|---------------|---------|
| nginx | ✅ | ✅ | - | - |
| php-fpm | - | ✅ | ✅ | ✅ |
| workers | - | ✅ | ✅ | ✅ |
| postgres | - | - | ✅ | - |
| mysql | - | - | ✅ | - |
| redis | - | - | ✅ | - |
| ollama | - | - | - | ✅ |
| vllm | - | - | - | ✅ |
| grafana | ✅ | - | - | - |
| prometheus | ✅ | ✅ | ✅ | - |
| postgres-exporter | - | - | ✅ | - |
| mysql-exporter | - | - | ✅ | - |
| redis-exporter | - | - | ✅ | - |
| php-fpm-exporter | - | ✅ | - | - |
| nginx-exporter | - | ✅ | - | - |
| maintenance | - | - | ✅ | - |
| redis-commander | ✅ | - | ✅ | - |

### Network Isolation Rules

1. **edge_public** (external-facing):
   - Only services that need to be accessed from outside the Docker network
   - nginx serves the application on port 5000
   - grafana serves monitoring UI on port 3000
   - prometheus serves metrics UI on port 9090

2. **app_core** (application layer):
   - Application logic and workers
   - php-fpm processes requests from nginx
   - Workers communicate with php-fpm

3. **data_services** (database layer - INTERNAL):
   - **No external access** - marked as `internal: true`
   - Only accessible from other Docker networks
   - Contains all database services (postgres, mysql, redis)
   - Database exporters for monitoring

4. **llm_gpu** (LLM services - INTERNAL):
   - **No external access** - marked as `internal: true`
   - Only accessible from application layer
   - Contains LLM services (ollama, vllm)

### Port Exposure

**ONLY** these services expose ports to the host:

- **nginx**: 5000 (HTTP)
- **grafana**: 3000 (monitoring UI)
- **prometheus**: 9090 (monitoring UI)
- **node-exporter**: 9100 (host metrics)
- **redis-commander**: 8081 (optional Redis UI - monitoring profile only)

**ALL** other services (postgres, mysql, redis, ollama, vllm) are **internal-only** and do NOT expose any ports to the host. They can only be accessed via Docker networks.

## Container Hardening

### Non-Root Users

All containers run as non-root users for enhanced security:

- **php-fpm**: appuser (UID 1001)
- **nginx**: nginx (UID 1001)
- **Others**: Use image defaults or create dedicated users

This prevents privilege escalation attacks and limits the damage from container breakouts.

### Resource Limits

Resource limits prevent DoS attacks and ensure fair resource allocation:

| Service | CPU Limit | CPU Reservation | Memory Limit | Memory Reservation |
|---------|-----------|-----------------|--------------|-------------------|
| nginx | 0.5 CPU | 0.25 CPU | 512Mi | 256Mi |
| php-fpm | 1.0 CPU | 0.5 CPU | 1536Mi | 1024Mi |
| postgres | 2.0 CPU | 1.0 CPU | 4096Mi | 2048Mi |
| mysql | 2.0 CPU | 1.0 CPU | 4096Mi | 2048Mi |
| redis | 0.5 CPU | 0.25 CPU | 1024Mi | 512Mi |
| ollama | 4.0 CPU | 2.0 CPU | 16384Mi | 8192Mi |
| vllm | 4.0 CPU | 2.0 CPU | 16384Mi | 8192Mi |

### Capability Dropping

All services drop unnecessary Linux capabilities to minimize attack surface:

```yaml
cap_drop:
  - ALL
```

Services only add back the capabilities they absolutely need:

- **nginx**: `NET_BIND_SERVICE`, `CHOWN`, `SETUID`, `SETGID`
- **Other services**: No additional capabilities

### Security Options

All services use the `no-new-privileges` security option to prevent privilege escalation:

```yaml
security_opt:
  - no-new-privileges:true
```

### Read-Only Filesystems

**nginx** runs with a read-only root filesystem for maximum security:

```yaml
read_only: true
tmpfs:
  - /tmp
  - /var/run
  - /var/cache/nginx
```

This prevents attackers from modifying files on the filesystem even if they compromise the container.

## Image Scanning

### Vulnerability Scanning with Trivy

Before deployment, all Docker images should be scanned for vulnerabilities using Trivy:

```bash
# Install Trivy (if not already installed)
curl -sfL https://raw.githubusercontent.com/aquasecurity/trivy/main/contrib/install.sh | sh -s -- -b /usr/local/bin

# Scan all images
./scripts/security/scan-images.sh
```

The script automatically discovers all images from docker-compose.yml and locally built images, then scans them for HIGH and CRITICAL vulnerabilities.

### Manual Scanning

To scan a specific image:

```bash
trivy image --severity HIGH,CRITICAL <image-name>
```

To generate a detailed report:

```bash
trivy image --severity HIGH,CRITICAL --format json -o report.json <image-name>
```

## Verification

### Runtime Verification

Verify that all security hardening measures are properly applied:

```bash
./scripts/security/verify-hardening.sh
```

This script checks:

1. **Network Segmentation**: All 4 networks exist and are properly configured
2. **Non-Root Users**: Containers run as non-root users
3. **Resource Limits**: Memory and CPU limits are applied
4. **Read-Only Filesystems**: nginx has read-only rootfs
5. **Port Exposure**: Internal services have no exposed ports
6. **Security Options**: no-new-privileges is enabled

### Manual Verification

Check network configuration:

```bash
docker network ls
docker network inspect travian_data_services
docker network inspect travian_llm_gpu
```

Check container user:

```bash
docker exec travian_app id
docker exec travian_nginx id
```

Check resource limits:

```bash
docker inspect travian_app | jq '.[0].HostConfig.Memory'
docker inspect travian_nginx | jq '.[0].HostConfig.NanoCpus'
```

Check security options:

```bash
docker inspect travian_app | jq '.[0].HostConfig.SecurityOpt'
docker inspect travian_nginx | jq '.[0].HostConfig.ReadonlyRootfs'
```

## Best Practices

### 1. Regular Updates

- Keep all base images up to date
- Regularly scan for vulnerabilities
- Apply security patches promptly

### 2. Secrets Management

- Never commit secrets to version control
- Use environment variables or Docker secrets
- Rotate credentials regularly (see `scripts/secrets/rotate-credentials.sh`)

### 3. Monitoring

- Monitor container resource usage
- Set up alerts for resource limit violations
- Track network traffic patterns

### 4. Audit Logging

- Enable audit logging for all services
- Review logs regularly for suspicious activity
- Retain logs for compliance requirements

### 5. Network Policies

- Minimize network connections between services
- Use internal networks for sensitive services
- Regularly review and update network policies

## Compliance

This security hardening implementation follows industry best practices:

- **CIS Docker Benchmark**: Container hardening guidelines
- **OWASP**: Secure deployment practices
- **NIST**: Container security standards

## Troubleshooting

### Container Won't Start After Hardening

1. **Check file permissions**: Non-root users need proper file ownership
2. **Review capabilities**: Service may need additional capabilities
3. **Check tmpfs mounts**: Services may need writable directories

### Network Connectivity Issues

1. **Verify network membership**: Services must be on same network to communicate
2. **Check internal flag**: Internal networks cannot access external resources
3. **Review DNS resolution**: Services use service names, not IP addresses

### Resource Limit Issues

1. **Monitor resource usage**: Use `docker stats` to check actual usage
2. **Adjust limits**: Increase limits if services are being throttled
3. **Optimize applications**: Reduce resource consumption where possible

## References

- [Docker Security Best Practices](https://docs.docker.com/engine/security/)
- [CIS Docker Benchmark](https://www.cisecurity.org/benchmark/docker)
- [OWASP Docker Security](https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html)
- [Trivy Documentation](https://aquasecurity.github.io/trivy/)
