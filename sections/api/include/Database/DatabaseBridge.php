<?php

namespace Database;

use PDO;
use Exception;

class DatabaseBridge
{
    private static $instance;
    private $postgresConnection;
    private $mysqlConnections = [];

    private function __construct()
    {
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPostgresConnection()
    {
        if (!$this->postgresConnection) {
            $this->postgresConnection = DB::getInstance();
        }
        return $this->postgresConnection;
    }

    public function getMySQLConnection($configFileLocation)
    {
        $configKey = substr(md5($configFileLocation), 0, 5);
        
        if (!isset($this->mysqlConnections[$configKey])) {
            $this->mysqlConnections[$configKey] = ServerDB::getInstance($configFileLocation);
        }
        
        return $this->mysqlConnections[$configKey];
    }

    public function getAINPCData($npcId)
    {
        $db = $this->getPostgresConnection();
        
        $stmt = $db->prepare("
            SELECT p.*, ac.* 
            FROM ai_players p
            LEFT JOIN ai_configs ac ON p.config_id = ac.id
            WHERE p.id = :npcId
        ");
        $stmt->bindValue(':npcId', $npcId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getGameWorldData($worldId, $playerId)
    {
        global $globalConfig;
        
        $worldInfo = $this->getWorldInfo($worldId);
        if (!$worldInfo) {
            throw new Exception("World not found: $worldId");
        }
        
        $configFile = $worldInfo['configfilelocation'];
        $db = $this->getMySQLConnection($configFile);
        
        $stmt = $db->prepare("
            SELECT u.*, v.* 
            FROM users u
            LEFT JOIN vdata v ON u.id = v.owner
            WHERE u.id = :playerId
        ");
        $stmt->bindValue(':playerId', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function syncNPCToGameWorld($npcId, $worldId)
    {
        try {
            $pgDb = $this->getPostgresConnection();
            $pgDb->beginTransaction();
            
            $npcData = $this->getAINPCData($npcId);
            if (!$npcData) {
                throw new Exception("NPC not found: $npcId");
            }
            
            $worldInfo = $this->getWorldInfo($worldId);
            if (!$worldInfo) {
                throw new Exception("World not found: $worldId");
            }
            
            $configFile = $worldInfo['configfilelocation'];
            $mysqlDb = $this->getMySQLConnection($configFile);
            
            $playerId = $this->getOrCreateGameWorldPlayer($mysqlDb, $npcData);
            
            $updateStmt = $pgDb->prepare("
                UPDATE ai_players 
                SET game_player_id = :playerId, 
                    last_sync = NOW() 
                WHERE id = :npcId
            ");
            $updateStmt->bindValue(':playerId', $playerId, PDO::PARAM_INT);
            $updateStmt->bindValue(':npcId', $npcId, PDO::PARAM_INT);
            $updateStmt->execute();
            
            $pgDb->commit();
            
            return [
                'success' => true,
                'npc_id' => $npcId,
                'game_player_id' => $playerId,
                'world_id' => $worldId
            ];
            
        } catch (Exception $e) {
            if ($pgDb && $pgDb->inTransaction()) {
                $pgDb->rollBack();
            }
            
            throw new Exception("Sync failed: " . $e->getMessage());
        }
    }

    private function getWorldInfo($worldId)
    {
        $db = $this->getPostgresConnection();
        
        $stmt = $db->prepare("
            SELECT * FROM gameservers 
            WHERE worldid = :worldId AND active = 1
        ");
        $stmt->bindValue(':worldId', $worldId, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getOrCreateGameWorldPlayer($mysqlDb, $npcData)
    {
        $checkStmt = $mysqlDb->prepare("
            SELECT id FROM users WHERE name = :username LIMIT 1
        ");
        $checkStmt->bindValue(':username', $npcData['username'], PDO::PARAM_STR);
        $checkStmt->execute();
        
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return $existing['id'];
        }
        
        $insertStmt = $mysqlDb->prepare("
            INSERT INTO users (name, email, password, tribe, access, timestamp)
            VALUES (:username, :email, :password, :tribe, 2, UNIX_TIMESTAMP())
        ");
        $insertStmt->bindValue(':username', $npcData['username'], PDO::PARAM_STR);
        $insertStmt->bindValue(':email', $npcData['email'] ?? 'npc@travian.local', PDO::PARAM_STR);
        $insertStmt->bindValue(':password', 'NPC_ACCOUNT', PDO::PARAM_STR);
        $insertStmt->bindValue(':tribe', $npcData['tribe_id'] ?? 1, PDO::PARAM_INT);
        $insertStmt->execute();
        
        return $mysqlDb->lastInsertId();
    }

    public function executePostgresQuery($query, $params = [])
    {
        $db = $this->getPostgresConnection();
        $stmt = $db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt;
    }

    public function executeMySQLQuery($configFileLocation, $query, $params = [])
    {
        $db = $this->getMySQLConnection($configFileLocation);
        $stmt = $db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt;
    }

    public function getAIPlayersByWorld($worldId)
    {
        $db = $this->getPostgresConnection();
        
        $stmt = $db->prepare("
            SELECT p.*, ac.behavior_type, ac.aggression_level
            FROM ai_players p
            LEFT JOIN ai_configs ac ON p.config_id = ac.id
            WHERE p.world_id = :worldId AND p.is_active = true
        ");
        $stmt->bindValue(':worldId', $worldId, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPlayerStatistics($worldId, $playerId)
    {
        $worldInfo = $this->getWorldInfo($worldId);
        if (!$worldInfo) {
            throw new Exception("World not found: $worldId");
        }
        
        $configFile = $worldInfo['configfilelocation'];
        $db = $this->getMySQLConnection($configFile);
        
        $stmt = $db->prepare("
            SELECT 
                u.name,
                u.tribe,
                u.alliance,
                COUNT(v.wref) as village_count,
                SUM(v.pop) as total_population,
                us.att as attack_points,
                us.def as defense_points
            FROM users u
            LEFT JOIN vdata v ON u.id = v.owner
            LEFT JOIN users_stats us ON u.id = us.id
            WHERE u.id = :playerId
            GROUP BY u.id, u.name, u.tribe, u.alliance, us.att, us.def
        ");
        $stmt->bindValue(':playerId', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function closeConnections()
    {
        $this->postgresConnection = null;
        $this->mysqlConnections = [];
    }
}
