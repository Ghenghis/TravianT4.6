<?php

namespace Services;

use Database\DB;

/**
 * WorldOrchestratorService
 * 
 * Coordinates the entire world creation pipeline.
 * Orchestrates SpawnPlannerService and SpawnSchedulerService.
 */
class WorldOrchestratorService
{
    private $db;
    private $spawnPlanner;
    private $spawnScheduler;

    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->spawnPlanner = new SpawnPlannerService();
        $this->spawnScheduler = new SpawnSchedulerService();
    }

    /**
     * Create a complete world with NPC spawns
     *
     * @param array $config World configuration
     * @return array Creation result
     * @throws \Exception On creation failure
     */
    public function createWorld($config)
    {
        $this->validateConfig($config);

        $this->db->beginTransaction();
        
        try {
            $worldId = $this->createWorldRecord($config);
            
            $spawnPreset = $this->loadSpawnPreset($config['spawn_preset_key']);
            
            if (!$spawnPreset) {
                throw new \Exception("Spawn preset not found: " . $config['spawn_preset_key']);
            }

            $worldSpawnSettingsId = $this->createWorldSpawnSettings($worldId, $spawnPreset, $config);
            
            $spawnPlan = $this->spawnPlanner->createSpawnPlan($worldId, $spawnPreset, $config, false);
            
            $instantBatch = $spawnPlan['batches'][0] ?? null;
            $instantResult = null;
            
            if ($instantBatch && $instantBatch['npcs_count'] > 0) {
                $instantResult = $this->spawnScheduler->executeInstantSpawns($worldId, $instantBatch);
            }

            $progressiveBatches = array_filter($spawnPlan['batches'], function($batch) {
                return $batch['batch_number'] > 0;
            });

            $progressiveResult = $this->spawnScheduler->scheduleProgressiveSpawns($worldId, $progressiveBatches);

            $totalSpawned = $instantResult ? $instantResult['npcs_spawned'] : 0;
            
            $this->updateWorldStatus($worldId, 'active', $totalSpawned);

            $this->db->commit();

            return [
                'success' => true,
                'world_id' => $worldId,
                'world_key' => $config['world_key'],
                'world_name' => $config['world_name'],
                'total_npcs_planned' => $spawnPlan['total_npcs'],
                'instant_spawns' => $instantResult ? $instantResult['npcs_spawned'] : 0,
                'progressive_batches' => count($progressiveBatches),
                'progressive_npcs_scheduled' => array_sum(array_column($progressiveBatches, 'npcs_count')),
                'message' => "World '{$config['world_name']}' created successfully with {$totalSpawned} NPCs spawned instantly"
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw new \Exception("Failed to create world: " . $e->getMessage());
        }
    }

    /**
     * Preview spawn plan without creating world
     *
     * @param string $presetKey Preset key
     * @param array $config Additional configuration
     * @return array Preview data
     */
    public function previewSpawnPlan($presetKey, $config = [])
    {
        $spawnPreset = $this->loadSpawnPreset($presetKey);
        
        if (!$spawnPreset) {
            throw new \Exception("Spawn preset not found: {$presetKey}");
        }

        $spawnPlan = $this->spawnPlanner->createSpawnPlan(0, $spawnPreset, $config, true);

        $preview = [
            'success' => true,
            'preset_name' => $spawnPreset['name'],
            'preset_key' => $presetKey,
            'total_npcs' => $spawnPlan['total_npcs'],
            'instant_spawns' => $spawnPlan['instant_spawns'],
            'progressive_batches' => $spawnPlan['progressive_batches'],
            'batches_breakdown' => []
        ];

        foreach ($spawnPlan['batches'] as $batch) {
            $preview['batches_breakdown'][] = [
                'batch_number' => $batch['batch_number'],
                'type' => $batch['type'],
                'npcs_count' => $batch['npcs_count'],
                'scheduled_at' => $batch['scheduled_at'],
                'days_offset' => $batch['days_offset'] ?? 0
            ];
        }

        return $preview;
    }

    /**
     * Create world record in database
     *
     * @param array $config World configuration
     * @return int World ID
     */
    public function createWorldRecord($config)
    {
        $stmt = $this->db->prepare("
            INSERT INTO worlds (
                world_key,
                world_name,
                database_name,
                speed,
                max_npcs,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, 'initializing', NOW())
        ");

        $stmt->execute([
            $config['world_key'],
            $config['world_name'],
            $config['database_name'] ?? 's1_' . $config['world_key'],
            $config['speed'] ?? 1.0,
            $config['max_npcs'] ?? 250
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Load spawn preset by key
     *
     * @param string $presetKey Preset key
     * @return array|null Preset data
     */
    public function loadSpawnPreset($presetKey)
    {
        return $this->spawnPlanner->loadSpawnPreset($presetKey);
    }

    /**
     * Create world spawn settings
     *
     * @param int $worldId World ID
     * @param array $spawnPreset Spawn preset data
     * @param array $config World configuration
     * @return int World spawn settings ID
     */
    public function createWorldSpawnSettings($worldId, $spawnPreset, $config)
    {
        $stmt = $this->db->prepare("
            INSERT INTO world_spawn_settings (
                world_id,
                spawn_preset_id,
                placement_algorithm,
                center_exclusion_radius,
                max_spawn_radius,
                progressive_spawning,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $presetConfig = is_string($spawnPreset['config_json']) 
            ? json_decode($spawnPreset['config_json'], true) 
            : $spawnPreset['config_json'];

        $spawnZones = $presetConfig['spawn_zones'] ?? [];

        $stmt->execute([
            $worldId,
            $spawnPreset['id'],
            $config['placement_algorithm'] ?? 'quadrant_balanced',
            $config['center_exclusion_radius'] ?? $spawnZones['center_exclusion_radius'] ?? 50,
            $config['max_spawn_radius'] ?? $spawnZones['max_spawn_radius'] ?? 300,
            1
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update world status and spawn count
     *
     * @param int $worldId World ID
     * @param string $status New status
     * @param int $npcsSpawned NPCs spawned count
     * @return void
     */
    private function updateWorldStatus($worldId, $status, $npcsSpawned)
    {
        $stmt = $this->db->prepare("
            UPDATE worlds 
            SET status = ?,
                total_npcs_spawned = ?,
                started_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $npcsSpawned, $worldId]);
    }

    /**
     * Get world details
     *
     * @param int $worldId World ID
     * @return array|null World data
     */
    public function getWorld($worldId)
    {
        $stmt = $this->db->prepare("
            SELECT 
                w.*,
                sp.name as preset_name,
                sp.preset_key,
                wss.placement_algorithm,
                wss.center_exclusion_radius,
                wss.max_spawn_radius
            FROM worlds w
            LEFT JOIN world_spawn_settings wss ON w.id = wss.world_id
            LEFT JOIN spawn_presets sp ON wss.spawn_preset_id = sp.id
            WHERE w.id = ?
        ");
        $stmt->execute([$worldId]);
        return $stmt->fetch();
    }

    /**
     * Get world by key
     *
     * @param string $worldKey World key
     * @return array|null World data
     */
    public function getWorldByKey($worldKey)
    {
        $stmt = $this->db->prepare("
            SELECT 
                w.*,
                sp.name as preset_name,
                sp.preset_key,
                wss.placement_algorithm
            FROM worlds w
            LEFT JOIN world_spawn_settings wss ON w.id = wss.world_id
            LEFT JOIN spawn_presets sp ON wss.spawn_preset_id = sp.id
            WHERE w.world_key = ?
        ");
        $stmt->execute([$worldKey]);
        return $stmt->fetch();
    }

    /**
     * List all worlds
     *
     * @param array $filters Optional filters
     * @return array Array of worlds
     */
    public function listWorlds($filters = [])
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "w.status = ?";
            $params[] = $filters['status'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->db->prepare("
            SELECT 
                w.id,
                w.world_key,
                w.world_name,
                w.status,
                w.total_npcs_spawned,
                w.max_npcs,
                w.speed,
                w.created_at,
                w.started_at,
                sp.name as preset_name
            FROM worlds w
            LEFT JOIN world_spawn_settings wss ON w.id = wss.world_id
            LEFT JOIN spawn_presets sp ON wss.spawn_preset_id = sp.id
            {$whereClause}
            ORDER BY w.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get world statistics
     *
     * @param int $worldId World ID
     * @return array Statistics
     */
    public function getWorldStatistics($worldId)
    {
        $world = $this->getWorld($worldId);
        
        if (!$world) {
            throw new \Exception("World not found: {$worldId}");
        }

        $batchStats = $this->spawnPlanner->getBatchStatistics($worldId);

        $npcStats = $this->getNPCStatistics($worldId);

        return [
            'world_id' => $worldId,
            'world_key' => $world['world_key'],
            'world_name' => $world['world_name'],
            'status' => $world['status'],
            'total_npcs_spawned' => $world['total_npcs_spawned'],
            'max_npcs' => $world['max_npcs'],
            'batch_statistics' => $batchStats,
            'npc_statistics' => $npcStats
        ];
    }

    /**
     * Get NPC spawn statistics
     *
     * @param int $worldId World ID
     * @return array NPC statistics
     */
    private function getNPCStatistics($worldId)
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_npcs,
                SUM(CASE WHEN tribe = 'romans' THEN 1 ELSE 0 END) as romans,
                SUM(CASE WHEN tribe = 'gauls' THEN 1 ELSE 0 END) as gauls,
                SUM(CASE WHEN tribe = 'teutons' THEN 1 ELSE 0 END) as teutons,
                SUM(CASE WHEN spawn_method = 'instant' THEN 1 ELSE 0 END) as instant_spawns,
                SUM(CASE WHEN spawn_method = 'progressive' THEN 1 ELSE 0 END) as progressive_spawns
            FROM world_npc_spawns
            WHERE world_id = ?
        ");
        $stmt->execute([$worldId]);
        return $stmt->fetch();
    }

    /**
     * Validate world configuration
     *
     * @param array $config Configuration to validate
     * @return void
     * @throws \Exception If validation fails
     */
    private function validateConfig($config)
    {
        $required = ['world_key', 'world_name', 'spawn_preset_key'];
        
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        if (!preg_match('/^[a-z0-9_]+$/', $config['world_key'])) {
            throw new \Exception("Invalid world_key format. Use lowercase alphanumeric and underscores only.");
        }
    }

    /**
     * Delete a world and all associated data
     *
     * @param int $worldId World ID
     * @return array Deletion result
     * @throws \Exception On deletion failure
     */
    public function deleteWorld($worldId)
    {
        $this->db->beginTransaction();
        
        try {
            $stmt = $this->db->prepare("DELETE FROM world_npc_spawns WHERE world_id = ?");
            $stmt->execute([$worldId]);
            $npcSpawnsDeleted = $stmt->rowCount();

            $stmt = $this->db->prepare("DELETE FROM spawn_batches WHERE world_id = ?");
            $stmt->execute([$worldId]);
            $batchesDeleted = $stmt->rowCount();

            $stmt = $this->db->prepare("DELETE FROM world_spawn_settings WHERE world_id = ?");
            $stmt->execute([$worldId]);

            $stmt = $this->db->prepare("DELETE FROM worlds WHERE id = ?");
            $stmt->execute([$worldId]);

            $this->db->commit();

            return [
                'success' => true,
                'world_id' => $worldId,
                'npc_spawns_deleted' => $npcSpawnsDeleted,
                'batches_deleted' => $batchesDeleted
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw new \Exception("Failed to delete world: " . $e->getMessage());
        }
    }
}
