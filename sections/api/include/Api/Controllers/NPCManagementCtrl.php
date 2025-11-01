<?php

namespace Api\Controllers;

use Api\ApiAbstractCtrl;
use Services\NPCInitializerService;
use Database\DB;

class NPCManagementCtrl extends ApiAbstractCtrl
{
    private $db;
    private $npcInitializer;
    
    public function __construct(&$response, &$payload)
    {
        parent::__construct($response, $payload);
        $this->db = DB::getInstance();
        $this->npcInitializer = new NPCInitializerService();
    }
    
    public function listNPCs()
    {
        try {
            $where = ["p.player_type = 'npc'"];
            $params = [];
            
            if (!empty($this->payload['world_id'])) {
                $where[] = "p.world_id = ?";
                $params[] = (int)$this->payload['world_id'];
            }
            
            if (!empty($this->payload['tribe'])) {
                $validTribes = ['romans', 'gauls', 'teutons'];
                if (!in_array($this->payload['tribe'], $validTribes)) {
                    return $this->error(400, 'Invalid tribe', 'INVALID_INPUT');
                }
                $where[] = "p.tribe = ?";
                $params[] = $this->payload['tribe'];
            }
            
            if (!empty($this->payload['difficulty'])) {
                $validDifficulties = ['easy', 'medium', 'hard', 'expert'];
                if (!in_array($this->payload['difficulty'], $validDifficulties)) {
                    return $this->error(400, 'Invalid difficulty', 'INVALID_INPUT');
                }
                $where[] = "ac.difficulty = ?";
                $params[] = $this->payload['difficulty'];
            }
            
            $limit = isset($this->payload['limit']) ? (int)$this->payload['limit'] : 100;
            $offset = isset($this->payload['offset']) ? (int)$this->payload['offset'] : 0;
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $this->db->prepare("
                SELECT 
                    p.id, p.username, p.tribe, p.world_id, p.is_active, p.created_at,
                    ac.difficulty, ac.personality, ac.decision_frequency_seconds, ac.llm_ratio
                FROM players p
                LEFT JOIN ai_configs ac ON p.id = ac.npc_player_id
                WHERE {$whereClause}
                ORDER BY p.created_at DESC
                LIMIT {$limit} OFFSET {$offset}
            ");
            $stmt->execute($params);
            $npcs = $stmt->fetchAll();
            
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total
                FROM players p
                LEFT JOIN ai_configs ac ON p.id = ac.npc_player_id
                WHERE {$whereClause}
            ");
            $stmt->execute($params);
            $total = $stmt->fetch()['total'];
            
            $this->success([
                'npcs' => $npcs,
                'pagination' => [
                    'total' => (int)$total,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ], 'NPCs retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'LIST_NPCS_FAILED');
        }
    }
    
    public function getNPC()
    {
        try {
            if (empty($this->payload['npcId'])) {
                return $this->error(400, 'Missing npcId', 'INVALID_INPUT');
            }
            
            $npcId = (int)$this->payload['npcId'];
            if ($npcId <= 0) {
                return $this->error(400, 'Invalid npcId', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                SELECT p.*, ac.*
                FROM players p
                LEFT JOIN ai_configs ac ON p.id = ac.npc_player_id
                WHERE p.id = ? AND p.player_type = 'npc'
            ");
            $stmt->execute([$npcId]);
            $npc = $stmt->fetch();
            
            if (!$npc) {
                return $this->error(404, 'NPC not found', 'NOT_FOUND');
            }
            
            $stmt = $this->db->prepare("
                SELECT * FROM villages WHERE player_id = ?
            ");
            $stmt->execute([$npcId]);
            $npc['villages'] = $stmt->fetchAll();
            
            if (!empty($npc['llm_bias_json'])) {
                $npc['llm_bias'] = is_string($npc['llm_bias_json']) 
                    ? json_decode($npc['llm_bias_json'], true) 
                    : $npc['llm_bias_json'];
            }
            
            $this->success($npc, 'NPC details retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'GET_NPC_FAILED');
        }
    }
    
    public function createNPC()
    {
        try {
            if (empty($this->payload['worldId'])) {
                return $this->error(400, 'Missing worldId', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['tribe'])) {
                return $this->error(400, 'Missing tribe', 'INVALID_INPUT');
            }
            
            $validTribes = ['romans', 'gauls', 'teutons'];
            if (!in_array($this->payload['tribe'], $validTribes)) {
                return $this->error(400, 'Invalid tribe', 'INVALID_INPUT');
            }
            
            $worldId = (int)$this->payload['worldId'];
            
            $config = [
                'tribe' => $this->payload['tribe'],
                'difficulty' => $this->payload['difficulty'] ?? 'medium',
                'personality' => $this->payload['personality'] ?? 'balanced'
            ];
            
            $location = null;
            if (!empty($this->payload['x']) && !empty($this->payload['y'])) {
                $location = [
                    'x' => (int)$this->payload['x'],
                    'y' => (int)$this->payload['y']
                ];
            }
            
            $result = $this->npcInitializer->createNPC($worldId, $config, $location);
            
            $this->success($result, 'NPC created successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'CREATE_NPC_FAILED');
        }
    }
    
    public function updateConfig()
    {
        try {
            if (empty($this->payload['npcId'])) {
                return $this->error(400, 'Missing npcId', 'INVALID_INPUT');
            }
            
            $npcId = (int)$this->payload['npcId'];
            if ($npcId <= 0) {
                return $this->error(400, 'Invalid npcId', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                SELECT id FROM ai_configs WHERE npc_player_id = ?
            ");
            $stmt->execute([$npcId]);
            if (!$stmt->fetch()) {
                return $this->error(404, 'NPC AI config not found', 'NOT_FOUND');
            }
            
            $updates = [];
            $params = [];
            
            if (isset($this->payload['difficulty'])) {
                $validDifficulties = ['easy', 'medium', 'hard', 'expert'];
                if (!in_array($this->payload['difficulty'], $validDifficulties)) {
                    return $this->error(400, 'Invalid difficulty', 'INVALID_INPUT');
                }
                $updates[] = "difficulty = ?";
                $params[] = $this->payload['difficulty'];
            }
            
            if (isset($this->payload['personality'])) {
                $updates[] = "personality = ?";
                $params[] = $this->payload['personality'];
            }
            
            if (isset($this->payload['decision_frequency_seconds'])) {
                $freq = (int)$this->payload['decision_frequency_seconds'];
                if ($freq < 60) {
                    return $this->error(400, 'Decision frequency must be at least 60 seconds', 'INVALID_INPUT');
                }
                $updates[] = "decision_frequency_seconds = ?";
                $params[] = $freq;
            }
            
            if (isset($this->payload['llm_ratio'])) {
                $ratio = (float)$this->payload['llm_ratio'];
                if ($ratio < 0 || $ratio > 1) {
                    return $this->error(400, 'LLM ratio must be between 0 and 1', 'INVALID_INPUT');
                }
                $updates[] = "llm_ratio = ?";
                $params[] = $ratio;
            }
            
            if (empty($updates)) {
                return $this->error(400, 'No fields to update', 'INVALID_INPUT');
            }
            
            $updates[] = "updated_at = NOW()";
            $params[] = $npcId;
            
            $sql = "UPDATE ai_configs SET " . implode(', ', $updates) . " WHERE npc_player_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $this->success(['npc_id' => $npcId], 'NPC config updated successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'UPDATE_CONFIG_FAILED');
        }
    }
    
    public function activate()
    {
        try {
            if (empty($this->payload['npcId'])) {
                return $this->error(400, 'Missing npcId', 'INVALID_INPUT');
            }
            
            $npcId = (int)$this->payload['npcId'];
            if ($npcId <= 0) {
                return $this->error(400, 'Invalid npcId', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                UPDATE players 
                SET is_active = 1, updated_at = NOW() 
                WHERE id = ? AND player_type = 'npc'
            ");
            $stmt->execute([$npcId]);
            
            if ($stmt->rowCount() === 0) {
                return $this->error(404, 'NPC not found', 'NOT_FOUND');
            }
            
            $this->success(['npc_id' => $npcId], 'NPC activated successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'ACTIVATE_FAILED');
        }
    }
    
    public function deactivate()
    {
        try {
            if (empty($this->payload['npcId'])) {
                return $this->error(400, 'Missing npcId', 'INVALID_INPUT');
            }
            
            $npcId = (int)$this->payload['npcId'];
            if ($npcId <= 0) {
                return $this->error(400, 'Invalid npcId', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                UPDATE players 
                SET is_active = 0, updated_at = NOW() 
                WHERE id = ? AND player_type = 'npc'
            ");
            $stmt->execute([$npcId]);
            
            if ($stmt->rowCount() === 0) {
                return $this->error(404, 'NPC not found', 'NOT_FOUND');
            }
            
            $this->success(['npc_id' => $npcId], 'NPC deactivated successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'DEACTIVATE_FAILED');
        }
    }
    
    public function deleteNPC()
    {
        try {
            if (empty($this->payload['npcId'])) {
                return $this->error(400, 'Missing npcId', 'INVALID_INPUT');
            }
            
            $npcId = (int)$this->payload['npcId'];
            if ($npcId <= 0) {
                return $this->error(400, 'Invalid npcId', 'INVALID_INPUT');
            }
            
            $this->db->beginTransaction();
            
            try {
                $stmt = $this->db->prepare("DELETE FROM villages WHERE player_id = ?");
                $stmt->execute([$npcId]);
                
                $stmt = $this->db->prepare("DELETE FROM ai_configs WHERE npc_player_id = ?");
                $stmt->execute([$npcId]);
                
                $stmt = $this->db->prepare("DELETE FROM world_npc_spawns WHERE npc_player_id = ?");
                $stmt->execute([$npcId]);
                
                $stmt = $this->db->prepare("DELETE FROM players WHERE id = ? AND player_type = 'npc'");
                $stmt->execute([$npcId]);
                
                if ($stmt->rowCount() === 0) {
                    $this->db->rollBack();
                    return $this->error(404, 'NPC not found', 'NOT_FOUND');
                }
                
                $this->db->commit();
                
                $this->success(['npc_id' => $npcId], 'NPC deleted successfully');
                
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'DELETE_NPC_FAILED');
        }
    }
    
    public function getNPCStatistics()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_npcs,
                    SUM(CASE WHEN p.is_active = 1 THEN 1 ELSE 0 END) as active_npcs,
                    SUM(CASE WHEN p.is_active = 0 THEN 1 ELSE 0 END) as inactive_npcs,
                    SUM(CASE WHEN p.tribe = 'romans' THEN 1 ELSE 0 END) as romans,
                    SUM(CASE WHEN p.tribe = 'gauls' THEN 1 ELSE 0 END) as gauls,
                    SUM(CASE WHEN p.tribe = 'teutons' THEN 1 ELSE 0 END) as teutons,
                    SUM(CASE WHEN ac.difficulty = 'easy' THEN 1 ELSE 0 END) as easy,
                    SUM(CASE WHEN ac.difficulty = 'medium' THEN 1 ELSE 0 END) as medium,
                    SUM(CASE WHEN ac.difficulty = 'hard' THEN 1 ELSE 0 END) as hard,
                    SUM(CASE WHEN ac.difficulty = 'expert' THEN 1 ELSE 0 END) as expert
                FROM players p
                LEFT JOIN ai_configs ac ON p.id = ac.npc_player_id
                WHERE p.player_type = 'npc'
            ");
            $stmt->execute();
            $stats = $stmt->fetch();
            
            $stmt = $this->db->prepare("
                SELECT ac.personality, COUNT(*) as count
                FROM players p
                JOIN ai_configs ac ON p.id = ac.npc_player_id
                WHERE p.player_type = 'npc'
                GROUP BY ac.personality
            ");
            $stmt->execute();
            $personalities = $stmt->fetchAll();
            
            $personalityBreakdown = [];
            foreach ($personalities as $p) {
                $personalityBreakdown[$p['personality']] = (int)$p['count'];
            }
            
            $this->success([
                'total_npcs' => (int)$stats['total_npcs'],
                'active_npcs' => (int)$stats['active_npcs'],
                'inactive_npcs' => (int)$stats['inactive_npcs'],
                'by_tribe' => [
                    'romans' => (int)$stats['romans'],
                    'gauls' => (int)$stats['gauls'],
                    'teutons' => (int)$stats['teutons']
                ],
                'by_difficulty' => [
                    'easy' => (int)$stats['easy'],
                    'medium' => (int)$stats['medium'],
                    'hard' => (int)$stats['hard'],
                    'expert' => (int)$stats['expert']
                ],
                'by_personality' => $personalityBreakdown
            ], 'NPC statistics retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'STATISTICS_FAILED');
        }
    }
}
