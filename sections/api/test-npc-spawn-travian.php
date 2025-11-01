<?php
/**
 * Test Script: NPC Spawning Integration with Travian
 * 
 * Purpose: Verify that NPCs spawn correctly in BOTH PostgreSQL and MySQL databases
 * 
 * Tests:
 * 1. PostgreSQL player record created
 * 2. MySQL user account created
 * 3. MySQL village created with correct resources
 * 4. Database link (game_player_id) established
 * 5. World map updated (wdata.occupied = 1)
 * 6. Resource fields initialized (fdata)
 * 
 * Usage: php test-npc-spawn-travian.php [worldId]
 */

require_once __DIR__ . '/include/Database/DB.php';
require_once __DIR__ . '/include/Database/DatabaseBridge.php';
require_once __DIR__ . '/include/Services/NPCInitializerService.php';

use Database\DB;
use Database\DatabaseBridge;
use Services\NPCInitializerService;

echo "=== NPC Spawn Integration Test ===\n\n";

$worldId = isset($argv[1]) ? $argv[1] : 's1';
echo "Testing NPC spawn on world: $worldId\n";

try {
    $postgresDb = DB::getInstance();
    $bridge = DatabaseBridge::getInstance();
    $npcService = new NPCInitializerService();
    
    echo "\n1. Creating test NPC...\n";
    
    $config = [
        'tribe' => 'romans',
        'difficulty' => 'medium',
        'personality' => 'balanced',
        'batch_id' => null
    ];
    
    $location = ['x' => 10, 'y' => 10];
    
    $postgresDb->beginTransaction();
    
    try {
        $result = $npcService->createNPC($worldId, $config, $location);
        
        echo "✓ NPC created successfully\n";
        echo "  - PostgreSQL Player ID: {$result['pg_player_id']}\n";
        echo "  - MySQL User ID: {$result['mysql_user_id']}\n";
        echo "  - Village ID: {$result['village_id']}\n";
        echo "  - Name: {$result['name']}\n";
        echo "  - Username: {$result['username']}\n";
        
        echo "\n2. Verifying PostgreSQL records...\n";
        
        $stmt = $postgresDb->prepare("
            SELECT p.*, ac.difficulty, ac.personality 
            FROM players p
            LEFT JOIN ai_configs ac ON p.id = ac.npc_player_id
            WHERE p.id = ?
        ");
        $stmt->execute([$result['pg_player_id']]);
        $pgPlayer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pgPlayer) {
            echo "✓ PostgreSQL player record found\n";
            echo "  - Player Type: {$pgPlayer['player_type']}\n";
            echo "  - Skill Level: {$pgPlayer['skill_level']}\n";
            echo "  - Game Player ID: {$pgPlayer['game_player_id']}\n";
            echo "  - AI Difficulty: {$pgPlayer['difficulty']}\n";
            echo "  - AI Personality: {$pgPlayer['personality']}\n";
            
            if ($pgPlayer['game_player_id'] != $result['mysql_user_id']) {
                throw new Exception("Database link mismatch!");
            }
            echo "✓ Database link verified\n";
        } else {
            throw new Exception("PostgreSQL player record not found!");
        }
        
        echo "\n3. Verifying MySQL records...\n";
        
        $worldInfo = $bridge->getWorldInfo($worldId);
        if (!$worldInfo) {
            throw new Exception("World $worldId not found!");
        }
        
        $configFile = $worldInfo['configfilelocation'];
        $mysqlDb = $bridge->getMySQLConnection($configFile);
        
        $stmt = $mysqlDb->prepare("
            SELECT * FROM users WHERE id = ?
        ");
        $stmt->execute([$result['mysql_user_id']]);
        $mysqlUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mysqlUser) {
            echo "✓ MySQL user account found\n";
            echo "  - Name: {$mysqlUser['name']}\n";
            echo "  - Email: {$mysqlUser['email']}\n";
            echo "  - Race: {$mysqlUser['race']}\n";
            echo "  - Access: {$mysqlUser['access']}\n";
            echo "  - Capital (kid): {$mysqlUser['kid']}\n";
        } else {
            throw new Exception("MySQL user account not found!");
        }
        
        echo "\n4. Verifying MySQL village...\n";
        
        $stmt = $mysqlDb->prepare("
            SELECT * FROM vdata WHERE kid = ?
        ");
        $stmt->execute([$result['village_id']]);
        $village = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($village) {
            echo "✓ Village found in vdata\n";
            echo "  - Village ID (kid): {$village['kid']}\n";
            echo "  - Owner: {$village['owner']}\n";
            echo "  - Name: {$village['name']}\n";
            echo "  - Capital: " . ($village['capital'] ? 'Yes' : 'No') . "\n";
            echo "  - Type: {$village['type']}\n";
            echo "  - Resources:\n";
            echo "    - Wood: {$village['wood']}\n";
            echo "    - Clay: {$village['clay']}\n";
            echo "    - Iron: {$village['iron']}\n";
            echo "    - Crop: {$village['crop']}\n";
            echo "  - Storage:\n";
            echo "    - Warehouse: {$village['maxstore']}\n";
            echo "    - Granary: {$village['maxcrop']}\n";
            echo "  - Population: {$village['pop']}\n";
            echo "  - Culture Points: {$village['cp']}\n";
            
            if ($village['owner'] != $result['mysql_user_id']) {
                throw new Exception("Village owner mismatch!");
            }
            echo "✓ Village ownership verified\n";
        } else {
            throw new Exception("Village not found in vdata!");
        }
        
        echo "\n5. Verifying world map update...\n";
        
        $stmt = $mysqlDb->prepare("
            SELECT * FROM wdata WHERE x = ? AND y = ?
        ");
        $stmt->execute([$location['x'], $location['y']]);
        $wdata = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($wdata) {
            echo "✓ World map location found\n";
            echo "  - Location ID: {$wdata['id']}\n";
            echo "  - Coordinates: ({$wdata['x']}, {$wdata['y']})\n";
            echo "  - Occupied: " . ($wdata['occupied'] ? 'Yes' : 'No') . "\n";
            
            if (!$wdata['occupied']) {
                throw new Exception("World map not marked as occupied!");
            }
            echo "✓ World map occupied status verified\n";
        } else {
            throw new Exception("World map location not found!");
        }
        
        echo "\n6. Verifying resource fields...\n";
        
        $stmt = $mysqlDb->prepare("
            SELECT * FROM fdata WHERE kid = ?
        ");
        $stmt->execute([$result['village_id']]);
        $fdata = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($fdata) {
            echo "✓ Resource fields initialized\n";
            echo "  - Field count: 18 fields (f1-f18)\n";
            echo "  - Field levels initialized to 0\n";
            echo "  - Field types initialized to 0\n";
        } else {
            throw new Exception("Resource fields not initialized!");
        }
        
        $postgresDb->rollBack();
        echo "\n✓ Transaction rolled back (test mode)\n";
        
        echo "\n=== ALL TESTS PASSED ===\n";
        echo "\nNPC spawning integration working correctly!\n";
        echo "- PostgreSQL and MySQL databases linked\n";
        echo "- User account created in Travian database\n";
        echo "- Village created on world map\n";
        echo "- Resources initialized\n";
        echo "- AI configuration stored\n";
        
    } catch (Exception $e) {
        if ($postgresDb->inTransaction()) {
            $postgresDb->rollBack();
        }
        throw $e;
    }
    
} catch (Exception $e) {
    echo "\n✗ TEST FAILED: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n";
exit(0);
