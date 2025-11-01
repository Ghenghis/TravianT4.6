#!/usr/bin/env bash
set -euo pipefail

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${YELLOW}🔍 SQL Injection Security Audit${NC}"
echo ""

TOTAL_ISSUES=0

# Pattern 1: String concatenation in queries (multiline aware)
echo "Pattern 1: String concatenation in SQL queries"
echo "Searching for: query/execute calls with concatenated variables..."

# FIX: Use grep -E for extended regex (| is OR operator)
CONCAT_FILE="/tmp/sql-concat.txt"
find sections/api/ -name "*.php" -exec grep -lE "query|execute" {} \; > /tmp/php-files-with-queries.txt

# Check each file for concatenation patterns
> "$CONCAT_FILE"
while read -r file; do
    # Look for variable concatenation in SQL context
    grep -Hn "\\\$.*\(SELECT\|INSERT\|UPDATE\|DELETE\)" "$file" 2>/dev/null | grep -v "prepare" | grep -v "^\s*//" >> "$CONCAT_FILE" || true
done < /tmp/php-files-with-queries.txt

if [ -s "$CONCAT_FILE" ]; then
    echo -e "${RED}⚠️  Found potential SQL concatenation:${NC}"
    cat "$CONCAT_FILE" | head -20
    CONCAT_COUNT=$(wc -l < "$CONCAT_FILE")
    echo -e "${YELLOW}Total: $CONCAT_COUNT instances${NC}"
    TOTAL_ISSUES=$((TOTAL_ISSUES + CONCAT_COUNT))
else
    echo -e "${GREEN}✅ No direct concatenation found${NC}"
    CONCAT_COUNT=0
    echo -e "${YELLOW}Total: 0 instances${NC}"
fi

# Pattern 2: SELECT with embedded variables
echo ""
echo "Pattern 2: SELECT queries with embedded variables"
SELECT_FILE="/tmp/sql-select.txt"
grep -rn "SELECT.*\\\$" sections/api/ --include="*.php" | \
    grep -v "prepare" | \
    grep -v "^\s*//" | \
    grep -v "//.*SELECT" > "$SELECT_FILE" || true

if [ -s "$SELECT_FILE" ]; then
    echo -e "${RED}⚠️  Found SELECT with embedded variables:${NC}"
    cat "$SELECT_FILE" | head -10
    SELECT_COUNT=$(wc -l < "$SELECT_FILE")
    echo -e "${YELLOW}Total: $SELECT_COUNT instances${NC}"
    TOTAL_ISSUES=$((TOTAL_ISSUES + SELECT_COUNT))
else
    echo -e "${GREEN}✅ No SELECT with embedded vars${NC}"
    SELECT_COUNT=0
    echo -e "${YELLOW}Total: 0 instances${NC}"
fi

# Pattern 3: UPDATE with embedded variables
echo ""
echo "Pattern 3: UPDATE queries with embedded variables"
UPDATE_FILE="/tmp/sql-update.txt"
grep -rn "UPDATE.*SET.*\\\$" sections/api/ --include="*.php" | \
    grep -v "prepare" | \
    grep -v "^\s*//" | \
    grep -v "//.*UPDATE" > "$UPDATE_FILE" || true

if [ -s "$UPDATE_FILE" ]; then
    echo -e "${RED}⚠️  Found UPDATE with embedded variables:${NC}"
    cat "$UPDATE_FILE" | head -10
    UPDATE_COUNT=$(wc -l < "$UPDATE_FILE")
    echo -e "${YELLOW}Total: $UPDATE_COUNT instances${NC}"
    TOTAL_ISSUES=$((TOTAL_ISSUES + UPDATE_COUNT))
else
    echo -e "${GREEN}✅ No UPDATE with embedded vars${NC}"
    UPDATE_COUNT=0
    echo -e "${YELLOW}Total: 0 instances${NC}"
fi

# Pattern 4: INSERT with embedded variables
echo ""
echo "Pattern 4: INSERT queries with embedded variables"
INSERT_FILE="/tmp/sql-insert.txt"
grep -rn "INSERT.*INTO.*\\\$" sections/api/ --include="*.php" | \
    grep -v "prepare" | \
    grep -v "^\s*//" | \
    grep -v "//.*INSERT" > "$INSERT_FILE" || true

if [ -s "$INSERT_FILE" ]; then
    echo -e "${RED}⚠️  Found INSERT with embedded variables:${NC}"
    cat "$INSERT_FILE" | head -10
    INSERT_COUNT=$(wc -l < "$INSERT_FILE")
    echo -e "${YELLOW}Total: $INSERT_COUNT instances${NC}"
    TOTAL_ISSUES=$((TOTAL_ISSUES + INSERT_COUNT))
else
    echo -e "${GREEN}✅ No INSERT with embedded vars${NC}"
    INSERT_COUNT=0
    echo -e "${YELLOW}Total: 0 instances${NC}"
fi

