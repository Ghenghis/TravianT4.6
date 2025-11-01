#!/usr/bin/env php
<?php
/**
 * AI Decision Worker - Execute AI decisions for NPCs
 * 
 * This worker runs every 5 minutes and:
 * 1. Finds active NPCs ready for decisions
 * 2. Calls AIDecisionEngine to make decisions
 * 3. Executes decisions via automation APIs
 * 4. Logs all decisions to decision_log
 * 
 * Usage:
 *   php ai-decision-worker.php
 *   php ai-decision-worker.php --npc-id=123  (test specific NPC)
 *   php ai-decision-worker.php --limit=10    (limit batch size)
 */

require_once __DIR__ . '/sections/api/include/bootstrap.php';

use Services\AIDecisionEngine;
use Database\DB;

class AIDecisionWorker
{
    private $db;
    private $aiEngine;
    private $startTime;
    
    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->aiEngine = new AIDecisionEngine();
        $this->startTime = microtime(true);
    }
    
    /**
     * Main worker execution
     */
    public function run($npcId = null, $limit = 50)
    {
        $this->log("AI Decision Worker Started");
        
        try {
            $npcs = $this->getReadyNPCs($npcId, $limit);
            $this->log("Found " . count($npcs) . " NPCs ready for decisions");
            
            $processed = 0;
            $decisions = ['success' => 0, 'error' => 0, 'skipped' => 0];
            
            foreach ($npcs as $npc) {
                $result = $this->processNPC($npc);
                $decisions[$result]++;
                $processed++;
            }
            
            $elapsed = microtime(true) - $this->startTime;
            $this->log(sprintf(
                "AI Decision Worker Completed: %d NPCs in %.2fs (Success: %d, Error: %d, Skipped: %d)",
                $processed, $elapsed, $decisions['success'], $decisions['error'], $decisions['skipped']
            ));
            
        } catch (\Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
        }
    }
    
    /**
     * Get NPCs ready for decisions
     */
    private function getReadyNPCs($specificNpcId = null, $limit = 50)
    {
        if ($specificNpcId) {
            $stmt = $this->db->prepare(
                "SELECT p.*, ac.* FROM players p 
                 JOIN ai_configs ac ON p.id = ac.npc_player_id 
                 WHERE p.id = ? AND p.is_active = TRUE LIMIT 1"
            );
            $stmt->execute([$specificNpcId]);
            return $stmt->fetchAll();
        }
        
        $stmt = $this->db->prepare(
            "SELECT p.*, ac.* FROM players p 
             JOIN ai_configs ac ON p.id = ac.npc_player_id 
             WHERE p.player_type = 'npc' 
             AND p.is_active = TRUE 
             AND (ac.last_decision_at IS NULL OR 
                  ac.last_decision_at + (ac.decision_frequency_seconds || ' seconds')::interval <= NOW())
             ORDER BY ac.last_decision_at ASC NULLS FIRST 
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Process single NPC decision
     */
    private function processNPC($npc)
    {
        $npcId = $npc['npc_player_id'];
        $difficulty = $npc['difficulty'] ?? 'medium';
        $personality = $npc['personality'] ?? 'balanced';
        
        $this->log("Processing NPC {$npcId} (Difficulty: {$difficulty}, Personality: {$personality})");
        
        try {
            $startTime = microtime(true);
            
            $context = ['worker' => 'ai-decision-worker'];
            $decision = $this->aiEngine->makeDecision($npcId, $context);
            
            $result = $this->aiEngine->executeDecision($npcId, $decision);
            
            $elapsed = (microtime(true) - $startTime) * 1000;
            $llmUsed = isset($decision['llm_used']) && $decision['llm_used'] ? 'YES' : 'NO';
            $action = $decision['action'] ?? 'unknown';
            
            $this->log(sprintf(
                "  ✓ Decision: %s (LLM: %s, Time: %.0fms)",
                $action,
                $llmUsed,
                $elapsed
            ));
            
            $stmt = $this->db->prepare(
                "UPDATE ai_configs SET last_decision_at = NOW() WHERE npc_player_id = ?"
            );
            $stmt->execute([$npcId]);
            
            return 'success';
            
        } catch (\Exception $e) {
            $this->log("  ✗ Error: " . $e->getMessage());
            return 'error';
        }
    }
    
    /**
     * Log message
     */
    private function log($message)
    {
        echo "[" . date('Y-m-d H:i:s') . "] $message\n";
    }
}

$npcId = null;
$limit = 50;

foreach ($argv as $arg) {
    if (strpos($arg, '--npc-id=') === 0) {
        $npcId = (int)substr($arg, 9);
    }
    if (strpos($arg, '--limit=') === 0) {
        $limit = (int)substr($arg, 8);
    }
}

$worker = new AIDecisionWorker();
$worker->run($npcId, $limit);
