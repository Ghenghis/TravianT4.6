# Security Helpers Integration Guide

## Overview

Three security helper classes have been created but are NOT YET INTEGRATED into the application:

1. **DatabaseSecurity** - Safe database operations
2. **ValidationMiddleware** - Input sanitization
3. **OutputEncoder** - Output encoding

This guide provides integration instructions and examples.

---

## 1. DatabaseSecurity Integration

### Location
`sections/api/include/Security/DatabaseSecurity.php`

### Purpose
Enforce PDO prepared statements for all database operations.

### Integration Plan

**Step 1:** Review all database operations in controllers

```bash
# Find all database query locations
grep -r "->query\|->exec" sections/api/include/Api/Ctrl/ --include="*.php"
```

**Step 2:** Replace raw queries with DatabaseSecurity helpers

**BEFORE (Unsafe):**
```php
$pdo = getDatabase();
$userId = $_GET['id'];  // User input
$query = "SELECT * FROM users WHERE id = $userId";  // SQL INJECTION!
$result = $pdo->query($query);
```

**AFTER (Safe):**
```php
use App\Security\DatabaseSecurity;

$pdo = getDatabase();
$userId = $_GET['id'];  // User input
$result = DatabaseSecurity::safeSelect(
    $pdo,
    "SELECT * FROM users WHERE id = ?",
    [$userId]  // Parameterized
);
```

**Step 3:** Apply to all CRUD operations

```php
// SELECT
$users = DatabaseSecurity::safeSelect($pdo, "SELECT * FROM users WHERE active = ?", [1]);

// INSERT
$id = DatabaseSecurity::safeInsert($pdo, "INSERT INTO users (name, email) VALUES (?, ?)", [$name, $email]);

// UPDATE
$rows = DatabaseSecurity::safeUpdate($pdo, "UPDATE users SET status = ? WHERE id = ?", ['active', $userId]);

// DELETE
$rows = DatabaseSecurity::safeDelete($pdo, "DELETE FROM sessions WHERE expires < ?", [time()]);
```

---

## 2. ValidationMiddleware Integration

### Location
`sections/api/include/Middleware/ValidationMiddleware.php`

### Purpose
Sanitize all user inputs before processing.

### Integration Plan

**Step 1:** Add to controller constructor or method

```php
use App\Middleware\ValidationMiddleware;

class UserController {
    public function createUser() {
        // Sanitize inputs
        $name = ValidationMiddleware::sanitizeString($_POST['name'] ?? '', 100);
        $email = ValidationMiddleware::sanitizeEmail($_POST['email'] ?? '');
        $age = ValidationMiddleware::sanitizeInt($_POST['age'] ?? 0);
        
        // Validate
        if (!$email) {
            return ['error' => 'Invalid email'];
        }
        
        // Safe to use
        $result = DatabaseSecurity::safeInsert(
            $pdo,
            "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
            [$name, $email, $age]
        );
    }
}
```

**Step 2:** Apply to all user input sources

```php
// GET parameters
$search = ValidationMiddleware::sanitizeString($_GET['q'] ?? '', 200);

// POST parameters
$username = ValidationMiddleware::sanitizeString($_POST['username'] ?? '', 50);
$email = ValidationMiddleware::sanitizeEmail($_POST['email'] ?? '');

// JSON body
$data = json_decode(file_get_contents('php://input'), true);
$title = ValidationMiddleware::sanitizeString($data['title'] ?? '', 255);

// File uploads
$filename = ValidationMiddleware::sanitizeString($_FILES['file']['name'] ?? '', 100);
```

---

## 3. OutputEncoder Integration

### Location
`sections/api/include/Security/OutputEncoder.php`

### Purpose
Encode all outputs to prevent XSS attacks.

### Integration Plan

**Step 1:** Use in views/templates

**BEFORE (Unsafe):**
```php
<h1>Welcome, <?php echo $username; ?></h1>  <!-- XSS VULNERABILITY! -->
```

