# Incident Response Playbook

## Overview

This playbook defines procedures for detecting, responding to, and recovering from security incidents.

## Severity Matrix

| Level | Description | Examples | Response Time | Escalation |
|-------|-------------|----------|---------------|------------|
| **P1 - Critical** | Production down, data breach, auth bypass | Database compromise, WAF bypass, mass account takeover | 15 minutes | Immediate - All hands |
| **P2 - High** | Degraded service, security vulnerability | CSRF attack, brute force, worker outage, API errors | 1 hour | On-call team |
| **P3 - Medium** | Minor impact, potential security issue | Rate limit hits, slow queries, failed jobs | 4 hours | Next business day |
| **P4 - Low** | Informational, no immediate impact | Deprecation warnings, config drift | 1 week | Backlog |

---

## Incident Response Workflow

### 1. Detection

**Automated Alerts:**
- Grafana alerts trigger notifications
- Prometheus threshold breaches
- Log-based anomaly detection
- WAF blocking patterns

**Manual Detection:**
- User reports
- Security monitoring
- Performance degradation
- Unusual system behavior

### 2. Triage (First 15 minutes)

**Actions:**
1. Acknowledge alert
2. Assess severity using matrix
3. Gather initial evidence:
   ```bash
   # Collect recent logs
   ./scripts/incident/collect-logs.sh
   
   # Check system status
   docker compose ps
   docker stats
   
   # Review Grafana dashboards
   # - Database Health
   # - Worker Throughput
   # - LLM Latency
   ```
4. Determine if escalation needed
5. Create incident ticket

**Decision Point:** Continue investigation or escalate?

### 3. Investigation

**Security Incidents:**
- Review audit trail: \`SELECT * FROM audit_events WHERE severity='critical' ORDER BY created_at DESC LIMIT 100;\`
- Check access logs: \`grep "401\|403" /var/log/nginx/access.log | tail -100\`
- Review CSRF failures: \`tail -100 /var/log/travian/csrf-failures.log\`
- Analyze WAF logs: \`docker logs travian_waf | grep BLOCK\`

**System Incidents:**
- Check container health: \`docker compose ps\`
- Review error logs: \`tail -100 /var/log/travian/app.log | jq .\`
- Database connections: \`SELECT count(*) FROM pg_stat_activity;\`
- Worker queue status: Check Grafana Worker Throughput dashboard

### 4. Containment

**Security Breach:**
1. Block attacker IP at WAF:
   ```bash
   # Add to ModSecurity blocklist
   echo "SecRule REMOTE_ADDR \"@ipMatch 1.2.3.4\" \"id:9000,phase:1,deny,status:403\"" >> /etc/modsecurity/blocklist.conf
   docker restart travian_waf
   ```

2. Revoke compromised credentials:
   ```bash
   # Rotate secrets
   ./scripts/secrets/rotate-credentials.sh redis
   ```

3. Enable maintenance mode (if necessary):
   ```bash
   # Create maintenance flag
   touch /var/www/maintenance.flag
   ```

**System Failure:**
1. Scale down affected services
2. Redirect traffic to healthy instances
3. Enable circuit breakers

### 5. Eradication

**Remove Threat:**
- Patch vulnerabilities
- Update dependencies
- Fix misconfigurations
- Remove malicious code
- Clean compromised data

**Verify Removal:**
- Re-run security scans
- Check audit logs
- Monitor for recurrence

### 6. Recovery

**Restore Service:**
1. Deploy fixes
2. Restart affected services
3. Verify functionality
4. Gradual traffic restoration
5. Monitor closely

**Validation:**
```bash
# Run health checks
./scripts/test-stack.sh

# Verify monitoring
curl http://localhost:9090/api/v1/query?query=up

# Check application
curl http://localhost:5000/v1/health
```

### 7. Post-Incident

**Post-Mortem (Within 48 hours):**
1. Timeline reconstruction
2. Root cause analysis
3. Impact assessment
4. Response evaluation
5. Lessons learned
6. Action items

**Template:**
```markdown
# Post-Mortem: [Incident Title]

**Date:** YYYY-MM-DD
**Severity:** P1/P2/P3/P4
**Duration:** Start - End (Total hours)
**Impact:** Users affected, data loss, downtime

## Timeline
- HH:MM - Event 1
- HH:MM - Event 2

## Root Cause
[Technical explanation]

## Resolution
[What fixed it]

## Action Items
- [ ] Item 1 (Owner: Name, Due: Date)
- [ ] Item 2 (Owner: Name, Due: Date)

