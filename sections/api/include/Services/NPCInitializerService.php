<?php

namespace Services;

use Database\DB;
use Database\DatabaseBridge;

/**
 * NPCInitializerService
 * 
 * Creates complete NPC entities with dual-database architecture:
 * 
 * POSTGRESQL (AI Decision-Making):
 * - Player record (players table)
 * - AI configuration (ai_configs table)
 * - Spawn tracking (world_npc_spawns table)
 * 
 * MYSQL (Travian Game World):
 * - User account (users table)
 * - Village data (vdata table)
 * - World map (wdata table)
 * - Field data (fdata table)
 * 
 * Both databases are linked via game_player_id in PostgreSQL players table
 */
class NPCInitializerService
{
    private $postgresDb;
    private $bridge;
    private $nameTemplates = null;

    public function __construct()
    {
        $this->postgresDb = DB::getInstance();
        $this->bridge = DatabaseBridge::getInstance();
    }

    /**
     * Get a SEPARATE database connection for status tracking
     * 
     * This connection operates in autocommit mode (no transactions)
     * so status updates persist immediately, independent of main transaction.
     * 
     * CRITICAL: This prevents status updates from being rolled back if the
     * main PostgreSQL transaction fails, ensuring the cleanup job can always
     * find evidence of orphaned MySQL data.
     * 
     * @return \PDO Separate PostgreSQL connection in autocommit mode
     */
    private function getStatusTrackingConnection()
    {
        static $statusConn = null;
        
        if ($statusConn === null) {
            $dbConfig = DB::getInstance()->getConfig();
            $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};sslmode=require";
            
            $statusConn = new \PDO(
                $dsn,
                $dbConfig['username'],
                $dbConfig['password'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_AUTOCOMMIT => true
                ]
            );
            
            $statusConn->exec("SET NAMES 'UTF8'");
        }
        
