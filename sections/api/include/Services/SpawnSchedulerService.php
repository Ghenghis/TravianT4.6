<?php

namespace Services;

use Database\DB;

/**
 * SpawnSchedulerService
 * 
 * Executes NPC spawn batches (instant and progressive).
 * Coordinates MapPlacementService and NPCInitializerService.
 */
class SpawnSchedulerService
{
    private $db;
    private $mapPlacement;
    private $npcInitializer;

    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->mapPlacement = new MapPlacementService();
        $this->npcInitializer = new NPCInitializerService();
    }

    /**
     * Execute instant spawns (batch 0)
     *
     * @param int $worldId World ID
     * @param array $batch Batch configuration
     * @return array Execution result
     */
    public function executeInstantSpawns($worldId, $batch)
    {
        return $this->executeBatchInternal($worldId, $batch, 0);
    }

    /**
     * Schedule progressive spawn batches
     *
     * @param int $worldId World ID
     * @param array $batches Array of batch configurations
     * @return array Scheduling result
     */
    public function scheduleProgressiveSpawns($worldId, $batches)
    {
        $scheduled = [];
        
        foreach ($batches as $batchNumber => $batch) {
            if ($batchNumber === 0) {
                continue;
            }

            $scheduled[] = [
                'batch_number' => $batchNumber,
                'npcs_count' => count($batch['npcs']),
                'scheduled_at' => $batch['scheduled_at'],
                'status' => 'pending'
            ];
        }

        return [
            'success' => true,
            'total_batches' => count($scheduled),
            'batches' => $scheduled
        ];
    }

    /**
     * Execute a specific batch (called by worker or manual trigger)
     *
     * @param int $batchId Batch ID
     * @return array Execution result
     * @throws \Exception On execution failure
     */
    public function executeBatch($batchId)
    {
        $batch = $this->getBatchById($batchId);
        
        if (!$batch) {
            throw new \Exception("Batch not found: {$batchId}");
        }

        if ($batch['status'] !== 'pending') {
            throw new \Exception("Batch {$batchId} is not in pending status");
        }

        $this->updateBatchStatus($batchId, 'in_progress');

        try {
            $worldSpawnSettings = $this->getWorldSpawnSettings($batch['world_id']);
            
            $locations = $this->mapPlacement->generateSpawnLocations(
                $batch['world_id'],
                $batch['npcs_to_spawn'],
                $worldSpawnSettings['placement_algorithm'],
                [
                    'center_exclusion_radius' => $worldSpawnSettings['center_exclusion_radius'],
                    'max_spawn_radius' => $worldSpawnSettings['max_spawn_radius']
                ]
            );

            $spawnedCount = 0;
            $errors = [];

            $npcConfigs = $this->generateNPCConfigsForBatch($batch['npcs_to_spawn'], $batch['world_id']);

            foreach ($npcConfigs as $index => $npcConfig) {
                if (!isset($locations[$index])) {
                    $errors[] = "No location available for NPC index {$index}";
                    continue;
                }

                try {
                    $npcConfig['batch_id'] = $batchId;
                    $this->npcInitializer->createNPC($batch['world_id'], $npcConfig, $locations[$index]);
                    $spawnedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to spawn NPC {$index}: " . $e->getMessage();
                }
            }

            $this->updateBatchCompletion($batchId, $spawnedCount, $errors);

            return [
                'success' => true,
                'batch_id' => $batchId,
                'npcs_spawned' => $spawnedCount,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            $this->updateBatchStatus($batchId, 'failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute batch internally (used by instant spawns and manual execution)
     *
     * @param int $worldId World ID
     * @param array $batch Batch configuration
     * @param int $batchNumber Batch number
     * @return array Execution result
     */
    private function executeBatchInternal($worldId, $batch, $batchNumber)
    {
        $worldSpawnSettings = $this->getWorldSpawnSettings($worldId);
        
        $npcCount = count($batch['npcs']);
        
        $locations = $this->mapPlacement->generateSpawnLocations(
            $worldId,
            $npcCount,
            $worldSpawnSettings['placement_algorithm'],
            [
                'center_exclusion_radius' => $worldSpawnSettings['center_exclusion_radius'],
                'max_spawn_radius' => $worldSpawnSettings['max_spawn_radius']
            ]
        );

        $spawnedNPCs = [];
        $errors = [];

        foreach ($batch['npcs'] as $index => $npcConfig) {
            if (!isset($locations[$index])) {
                $errors[] = "No location available for NPC index {$index}";
                continue;
            }

            try {
                $npcConfig['batch_id'] = $batchNumber;
                $npc = $this->npcInitializer->createNPC($worldId, $npcConfig, $locations[$index]);
                $spawnedNPCs[] = $npc;
            } catch (\Exception $e) {
                $errors[] = "Failed to spawn NPC {$index}: " . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'npcs_spawned' => count($spawnedNPCs),
            'total_requested' => $npcCount,
            'errors' => $errors,
            'npcs' => $spawnedNPCs
        ];
    }

    /**
     * Get world spawn settings
     *
     * @param int $worldId World ID
     * @return array Spawn settings
     * @throws \Exception If settings not found
     */
    public function getWorldSpawnSettings($worldId)
    {
        $stmt = $this->db->prepare("
            SELECT 
                wss.*,
                sp.config_json as preset_config
            FROM world_spawn_settings wss
            LEFT JOIN spawn_presets sp ON wss.spawn_preset_id = sp.id
            WHERE wss.world_id = ?
        ");
        $stmt->execute([$worldId]);
        $settings = $stmt->fetch();

        if (!$settings) {
            throw new \Exception("World spawn settings not found for world {$worldId}");
        }

        if (isset($settings['preset_config'])) {
            $settings['preset_config'] = json_decode($settings['preset_config'], true);
        }

        if (isset($settings['override_config_json'])) {
            $settings['override_config'] = json_decode($settings['override_config_json'], true);
        }

        return $settings;
    }

    /**
     * Get batch by ID
     *
     * @param int $batchId Batch ID
     * @return array|null Batch data
     */
    private function getBatchById($batchId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM spawn_batches WHERE id = ?
        ");
        $stmt->execute([$batchId]);
        return $stmt->fetch();
    }

    /**
     * Update batch status
     *
     * @param int $batchId Batch ID
     * @param string $status New status
     * @param string|null $errorMessage Error message if failed
     * @return void
     */
    public function updateBatchStatus($batchId, $status, $errorMessage = null)
    {
        $stmt = $this->db->prepare("
            UPDATE spawn_batches 
            SET status = ?, 
                error_message = ?,
                started_at = CASE WHEN ? = 'in_progress' THEN NOW() ELSE started_at END
            WHERE id = ?
        ");
        $stmt->execute([$status, $errorMessage, $status, $batchId]);
    }

    /**
     * Update batch completion
     *
     * @param int $batchId Batch ID
     * @param int $spawnedCount Number spawned
     * @param array $errors Errors encountered
     * @return void
     */
    private function updateBatchCompletion($batchId, $spawnedCount, $errors)
    {
        $status = count($errors) > 0 ? 'completed' : 'completed';
        $errorMessage = count($errors) > 0 ? implode('; ', $errors) : null;

        $stmt = $this->db->prepare("
            UPDATE spawn_batches 
            SET status = ?,
                npcs_spawned = ?,
                error_message = ?,
                completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $spawnedCount, $errorMessage, $batchId]);
    }

    /**
     * Generate NPC configurations for a batch
     *
     * @param int $count Number of NPCs
     * @param int $worldId World ID
     * @return array Array of NPC configs
     */
    private function generateNPCConfigsForBatch($count, $worldId)
    {
        $worldSettings = $this->getWorldSpawnSettings($worldId);
        $presetConfig = $worldSettings['preset_config'] ?? [];

        $tribes = $this->parseDistribution($presetConfig['tribe_distribution'] ?? []);
        $difficulties = $this->parseDistribution($presetConfig['difficulty_distribution'] ?? []);
        $personalities = $this->parseDistribution($presetConfig['personality_distribution'] ?? []);

        $configs = [];
        for ($i = 0; $i < $count; $i++) {
            $configs[] = [
                'tribe' => $this->selectFromDistribution($tribes),
                'difficulty' => $this->selectFromDistribution($difficulties),
                'personality' => $this->selectFromDistribution($personalities)
            ];
        }

        return $configs;
    }

    /**
     * Parse distribution percentages into weighted array
     *
     * @param array $distribution Distribution data
     * @return array Weighted array
     */
    private function parseDistribution($distribution)
    {
        if (empty($distribution)) {
            return [];
        }

        $weighted = [];
        foreach ($distribution as $key => $percentage) {
            for ($i = 0; $i < $percentage; $i++) {
                $weighted[] = $key;
            }
        }
        return $weighted;
    }

    /**
     * Select random item from weighted distribution
     *
     * @param array $distribution Weighted array
     * @return mixed Selected item
     */
    private function selectFromDistribution($distribution)
    {
        if (empty($distribution)) {
            return null;
        }
        return $distribution[array_rand($distribution)];
    }
}
