<?php

namespace Api\Controllers;

use Api\ApiAbstractCtrl;
use Database\DB;

class TrainingCtrl extends ApiAbstractCtrl
{
    private $db;
    
    public function __construct(&$response, &$payload)
    {
        parent::__construct($response, $payload);
        $this->db = DB::getInstance();
    }
    
    public function executeTraining()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('training')) {
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
            
            if (empty($this->payload['troopPlan']) || !is_array($this->payload['troopPlan'])) {
                return $this->error(400, 'Missing or invalid troopPlan', 'INVALID_INPUT');
            }
            
            $villageId = $this->payload['villageId'];
            $troopPlan = $this->payload['troopPlan'];
            
            $queuedTroops = [];
            $totalCost = ['wood' => 0, 'clay' => 0, 'iron' => 0, 'crop' => 0];
            
            foreach ($troopPlan as $troop) {
                if (!isset($troop['type']) || !isset($troop['quantity'])) {
                    continue;
                }
                
                $stmt = $this->db->prepare("
                    INSERT INTO training_queue 
                    (village_id, player_id, troop_type, quantity, status, created_at) 
                    VALUES (?, ?, ?, ?, 'queued', NOW())
                ");
                $stmt->execute([
                    $villageId,
                    $playerId,
                    $troop['type'],
                    $troop['quantity']
                ]);
                
                $queuedTroops[] = [
                    'queue_id' => (int)$this->db->lastInsertId(),
                    'troop_type' => $troop['type'],
                    'quantity' => $troop['quantity']
                ];
                
                $cost = $this->calculateTroopCost($troop['type'], $troop['quantity']);
                $totalCost['wood'] += $cost['wood'];
                $totalCost['clay'] += $cost['clay'];
                $totalCost['iron'] += $cost['iron'];
                $totalCost['crop'] += $cost['crop'];
            }
            
            $result = [
                'queued_troops' => $queuedTroops,
                'total_cost' => $totalCost,
                'total_queued' => count($queuedTroops)
            ];
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'train_troops', $result, 'success', $executionTime);
            
            $this->success($result, 'Troop training queued successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'TRAINING_EXECUTION_ERROR');
        }
    }
    
    public function enableContinuous()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('training')) {
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
            
            if (empty($this->payload['troopType'])) {
                return $this->error(400, 'Missing troopType', 'INVALID_INPUT');
            }
            
            if (!isset($this->payload['enabled'])) {
                return $this->error(400, 'Missing enabled field', 'INVALID_INPUT');
            }
            
            $villageId = $this->payload['villageId'];
            $troopType = $this->payload['troopType'];
            $enabled = (bool)$this->payload['enabled'];
            
            $stmt = $this->db->prepare("
                SELECT id FROM continuous_training 
                WHERE village_id = ? AND player_id = ? AND troop_type = ?
            ");
            $stmt->execute([$villageId, $playerId, $troopType]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $updateStmt = $this->db->prepare("
                    UPDATE continuous_training 
                    SET enabled = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([$enabled ? 1 : 0, $existing['id']]);
            } else {
                $insertStmt = $this->db->prepare("
                    INSERT INTO continuous_training 
                    (village_id, player_id, troop_type, enabled, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $insertStmt->execute([$villageId, $playerId, $troopType, $enabled ? 1 : 0]);
            }
            
            $result = [
                'village_id' => $villageId,
                'troop_type' => $troopType,
                'enabled' => $enabled
            ];
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'enable_continuous_training', $result, 'success', $executionTime);
            
            $this->success($result, 'Continuous training config updated successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'CONTINUOUS_TRAINING_ERROR');
        }
    }
    
    public function getTrainingQueue()
    {
        try {
            if (!$this->requireFeature('training')) {
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
                SELECT * FROM training_queue 
                WHERE village_id = ? AND player_id = ? 
                ORDER BY created_at ASC
            ");
            $stmt->execute([$villageId, $playerId]);
            $queue = $stmt->fetchAll();
            
            $this->success(['queue' => $queue], 'Training queue retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'GET_TRAINING_QUEUE_ERROR');
        }
    }
    
    public function cancelTraining()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('training')) {
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
            
            $stmt = $this->db->prepare("SELECT id FROM training_queue WHERE id = ? AND player_id = ?");
            $stmt->execute([$queueId, $playerId]);
            if (!$stmt->fetch()) {
                return $this->error(404, 'Training queue item not found', 'NOT_FOUND');
            }
            
            $deleteStmt = $this->db->prepare("DELETE FROM training_queue WHERE id = ?");
            $deleteStmt->execute([$queueId]);
            
            $result = ['queue_id' => $queueId, 'cancelled' => true];
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'cancel_training', $result, 'success', $executionTime);
            
            $this->success($result, 'Training cancelled successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'CANCEL_TRAINING_ERROR');
        }
    }
    
    private function calculateTroopCost($troopType, $quantity)
    {
        $costs = [
            'spearman' => ['wood' => 95, 'clay' => 75, 'iron' => 40, 'crop' => 40],
            'swordsman' => ['wood' => 140, 'clay' => 150, 'iron' => 185, 'crop' => 60],
            'cavalry' => ['wood' => 450, 'clay' => 515, 'iron' => 480, 'crop' => 80],
        ];
        
        $baseCost = $costs[$troopType] ?? ['wood' => 100, 'clay' => 100, 'iron' => 100, 'crop' => 50];
        
        return [
            'wood' => $baseCost['wood'] * $quantity,
            'clay' => $baseCost['clay'] * $quantity,
            'iron' => $baseCost['iron'] * $quantity,
            'crop' => $baseCost['crop'] * $quantity
        ];
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
                'training',
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
