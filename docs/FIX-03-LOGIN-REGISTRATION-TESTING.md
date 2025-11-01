# Fix 03: Login & Registration System Testing

## Problem Statement

After MySQL conversion and game world setup, we need to **verify and fix** the complete user flow:
1. User registration
2. Account activation
3. User login
4. Access to game world

## Current Status

### ✅ What's Working
- API endpoints respond
- Registration saves to `activation` table
- Frontend calls correct API URLs

### ❌ What Needs Testing
- End-to-end registration flow
- Email activation system
- Login with valid credentials
- Redirect to game world after login
- Session management

---

## Step 1: Test User Registration

### Test Registration API Endpoint

**CREATE TEST FILE**: `test-registration.php`

```php
<?php
require_once __DIR__ . '/sections/globalConfig.php';

// Test registration endpoint
$url = 'http://localhost:5000/v1/register/register';

$data = [
    'lang' => 'international',
    'gameWorld' => 1,
    'username' => 'testuser_' . time(),
    'email' => 'test_' . time() . '@example.com',
    'password' => 'TestPassword123',
    'termsAndConditions' => true,
    'subscribeNewsletter' => false
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: {$response}\n\n";

$result = json_decode($response, true);

if ($result['success'] ?? false) {
    echo "✓ Registration successful!\n";
    echo "  Username: {$data['username']}\n";
    echo "  Email: {$data['email']}\n";
    
    // Check database
    require_once __DIR__ . '/sections/api/include/Database/DB.php';
    $db = Database\DB::getInstance();
    $stmt = $db->prepare("SELECT * FROM activation WHERE name = :username");
    $stmt->execute(['username' => $data['username']]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✓ User found in database\n";
        echo "  Token: {$user['token']}\n";
        echo "  Time: " . date('Y-m-d H:i:s', $user['time']) . "\n";
    } else {
        echo "✗ User NOT found in database!\n";
    }
} else {
    echo "✗ Registration failed\n";
    print_r($result);
}
```

**Run test**:
```bash
php test-registration.php
```

**Expected output**:
```
HTTP Code: 200
Response: {"success":true,"error":{"errorType":null,"errorMsg":null},"data":{...}}

✓ Registration successful!
  Username: testuser_1234567890
  Email: test_1234567890@example.com
✓ User found in database
  Token: a1b2c3d4e5f6g7h8i9j0
  Time: 2025-10-28 12:00:00
```

### Fix If Registration Fails

**Common Issue 1**: Email blacklist check fails

**EDIT**: `sections/api/include/Api/Ctrl/RegisterCtrl.php`

**Find this code** (around line 100):
```php
// Check email blacklist
$stmt = $db->prepare("SELECT * FROM email_blacklist WHERE email = :email OR domain = :domain");
```

**If table doesn't exist**, temporarily disable:
```php
// TODO: Re-enable when email_blacklist table exists
// $stmt = $db->prepare("SELECT * FROM email_blacklist WHERE email = :email OR domain = :domain");
```

**Common Issue 2**: Missing filtering file

**CREATE FILE**: `filtering/blackListedNames.txt`

```
admin
administrator
moderator
support
system
test
demo
guest
```

---

## Step 2: Test Login System

### Test Login API Endpoint

**CREATE TEST FILE**: `test-login.php`

