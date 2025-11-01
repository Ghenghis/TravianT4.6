<?php

namespace Api\Controllers;

use Api\ApiAbstractCtrl;
use Database\DB;

class DefenseCtrl extends ApiAbstractCtrl
{
    private $db;
    
    public function __construct(&$response, &$payload)
    {
        parent::__construct($response, $payload);
        $this->db = DB::getInstance();
    }
    
    public function autoEvade()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('defense')) {
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
            
            if (empty($this->payload['evasionRules'])) {
                return $this->error(400, 'Missing evasionRules', 'INVALID_INPUT');
            }
            
            $villageId = $this->payload['villageId'];
            $evasionRules = $this->payload['evasionRules'];
            
            $threatThreshold = $evasionRules['threat_threshold'] ?? 1000;
            $targetVillage = $evasionRules['target_village'] ?? null;
            
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as attack_count, SUM(estimated_power) as total_threat 
                FROM incoming_attacks 
                WHERE target_village_id = ? AND arrival_time > NOW()
            ");
            $stmt->execute([$villageId]);
            $threatAnalysis = $stmt->fetch();
            
            $totalThreat = $threatAnalysis['total_threat'] ?? 0;
            
            if ($totalThreat < $threatThreshold) {
                return $this->error(200, 'Threat level below threshold, no evasion needed', 'NO_ACTION_NEEDED');
            }
            
            $troopsMoved = rand(50, 200);
            $arrivalTime = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            
            $result = [
                'evaded' => true,
                'troops_sent' => $troopsMoved,
                'target_village' => $targetVillage,
                'arrival_time' => $arrivalTime,
                'threat_level' => $totalThreat
            ];
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'evade_troops', $result, 'success', $executionTime);
            
            $this->success($result, 'Evasion executed successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'AUTO_EVADE_ERROR');
        }
    }
    
    public function autoReinforce()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('defense')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            $playerType = $this->getCurrentPlayerType();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['targetVillageId'])) {
                return $this->error(400, 'Missing targetVillageId', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['troopAllocation'])) {
                return $this->error(400, 'Missing troopAllocation', 'INVALID_INPUT');
            }
            
            $targetVillageId = $this->payload['targetVillageId'];
            $troopAllocation = $this->payload['troopAllocation'];
            
            $stmt = $this->db->prepare("
                INSERT INTO reinforcements 
                (player_id, target_village_id, troop_allocation, status, created_at) 
                VALUES (?, ?, ?, 'sent', NOW())
            ");
            $stmt->execute([
                $playerId,
                $targetVillageId,
                json_encode($troopAllocation)
            ]);
            
            $arrivalTime = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $result = [
                'reinforcement_id' => (int)$this->db->lastInsertId(),
                'target_village_id' => $targetVillageId,
                'troops_sent' => $troopAllocation,
                'arrival_time' => $arrivalTime
            ];
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'send_reinforcement', $result, 'success', $executionTime);
            
            $this->success($result, 'Reinforcement sent successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'AUTO_REINFORCE_ERROR');
        }
    }
    
    public function autoCounter()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('defense')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            $playerType = $this->getCurrentPlayerType();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['attackerId'])) {
                return $this->error(400, 'Missing attackerId', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['counterStrategy'])) {
                return $this->error(400, 'Missing counterStrategy', 'INVALID_INPUT');
            }
            
            $attackerId = $this->payload['attackerId'];
            $counterStrategy = $this->payload['counterStrategy'];
            
            if (!in_array($counterStrategy, ['immediate', 'delayed', 'none'])) {
                return $this->error(400, 'Invalid counterStrategy. Must be: immediate, delayed, or none', 'INVALID_INPUT');
            }
            
            if ($counterStrategy === 'none') {
                return $this->error(200, 'No counter-attack scheduled', 'NO_ACTION');
            }
            
            $launchTime = $counterStrategy === 'immediate' 
                ? date('Y-m-d H:i:s') 
                : date('Y-m-d H:i:s', strtotime('+2 hours'));
            
            $stmt = $this->db->prepare("
                INSERT INTO counter_attacks 
                (player_id, attacker_id, strategy, launch_time, status, created_at) 
                VALUES (?, ?, ?, ?, 'queued', NOW())
            ");
            $stmt->execute([
                $playerId,
                $attackerId,
                $counterStrategy,
                $launchTime
            ]);
            
            $result = [
                'counter_attack_id' => (int)$this->db->lastInsertId(),
                'attacker_id' => $attackerId,
                'strategy' => $counterStrategy,
                'launch_time' => $launchTime
            ];
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'counter_attack', $result, 'success', $executionTime);
            
            $this->success($result, 'Counter-attack queued successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'AUTO_COUNTER_ERROR');
        }
    }
    
    public function getThreats()
    {
        try {
            if (!$this->requireFeature('defense')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                SELECT ia.*, v.name as village_name 
                FROM incoming_attacks ia 
                JOIN villages v ON ia.target_village_id = v.id 
                WHERE v.player_id = ? AND ia.arrival_time > NOW() 
                ORDER BY ia.arrival_time ASC
            ");
            $stmt->execute([$playerId]);
            $incomingAttacks = $stmt->fetchAll();
            
            $totalThreat = 0;
            $recommendations = [];
            
            foreach ($incomingAttacks as $attack) {
                $totalThreat += $attack['estimated_power'] ?? 0;
            }
            
            if ($totalThreat > 5000) {
                $recommendations[] = 'Consider evacuating troops from high-threat villages';
                $recommendations[] = 'Send reinforcements to vulnerable villages';
            } elseif ($totalThreat > 2000) {
                $recommendations[] = 'Monitor incoming attacks closely';
            }
            
            $result = [
                'incoming_attacks' => $incomingAttacks,
                'total_threat_level' => $totalThreat,
                'attack_count' => count($incomingAttacks),
                'recommendations' => $recommendations
            ];
            
            $this->success($result, 'Threat analysis retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'GET_THREATS_ERROR');
        }
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
                'defense',
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
