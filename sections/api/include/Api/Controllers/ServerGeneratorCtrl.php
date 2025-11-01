<?php

namespace Api\Controllers;

use Api\ApiAbstractCtrl;
use Services\WorldOrchestratorService;
use Database\DB;

class ServerGeneratorCtrl extends ApiAbstractCtrl
{
    private $db;
    private $orchestrator;
    
    public function __construct(&$response, &$payload)
    {
        parent::__construct($response, $payload);
        $this->db = DB::getInstance();
        $this->orchestrator = new WorldOrchestratorService();
    }
    
    public function create()
    {
        try {
            if (empty($this->payload['world_key'])) {
                return $this->error(400, 'Missing world_key', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['world_name'])) {
                return $this->error(400, 'Missing world_name', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['spawn_preset_key'])) {
                return $this->error(400, 'Missing spawn_preset_key', 'INVALID_INPUT');
            }
            
            $config = [
                'world_key' => $this->payload['world_key'],
                'world_name' => $this->payload['world_name'],
                'database_name' => $this->payload['database_name'] ?? 's1_' . $this->payload['world_key'],
                'speed' => $this->payload['speed'] ?? 1.0,
                'spawn_preset_key' => $this->payload['spawn_preset_key'],
                'placement_algorithm' => $this->payload['placement_algorithm'] ?? 'quadrant_balanced',
                'max_npcs' => $this->payload['max_npcs'] ?? 250
            ];
            
            $result = $this->orchestrator->createWorld($config);
            
            $this->success($result, 'World created successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'WORLD_CREATION_FAILED');
        }
    }
    
    public function preview()
    {
        try {
            if (empty($this->payload['spawn_preset_key'])) {
                return $this->error(400, 'Missing spawn_preset_key', 'INVALID_INPUT');
            }
            
            $config = [
                'placement_algorithm' => $this->payload['placement_algorithm'] ?? 'quadrant_balanced'
            ];
            
            $result = $this->orchestrator->previewSpawnPlan(
                $this->payload['spawn_preset_key'],
                $config
            );
            
            $this->success($result, 'Spawn plan preview generated');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'PREVIEW_FAILED');
        }
    }
    
    public function listWorlds()
    {
        try {
            $filters = [];
            
            if (!empty($this->payload['status'])) {
                $filters['status'] = $this->payload['status'];
            }
            
            $worlds = $this->orchestrator->listWorlds($filters);
            
            $this->success(['worlds' => $worlds], 'Worlds retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'LIST_FAILED');
        }
    }
    
    public function getStatistics()
    {
        try {
            if (empty($this->payload['worldId'])) {
                return $this->error(400, 'Missing worldId', 'INVALID_INPUT');
            }
            
            $worldId = (int)$this->payload['worldId'];
            if ($worldId <= 0) {
                return $this->error(400, 'Invalid worldId', 'INVALID_INPUT');
            }
            
            $stats = $this->orchestrator->getWorldStatistics($worldId);
            
            $this->success($stats, 'World statistics retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'STATISTICS_FAILED');
        }
    }
    
    public function deleteWorld()
    {
        try {
            if (empty($this->payload['worldId'])) {
                return $this->error(400, 'Missing worldId', 'INVALID_INPUT');
            }
            
            $worldId = (int)$this->payload['worldId'];
            if ($worldId <= 0) {
                return $this->error(400, 'Invalid worldId', 'INVALID_INPUT');
            }
            
            $result = $this->orchestrator->deleteWorld($worldId);
            
            $this->success($result, 'World deleted successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'DELETE_FAILED');
        }
    }
}
