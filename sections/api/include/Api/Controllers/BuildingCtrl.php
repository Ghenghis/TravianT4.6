<?php

namespace Api\Controllers;

use Api\ApiAbstractCtrl;
use Database\DB;

class BuildingCtrl extends ApiAbstractCtrl
{
    private $db;
    
    public function __construct(&$response, &$payload)
    {
        parent::__construct($response, $payload);
        $this->db = DB::getInstance();
    }
    
    public function queueBuildings()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('building')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            $playerType = $this->getCurrentPlayerType();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['villageId'])) {
                return $this->error(400, 'Missing villageId', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['buildingPlan']) || !is_array($this->payload['buildingPlan'])) {
                return $this->error(400, 'Missing or invalid buildingPlan', 'INVALID_INPUT');
            }
            
            $villageId = $this->payload['villageId'];
            $buildingPlan = $this->payload['buildingPlan'];
            
            $queuedBuildings = [];
            $skippedBuildings = [];
            
            foreach ($buildingPlan as $building) {
                if (!isset($building['type']) || !isset($building['level'])) {
                    $skippedBuildings[] = $building;
                    continue;
                }
                
                $stmt = $this->db->prepare("
                    INSERT INTO building_queue 
                    (village_id, player_id, building_type, target_level, status, created_at) 
                    VALUES (?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $villageId,
                    $playerId,
                    $building['type'],
                    $building['level']
                ]);
                
                $queuedBuildings[] = [
                    'queue_id' => (int)$this->db->lastInsertId(),
                    'building_type' => $building['type'],
                    'target_level' => $building['level']
                ];
            }
            
            $result = [
                'queued_buildings' => $queuedBuildings,
                'skipped_buildings' => $skippedBuildings,
                'total_queued' => count($queuedBuildings)
            ];
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'queue_buildings', $result, 'success', $executionTime);
            
            $this->success($result, 'Buildings queued successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'QUEUE_BUILDINGS_ERROR');
        }
    }
    
    public function getQueue()
    {
        try {
            if (!$this->requireFeature('building')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['villageId'])) {
                return $this->error(400, 'Missing villageId', 'INVALID_INPUT');
            }
            
            $villageId = $this->payload['villageId'];
            
            $stmt = $this->db->prepare("
                SELECT * FROM building_queue 
                WHERE village_id = ? AND player_id = ? 
                ORDER BY created_at ASC
            ");
            $stmt->execute([$villageId, $playerId]);
            $queue = $stmt->fetchAll();
            
            $this->success(['queue' => $queue], 'Building queue retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'GET_QUEUE_ERROR');
        }
    }
    
    public function balanceResources()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('building')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            $playerType = $this->getCurrentPlayerType();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['strategy'])) {
                return $this->error(400, 'Missing strategy', 'INVALID_INPUT');
            }
            
            $strategy = $this->payload['strategy'];
            $autoQueue = $this->payload['autoQueue'] ?? false;
            
            if (!in_array($strategy, ['economic', 'military', 'balanced'])) {
                return $this->error(400, 'Invalid strategy. Must be: economic, military, or balanced', 'INVALID_INPUT');
            }
            
            $recommendations = $this->generateRecommendations($strategy);
            
            $result = [
                'strategy' => $strategy,
                'recommendations' => $recommendations,
                'auto_queued' => $autoQueue
            ];
            
            if ($autoQueue) {
                foreach ($recommendations as $rec) {
                    $stmt = $this->db->prepare("
                        INSERT INTO building_queue 
                        (village_id, player_id, building_type, target_level, status, created_at) 
                        VALUES (?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt->execute([
                        $rec['village_id'],
                        $playerId,
                        $rec['building_type'],
                        $rec['target_level']
                    ]);
                }
            }
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'balance_resources', $result, 'success', $executionTime);
            
            $this->success($result, 'Resource balance analysis completed');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'BALANCE_RESOURCES_ERROR');
        }
    }
    
    public function cancelQueue()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('building')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            $playerType = $this->getCurrentPlayerType();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['queueId'])) {
                return $this->error(400, 'Missing queueId', 'INVALID_INPUT');
            }
            
            $queueId = $this->payload['queueId'];
            
            $stmt = $this->db->prepare("SELECT id FROM building_queue WHERE id = ? AND player_id = ?");
            $stmt->execute([$queueId, $playerId]);
            if (!$stmt->fetch()) {
                return $this->error(404, 'Queue item not found', 'NOT_FOUND');
            }
            
            $deleteStmt = $this->db->prepare("DELETE FROM building_queue WHERE id = ?");
            $deleteStmt->execute([$queueId]);
            
            $result = ['queue_id' => $queueId, 'cancelled' => true];
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'cancel_building_queue', $result, 'success', $executionTime);
            
            $this->success($result, 'Building queue cancelled successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'CANCEL_QUEUE_ERROR');
        }
    }
    
    private function generateRecommendations($strategy)
    {
        $recommendations = [];
        
        switch ($strategy) {
            case 'economic':
                $recommendations[] = [
                    'village_id' => 1,
                    'building_type' => 'warehouse',
                    'target_level' => 10,
                    'priority' => 'high'
                ];
                $recommendations[] = [
                    'village_id' => 1,
                    'building_type' => 'granary',
                    'target_level' => 10,
                    'priority' => 'high'
                ];
                break;
            
            case 'military':
                $recommendations[] = [
                    'village_id' => 1,
                    'building_type' => 'barracks',
                    'target_level' => 15,
                    'priority' => 'high'
                ];
                break;
            
            case 'balanced':
                $recommendations[] = [
                    'village_id' => 1,
                    'building_type' => 'warehouse',
                    'target_level' => 8,
                    'priority' => 'medium'
                ];
                $recommendations[] = [
                    'village_id' => 1,
                    'building_type' => 'barracks',
                    'target_level' => 10,
                    'priority' => 'medium'
                ];
                break;
        }
        
        return $recommendations;
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
                'building',
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
