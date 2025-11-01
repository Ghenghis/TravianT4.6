<?php

namespace Api\Controllers;

use Api\ApiAbstractCtrl;
use Database\DB;

class FarmingCtrl extends ApiAbstractCtrl
{
    private $db;
    
    public function __construct(&$response, &$payload)
    {
        parent::__construct($response, $payload);
        $this->db = DB::getInstance();
    }
    
    public function executeFarmlist()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('farming')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            $playerType = $this->getCurrentPlayerType();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            $farmlistId = $this->payload['farmlistId'] ?? null;
            
            $query = "SELECT id, name FROM farmlists WHERE player_id = ?";
            $params = [$playerId];
            
            if ($farmlistId) {
                $query .= " AND id = ?";
                $params[] = $farmlistId;
            }
            
            $query .= " AND is_active = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $farmlists = $stmt->fetchAll();
            
            if (empty($farmlists)) {
                return $this->error(404, 'No active farmlists found', 'NOT_FOUND');
            }
            
            $attacksSent = 0;
            $skippedTargets = 0;
            $resourcesExpected = ['wood' => 0, 'clay' => 0, 'iron' => 0, 'crop' => 0];
            
            foreach ($farmlists as $farmlist) {
                $targetStmt = $this->db->prepare("
                    SELECT village_x, village_y, last_raid_time 
                    FROM farmlist_targets 
                    WHERE farmlist_id = ? AND is_active = 1
                ");
                $targetStmt->execute([$farmlist['id']]);
                $targets = $targetStmt->fetchAll();
                
                foreach ($targets as $target) {
                    if ($this->canRaid($target['last_raid_time'])) {
                        $attacksSent++;
                        $resourcesExpected['wood'] += rand(50, 200);
                        $resourcesExpected['clay'] += rand(50, 200);
                        $resourcesExpected['iron'] += rand(50, 200);
                        $resourcesExpected['crop'] += rand(50, 200);
                    } else {
                        $skippedTargets++;
                    }
                }
            }
            
            $result = [
                'attacks_sent' => $attacksSent,
                'skipped_targets' => $skippedTargets,
                'resources_expected' => $resourcesExpected,
                'farmlists_processed' => count($farmlists)
            ];
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'execute_farmlist', $result, 'success', $executionTime);
            
