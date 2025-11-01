#!/bin/bash
# MySQL to PostgreSQL Schema Conversion Script
# Converts T4.4.sql and main.sql to PostgreSQL-compatible syntax

set -e

INPUT_FILE="$1"
OUTPUT_FILE="$2"

if [ -z "$INPUT_FILE" ] || [ -z "$OUTPUT_FILE" ]; then
    echo "Usage: $0 <input_mysql_file> <output_postgresql_file>"
    echo "Example: $0 main_script/include/schema/T4.4.sql T4.4-PostgreSQL-Full.sql"
    exit 1
fi

echo "Converting $INPUT_FILE to PostgreSQL format..."
echo "Output: $OUTPUT_FILE"

# Create temporary file for processing
TEMP_FILE=$(mktemp)

# Copy input to temp file
cp "$INPUT_FILE" "$TEMP_FILE"

# 1. Remove backticks (MySQL quoting)
sed -i 's/`//g' "$TEMP_FILE"

# 2. Convert AUTO_INCREMENT to SERIAL/BIGSERIAL
# For BIGINT AUTO_INCREMENT → BIGSERIAL
sed -i 's/BIGINT([0-9]*) UNSIGNED NOT NULL AUTO_INCREMENT/BIGSERIAL NOT NULL/g' "$TEMP_FILE"
sed -i 's/BIGINT([0-9]*) NOT NULL AUTO_INCREMENT/BIGSERIAL NOT NULL/g' "$TEMP_FILE"
# For INT AUTO_INCREMENT → SERIAL
sed -i 's/INT([0-9]*) UNSIGNED NOT NULL AUTO_INCREMENT/SERIAL NOT NULL/g' "$TEMP_FILE"
sed -i 's/INT([0-9]*) NOT NULL AUTO_INCREMENT/SERIAL NOT NULL/g' "$TEMP_FILE"

# 3. Remove ENGINE and CHARSET lines
sed -i '/ENGINE = InnoDB/d' "$TEMP_FILE"
sed -i '/ENGINE=InnoDB/d' "$TEMP_FILE"
sed -i '/DEFAULT CHARSET = utf8mb4/d' "$TEMP_FILE"
sed -i '/DEFAULT CHARSET=utf8mb4/d' "$TEMP_FILE"
sed -i '/DEFAULT CHARSET = utf8/d' "$TEMP_FILE"
sed -i '/DEFAULT CHARSET=utf8/d' "$TEMP_FILE"
sed -i '/DEFAULT CHARSET = latin1/d' "$TEMP_FILE"
sed -i '/DEFAULT CHARSET=latin1/d' "$TEMP_FILE"

# 4. Remove AUTO_INCREMENT = X lines
sed -i '/AUTO_INCREMENT = [0-9]/d' "$TEMP_FILE"
sed -i '/AUTO_INCREMENT=[0-9]/d' "$TEMP_FILE"

# 5. Convert data types
# TINYINT → SMALLINT
sed -i 's/tinyint([0-9]*) UNSIGNED/SMALLINT/gi' "$TEMP_FILE"
sed -i 's/tinyint([0-9]*)/SMALLINT/gi' "$TEMP_FILE"
sed -i 's/tinyint UNSIGNED/SMALLINT/gi' "$TEMP_FILE"
sed -i 's/\btinyint\b/SMALLINT/gi' "$TEMP_FILE"

# MEDIUMINT → INTEGER
sed -i 's/mediumint([0-9]*) UNSIGNED/INTEGER/gi' "$TEMP_FILE"
sed -i 's/mediumint([0-9]*)/INTEGER/gi' "$TEMP_FILE"

# INT → INTEGER (but preserve BIGINT, SERIAL, etc.)
sed -i 's/\bint([0-9]*) UNSIGNED/INTEGER/gi' "$TEMP_FILE"
sed -i 's/\bint([0-9]*)/INTEGER/gi' "$TEMP_FILE"
sed -i 's/\bint UNSIGNED/INTEGER/gi' "$TEMP_FILE"

# BIGINT → BIGINT (remove size specifiers)
sed -i 's/bigint([0-9]*) UNSIGNED/BIGINT/gi' "$TEMP_FILE"
sed -i 's/bigint([0-9]*)/BIGINT/gi' "$TEMP_FILE"

# DOUBLE → DOUBLE PRECISION
sed -i 's/\bdouble\b/DOUBLE PRECISION/gi' "$TEMP_FILE"

# 6. Remove KEY statements (PostgreSQL handles differently)
# Remove lines that start with KEY
sed -i '/^  KEY /d' "$TEMP_FILE"
# Remove standalone KEY lines
sed -i '/^\s*KEY /d' "$TEMP_FILE"

# 7. Handle ENUM types (PostgreSQL doesn't support inline ENUM the same way)
# Convert enum to VARCHAR for simplicity
sed -i "s/enum([^)]*)/VARCHAR(50)/g" "$TEMP_FILE"

# 8. Remove UNSIGNED constraint comments (PostgreSQL doesn't have UNSIGNED)
# Just remove the word UNSIGNED where it remains
sed -i 's/ UNSIGNED//g' "$TEMP_FILE"

# 9. Fix current_timestamp() → NOW()
sed -i 's/current_timestamp()/NOW()/g' "$TEMP_FILE"
sed -i 's/CURRENT_TIMESTAMP/NOW()/g' "$TEMP_FILE"

# 10. Convert DEFAULT 'value' to DEFAULT 'value'::type where needed
# PostgreSQL is stricter about type casting

# 11. Remove trailing semicolons from ENGINE/CHARSET lines (already removed above)

# 12. Fix TEXT types
sed -i 's/\blongtext\b/TEXT/gi' "$TEMP_FILE"
sed -i 's/\bmediumtext\b/TEXT/gi' "$TEMP_FILE"

# 13. Remove any remaining AUTO_INCREMENT references
sed -i 's/ AUTO_INCREMENT//g' "$TEMP_FILE"

# 14. Fix CHARACTER SET statements
sed -i 's/ CHARACTER SET [a-z0-9]*//gi' "$TEMP_FILE"
sed -i 's/ COLLATE [a-z0-9_]*//gi' "$TEMP_FILE"

# 15. Close table definitions properly (remove trailing commas before closing paren)
sed -i 's/,\s*)/\n)/g' "$TEMP_FILE"

# 13. Add IF NOT EXISTS to CREATE TABLE statements for safety (only if not already there)
sed -i 's/^CREATE TABLE \([^I]\)/CREATE TABLE IF NOT EXISTS \1/g' "$TEMP_FILE"

# 14. Keep DROP TABLE IF EXISTS statements as-is (they're compatible)

# Output the converted file
cp "$TEMP_FILE" "$OUTPUT_FILE"
rm "$TEMP_FILE"

echo "✅ Conversion complete!"
echo "Output written to: $OUTPUT_FILE"
echo ""
echo "Review the file and run:"
echo "  psql \$DATABASE_URL < $OUTPUT_FILE"