        return $statusConn;
    }

    /**
     * Create a complete NPC entity in BOTH PostgreSQL and MySQL databases
     * 
     * CRASH-SAFE ARCHITECTURE with Pending State Log:
     * 
     * 1. Create pending record FIRST (outside any transaction)
     * 2. Get MySQL game world database configuration
     * 3. Start transactions on both databases
     * 4. Execute MySQL operations (user account, village, resources, map)
     * 5. CRITICAL: Write MySQL identifiers to pending record BEFORE MySQL commit
     * 6. Commit MySQL
     * 7. Update pending status to 'mysql_committed'
     * 8. Execute PostgreSQL operations (player record, AI config, spawn tracking)
     * 9. Commit PostgreSQL
     * 10. Mark pending record as 'completed'
     * 
     * If crash occurs at any point, pending_npc_creations table shows:
     * - 'pending' + mysql_user_id NULL = crashed before MySQL commit (no cleanup needed)
     * - 'mysql_committing' + mysql_user_id populated = crashed during/after MySQL commit (cleanup job removes orphaned MySQL data)
     * - 'mysql_committed' + mysql_user_id populated = crashed after MySQL commit (cleanup job removes orphaned MySQL data)
     * - 'completed' = success
     *
     * @param int $worldId World ID
     * @param array $config NPC configuration [tribe, difficulty, personality]
     * @param array $location Spawn location [x, y]
     * @return array Created NPC data [npc_id, mysql_user_id, pg_player_id, village_id]
     * @throws \Exception On creation failure
     */
    public function createNPC($worldId, $config, $location)
    {
        $tribe = $config['tribe'];
        $difficulty = $config['difficulty'];
        $personality = $config['personality'];
        $x = $location['x'];
        $y = $location['y'];

        $worldInfo = $this->getWorldInfo($worldId);
        if (!$worldInfo) {
            throw new \Exception("World $worldId not found or inactive");
        }
        
        $configFile = $worldInfo['configfilelocation'];
        $mysqlDb = $this->bridge->getMySQLConnection($configFile);
        $postgresDb = $this->postgresDb;
        
        $statusConn = $this->getStatusTrackingConnection();
        
        $pendingId = null;

        try {
            $pendingId = $this->createPendingRecord($statusConn, $worldId, $tribe, $difficulty, $personality, $location);
            
            $postgresDb->beginTransaction();
            $mysqlDb->beginTransaction();
            
            $npcName = $this->generateNPCName($tribe);
            $username = str_replace(' ', '_', $npcName);
            
            $mysqlUserId = $this->createUserAccountMySQL($mysqlDb, $username, $tribe);
            
            $villageName = $npcName . "'s Village";
            $villageId = $this->createVillageMySQL($mysqlDb, $mysqlUserId, $location, $tribe, $villageName);
            
            $this->initializeVillageResourcesMySQL($mysqlDb, $villageId, $difficulty);
            
            $this->updatePendingStatus($statusConn, $pendingId, 'mysql_committing', $mysqlUserId, $villageId, $location);
            
            $mysqlDb->commit();
            
            $this->updatePendingStatus($statusConn, $pendingId, 'mysql_committed', $mysqlUserId, $villageId, $location);
            
            $playerId = $this->createPlayerRecordPostgres($worldId, $mysqlUserId, $username, $config);
            
            $this->createAIConfigPostgres($playerId, $config);
            
            $this->linkPlayerToGameWorld($playerId, $mysqlUserId);
            
            $spawnId = $this->createSpawnRecordPostgres($worldId, $playerId, $location, $tribe, $villageId, $config['batch_id'] ?? null);
            
            $this->updatePendingStatusWithPostgresId($statusConn, $pendingId, 'postgres_committing', $playerId);
            
            $postgresDb->commit();
            
            $this->updatePendingStatusWithPostgresId($statusConn, $pendingId, 'postgres_committed', $playerId);
            
            $this->completePendingRecord($statusConn, $pendingId, $playerId);
            
            return [
                'npc_id' => $spawnId,
                'mysql_user_id' => $mysqlUserId,
                'pg_player_id' => $playerId,
                'village_id' => $villageId,
                'name' => $npcName,
                'username' => $username,
                'world_id' => $worldId
            ];
            
        } catch (\Exception $e) {
            error_log("NPC creation failed: " . $e->getMessage());
            
            if ($pendingId) {
                try {
                    $this->failPendingRecord($statusConn, $pendingId, $e->getMessage());
                } catch (\Exception $logError) {
                    error_log("Failed to update pending record: " . $logError->getMessage());
                }
            }
            
            try {
                if ($postgresDb->inTransaction()) {
                    $postgresDb->rollBack();
                }
            } catch (\Exception $rb) {
                error_log("PostgreSQL rollback failed: " . $rb->getMessage());
            }
            
            try {
                if ($mysqlDb->inTransaction()) {
                    $mysqlDb->rollBack();
                }
            } catch (\Exception $rb) {
                error_log("MySQL rollback failed: " . $rb->getMessage());
            }
            
            throw new \Exception("NPC creation failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get world information from PostgreSQL
     *
     * @param int $worldId World ID
     * @return array|false World info or false if not found
     */
    private function getWorldInfo($worldId)
    {
        $stmt = $this->postgresDb->prepare("
            SELECT * FROM gameservers 
            WHERE worldid = ? AND active = 1
        ");
        $stmt->execute([$worldId]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Generate NPC name from templates
     *
     * @param string $tribe Tribe name (romans, gauls, teutons)
     * @return string Generated NPC name
     */
    public function generateNPCName($tribe)
    {
        if ($this->nameTemplates === null) {
            $this->loadNameTemplates();
        }

        $tribe = strtolower($tribe);
        
        // Use fallback if templates not loaded or tribe not found
        if (empty($this->nameTemplates) || !isset($this->nameTemplates[$tribe])) {
            return $this->generateFallbackName($tribe);
        }

        $templates = $this->nameTemplates[$tribe];

        // Verify template has required arrays before using
        switch ($tribe) {
            case 'romans':
                if (empty($templates['male_first_names']) || empty($templates['family_names'])) {
                    return $this->generateFallbackName($tribe);
                }
                $first = $templates['male_first_names'][array_rand($templates['male_first_names'])];
                $family = $templates['family_names'][array_rand($templates['family_names'])];
                return $first . ' ' . $family;

            case 'gauls':
                if (empty($templates['male_first_names']) || empty($templates['suffixes'])) {
                    return $this->generateFallbackName($tribe);
                }
                $first = $templates['male_first_names'][array_rand($templates['male_first_names'])];
                $suffix = $templates['suffixes'][array_rand($templates['suffixes'])];
                return $first . '_' . $suffix;

            case 'teutons':
                if (empty($templates['male_first_names']) || empty($templates['titles'])) {
                    return $this->generateFallbackName($tribe);
                }
                $first = $templates['male_first_names'][array_rand($templates['male_first_names'])];
                $title = $templates['titles'][array_rand($templates['titles'])];
                return $first . '_' . $title;

            default:
                return $this->generateFallbackName($tribe);
        }
    }

    /**
     * Load NPC name templates from YAML config
     *
     * @return void
     */
    private function loadNameTemplates()
    {
        $configPath = __DIR__ . '/../../../../config/npc-names.yml';
        
        if (!file_exists($configPath)) {
            $this->nameTemplates = [];
            return;
        }

        $yamlContent = file_get_contents($configPath);
        
        if (function_exists('yaml_parse')) {
            $this->nameTemplates = yaml_parse($yamlContent);
        } else {
            $this->nameTemplates = $this->parseSimpleYaml($yamlContent);
        }
    }

    /**
     * Simple YAML parser for name templates (fallback if yaml extension not available)
     *
     * @param string $yamlContent YAML content
     * @return array Parsed data
     */
    private function parseSimpleYaml($yamlContent)
    {
        $result = [];
        $lines = explode("\n", $yamlContent);
        $currentSection = null;
        $currentKey = null;

        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            if (preg_match('/^(\w+):$/', $line, $matches)) {
                $currentSection = $matches[1];
                $result[$currentSection] = [];
            } elseif (preg_match('/^  (\w+):$/', $line, $matches)) {
                $currentKey = $matches[1];
                $result[$currentSection][$currentKey] = [];
            } elseif (preg_match('/^    - (.+)$/', $line, $matches)) {
                $result[$currentSection][$currentKey][] = $matches[1];
            }
        }

        return $result;
    }

    /**
     * Generate fallback name
     *
     * @param string $tribe Tribe name
     * @return string Fallback name
     */
    private function generateFallbackName($tribe)
    {
        $number = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        return 'AI_' . ucfirst($tribe) . '_' . $number;
    }

    /**
     * Create user account in MySQL Travian game world database
     *
     * @param \PDO $mysqlDb MySQL database connection
     * @param string $username Username
     * @param string $tribe Tribe name
     * @return int MySQL User ID
     */
    private function createUserAccountMySQL($mysqlDb, $username, $tribe = 'romans')
    {
        $email = $username . '@ai.npc';
        $password = md5(bin2hex(random_bytes(16)));
        $raceId = $this->tribeToRaceId($tribe);
        $timestamp = time();

        $stmt = $mysqlDb->prepare("
            INSERT INTO users (
                name, email, password, race, access, kid, 
                desc1, desc2, note, signupTime, protection, last_login_time
            ) VALUES (
                ?, ?, ?, ?, 2, 0, 
                'AI NPC Player', '', '', ?, ?, ?
            )
        ");
        $stmt->execute([
            $username, 
            $email, 
            $password, 
            $raceId,
            $timestamp,
            $timestamp + (3600 * 24 * 3),
            $timestamp
        ]);
        
        return (int)$mysqlDb->lastInsertId();
    }

    /**
     * Convert tribe name to race ID for users table
     *
     * @param string $tribe Tribe name
     * @return int Race ID (1=romans, 2=teutons, 3=gauls)
     */
    private function tribeToRaceId($tribe)
    {
        return match(strtolower($tribe)) {
            'romans' => 1,
            'teutons' => 2,
            'gauls' => 3,
            default => 1
        };
    }

    /**
     * Convert tribe name to type ID for vdata table
     *
     * @param string $tribe Tribe name
     * @return int Type ID (1=romans, 2=teutons, 3=gauls)
     */
    private function tribeToTypeId($tribe)
    {
        return match(strtolower($tribe)) {
            'romans' => 1,
            'teutons' => 2,
            'gauls' => 3,
            default => 1
        };
    }

    /**
     * Create player record in PostgreSQL for AI decision tracking
     *
     * @param int $worldId World ID
     * @param int $mysqlUserId MySQL User ID (will be stored as game_player_id)
     * @param string $username Username
     * @param array $config NPC configuration
     * @return int PostgreSQL Player ID
     */
    private function createPlayerRecordPostgres($worldId, $mysqlUserId, $username, $config)
    {
        $skillLevel = match(strtolower($config['difficulty'])) {
            'easy' => 'beginner',
            'medium' => 'intermediate',
            'hard' => 'advanced',
            'expert' => 'expert',
            default => 'intermediate'
        };

        $stmt = $this->postgresDb->prepare("
            INSERT INTO players (
                world_id, account_id, player_type, skill_level, 
                is_active, game_player_id, created_at
            ) VALUES (?, ?, 'npc', ?, TRUE, ?, NOW())
        ");
        $stmt->execute([$worldId, $mysqlUserId, $skillLevel, $mysqlUserId]);
        
        return (int)$this->postgresDb->lastInsertId();
    }

    /**
     * Create AI configuration in PostgreSQL
     *
     * @param int $playerId PostgreSQL Player ID
     * @param array $config NPC configuration
     * @return int AI Config ID
     */
    private function createAIConfigPostgres($playerId, $config)
    {
        $llmRatio = 0.05;
        $decisionFrequency = 300;

        $stmt = $this->postgresDb->prepare("
            INSERT INTO ai_configs (
                npc_player_id, difficulty, personality, 
                decision_frequency_seconds, llm_ratio, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $playerId,
            $config['difficulty'],
            $config['personality'],
            $decisionFrequency,
            $llmRatio
        ]);
        
        return (int)$this->postgresDb->lastInsertId();
    }

    /**
     * Link PostgreSQL player record to MySQL game world user
     *
     * @param int $playerId PostgreSQL Player ID
     * @param int $mysqlUserId MySQL User ID
     * @return void
     */
    private function linkPlayerToGameWorld($playerId, $mysqlUserId)
    {
        $stmt = $this->postgresDb->prepare("
            UPDATE players 
            SET game_player_id = ?, last_sync = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$mysqlUserId, $playerId]);
    }

    /**
     * Create starting village in MySQL Travian vdata table
     *
     * @param \PDO $mysqlDb MySQL database connection
     * @param int $userId MySQL User ID (owner)
     * @param array $location Location [x, y]
     * @param string $tribe Tribe name
     * @param string $name Village name
     * @return int Village ID (kid)
     */
    private function createVillageMySQL($mysqlDb, $userId, $location, $tribe, $name)
    {
        $x = $location['x'];
        $y = $location['y'];
        $typeId = $this->tribeToTypeId($tribe);
        $created = time();

        $stmt = $mysqlDb->prepare("
            SELECT id FROM wdata WHERE x = ? AND y = ? LIMIT 1
        ");
        $stmt->execute([$x, $y]);
        $wdataRow = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$wdataRow) {
            throw new \Exception("World location ($x, $y) not found in wdata");
        }
        
        $villageId = $wdataRow['id'];

        $fieldTypeId = $typeId;
        
        $stmt = $mysqlDb->prepare("
            INSERT INTO vdata (
                kid, owner, name, capital, type, fieldtype,
                wood, clay, iron, crop, 
                woodp, clayp, ironp, cropp,
                maxstore, maxcrop, upkeep,
                created, pop, cp, expandedfrom
            ) VALUES (
                ?, ?, ?, 1, ?, ?,
                750, 750, 750, 750,
                0, 0, 0, 0,
                800, 800, 0,
                ?, 2, 1, 0
            )
        ");
        $stmt->execute([
            $villageId, 
            $userId, 
            $name, 
            $typeId, 
            $fieldTypeId,
            $created
        ]);

        $stmt = $mysqlDb->prepare("
            UPDATE wdata SET occupied = 1 WHERE id = ?
        ");
        $stmt->execute([$villageId]);

        $stmt = $mysqlDb->prepare("
            INSERT INTO fdata (
                kid, 
                f1, f2, f3, f4, f5, f6, f7, f8, f9, f10, f11, f12, f13, f14, f15, f16, f17, f18,
                f1t, f2t, f3t, f4t, f5t, f6t, f7t, f8t, f9t, f10t, f11t, f12t, f13t, f14t, f15t, f16t, f17t, f18t
            ) VALUES (
                ?,
                0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
                0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0
            )
        ");
        $stmt->execute([$villageId]);

        $stmt = $mysqlDb->prepare("
            UPDATE users SET kid = ? WHERE id = ?
        ");
        $stmt->execute([$villageId, $userId]);
        
        return $villageId;
    }

    /**
     * Initialize village resources in MySQL vdata table based on difficulty
     *
     * @param \PDO $mysqlDb MySQL database connection
     * @param int $villageId Village ID (kid)
     * @param string $difficulty Difficulty level
     * @return void
     */
    private function initializeVillageResourcesMySQL($mysqlDb, $villageId, $difficulty)
    {
        $multipliers = [
            'easy' => 1.0,
            'medium' => 1.5,
            'hard' => 2.0,
            'expert' => 3.0
        ];
        
        $multiplier = $multipliers[strtolower($difficulty)] ?? 1.0;

        $baseResources = [
            'wood' => 750,
            'clay' => 750,
            'iron' => 750,
            'crop' => 750
        ];

        $wood = (int)($baseResources['wood'] * $multiplier);
        $clay = (int)($baseResources['clay'] * $multiplier);
        $iron = (int)($baseResources['iron'] * $multiplier);
        $crop = (int)($baseResources['crop'] * $multiplier);

        $stmt = $mysqlDb->prepare("
            UPDATE vdata 
            SET wood = ?, clay = ?, iron = ?, crop = ?
            WHERE kid = ?
        ");
        $stmt->execute([$wood, $clay, $iron, $crop, $villageId]);
    }

    /**
     * Create spawn tracking record in PostgreSQL
     *
     * @param int $worldId World ID
     * @param int $playerId PostgreSQL Player ID
     * @param array $location Location [x, y]
     * @param string $tribe Tribe name
     * @param int $villageId MySQL Village ID (kid)
     * @param int|null $batchId Batch ID
     * @return int Spawn ID
     */
    private function createSpawnRecordPostgres($worldId, $playerId, $location, $tribe, $villageId, $batchId = null)
    {
        $spawnMethod = $batchId === null ? 'manual' : ($batchId == 0 ? 'instant' : 'progressive');

        $stmt = $this->postgresDb->prepare("
            INSERT INTO world_npc_spawns (
                world_id, npc_player_id, batch_id, 
                spawn_x, spawn_y, tribe, 
                starting_village_id, spawn_method, spawned_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $worldId,
            $playerId,
            $batchId,
            $location['x'],
            $location['y'],
            $tribe,
            $villageId,
            $spawnMethod
        ]);
        
        return (int)$this->postgresDb->lastInsertId();
    }

    /**
     * Clean up orphaned MySQL data if PostgreSQL commit fails
     * 
     * This is a compensating transaction that removes MySQL data
     * when MySQL committed successfully but PostgreSQL commit failed.
     * 
     * This handles the case where MySQL has committed but PostgreSQL failed,
     * leaving orphaned game accounts in the Travian database.
     * 
     * @param \PDO $mysqlConn MySQL database connection
     * @param int $gamePlayerId MySQL User ID
     * @param int $villageId MySQL Village ID (kid)
     * @param array $coordinates Location array with 'x' and 'y' keys
     * @return void
     */
    private function cleanupOrphanedMySQLData($mysqlConn, $gamePlayerId, $villageId, $coordinates)
    {
        try {
            error_log("Cleaning up orphaned MySQL data for user ID: {$gamePlayerId}, village ID: {$villageId}");
            
            // Delete in proper order (respect foreign keys)
            
            // 1. Delete resource fields (depends on village)
            $stmt = $mysqlConn->prepare("DELETE FROM fdata WHERE kid = ?");
            $stmt->execute([$villageId]);
            
            // 2. Delete village (depends on user)
            $stmt = $mysqlConn->prepare("DELETE FROM vdata WHERE kid = ?");
            $stmt->execute([$villageId]);
            
            // 3. Update world map (set occupied back to 0)
            $stmt = $mysqlConn->prepare("UPDATE wdata SET occupied = 0 WHERE x = ? AND y = ?");
            $stmt->execute([$coordinates['x'], $coordinates['y']]);
            
            // 4. Delete user account
            $stmt = $mysqlConn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$gamePlayerId]);
            
            error_log("Successfully cleaned up orphaned MySQL data");
            
        } catch (\Exception $cleanupError) {
            // Log the error but don't throw - we're already handling a failure
            error_log("CRITICAL: Failed to cleanup orphaned MySQL data: " . $cleanupError->getMessage());
            error_log("Manual cleanup required for MySQL user ID: {$gamePlayerId}, village ID: {$villageId}");
        }
    }

    /**
     * Create pending NPC creation record
     * 
     * This creates a record BEFORE any database transactions begin.
     * If the PHP process crashes at any point, this record will show
     * exactly where in the process the crash occurred.
     * 
     * CRITICAL: Uses separate connection to ensure immediate durability.
     *
     * @param \PDO $conn Separate status tracking connection (NOT in transaction)
     * @param int $worldId World ID
     * @param string $tribe Tribe name
     * @param string $difficulty Difficulty level
     * @param string $personality Personality type
     * @param array $location Location array with 'x' and 'y' keys
     * @return int Pending record ID
     */
    private function createPendingRecord($conn, $worldId, $tribe, $difficulty, $personality, $location)
    {
        if ($conn->inTransaction()) {
            throw new \Exception("Status tracking connection should not be in transaction");
        }
        
        $stmt = $conn->prepare("
            INSERT INTO pending_npc_creations (
                world_id, tribe, difficulty, personality, 
                coordinates_x, coordinates_y, status
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending')
            RETURNING id
        ");
        $stmt->execute([
            $worldId, 
            $tribe, 
            $difficulty, 
            $personality, 
            $location['x'], 
            $location['y']
        ]);
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Update pending record status
     * 
     * CRITICAL: Uses separate connection to ensure immediate durability.
     *
     * @param \PDO $conn Separate status tracking connection (NOT in transaction)
     * @param int $pendingId Pending record ID
     * @param string $status New status
     * @param int|null $mysqlUserId MySQL User ID
     * @param int|null $villageId MySQL Village ID
     * @param array|null $location Location array
     * @return void
     */
    private function updatePendingStatus($conn, $pendingId, $status, $mysqlUserId = null, $villageId = null, $location = null)
    {
        if ($conn->inTransaction()) {
            throw new \Exception("Status tracking connection should not be in transaction");
        }
        
        $stmt = $conn->prepare("
            UPDATE pending_npc_creations 
            SET status = ?, 
                mysql_user_id = ?, 
                mysql_village_id = ?,
                coordinates_x = COALESCE(?, coordinates_x),
                coordinates_y = COALESCE(?, coordinates_y)
            WHERE id = ?
        ");
        $stmt->execute([
            $status,
            $mysqlUserId,
            $villageId,
            $location['x'] ?? null,
            $location['y'] ?? null,
            $pendingId
        ]);
    }

    /**
     * Update pending status with PostgreSQL player ID
     * 
     * This method is used to track PostgreSQL commit stages (postgres_committing, postgres_committed).
     * Writing the postgres_player_id BEFORE PostgreSQL commit ensures the cleanup job can find
     * and remove orphaned PostgreSQL data if a crash occurs after commit but before completion.
     * 
     * CRITICAL: Uses separate connection to ensure immediate durability.
     *
     * @param \PDO $conn Separate status tracking connection (NOT in transaction)
     * @param int $pendingId Pending record ID
     * @param string $status New status (postgres_committing or postgres_committed)
     * @param int $postgresPlayerId PostgreSQL Player ID
     * @return void
     */
    private function updatePendingStatusWithPostgresId($conn, $pendingId, $status, $postgresPlayerId)
    {
        if ($conn->inTransaction()) {
            throw new \Exception("Status tracking connection should not be in transaction");
        }
        
        $stmt = $conn->prepare("
            UPDATE pending_npc_creations 
            SET status = ?, postgres_player_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $postgresPlayerId, $pendingId]);
    }

    /**
     * Mark pending record as completed
     * 
     * CRITICAL: Uses separate connection to ensure immediate durability.
     *
     * @param \PDO $conn Separate status tracking connection (NOT in transaction)
     * @param int $pendingId Pending record ID
     * @param int $postgresPlayerId PostgreSQL Player ID
     * @return void
     */
    private function completePendingRecord($conn, $pendingId, $postgresPlayerId)
    {
        if ($conn->inTransaction()) {
            throw new \Exception("Status tracking connection should not be in transaction");
        }
        
        $stmt = $conn->prepare("
            UPDATE pending_npc_creations 
            SET status = 'completed', 
                postgres_player_id = ?, 
                completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$postgresPlayerId, $pendingId]);
    }

    /**
     * Mark pending record as failed
     * 
     * CRITICAL: Uses separate connection to ensure immediate durability.
     *
     * @param \PDO $conn Separate status tracking connection (NOT in transaction)
     * @param int $pendingId Pending record ID
     * @param string $errorMessage Error message
     * @return void
     */
    private function failPendingRecord($conn, $pendingId, $errorMessage)
    {
        if ($conn->inTransaction()) {
            throw new \Exception("Status tracking connection should not be in transaction");
        }
        
        $stmt = $conn->prepare("
            UPDATE pending_npc_creations 
            SET status = 'failed', 
                error_message = ?, 
                completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([substr($errorMessage, 0, 1000), $pendingId]);
    }
}
