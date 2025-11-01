#!/bin/bash
echo "=== Testing Registration API Endpoint ==="
echo ""
echo "Test 1: With lang parameter in payload"
curl -s -X POST 'http://localhost:5000/v1/register/register' \
  -H "Content-Type: application/json" \
  -d '{
    "lang": "en",
    "gameWorld": 1,
    "username": "testuser123",
    "email": "testuser123@example.com",
    "password": "Test123!",
    "termsAndConditions": true,
    "subscribeNewsletter": false
  }' | python3 -c "import sys, json; print(json.dumps(json.load(sys.stdin), indent=2))" 2>&1

echo ""
echo "=== Check database for registration ==="
