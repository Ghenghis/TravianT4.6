<?php

namespace Services;

use Database\DB;

class AIDecisionEngine
{
    private $db;
    private $personalityService;
    private $difficultyScaler;
    private $llmService;

    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->personalityService = new PersonalityService();
        $this->difficultyScaler = new DifficultyScalerService();
        $this->llmService = new LLMIntegrationService();
    }

    public function makeDecision(int $npcPlayerId, array $context = []): array
    {
        $startTime = microtime(true);
        
        try {
            $aiConfig = $this->loadAIConfig($npcPlayerId);
            
            if (empty($aiConfig)) {
                throw new \Exception("AI config not found for NPC player ID: {$npcPlayerId}");
            }
            
            $gameState = $this->evaluateGameState($npcPlayerId);
            
            $useLLM = $this->shouldUseLLM($aiConfig);
            
            if ($useLLM && $this->llmService->isAvailable()) {
                $decision = $this->llmService->getDecision($gameState, $aiConfig, $context);
                $decision['decision_method'] = 'llm';
            } else {
                $decision = $this->selectAction($gameState, $aiConfig);
                $decision['decision_method'] = 'rules';
                $decision['llm_used'] = false;
            }
            
            $decision = $this->personalityService->applyPersonality($decision, $aiConfig['personality']);
            
            $decision = $this->difficultyScaler->scaleDifficulty($decision, $aiConfig['difficulty']);
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $decision['execution_time_ms'] = $executionTime;
            
            $this->updateLastDecisionTime($npcPlayerId);
            
            return $decision;
            
        } catch (\Exception $e) {
            error_log("AI Decision Error: " . $e->getMessage());
            return [
                'action' => 'idle',
                'parameters' => [],
                'reasoning' => 'Error occurred: ' . $e->getMessage(),
                'confidence' => 0.0,
                'decision_method' => 'error_fallback'
            ];
        }
    }

    public function executeDecision(int $npcPlayerId, array $decision): array
    {
        $startTime = microtime(true);
        
        try {
            $result = [];
            $action = $decision['action'] ?? 'idle';
            $parameters = $decision['parameters'] ?? [];
            
            switch ($action) {
                case 'farm':
                    $result = $this->executeFarmingAction($npcPlayerId, $parameters);
                    break;
                
                case 'build':
                    $result = $this->executeBuildingAction($npcPlayerId, $parameters);
                    break;
                
                case 'train':
                    $result = $this->executeTrainingAction($npcPlayerId, $parameters);
                    break;
                
                case 'attack':
                    $result = $this->executeAttackAction($npcPlayerId, $parameters);
                    break;
                
                case 'defend':
                    $result = $this->executeDefenseAction($npcPlayerId, $parameters);
                    break;
                
                case 'trade':
                    $result = $this->executeTradeAction($npcPlayerId, $parameters);
                    break;
                
                case 'idle':
                default:
                    $result = ['action' => 'idle', 'outcome' => 'success'];
                    break;
            }
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            
            $this->logDecision(
                $npcPlayerId,
                'npc',
                $this->getFeatureCategory($action),
                $action,
                array_merge($decision, $result),
                $result['outcome'] ?? 'success',
                $executionTime,
                $decision['llm_used'] ?? false
            );
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("AI Execution Error: " . $e->getMessage());
            return [
                'action' => $action ?? 'unknown',
                'outcome' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    public function evaluateGameState(int $npcPlayerId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT kid as id, name 
                FROM vdata 
                WHERE owner = ?
            ");
            $stmt->execute([$npcPlayerId]);
            $villages = $stmt->fetchAll();
            
            $resources = $this->evaluateResources($npcPlayerId, $villages);
            $troops = $this->evaluateTroops($npcPlayerId, $villages);
            $threats = $this->evaluateThreats($npcPlayerId, $villages);
            $opportunities = $this->evaluateOpportunities($npcPlayerId);
            
            return [
                'villages' => [
                    'count' => count($villages),
                    'list' => $villages,
                    'needResourceBuildings' => $this->checkNeedResourceBuildings($villages)
                ],
                'resources' => $resources,
                'troops' => $troops,
                'threats' => $threats,
                'opportunities' => $opportunities,
                'timestamp' => time()
            ];
            
        } catch (\Exception $e) {
            error_log("Game State Evaluation Error: " . $e->getMessage());
            return [
                'villages' => ['count' => 0, 'list' => []],
                'resources' => ['total' => 0],
                'troops' => ['total' => 0, 'idle' => 0],
                'threats' => ['incoming_attacks' => 0],
                'opportunities' => []
            ];
        }
    }

    private function selectAction(array $gameState, array $aiConfig): array
    {
        $personality = $aiConfig['personality'] ?? 'balanced';
        $difficulty = $aiConfig['difficulty'] ?? 'medium';
        
        $action = $this->personalityService->selectActionByPersonality($personality, $gameState);
        
        $parameters = $this->generateActionParameters($action, $gameState, $aiConfig);
        
        $confidence = $this->calculateConfidence($action, $gameState, $difficulty);
        
        return [
            'action' => $action,
            'parameters' => $parameters,
            'reasoning' => $this->generateReasoning($action, $gameState, $personality),
            'confidence' => $confidence,
            'source' => 'rules'
        ];
    }

    private function shouldUseLLM(array $aiConfig): bool
    {
        $llmRatio = $aiConfig['llm_ratio'] ?? 0.05;
        
        $random = mt_rand(0, 10000) / 10000;
        
        return $random < $llmRatio;
    }

    private function loadAIConfig(int $npcPlayerId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM ai_configs 
                WHERE npc_player_id = ? 
                LIMIT 1
            ");
            $stmt->execute([$npcPlayerId]);
            $config = $stmt->fetch();
            
            if ($config && isset($config['llm_bias_json'])) {
                $config['llm_bias'] = json_decode($config['llm_bias_json'], true);
            }
            
            return $config ?: null;
            
        } catch (\Exception $e) {
            error_log("Load AI Config Error: " . $e->getMessage());
            return null;
        }
    }

    private function updateLastDecisionTime(int $npcPlayerId): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE ai_configs 
                SET last_decision_at = NOW() 
                WHERE npc_player_id = ?
            ");
            $stmt->execute([$npcPlayerId]);
        } catch (\Exception $e) {
            error_log("Update Last Decision Time Error: " . $e->getMessage());
        }
    }

    private function evaluateResources(int $npcPlayerId, array $villages): array
    {
        $totalResources = [
            'wood' => 0,
            'clay' => 0,
            'iron' => 0,
            'crop' => 0,
            'total' => 0,
            'surplus' => 0
        ];
        
        foreach ($villages as $village) {
            $totalResources['wood'] += rand(1000, 5000);
            $totalResources['clay'] += rand(1000, 5000);
            $totalResources['iron'] += rand(1000, 5000);
            $totalResources['crop'] += rand(1000, 5000);
        }
        
        $totalResources['total'] = 
            $totalResources['wood'] + 
            $totalResources['clay'] + 
            $totalResources['iron'] + 
            $totalResources['crop'];
        
        $totalResources['surplus'] = max(0, $totalResources['total'] - 5000);
        
        return $totalResources;
    }

    private function evaluateTroops(int $npcPlayerId, array $villages): array
    {
        return [
            'total' => rand(50, 200),
            'idle' => rand(20, 100),
            'in_transit' => rand(0, 50),
            'details' => [
                'infantry' => rand(20, 80),
                'cavalry' => rand(10, 50),
                'scouts' => rand(5, 20)
            ]
        ];
    }

    private function evaluateThreats(int $npcPlayerId, array $villages): array
    {
        return [
            'incoming_attacks' => rand(0, 2),
            'nearby_enemies' => rand(0, 5),
            'threat_level' => 'low'
        ];
    }

    private function evaluateOpportunities(int $npcPlayerId): array
    {
        return [
            'weak_targets_nearby' => rand(3, 10),
            'trade_opportunities' => rand(0, 3),
            'alliance_invites' => rand(0, 1)
        ];
    }

    private function checkNeedResourceBuildings(array $villages): bool
    {
        return count($villages) > 0 && rand(0, 1) === 1;
    }

    private function generateActionParameters(string $action, array $gameState, array $aiConfig): array
    {
        $params = [];
        
        switch ($action) {
            case 'farm':
                $params['intensity'] = 'medium';
                $params['target_count'] = rand(5, 15);
                break;
            
            case 'build':
                $params['building_type'] = 'resource';
                $params['priority'] = 'medium';
                break;
            
            case 'train':
                $params['troop_type'] = 'infantry';
                $params['quantity'] = rand(10, 50);
                break;
            
            case 'attack':
                $params['target_type'] = 'weak';
                $params['troop_count'] = rand(30, 100);
                break;
            
            case 'defend':
                $params['action_type'] = 'reinforce';
                break;
            
            case 'trade':
                $params['resource_type'] = 'wood';
                $params['amount'] = rand(500, 2000);
                break;
        }
        
        return $params;
    }

    private function calculateConfidence(string $action, array $gameState, string $difficulty): float
    {
        $baseConfidence = 0.7;
        
        if ($difficulty === 'expert') {
            $baseConfidence = 0.95;
        } elseif ($difficulty === 'easy') {
            $baseConfidence = 0.5;
        }
        
        return min(1.0, max(0.0, $baseConfidence + (rand(-10, 10) / 100)));
    }

    private function generateReasoning(string $action, array $gameState, string $personality): string
    {
        $reasons = [
            'farm' => "Resources needed, initiating farming operations",
            'build' => "Improving infrastructure for {$personality} strategy",
            'train' => "Building military strength",
            'attack' => "Aggressive expansion opportunity detected",
            'defend' => "Defensive measures required",
            'trade' => "Optimizing resource distribution",
            'idle' => "Waiting for optimal conditions"
        ];
        
        return $reasons[$action] ?? "Action selected based on {$personality} personality";
    }

    private function executeFarmingAction(int $npcPlayerId, array $parameters): array
    {
        return [
            'action' => 'farm',
            'outcome' => 'success',
            'raids_sent' => $parameters['target_count'] ?? 5
        ];
    }

    private function executeBuildingAction(int $npcPlayerId, array $parameters): array
    {
        return [
            'action' => 'build',
            'outcome' => 'success',
            'building_queued' => $parameters['building_type'] ?? 'unknown'
        ];
    }

    private function executeTrainingAction(int $npcPlayerId, array $parameters): array
    {
        return [
            'action' => 'train',
            'outcome' => 'success',
            'troops_queued' => $parameters['quantity'] ?? 0
        ];
    }

    private function executeAttackAction(int $npcPlayerId, array $parameters): array
    {
        return [
            'action' => 'attack',
            'outcome' => 'success',
            'troops_sent' => $parameters['troop_count'] ?? 0
        ];
    }

    private function executeDefenseAction(int $npcPlayerId, array $parameters): array
    {
        return [
            'action' => 'defend',
            'outcome' => 'success',
            'defense_action' => $parameters['action_type'] ?? 'reinforce'
        ];
    }

    private function executeTradeAction(int $npcPlayerId, array $parameters): array
    {
        return [
            'action' => 'trade',
            'outcome' => 'success',
            'resources_traded' => $parameters['amount'] ?? 0
        ];
    }

    private function getFeatureCategory(string $action): string
    {
        $mapping = [
            'farm' => 'farming',
            'build' => 'building',
            'train' => 'training',
            'attack' => 'farming',
            'defend' => 'defense',
            'trade' => 'market',
            'idle' => 'strategy'
        ];
        
        return $mapping[$action] ?? 'strategy';
    }

    private function logDecision(
        int $actorId, 
        string $actorType, 
        string $category, 
        string $action, 
        array $metadata, 
        string $outcome, 
        int $executionTime, 
        bool $llmUsed
    ): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO decision_log 
                (actor_id, actor_type, feature_category, action, metadata_json, outcome, execution_time_ms, llm_used, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $actorId,
                $actorType,
                $category,
                $action,
                json_encode($metadata),
                $outcome,
                $executionTime,
                $llmUsed ? 1 : 0
            ]);
        } catch (\Exception $e) {
            error_log("Log Decision Error: " . $e->getMessage());
        }
    }
}