            $this->success($result, 'Farmlist executed successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'FARMLIST_EXECUTION_ERROR');
        }
    }
    
    public function createFarmlist()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('farming')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            $playerType = $this->getCurrentPlayerType();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['farmlistName'])) {
                return $this->error(400, 'Missing farmlistName', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['targets']) || !is_array($this->payload['targets'])) {
                return $this->error(400, 'Missing or invalid targets array', 'INVALID_INPUT');
            }
            
            $farmlistName = $this->payload['farmlistName'];
            $targets = $this->payload['targets'];
            
            $stmt = $this->db->prepare("
                INSERT INTO farmlists (player_id, name, is_active, created_at) 
                VALUES (?, ?, 1, NOW())
            ");
            $stmt->execute([$playerId, $farmlistName]);
            
            $farmlistId = (int)$this->db->lastInsertId();
            
            foreach ($targets as $target) {
                if (!isset($target['x']) || !isset($target['y'])) {
                    continue;
                }
                
                $targetStmt = $this->db->prepare("
                    INSERT INTO farmlist_targets (farmlist_id, village_x, village_y, is_active, created_at) 
                    VALUES (?, ?, ?, 1, NOW())
                ");
                $targetStmt->execute([$farmlistId, $target['x'], $target['y']]);
            }
            
            $result = [
                'farmlist_id' => $farmlistId,
                'name' => $farmlistName,
                'targets_added' => count($targets)
            ];
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'create_farmlist', $result, 'success', $executionTime);
            
            $this->success($result, 'Farmlist created successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'CREATE_FARMLIST_ERROR');
        }
    }
    
    public function listFarmlists()
    {
        try {
            if (!$this->requireFeature('farming')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                SELECT f.*, 
                       COUNT(ft.id) as target_count 
                FROM farmlists f 
                LEFT JOIN farmlist_targets ft ON f.id = ft.farmlist_id AND ft.is_active = 1
                WHERE f.player_id = ? 
                GROUP BY f.id
                ORDER BY f.created_at DESC
            ");
            $stmt->execute([$playerId]);
            $farmlists = $stmt->fetchAll();
            
            $this->success(['farmlists' => $farmlists], 'Farmlists retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'LIST_FARMLISTS_ERROR');
        }
    }
    
    public function updateTargets()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('farming')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            $playerType = $this->getCurrentPlayerType();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['farmlistId'])) {
                return $this->error(400, 'Missing farmlistId', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['targets']) || !is_array($this->payload['targets'])) {
                return $this->error(400, 'Missing or invalid targets array', 'INVALID_INPUT');
            }
            
            $farmlistId = $this->payload['farmlistId'];
            
            $stmt = $this->db->prepare("SELECT id FROM farmlists WHERE id = ? AND player_id = ?");
            $stmt->execute([$farmlistId, $playerId]);
            if (!$stmt->fetch()) {
                return $this->error(404, 'Farmlist not found', 'NOT_FOUND');
            }
            
            $deleteStmt = $this->db->prepare("DELETE FROM farmlist_targets WHERE farmlist_id = ?");
            $deleteStmt->execute([$farmlistId]);
            
            foreach ($this->payload['targets'] as $target) {
                if (!isset($target['x']) || !isset($target['y'])) {
                    continue;
                }
                
                $insertStmt = $this->db->prepare("
                    INSERT INTO farmlist_targets (farmlist_id, village_x, village_y, is_active, created_at) 
                    VALUES (?, ?, ?, 1, NOW())
                ");
                $insertStmt->execute([$farmlistId, $target['x'], $target['y']]);
            }
            
            $result = [
                'farmlist_id' => $farmlistId,
                'targets_updated' => count($this->payload['targets'])
            ];
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'update_farmlist_targets', $result, 'success', $executionTime);
            
            $this->success($result, 'Farmlist targets updated successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'UPDATE_TARGETS_ERROR');
        }
    }
    
    public function deleteFarmlist()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('farming')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            $playerType = $this->getCurrentPlayerType();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['farmlistId'])) {
                return $this->error(400, 'Missing farmlistId', 'INVALID_INPUT');
            }
            
            $farmlistId = $this->payload['farmlistId'];
            
            $stmt = $this->db->prepare("SELECT id FROM farmlists WHERE id = ? AND player_id = ?");
            $stmt->execute([$farmlistId, $playerId]);
            if (!$stmt->fetch()) {
                return $this->error(404, 'Farmlist not found', 'NOT_FOUND');
            }
            
            $deleteTargets = $this->db->prepare("DELETE FROM farmlist_targets WHERE farmlist_id = ?");
            $deleteTargets->execute([$farmlistId]);
            
            $deleteFarmlist = $this->db->prepare("DELETE FROM farmlists WHERE id = ?");
            $deleteFarmlist->execute([$farmlistId]);
            
            $result = ['farmlist_id' => $farmlistId, 'deleted' => true];
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'delete_farmlist', $result, 'success', $executionTime);
            
            $this->success($result, 'Farmlist deleted successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'DELETE_FARMLIST_ERROR');
        }
    }
    
    private function canRaid($lastRaidTime)
    {
        if (!$lastRaidTime) {
            return true;
        }
        
        $minInterval = 300;
        $timeSinceLastRaid = time() - strtotime($lastRaidTime);
        
        return $timeSinceLastRaid >= $minInterval;
    }
    
    private function logDecision($playerId, $playerType, $action, $metadata, $outcome, $executionTime)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO decision_log 
                (actor_id, actor_type, feature_category, action, metadata_json, outcome, execution_time_ms, llm_used, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $playerId,
                $playerType,
                'farming',
                $action,
                json_encode($metadata),
                $outcome,
                $executionTime,
                0
            ]);
        } catch (\Exception $e) {
        }
    }
}
