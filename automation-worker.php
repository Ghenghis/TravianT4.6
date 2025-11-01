#!/usr/bin/env php
<?php
/**
 * Automation Worker - Execute scheduled automation actions
 * 
 * This worker runs every 5 minutes and:
 * 1. Finds players with automation enabled
 * 2. Executes farming, building, training, defense actions
 * 3. Logs all actions to decision_log
 * 4. Respects feature flags and rate limits
 * 
 * Usage:
 *   php automation-worker.php
 *   php automation-worker.php --player-id=123  (test specific player)
 */

require_once __DIR__ . '/sections/api/include/bootstrap.php';

use Services\FeatureGateService;
use Api\Controllers\FarmingCtrl;
use Api\Controllers\BuildingCtrl;
use Api\Controllers\TrainingCtrl;
use Api\Controllers\DefenseCtrl;
use Api\Controllers\LogisticsCtrl;
use Database\DB;

class AutomationWorker
{
    private $db;
    private $featureGate;
    private $startTime;
    
    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->featureGate = new FeatureGateService();
        $this->startTime = microtime(true);
    }
    
    /**
     * Main worker execution
     */
    public function run($playerId = null)
    {
        $this->log("Automation Worker Started");
        
        try {
            $players = $this->getAutomationPlayers($playerId);
            $this->log("Found " . count($players) . " players with automation enabled");
            
            $processed = 0;
            foreach ($players as $player) {
                $this->processPlayer($player);
                $processed++;
            }
            
            $elapsed = microtime(true) - $this->startTime;
            $this->log(sprintf("Automation Worker Completed: %d players in %.2fs", $processed, $elapsed));
            
        } catch (\Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
        }
    }
    
    /**
     * Get players with automation enabled
     */
    private function getAutomationPlayers($specificPlayerId = null)
    {
        if ($specificPlayerId) {
            $stmt = $this->db->prepare("SELECT * FROM players WHERE id = ? AND is_active = TRUE LIMIT 1");
            $stmt->execute([$specificPlayerId]);
            return $stmt->fetchAll();
        }
        
        $stmt = $this->db->prepare(
            "SELECT p.* FROM players p 
             WHERE p.is_active = TRUE 
             AND (p.settings_json->>'automation_enabled')::boolean = true
             ORDER BY p.id"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Process single player automation
     */
    private function processPlayer($player)
    {
        $playerId = $player['id'];
        $this->log("Processing player {$playerId} ({$player['player_type']})");
        
        $settings = json_decode($player['settings_json'] ?? '{}', true) ?? [];
        $intensity = $settings['automation_intensity'] ?? 'medium';
        
        if ($this->featureGate->isEnabled('farming', $playerId, $player['player_type'])) {
            $this->executeFarming($playerId);
        }
        
        if ($this->featureGate->isEnabled('building', $playerId, $player['player_type'])) {
            $this->executeBuilding($playerId, $intensity);
        }
        
        if ($this->featureGate->isEnabled('training', $playerId, $player['player_type'])) {
            $this->executeTraining($playerId, $intensity);
        }
        
        if ($this->featureGate->isEnabled('defense', $playerId, $player['player_type'])) {
            $this->executeDefense($playerId);
        }
        
        if ($this->featureGate->isEnabled('logistics', $playerId, $player['player_type'])) {
            $this->executeLogistics($playerId);
        }
    }
    
    /**
     * Execute farming automation
     */
    private function executeFarming($playerId)
    {
        try {
            $response = [];
            $payload = ['playerId' => $playerId];
            
            $farmingCtrl = new FarmingCtrl($response, $payload);
            $farmingCtrl->executeFarmlist();
            
            if (isset($response['success']) && $response['success']) {
                $this->log("  ✓ Farming executed for player $playerId");
            } else {
                $this->log("  ⚠ Farming completed with warnings for player $playerId");
            }
        } catch (\Exception $e) {
            $this->log("  ✗ Farming error: " . $e->getMessage());
        }
    }
    
    /**
     * Execute building automation
     */
    private function executeBuilding($playerId, $intensity)
    {
        try {
            $response = [];
            $payload = [
                'playerId' => $playerId,
                'strategy' => $intensity === 'high' ? 'economic' : 'balanced'
            ];
            
            $buildingCtrl = new BuildingCtrl($response, $payload);
            $buildingCtrl->balanceResources();
            
            if (isset($response['success']) && $response['success']) {
                $this->log("  ✓ Building executed for player $playerId");
            } else {
                $this->log("  ⚠ Building completed with warnings for player $playerId");
            }
        } catch (\Exception $e) {
            $this->log("  ✗ Building error: " . $e->getMessage());
        }
    }
    
    /**
     * Execute training automation
     */
    private function executeTraining($playerId, $intensity)
    {
        try {
            $response = [];
            $payload = [
                'playerId' => $playerId,
                'intensity' => $intensity
            ];
            
            $trainingCtrl = new TrainingCtrl($response, $payload);
            $trainingCtrl->autoTrain();
            
            if (isset($response['success']) && $response['success']) {
                $this->log("  ✓ Training executed for player $playerId");
            } else {
                $this->log("  ⚠ Training completed with warnings for player $playerId");
            }
        } catch (\Exception $e) {
            $this->log("  ✗ Training error: " . $e->getMessage());
        }
    }
    
    /**
     * Execute defense automation
     */
    private function executeDefense($playerId)
    {
        try {
            $response = [];
            $payload = ['playerId' => $playerId];
            
            $defenseCtrl = new DefenseCtrl($response, $payload);
            $defenseCtrl->checkThreats();
            
            if (isset($response['success']) && $response['success']) {
                $this->log("  ✓ Defense checked for player $playerId");
            } else {
                $this->log("  ⚠ Defense completed with warnings for player $playerId");
            }
        } catch (\Exception $e) {
            $this->log("  ✗ Defense error: " . $e->getMessage());
        }
    }
    
    /**
     * Execute logistics automation
     */
    private function executeLogistics($playerId)
    {
        try {
            $response = [];
            $payload = ['playerId' => $playerId];
            
            $logisticsCtrl = new LogisticsCtrl($response, $payload);
            $logisticsCtrl->balanceResources();
            
            if (isset($response['success']) && $response['success']) {
                $this->log("  ✓ Logistics executed for player $playerId");
            } else {
                $this->log("  ⚠ Logistics completed with warnings for player $playerId");
            }
        } catch (\Exception $e) {
            $this->log("  ✗ Logistics error: " . $e->getMessage());
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

$playerId = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--player-id=') === 0) {
        $playerId = (int)substr($arg, 12);
    }
}

$worker = new AutomationWorker();
$worker->run($playerId);