**AFTER (Safe):**
```php
use App\Security\OutputEncoder;

<h1>Welcome, <?php echo OutputEncoder::html($username); ?></h1>
```

**Step 2:** Context-aware encoding

```php
use App\Security\OutputEncoder;

// HTML context
<p><?php echo OutputEncoder::html($userBio); ?></p>

// HTML attribute context
<input value="<?php echo OutputEncoder::attr($username); ?>">

// JavaScript context
<script>
var username = <?php echo OutputEncoder::js($username); ?>;
</script>

// URL context
<a href="profile.php?user=<?php echo OutputEncoder::url($username); ?>">Profile</a>

// JSON API response
header('Content-Type: application/json');
echo OutputEncoder::json(['user' => $username, 'bio' => $userBio]);
```

**Step 3:** Apply to all output points

```php
// Direct output
echo OutputEncoder::html($data);

// In loops
foreach ($users as $user) {
    echo '<li>' . OutputEncoder::html($user['name']) . '</li>';
}

// API responses (JSON)
echo OutputEncoder::json([
    'status' => 'success',
    'data' => $results
]);
```

---

## Integration Priority

### HIGH PRIORITY (Immediate)
1. Database operations in authentication (UserController, AuthController)
2. User input in registration/login flows
3. API responses containing user-generated content

### MEDIUM PRIORITY (Next Sprint)
1. All remaining controller database operations
2. Admin panel user inputs
3. Game action API endpoints

### LOW PRIORITY (Ongoing)
1. Legacy code refactoring
2. Worker scripts database operations
3. Internal tool outputs

---

## Automated Integration

### Step 1: Find All Database Operations

```bash
# Generate list of files with database operations
find sections/api/include/Api/Ctrl/ -name "*.php" -exec grep -l "query\|exec" {} \; > /tmp/db-files.txt
```

### Step 2: Review Each File

```bash
# For each file, check SQL patterns
while read file; do
    echo "=== $file ==="
    grep -n "query\|exec" "$file"
done < /tmp/db-files.txt
```

### Step 3: Systematic Replacement

Create a task per controller:
- Review database operations
- Replace with DatabaseSecurity helpers
- Add input validation
- Add output encoding
- Test functionality

---

## Testing Integration

### Unit Tests

```php
// Test DatabaseSecurity
class DatabaseSecurityTest extends PHPUnit\Framework\TestCase {
    public function testSafeSelect() {
        $pdo = $this->getPdoMock();
        $result = DatabaseSecurity::safeSelect($pdo, "SELECT * FROM users WHERE id = ?", [1]);
        $this->assertIsArray($result);
    }
    
    public function testInvalidQueryTypeThrows() {
        $this->expectException(\InvalidArgumentException::class);
        DatabaseSecurity::safeSelect($pdo, "DELETE FROM users", []);  // Wrong type!
    }
}

// Test ValidationMiddleware
class ValidationMiddlewareTest extends PHPUnit\Framework\TestCase {
    public function testSanitizeEmail() {
        $valid = ValidationMiddleware::sanitizeEmail('user@example.com');
        $this->assertEquals('user@example.com', $valid);
        
        $invalid = ValidationMiddleware::sanitizeEmail('not-an-email');
        $this->assertNull($invalid);
    }
}

// Test OutputEncoder
class OutputEncoderTest extends PHPUnit\Framework\TestCase {
    public function testHtmlEncoding() {
        $input = '<script>alert("XSS")</script>';
        $output = OutputEncoder::html($input);
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }
}
```

---

## Status Tracking

| Component | Status | Files Updated | Tests Added |
|-----------|--------|---------------|-------------|
| DatabaseSecurity | ⚠️ Not Integrated | 0 | 0 |
| ValidationMiddleware | ⚠️ Not Integrated | 0 | 0 |
| OutputEncoder | ⚠️ Not Integrated | 0 | 0 |

**Target:** 100% integration before production deployment

---

## Next Steps

1. Create integration tickets for each controller
2. Assign to development team
3. Review and test integrations
4. Update this document with progress
5. Mark as complete when all controllers use helpers
