<?php

namespace Api\Controllers;

use Api\ApiAbstractCtrl;
use Database\DB;

class LogisticsCtrl extends ApiAbstractCtrl
{
    private $db;
    
    public function __construct(&$response, &$payload)
    {
        parent::__construct($response, $payload);
        $this->db = DB::getInstance();
    }
    
    public function transportResources()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('logistics')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            $playerType = $this->getCurrentPlayerType();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['fromVillageId'])) {
                return $this->error(400, 'Missing fromVillageId', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['toVillageId'])) {
                return $this->error(400, 'Missing toVillageId', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['resources'])) {
                return $this->error(400, 'Missing resources', 'INVALID_INPUT');
            }
            
            $fromVillageId = $this->payload['fromVillageId'];
            $toVillageId = $this->payload['toVillageId'];
            $resources = $this->payload['resources'];
            
            $wood = $resources['wood'] ?? 0;
            $clay = $resources['clay'] ?? 0;
            $iron = $resources['iron'] ?? 0;
            $crop = $resources['crop'] ?? 0;
            
            $totalResources = $wood + $clay + $iron + $crop;
            $merchantsNeeded = ceil($totalResources / 500);
            
            $stmt = $this->db->prepare("
                INSERT INTO resource_transports 
                (player_id, from_village_id, to_village_id, wood, clay, iron, crop, merchants_used, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'in_transit', NOW())
            ");
            $stmt->execute([
                $playerId,
                $fromVillageId,
                $toVillageId,
                $wood,
                $clay,
                $iron,
                $crop,
                $merchantsNeeded
            ]);
            
            $arrivalTime = date('Y-m-d H:i:s', strtotime('+45 minutes'));
            
            $result = [
                'transport_id' => (int)$this->db->lastInsertId(),
                'from_village_id' => $fromVillageId,
                'to_village_id' => $toVillageId,
                'resources' => $resources,
                'merchants_used' => $merchantsNeeded,
                'arrival_time' => $arrivalTime
            ];
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'transport_resources', $result, 'success', $executionTime);
            
            $this->success($result, 'Resources transport initiated successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'TRANSPORT_RESOURCES_ERROR');
        }
    }
    
    public function balanceResources()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('logistics')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            $playerType = $this->getCurrentPlayerType();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['balanceStrategy'])) {
                return $this->error(400, 'Missing balanceStrategy', 'INVALID_INPUT');
            }
            
            $balanceStrategy = $this->payload['balanceStrategy'];
            
            if (!in_array($balanceStrategy, ['equal', 'priority_capital', 'by_need'])) {
                return $this->error(400, 'Invalid balanceStrategy. Must be: equal, priority_capital, or by_need', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                SELECT id, name, wood, clay, iron, crop 
                FROM villages 
                WHERE player_id = ?
            ");
            $stmt->execute([$playerId]);
            $villages = $stmt->fetchAll();
            
            $balancingPlan = $this->generateBalancingPlan($villages, $balanceStrategy);
            
            $transportsExecuted = [];
            foreach ($balancingPlan as $plan) {
                $transportStmt = $this->db->prepare("
                    INSERT INTO resource_transports 
                    (player_id, from_village_id, to_village_id, wood, clay, iron, crop, merchants_used, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'in_transit', NOW())
                ");
                $transportStmt->execute([
                    $playerId,
                    $plan['from_village_id'],
                    $plan['to_village_id'],
                    $plan['wood'],
                    $plan['clay'],
                    $plan['iron'],
                    $plan['crop'],
                    $plan['merchants_needed']
                ]);
                
                $transportsExecuted[] = [
                    'transport_id' => (int)$this->db->lastInsertId(),
                    'from' => $plan['from_village_id'],
                    'to' => $plan['to_village_id'],
                    'resources' => [
                        'wood' => $plan['wood'],
                        'clay' => $plan['clay'],
                        'iron' => $plan['iron'],
                        'crop' => $plan['crop']
                    ]
                ];
            }
            
            $result = [
                'strategy' => $balanceStrategy,
                'villages_analyzed' => count($villages),
                'transports_executed' => $transportsExecuted,
                'total_transports' => count($transportsExecuted)
            ];
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'balance_resources', $result, 'success', $executionTime);
            
            $this->success($result, 'Resource balancing completed successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'BALANCE_RESOURCES_ERROR');
        }
    }
    
    public function getLogisticsStatus()
    {
        try {
            if (!$this->requireFeature('logistics')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            $ongoingStmt = $this->db->prepare("
                SELECT * FROM resource_transports 
                WHERE player_id = ? AND status = 'in_transit' 
                ORDER BY created_at DESC
            ");
            $ongoingStmt->execute([$playerId]);
            $ongoingTransports = $ongoingStmt->fetchAll();
            
            $villageStmt = $this->db->prepare("
                SELECT id, name, wood, clay, iron, crop, 
                       warehouse_capacity, granary_capacity 
                FROM villages 
                WHERE player_id = ?
            ");
            $villageStmt->execute([$playerId]);
            $villageCapacities = $villageStmt->fetchAll();
            
            $merchantsAvailable = 10;
            $merchantsInUse = count($ongoingTransports);
            
            $result = [
                'ongoing_transports' => $ongoingTransports,
                'village_capacities' => $villageCapacities,
                'merchants' => [
                    'available' => $merchantsAvailable,
                    'in_use' => $merchantsInUse,
                    'total' => $merchantsAvailable + $merchantsInUse
                ]
            ];
            
            $this->success($result, 'Logistics status retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'GET_LOGISTICS_STATUS_ERROR');
        }
    }
    
    private function generateBalancingPlan($villages, $strategy)
    {
        $plan = [];
        
        if (count($villages) < 2) {
            return $plan;
        }
        
        switch ($strategy) {
            case 'equal':
                $plan[] = [
                    'from_village_id' => $villages[0]['id'],
                    'to_village_id' => $villages[1]['id'],
                    'wood' => 500,
                    'clay' => 500,
                    'iron' => 500,
                    'crop' => 500,
                    'merchants_needed' => 4
                ];
                break;
            
            case 'priority_capital':
                if (count($villages) > 0) {
                    $plan[] = [
                        'from_village_id' => $villages[1]['id'] ?? $villages[0]['id'],
                        'to_village_id' => $villages[0]['id'],
                        'wood' => 1000,
                        'clay' => 1000,
                        'iron' => 1000,
                        'crop' => 1000,
                        'merchants_needed' => 8
                    ];
                }
                break;
            
            case 'by_need':
                $plan[] = [
                    'from_village_id' => $villages[0]['id'],
                    'to_village_id' => $villages[1]['id'] ?? $villages[0]['id'],
                    'wood' => 300,
                    'clay' => 300,
                    'iron' => 300,
                    'crop' => 300,
                    'merchants_needed' => 3
                ];
                break;
        }
        
        return $plan;
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
                'logistics',
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
