<?php
require_once __DIR__ . '/sections/api/include/Database/ServerDB.php';

use Database\ServerDB;

// Test testworld connection
echo "Testing testworld schema connection...\n";
try {
    $configPath = __DIR__ . '/sections/servers/testworld/include/connection.php';
    
    if (!file_exists($configPath)) {
        throw new Exception("Config file not found: {$configPath}");
    }
    
    $db = ServerDB::getInstance($configPath);
    
    // Test table access
    $tables = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
    echo "✅ Testworld connected! Found " . count($tables) . " tables\n";
    echo "Schema: " . $db->query("SELECT current_schema()")->fetchColumn() . "\n";
    
    // Test activation table
    $count = $db->query("SELECT COUNT(*) FROM activation")->fetchColumn();
    echo "✅ Activation table accessible, " . $count . " records\n\n";
    
} catch (Exception $e) {
    echo "❌ Testworld failed: " . $e->getMessage() . "\n\n";
}

// Test demo connection
echo "Testing demo schema connection...\n";
try {
    $configPath = __DIR__ . '/sections/servers/demo/include/connection.php';
    $db = ServerDB::getInstance($configPath);
    
    $count = $db->query("SELECT COUNT(*) FROM activation")->fetchColumn();
    echo "✅ Demo connected! Activation table has " . $count . " records\n";
    echo "Schema: " . $db->query("SELECT current_schema()")->fetchColumn() . "\n\n";
    
} catch (Exception $e) {
    echo "❌ Demo failed: " . $e->getMessage() . "\n\n";
}

echo "✅ Database connection test complete!\n";
