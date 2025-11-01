#!/usr/bin/env php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Creating Test Accounts for Travian Game Worlds ===\n\n";

// Database connection
$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    die("❌ DATABASE_URL not found\n");
}

try {
    $db = new PDO($dbUrl);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connected to database\n\n";
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// Test accounts to create
$testAccounts = [
    [
        'worldid' => 1,  // testworld
        'name' => 'TestPlayer1',
        'email' => 'test1@travian.local',
        'password' => password_hash('test123', PASSWORD_DEFAULT)
    ],
    [
        'worldid' => 2,  // demo
        'name' => 'TestPlayer2',
        'email' => 'test2@travian.local',
        'password' => password_hash('test123', PASSWORD_DEFAULT)
    ],
    [
        'worldid' => 6,  // speed500k
        'name' => 'TestPlayer3',
        'email' => 'test3@travian.local',
        'password' => password_hash('test123', PASSWORD_DEFAULT)
    ],
    [
        'worldid' => 1,  // testworld - admin account
        'name' => 'AdminTest',
        'email' => 'admin@travian.local',
        'password' => password_hash('admin123', PASSWORD_DEFAULT)
    ]
];

echo "Creating test accounts:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    INSERT INTO activation (worldid, name, password, email, activationcode, token, newsletter, used, refuid, time, reminded)
    VALUES (:worldid, :name, :password, :email, '', :token, 0, 1, 0, :time, 0)
    ON CONFLICT (name, worldid) DO UPDATE 
    SET password = EXCLUDED.password, 
        email = EXCLUDED.email,
        used = 1,
        token = EXCLUDED.token
");

$created = 0;
foreach ($testAccounts as $account) {
    $token = bin2hex(random_bytes(16));
    $time = time();
    
    try {
        $stmt->execute([
            ':worldid' => $account['worldid'],
            ':name' => $account['name'],
            ':password' => $account['password'],
            ':email' => $account['email'],
            ':token' => $token,
            ':time' => $time
        ]);
        
        echo "✅ Created: {$account['name']} (World ID: {$account['worldid']}, Email: {$account['email']})\n";
        $created++;
    } catch (PDOException $e) {
        echo "⚠️  Skipped: {$account['name']} - " . $e->getMessage() . "\n";
    }
}

echo str_repeat("-", 80) . "\n";
echo "\n✅ Created/Updated {$created} test accounts\n\n";

// Display login credentials
echo "TEST CREDENTIALS:\n";
echo str_repeat("=", 80) . "\n";
echo "Username: TestPlayer1 | Password: test123 | World: testworld (ID: 1)\n";
echo "Username: TestPlayer2 | Password: test123 | World: demo (ID: 2)\n";
echo "Username: TestPlayer3 | Password: test123 | World: speed500k (ID: 6)\n";
echo "Username: AdminTest   | Password: admin123 | World: testworld (ID: 1)\n";
echo str_repeat("=", 80) . "\n";

// Verify accounts
echo "\nVerifying accounts in database:\n";
$verify = $db->query("SELECT worldid, name, email, used FROM activation WHERE name LIKE 'Test%' OR name = 'AdminTest' ORDER BY worldid, name");
$accounts = $verify->fetchAll(PDO::FETCH_ASSOC);

foreach ($accounts as $acc) {
    $status = $acc['used'] == 1 ? '✅ ACTIVATED' : '⚠️  PENDING';
    echo "{$status} - World {$acc['worldid']}: {$acc['name']} ({$acc['email']})\n";
}

echo "\n🎮 Test accounts ready for gameplay testing!\n";
