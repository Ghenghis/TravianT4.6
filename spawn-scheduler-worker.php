#!/usr/bin/env php
<?php
/**
 * Spawn Scheduler Worker - Execute progressive spawn batches
 * 
 * This worker runs every 15 minutes and:
 * 1. Finds pending spawn batches ready for execution
 * 2. Calls SpawnSchedulerService to execute batches
 * 3. Logs all spawns to world_npc_spawns
 * 4. Updates batch status
 * 
 * Usage:
 *   php spawn-scheduler-worker.php
 *   php spawn-scheduler-worker.php --world-id=1  (specific world)
 *   php spawn-scheduler-worker.php --batch-id=5  (specific batch)
 */

require_once __DIR__ . '/sections/api/include/bootstrap.php';

use Services\SpawnSchedulerService;
use Database\DB;

class SpawnSchedulerWorker
{
    private $db;
    private $spawnScheduler;
    private $startTime;
    
    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->spawnScheduler = new SpawnSchedulerService();
        $this->startTime = microtime(true);
    }
    
    /**
     * Main worker execution
     */
    public function run($worldId = null, $batchId = null)
    {
        $this->log("Spawn Scheduler Worker Started");
        
        try {
            $batches = $this->getPendingBatches($worldId, $batchId);
            $this->log("Found " . count($batches) . " pending spawn batches");
            
            $executed = 0;
            $totalSpawned = 0;
            $errors = [];
            
            foreach ($batches as $batch) {
                $result = $this->executeBatch($batch);
                $executed++;
                $totalSpawned += $result['spawned_count'];
                
                if (!empty($result['errors'])) {
                    $errors = array_merge($errors, $result['errors']);
                }
            }
            
            $elapsed = microtime(true) - $this->startTime;
            $this->log(sprintf(
                "Spawn Scheduler Worker Completed: %d batches, %d NPCs spawned in %.2fs",
                $executed, $totalSpawned, $elapsed
            ));
            
            if (!empty($errors)) {
                $this->log("Errors encountered: " . count($errors));
                foreach (array_slice($errors, 0, 5) as $error) {
                    $this->log("  - " . $error);
                }
            }
            
        } catch (\Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
        }
    }
    
    /**
     * Get pending spawn batches
     */
    private function getPendingBatches($worldId = null, $batchId = null)
    {
        if ($batchId) {
            $stmt = $this->db->prepare("SELECT * FROM spawn_batches WHERE id = ? LIMIT 1");
            $stmt->execute([$batchId]);
            return $stmt->fetchAll();
        }
        
        if ($worldId) {
            $stmt = $this->db->prepare(
                "SELECT * FROM spawn_batches 
                 WHERE world_id = ? 
                 AND status = 'pending' 
                 AND scheduled_at <= NOW() 
                 ORDER BY scheduled_at"
            );
            $stmt->execute([$worldId]);
            return $stmt->fetchAll();
        }
        
        $stmt = $this->db->prepare(
            "SELECT * FROM spawn_batches 
             WHERE status = 'pending' 
             AND scheduled_at <= NOW() 
             ORDER BY scheduled_at"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Execute single spawn batch
     */
    private function executeBatch($batch)
    {
        $batchId = $batch['id'];
        $worldId = $batch['world_id'];
        $npcCount = $batch['npcs_to_spawn'];
        
        $this->log("Executing batch {$batchId} (World: {$worldId}, NPCs: {$npcCount})");
        
        try {
            $startTime = microtime(true);
            
            $result = $this->spawnScheduler->executeBatch($batchId);
            
            $elapsed = (microtime(true) - $startTime) * 1000;
            $spawnedCount = $result['npcs_spawned'] ?? 0;
            $successRate = $npcCount > 0 ? ($spawnedCount / $npcCount) * 100 : 0;
            
            $this->log(sprintf(
                "  ✓ Batch {$batchId}: %d/%d NPCs spawned in %.0fms (Success: %.1f%%)",
                $spawnedCount,
                $npcCount,
                $elapsed,
                $successRate
            ));
            
            $stmt = $this->db->prepare(
                "UPDATE worlds SET total_npcs_spawned = total_npcs_spawned + ? WHERE id = ?"
            );
            $stmt->execute([$spawnedCount, $worldId]);
            
            return [
                'spawned_count' => $spawnedCount,
                'errors' => $result['errors'] ?? []
            ];
            
        } catch (\Exception $e) {
            $this->log("  ✗ Batch {$batchId} error: " . $e->getMessage());
            
            $stmt = $this->db->prepare(
                "UPDATE spawn_batches SET status = 'failed', error_message = ? WHERE id = ?"
            );
            $stmt->execute([$e->getMessage(), $batchId]);
            
            return [
                'spawned_count' => 0,
                'errors' => [$e->getMessage()]
            ];
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

$worldId = null;
$batchId = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--world-id=') === 0) {
        $worldId = (int)substr($arg, 11);
    }
    if (strpos($arg, '--batch-id=') === 0) {
        $batchId = (int)substr($arg, 11);
    }
}

$worker = new SpawnSchedulerWorker();
$worker->run($worldId, $batchId);
