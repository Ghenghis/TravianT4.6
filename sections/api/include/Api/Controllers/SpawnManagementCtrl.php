<?php

namespace Api\Controllers;

use Api\ApiAbstractCtrl;
use Services\SpawnSchedulerService;
use Database\DB;

class SpawnManagementCtrl extends ApiAbstractCtrl
{
    private $db;
    private $scheduler;
    
    public function __construct(&$response, &$payload)
    {
        parent::__construct($response, $payload);
        $this->db = DB::getInstance();
        $this->scheduler = new SpawnSchedulerService();
    }
    
    public function getBatches()
    {
        try {
            if (empty($this->payload['worldId'])) {
                return $this->error(400, 'Missing worldId', 'INVALID_INPUT');
            }
            
            $worldId = (int)$this->payload['worldId'];
            if ($worldId <= 0) {
                return $this->error(400, 'Invalid worldId', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                SELECT * FROM spawn_batches 
                WHERE world_id = ? 
                ORDER BY batch_number
            ");
            $stmt->execute([$worldId]);
            $batches = $stmt->fetchAll();
            
            $this->success(['batches' => $batches], 'Spawn batches retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'GET_BATCHES_FAILED');
        }
    }
    
    public function executeBatch()
    {
        try {
            if (empty($this->payload['batchId'])) {
                return $this->error(400, 'Missing batchId', 'INVALID_INPUT');
            }
            
            $batchId = (int)$this->payload['batchId'];
            if ($batchId <= 0) {
                return $this->error(400, 'Invalid batchId', 'INVALID_INPUT');
            }
            
            $result = $this->scheduler->executeBatch($batchId);
            
            $this->success($result, 'Batch executed successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'EXECUTE_BATCH_FAILED');
        }
    }
    
    public function pauseSpawning()
    {
        try {
            if (empty($this->payload['worldId'])) {
                return $this->error(400, 'Missing worldId', 'INVALID_INPUT');
            }
            
            $worldId = (int)$this->payload['worldId'];
            if ($worldId <= 0) {
                return $this->error(400, 'Invalid worldId', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                UPDATE spawn_batches 
                SET status = 'paused', updated_at = NOW() 
                WHERE world_id = ? AND status = 'pending'
            ");
            $stmt->execute([$worldId]);
            $affected = $stmt->rowCount();
            
            $this->success([
                'world_id' => $worldId,
                'batches_paused' => $affected
            ], "Spawning paused for world {$worldId}");
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'PAUSE_FAILED');
        }
    }
    
    public function resumeSpawning()
    {
        try {
            if (empty($this->payload['worldId'])) {
                return $this->error(400, 'Missing worldId', 'INVALID_INPUT');
            }
            
            $worldId = (int)$this->payload['worldId'];
            if ($worldId <= 0) {
                return $this->error(400, 'Invalid worldId', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                UPDATE spawn_batches 
                SET status = 'pending', updated_at = NOW() 
                WHERE world_id = ? AND status = 'paused'
            ");
            $stmt->execute([$worldId]);
            $affected = $stmt->rowCount();
            
            $this->success([
                'world_id' => $worldId,
                'batches_resumed' => $affected
            ], "Spawning resumed for world {$worldId}");
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'RESUME_FAILED');
        }
    }
    
    public function getPendingBatches()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT sb.*, w.world_key, w.world_name 
                FROM spawn_batches sb
                JOIN worlds w ON sb.world_id = w.id
                WHERE sb.status = 'pending' AND sb.scheduled_at <= NOW()
                ORDER BY sb.scheduled_at ASC
            ");
            $stmt->execute();
            $batches = $stmt->fetchAll();
            
            $this->success([
                'pending_batches' => $batches,
                'count' => count($batches)
            ], 'Pending batches retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'GET_PENDING_FAILED');
        }
    }
    
    public function retryBatch()
    {
        try {
            if (empty($this->payload['batchId'])) {
                return $this->error(400, 'Missing batchId', 'INVALID_INPUT');
            }
            
            $batchId = (int)$this->payload['batchId'];
            if ($batchId <= 0) {
                return $this->error(400, 'Invalid batchId', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                UPDATE spawn_batches 
                SET status = 'pending', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$batchId]);
            
            $result = $this->scheduler->executeBatch($batchId);
            
            $this->success($result, 'Batch retry executed successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'RETRY_BATCH_FAILED');
        }
    }
}