```php
<?php
require_once __DIR__ . '/sections/globalConfig.php';

// First, register a test user
echo "Creating test user...\n";
require_once __DIR__ . '/sections/api/include/Database/DB.php';
require_once __DIR__ . '/sections/api/include/Database/ServerDB.php';

$globalDb = Database\DB::getInstance();

// Insert into activation table
$username = 'logintest';
$password = 'TestPass123';
$email = 'logintest@example.com';

$stmt = $globalDb->prepare("
    INSERT INTO activation (wid, name, password, email, token, time)
    VALUES (:wid, :name, :password, :email, :token, :time)
");

$stmt->execute([
    'wid' => 1,
    'name' => $username,
    'password' => sha1($password),
    'email' => $email,
    'token' => md5(uniqid()),
    'time' => time()
]);

echo "✓ Test user created in activation table\n";

// Now move user to game world (simulate activation)
$worldConfig = __DIR__ . '/sections/servers/testworld/include/connection.php';
$worldDb = Database\ServerDB::getInstance($worldConfig);

$stmt = $worldDb->prepare("
    INSERT INTO users (name, password, email, race, kid, signupTime)
    VALUES (:name, :password, :email, :race, :kid, :time)
");

$stmt->execute([
    'name' => $username,
    'password' => sha1($password),
    'email' => $email,
    'race' => 1,  // Romans
    'kid' => 1,   // Village ID (placeholder)
    'time' => time()
]);

echo "✓ Test user activated in game world\n\n";

// Now test login
echo "Testing login...\n";
$url = 'http://localhost:5000/v1/auth/login';

$data = [
    'lang' => 'international',
    'gameWorldId' => 1,
    'usernameOrEmail' => $username,
    'password' => $password,
    'lowResMode' => false
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: {$response}\n\n";

$result = json_decode($response, true);

if (isset($result['redirect'])) {
    echo "✓ Login successful!\n";
    echo "  Redirect URL: {$result['redirect']}\n";
} else {
    echo "✗ Login failed\n";
    print_r($result);
}
```

**Run test**:
```bash
php test-login.php
```

**Expected output**:
```
Creating test user...
✓ Test user created in activation table
✓ Test user activated in game world

Testing login...
HTTP Code: 200
Response: {"redirect":"http://testworld.travian.local/login.php?detectLang&lowRes=0&handshake=abc123"}

✓ Login successful!
  Redirect URL: http://testworld.travian.local/login.php?detectLang&lowRes=0&handshake=abc123
```

### Fix If Login Fails

**Common Issue 1**: LoginOperator class not found

**CHECK FILE**: `sections/api/include/Core/LoginOperator.php`

**If missing, CREATE**:

```php
<?php
namespace Core;

use PDO;

class LoginOperator
{
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    /**
     * Find login by username or email
     */
    public function findLogin($wid, $usernameOrEmail)
    {
        // Check activation table first
        $globalDb = \Database\DB::getInstance();
        $stmt = $globalDb->prepare("
            SELECT * FROM activation 
            WHERE (name = :login OR email = :login) AND wid = :wid
        ");
        $stmt->execute([
            'login' => $usernameOrEmail,
            'wid' => $wid
        ]);
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return [
                'type' => 2,  // Activation pending
                'row' => $row
            ];
        }
        
        // Check users table (activated accounts)
        $stmt = $this->db->prepare("
            SELECT * FROM users 
            WHERE name = :login OR email = :login
        ");
        $stmt->execute(['login' => $usernameOrEmail]);
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return [
                'type' => 1,  // Activated user
                'row' => $row
            ];
        }
        
        return ['type' => 0];  // Not found
    }
    
    /**
     * Check password
     */
    public function checkLogin($password, $find)
    {
        if ($find['type'] == 0) {
            return 3;  // User not found
        }
        
        if ($find['row']['password'] === $password) {
            return 0;  // Correct password
        }
        
        return 3;  // Wrong password
    }
    
    /**
     * Insert handshake for session
     */
    public function insertHandshake($uid, $isSitter = false)
    {
        $handshake = md5(uniqid() . time() . $uid);
        
        // Store handshake in database or session
        // For now, just return it
        return $handshake;
    }
}
```

**Common Issue 2**: Missing handshake table