## Lessons Learned
1. What worked well
2. What didn't work
3. What we'll do differently
```

---

## Common Incident Runbooks

### Runbook 1: DDoS Attack

**Symptoms:**
- High traffic volume
- WAF blocking many requests
- Slow response times

**Response:**
1. Verify attack: \`docker logs travian_waf | grep -c BLOCK\`
2. Enable aggressive rate limiting:
   ```nginx
   limit_req zone=global_limit burst=5 nodelay;
   ```
3. Block attack patterns in ModSecurity
4. Contact CDN/DDoS protection provider
5. Monitor bandwidth usage

### Runbook 2: Database Failover

**Symptoms:**
- Database connection errors
- Slow queries
- Connection pool exhausted

**Response:**
1. Check database status:
   ```bash
   docker exec travian_postgresql pg_isready
   SELECT version();
   ```

2. Check connections:
   ```sql
   SELECT count(*), state FROM pg_stat_activity GROUP BY state;
   ```

3. Kill long-running queries:
   ```sql
   SELECT pg_terminate_backend(pid) FROM pg_stat_activity
   WHERE state = 'active' AND query_start < NOW() - INTERVAL '5 minutes';
   ```

4. Restart database (last resort):
   ```bash
   docker restart travian_postgresql
   ```

### Runbook 3: Compromised Credentials

**Symptoms:**
- Unauthorized access attempts
- Unusual admin actions
- CSRF failures from legitimate users

**Response:**
1. Identify compromised account
2. Disable account immediately
3. Revoke all sessions:
   ```sql
   DELETE FROM sessions WHERE user_id = ?;
   ```
4. Force password reset
5. Review audit trail:
   ```sql
   SELECT * FROM audit_events WHERE actor_id = ? ORDER BY created_at DESC;
   ```
6. Check for data exfiltration
7. Notify user via secure channel

### Runbook 4: Worker Crash Loop

**Symptoms:**
- Workers constantly restarting
- Queue backlog growing
- Memory/CPU spikes

**Response:**
1. Check worker logs:
   ```bash
   tail -100 /var/log/travian/automation-worker.log
   ```

2. Check resource usage:
   ```bash
   docker stats travian_automation_worker
   ```

3. Identify failing job:
   ```sql
   SELECT * FROM automation_actions WHERE status = 'failed' ORDER BY updated_at DESC LIMIT 10;
   ```

4. Disable problematic job type temporarily
5. Fix underlying issue
6. Gradually re-enable

---

## Evidence Preservation

**Critical:** Preserve evidence BEFORE making changes!

```bash
# Run evidence collection script
./scripts/incident/collect-logs.sh [incident-id]

# Manual collection
tar -czf incident-$(date +%Y%m%d-%H%M%S).tar.gz \
  /var/log/travian/ \
  /var/log/nginx/ \
  /tmp/sql-*.txt
```

**What to Collect:**
- All log files (last 24 hours)
- Audit trail exports
- Database dumps (if data breach)
- Network captures (if available)
- Screenshots of Grafana dashboards
- Docker container states

---

## Communication Templates

### Internal Alert (Slack/PagerDuty)

```
🚨 **[P1/P2/P3] Incident:** [Brief Description]

**Status:** Investigating/Contained/Resolved
**Started:** YYYY-MM-DD HH:MM UTC
**Impact:** [User-facing impact]
**Owner:** @name

**Next Update:** [Time]

Updates: [Incident URL]
```

### User Communication

```
Subject: Service Update - [Date]

We are currently experiencing [brief description]. Our team is actively working to resolve this issue.

**Impact:** [What users experience]
**Status:** [Current status]
**ETA:** [If known]

We apologize for the inconvenience. Updates will be posted at [status page URL].
```

---

## On-Call Rotation

**Primary:** [Name] - [Contact]
**Secondary:** [Name] - [Contact]
**Escalation:** [Manager] - [Contact]

**Handoff Checklist:**
- [ ] Review open incidents
- [ ] Check monitoring dashboards
- [ ] Review recent deployments
- [ ] Test alert notifications
- [ ] Update on-call contacts

---

## Tools and Scripts

**Log Collection:**
```bash
./scripts/incident/collect-logs.sh [incident-id]
```

**Quick Status Check:**
```bash
./scripts/test-stack.sh
```

**Database Backup:**
```bash
./scripts/backup-postgres.sh
./scripts/backup-mysql.sh
```

**Secret Rotation:**
```bash
./scripts/secrets/rotate-credentials.sh [service]
```

---

## Appendix

### Security Contacts
- **Security Team:** security@travian.local
- **Infrastructure:** infra@travian.local
- **Legal:** legal@travian.local

### External Contacts
- **Hosting Provider:** [Contact info]
- **DDoS Protection:** [Contact info]
- **Security Consultant:** [Contact info]

### References
- Security Audit Report: docs/SECURITY-AUDIT.md
- Logging Documentation: docs/LOGGING.md
- Monitoring Setup: docs/MONITORING.md
- Deployment Guide: docs/DEPLOYMENT-GUIDE.md