# Pattern 5: DELETE with embedded variables
echo ""
echo "Pattern 5: DELETE queries with embedded variables"
DELETE_FILE="/tmp/sql-delete.txt"
grep -rn "DELETE.*FROM.*\\\$" sections/api/ --include="*.php" | \
    grep -v "prepare" | \
    grep -v "^\s*//" | \
    grep -v "//.*DELETE" > "$DELETE_FILE" || true

if [ -s "$DELETE_FILE" ]; then
    echo -e "${RED}⚠️  Found DELETE with embedded variables:${NC}"
    cat "$DELETE_FILE" | head -10
    DELETE_COUNT=$(wc -l < "$DELETE_FILE")
    echo -e "${YELLOW}Total: $DELETE_COUNT instances${NC}"
    TOTAL_ISSUES=$((TOTAL_ISSUES + DELETE_COUNT))
else
    echo -e "${GREEN}✅ No DELETE with embedded vars${NC}"
    DELETE_COUNT=0
    echo -e "${YELLOW}Total: 0 instances${NC}"
fi

# Pattern 6: sprintf/printf string building (potential SQL injection)
echo ""
echo "Pattern 6: sprintf/printf SQL string building"
SPRINTF_FILE="/tmp/sql-sprintf.txt"
grep -rn "sprintf.*SELECT\|sprintf.*INSERT\|sprintf.*UPDATE\|sprintf.*DELETE" sections/api/ --include="*.php" > "$SPRINTF_FILE" || true

if [ -s "$SPRINTF_FILE" ]; then
    echo -e "${YELLOW}⚠️  Found sprintf SQL building (review needed):${NC}"
    cat "$SPRINTF_FILE" | head -10
    SPRINTF_COUNT=$(wc -l < "$SPRINTF_FILE")
    echo -e "${YELLOW}Total: $SPRINTF_COUNT instances${NC}"
    TOTAL_ISSUES=$((TOTAL_ISSUES + SPRINTF_COUNT))
else
    echo -e "${GREEN}✅ No sprintf SQL building${NC}"
    SPRINTF_COUNT=0
    echo -e "${YELLOW}Total: 0 instances${NC}"
fi

# Pattern 7: Legacy mysqli usage
echo ""
echo "Pattern 7: Legacy mysqli usage"
MYSQLI_FILE="/tmp/sql-mysqli.txt"
grep -rn "mysqli_query\|mysql_query" sections/api/ --include="*.php" > "$MYSQLI_FILE" || true

if [ -s "$MYSQLI_FILE" ]; then
    echo -e "${RED}⚠️  Found legacy mysqli usage:${NC}"
    cat "$MYSQLI_FILE"
    MYSQLI_COUNT=$(wc -l < "$MYSQLI_FILE")
    echo -e "${YELLOW}Total: $MYSQLI_COUNT instances${NC}"
    TOTAL_ISSUES=$((TOTAL_ISSUES + MYSQLI_COUNT))
else
    echo -e "${GREEN}✅ No legacy mysqli usage${NC}"
    MYSQLI_COUNT=0
    echo -e "${YELLOW}Total: 0 instances${NC}"
fi

# Pattern 8: PDO prepared statements (safe pattern - for reference)
echo ""
echo "Pattern 8: PDO prepared statement usage (safe pattern)"
PREPARED_STMTS=$(grep -rn '\->prepare(' sections/api/ --include="*.php" | wc -l)
PREPARE_FILES=$(grep -rl '\->prepare(' sections/api/ --include="*.php" | wc -l)
echo -e "${GREEN}✅ Found $PREPARED_STMTS prepared statement calls in $PREPARE_FILES files${NC}"

# Summary
echo ""
echo -e "${YELLOW}=== Summary ===${NC}"

if [ $TOTAL_ISSUES -eq 0 ]; then
    echo -e "${GREEN}✅ No SQL injection vulnerabilities detected${NC}"
else
    echo -e "${RED}⚠️  Total potential issues: $TOTAL_ISSUES${NC}"
    echo ""
    echo "Breakdown:"
    echo "  - Concatenation: $CONCAT_COUNT"
    echo "  - SELECT with vars: $SELECT_COUNT"
    echo "  - UPDATE with vars: $UPDATE_COUNT"
    echo "  - INSERT with vars: $INSERT_COUNT"
    echo "  - DELETE with vars: $DELETE_COUNT"
    echo "  - sprintf building: $SPRINTF_COUNT"
    echo "  - Legacy mysqli: $MYSQLI_COUNT"
fi

echo ""
echo -e "${GREEN}Safe patterns: $PREPARED_STMTS prepared statement calls in $PREPARE_FILES files${NC}"
echo ""
echo "Evidence files generated:"
echo "  - /tmp/sql-concat.txt ($CONCAT_COUNT issues)"
echo "  - /tmp/sql-select.txt ($SELECT_COUNT issues)"
echo "  - /tmp/sql-update.txt ($UPDATE_COUNT issues)"
echo "  - /tmp/sql-insert.txt ($INSERT_COUNT issues)"
echo "  - /tmp/sql-delete.txt ($DELETE_COUNT issues)"
echo "  - /tmp/sql-sprintf.txt ($SPRINTF_COUNT issues)"
echo "  - /tmp/sql-mysqli.txt ($MYSQLI_COUNT issues)"
