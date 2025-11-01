#!/usr/bin/env bash
set -euo pipefail

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${YELLOW}🔒 Running Complete Security Audit${NC}"
echo ""

# Initialize report
REPORT_FILE="docs/SECURITY-AUDIT.md"
TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")

cat > "$REPORT_FILE" <<EOF
# Security Audit Report

**Date:** $TIMESTAMP
**Scope:** SQL Injection + XSS + Dependencies + Infrastructure

## Executive Summary

Security audit covering:
- SQL Injection vulnerabilities
- XSS vulnerabilities  
- PHP dependency vulnerabilities
- Docker image vulnerabilities
- Dockerfile best practices

---

EOF

# ===== SQL Injection Audit =====
echo "=== SQL Injection Audit ==="
echo "" >> "$REPORT_FILE"
echo "## 1. SQL Injection (SQLi) Audit" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

# Capture SQL audit output
SQL_OUTPUT_FILE="/tmp/sql-audit-output.txt"
./scripts/security/audit-sql-injection.sh > "$SQL_OUTPUT_FILE" 2>&1 || true
SQL_OUTPUT=$(cat "$SQL_OUTPUT_FILE")
echo "$SQL_OUTPUT"

# FIX: Parse counts deterministically using line-by-line matching
# Each pattern now always prints "Total: N instances"
# Strip ANSI codes first for reliable parsing
SQL_CLEAN=$(echo "$SQL_OUTPUT" | sed 's/\x1b\[[0-9;]*m//g')
CONCAT_COUNT=$(echo "$SQL_CLEAN" | grep "Pattern 1" -A 50 | grep "Total:" | grep -o "[0-9]\+" | head -1 || echo "0")
SELECT_COUNT=$(echo "$SQL_CLEAN" | grep "Pattern 2" -A 30 | grep "Total:" | grep -o "[0-9]\+" | head -1 || echo "0")
UPDATE_COUNT=$(echo "$SQL_CLEAN" | grep "Pattern 3" -A 30 | grep "Total:" | grep -o "[0-9]\+" | head -1 || echo "0")
INSERT_COUNT=$(echo "$SQL_CLEAN" | grep "Pattern 4" -A 30 | grep "Total:" | grep -o "[0-9]\+" | head -1 || echo "0")
DELETE_COUNT=$(echo "$SQL_CLEAN" | grep "Pattern 5" -A 30 | grep "Total:" | grep -o "[0-9]\+" | head -1 || echo "0")
SPRINTF_COUNT=$(echo "$SQL_CLEAN" | grep "Pattern 6" -A 30 | grep "Total:" | grep -o "[0-9]\+" | head -1 || echo "0")
MYSQLI_COUNT=$(echo "$SQL_CLEAN" | grep "Pattern 7" -A 30 | grep "Total:" | grep -o "[0-9]\+" | head -1 || echo "0")
PREPARED_COUNT=$(echo "$SQL_CLEAN" | grep "Pattern 8" -A 5 | grep "Found [0-9]\+ prepared" | grep -o "[0-9]\+" | head -1 || echo "0")

cat >> "$REPORT_FILE" <<EOF
### Summary

- String concatenation patterns: **$CONCAT_COUNT**
- SELECT with embedded variables: **$SELECT_COUNT**
- UPDATE with embedded variables: **$UPDATE_COUNT**
- INSERT with embedded variables: **$INSERT_COUNT**
- DELETE with embedded variables: **$DELETE_COUNT**
- sprintf/printf SQL building: **$SPRINTF_COUNT**
- Legacy mysqli usage: **$MYSQLI_COUNT**
- Prepared statements (safe): **$PREPARED_COUNT**

### Detailed Findings