**Run this SQL**:
```sql
USE travian_testworld;

CREATE TABLE IF NOT EXISTS `handshake` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid` INT(11) UNSIGNED NOT NULL,
  `handshake` VARCHAR(64) NOT NULL,
  `sitter` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `handshake` (`handshake`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Step 3: Test Complete User Flow

### End-to-End Test

**CREATE TEST FILE**: `test-complete-flow.php`

```php
<?php
require_once __DIR__ . '/sections/globalConfig.php';

echo "=== Complete User Flow Test ===\n\n";

$username = 'flowtest_' . time();
$email = "flowtest_{time()}@example.com";
$password = 'TestPassword123';

// Step 1: Register
echo "Step 1: Registration\n";
$data = [
    'lang' => 'international',
    'gameWorld' => 1,
    'username' => $username,
    'email' => $email,
    'password' => $password,
    'termsAndConditions' => true
];

$ch = curl_init('http://localhost:5000/v1/register/register');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

if ($response['success'] ?? false) {
    echo "✓ Registration successful\n\n";
} else {
    echo "✗ Registration failed\n";
    print_r($response);
    exit(1);
}

// Step 2: Simulate activation (normally done via email link)
echo "Step 2: Account Activation (simulated)\n";

require_once __DIR__ . '/sections/api/include/Database/DB.php';
require_once __DIR__ . '/sections/api/include/Database/ServerDB.php';

$globalDb = Database\DB::getInstance();
$stmt = $globalDb->prepare("SELECT * FROM activation WHERE name = :username");
$stmt->execute(['username' => $username]);
$activation = $stmt->fetch();

if (!$activation) {
    echo "✗ User not found in activation table\n";
    exit(1);
}

// Move to game world users table
$worldConfig = __DIR__ . '/sections/servers/testworld/include/connection.php';
$worldDb = Database\ServerDB::getInstance($worldConfig);

$stmt = $worldDb->prepare("
    INSERT INTO users (name, password, email, race, kid, signupTime)
    VALUES (:name, :password, :email, :race, :kid, :time)
");

$stmt->execute([
    'name' => $activation['name'],
    'password' => $activation['password'],
    'email' => $activation['email'],
    'race' => 1,
    'kid' => 1,
    'time' => time()
]);

// Delete from activation table
$globalDb->prepare("DELETE FROM activation WHERE id = :id")->execute(['id' => $activation['id']]);

echo "✓ Account activated\n\n";

// Step 3: Login
echo "Step 3: Login\n";

$data = [
    'lang' => 'international',
    'gameWorldId' => 1,
    'usernameOrEmail' => $username,
    'password' => $password
];

$ch = curl_init('http://localhost:5000/v1/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

if (isset($response['redirect'])) {
    echo "✓ Login successful\n";
    echo "  Redirect: {$response['redirect']}\n\n";
} else {
    echo "✗ Login failed\n";
    print_r($response);
    exit(1);
}

// Step 4: Verify user in database
echo "Step 4: Database Verification\n";

$stmt = $worldDb->prepare("SELECT * FROM users WHERE name = :username");
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

if ($user) {
    echo "✓ User exists in game world database\n";
    echo "  ID: {$user['id']}\n";
    echo "  Name: {$user['name']}\n";
    echo "  Email: {$user['email']}\n";
    echo "  Race: {$user['race']}\n";
} else {
    echo "✗ User not found in game world\n";
    exit(1);
}

echo "\n=== All Tests Passed! ===\n";
```

**Run complete flow test**:
```bash
php test-complete-flow.php
```

**Expected output**:
```
=== Complete User Flow Test ===

Step 1: Registration
✓ Registration successful

Step 2: Account Activation (simulated)
✓ Account activated

Step 3: Login
✓ Login successful
  Redirect: http://testworld.travian.local/login.php?detectLang&lowRes=0&handshake=abc123

Step 4: Database Verification
✓ User exists in game world database
  ID: 1
  Name: flowtest_1234567890
  Email: flowtest_1234567890@example.com
  Race: 1

=== All Tests Passed! ===
```

---

## Verification Checklist

- [ ] Registration API endpoint works
- [ ] User data saves to activation table
- [ ] Login API endpoint works
- [ ] User can login with username
- [ ] User can login with email
- [ ] Login returns redirect URL
- [ ] User exists in game world database after activation
- [ ] Complete flow test passes

---

## Common Issues and Fixes

### "User does not exist"

**Cause**: User not in `users` table

**Fix**: Ensure activation process moves user from `activation` to `users` table

### "Password wrong"

**Cause**: Password hashing mismatch

**Fix**: Ensure both registration and login use `sha1()`:
```php
$hashedPassword = sha1($password);
```

### "Unknown game world"

**Cause**: Invalid gameWorldId or missing server record

**Fix**: Check gameServers table:
```sql
SELECT * FROM gameServers WHERE id = 1;
```

### "Configuration file not found"

**Cause**: Missing connection.php or wrong path

**Fix**: Verify file exists and path in database matches:
```bash
ls -la sections/servers/testworld/include/connection.php
```

---

## Next Steps

Once login/registration is working:
- ✅ Continue to **FIX-04-EMAIL-CONFIGURATION.md**
- Configure SMTP for real email activation
- Test password recovery
- Set up automated email queue processing
