#!/bin/bash
# Fix PostgreSQL SQL files - remove trailing commas

INPUT="$1"
OUTPUT="${INPUT}.fixed"

# Remove trailing commas before closing parentheses
sed 's/,\s*)/)/g' "$INPUT" > "$OUTPUT"

mv "$OUTPUT" "$INPUT"
echo "✅ Fixed $INPUT"