\`\`\`
$SQL_OUTPUT
\`\`\`

### Recommendations
1. Review all flagged patterns above
2. Convert to PDO prepared statements using DatabaseSecurity helper
3. Never concatenate user input into SQL strings
4. Add unit tests for all database operations

### Status
- [ ] All flagged patterns reviewed
- [ ] Conversions to prepared statements complete
- [ ] Unit tests added

---

EOF

# ===== XSS Audit =====
echo ""
echo "=== XSS Audit ==="
echo "" >> "$REPORT_FILE"
echo "## 2. Cross-Site Scripting (XSS) Audit" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

XSS_OUTPUT_FILE="/tmp/xss-audit-output.txt"
./scripts/security/audit-xss.sh > "$XSS_OUTPUT_FILE" 2>&1 || true
XSS_OUTPUT=$(cat "$XSS_OUTPUT_FILE")
echo "$XSS_OUTPUT"

# Strip ANSI codes and use [0-9]\+ for reliable extraction
XSS_CLEAN=$(echo "$XSS_OUTPUT" | sed 's/\x1b\[[0-9;]*m//g')
JSON_COUNT=$(echo "$XSS_CLEAN" | grep "Found [0-9]* json_encode" | grep -o "[0-9]\+" | head -1 || echo "0")

cat >> "$REPORT_FILE" <<EOF
### Summary

- json_encode usages: **$JSON_COUNT**
- OutputEncoder helper: Available

### Detailed Findings

\`\`\`
$XSS_OUTPUT
\`\`\`

### Recommendations
1. Use OutputEncoder::html() for all user-generated content in HTML
2. Use ValidationMiddleware for input sanitization
3. Use OutputEncoder::json() for API responses
4. Add functional XSS tests

### Status
- [ ] All user outputs use OutputEncoder
- [ ] All user inputs use ValidationMiddleware
- [ ] XSS tests added

---

EOF

# ===== Dependency Scanning =====
echo ""
echo "=== Dependency Scanning ==="
echo "" >> "$REPORT_FILE"
echo "## 3. Dependency Vulnerabilities" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

DEP_OUTPUT_FILE="/tmp/dep-scan-output.txt"
./scripts/security/scan-dependencies.sh > "$DEP_OUTPUT_FILE" 2>&1 || true
DEP_OUTPUT=$(cat "$DEP_OUTPUT_FILE")
echo "$DEP_OUTPUT"

cat >> "$REPORT_FILE" <<EOF
### Detailed Findings

\`\`\`
$DEP_OUTPUT
\`\`\`

### Recommendations
1. Update vulnerable PHP packages: \`composer update\`
2. Rebuild Docker images with patches
3. Monitor CVE feeds for new vulnerabilities

### Status
- [ ] Vulnerable packages updated
- [ ] Docker images rebuilt
- [ ] No HIGH/CRITICAL CVEs remaining

---

EOF

# ===== Dockerfile Scanning =====
echo ""
echo "=== Dockerfile Scanning ==="
echo "" >> "$REPORT_FILE"
echo "## 4. Dockerfile Best Practices" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

DF_OUTPUT_FILE="/tmp/dockerfile-scan-output.txt"
./scripts/security/scan-dockerfile.sh > "$DF_OUTPUT_FILE" 2>&1 || true
DF_OUTPUT=$(cat "$DF_OUTPUT_FILE")
echo "$DF_OUTPUT"

cat >> "$REPORT_FILE" <<EOF
### Detailed Findings

\`\`\`
$DF_OUTPUT
\`\`\`

### Status
- [ ] Dockerfile warnings addressed

---

## 5. Security Controls Status

### DatabaseSecurity Helper
- ✅ Created: sections/api/include/Security/DatabaseSecurity.php
- ⚠️ **NOT YET INTEGRATED** - See docs/SECURITY-HELPERS-INTEGRATION.md

### ValidationMiddleware
- ✅ Created: sections/api/include/Middleware/ValidationMiddleware.php
- ⚠️ **NOT YET INTEGRATED** - See docs/SECURITY-HELPERS-INTEGRATION.md

### OutputEncoder
- ✅ Created: sections/api/include/Security/OutputEncoder.php
- ⚠️ **NOT YET INTEGRATED** - See docs/SECURITY-HELPERS-INTEGRATION.md

---

## Next Actions

1. **Review flagged SQL patterns** - CONCAT=$CONCAT_COUNT, SELECT=$SELECT_COUNT, UPDATE=$UPDATE_COUNT, INSERT=$INSERT_COUNT, DELETE=$DELETE_COUNT, sprintf=$SPRINTF_COUNT, mysqli=$MYSQLI_COUNT
2. **Integrate DatabaseSecurity** into data access layer
3. **Integrate ValidationMiddleware** into API controllers
4. **Integrate OutputEncoder** into response generation
5. **Update vulnerable dependencies**
6. **Rebuild Docker images** with security patches

---

**Report Generated:** $TIMESTAMP
**Tools Used:** composer audit, Trivy (optional), hadolint (optional), custom static analysis
EOF

echo ""
echo -e "${GREEN}✅ Complete security audit finished${NC}"
echo ""
echo "Review comprehensive report: $REPORT_FILE"
