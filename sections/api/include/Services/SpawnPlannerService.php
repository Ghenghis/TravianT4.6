<?php

namespace Services;

use Database\DB;

/**
 * SpawnPlannerService
 * 
 * Translates spawn presets into spawn batches with NPC distributions.
 * Generates instant and progressive spawn schedules.
 */
class SpawnPlannerService
{
    private $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    /**
     * Create spawn plan from preset
     *
     * @param int $worldId World ID
     * @param array $spawnPreset Spawn preset configuration
     * @param array $config Additional configuration
     * @param bool $dryRun If true, don't save to database
     * @return array Spawn plan
     */
    public function createSpawnPlan($worldId, $spawnPreset, $config = [], $dryRun = false)
    {
        $presetConfig = is_string($spawnPreset['config_json']) 
            ? json_decode($spawnPreset['config_json'], true) 
            : $spawnPreset['config_json'];

        $totalNPCs = $presetConfig['total_npcs'] ?? 0;
        
        $instantCount = $this->calculateInstantSpawnCount($presetConfig);
        
        $progressiveBatches = $this->calculateProgressiveBatches($presetConfig, $totalNPCs - $instantCount);
        
        $npcDistributions = $this->generateNPCDistributions($totalNPCs, $presetConfig);
        
        $batches = $this->organizeBatches($instantCount, $progressiveBatches, $npcDistributions);

        if (!$dryRun) {
            $this->saveBatchSchedules($worldId, $batches);
        }

        return [
            'success' => true,
            'total_npcs' => $totalNPCs,
            'instant_spawns' => $instantCount,
            'progressive_batches' => count($progressiveBatches),
            'batches' => $batches
        ];
    }

    /**
     * Calculate instant spawn count
     *
     * @param array $config Preset configuration
     * @return int Instant spawn count
     */
    public function calculateInstantSpawnCount($config)
    {
        $spawnTiming = $config['spawn_timing'] ?? [];
        return $spawnTiming['instant'] ?? 0;
    }

    /**
     * Calculate progressive batches
     *
     * @param array $config Preset configuration
     * @param int $remaining Remaining NPCs after instant spawn
     * @return array Progressive batches
     */
    public function calculateProgressiveBatches($config, $remaining)
    {
        $spawnTiming = $config['spawn_timing'] ?? [];
        $progressive = $spawnTiming['progressive'] ?? [];

        $batches = [];
        $batchNumber = 1;

        foreach ($progressive as $dayKey => $count) {
            if ($count <= 0 || $remaining <= 0) {
                continue;
            }

            $actualCount = min($count, $remaining);
            $days = (int)str_replace('day_', '', $dayKey);

            $batches[] = [
                'batch_number' => $batchNumber,
                'npcs_count' => $actualCount,
                'days_offset' => $days,
                'scheduled_at' => $this->calculateScheduledAt($days)
            ];

            $remaining -= $actualCount;
            $batchNumber++;
        }

        return $batches;
    }

    /**
     * Calculate scheduled timestamp
     *
     * @param int $daysOffset Days from now
     * @return string Timestamp
     */
    private function calculateScheduledAt($daysOffset)
    {
        return date('Y-m-d H:i:s', strtotime("+{$daysOffset} days"));
    }

    /**
     * Generate NPC distributions (tribes, difficulties, personalities)
     *
     * @param int $totalNpcs Total NPCs to distribute
     * @param array $config Preset configuration
     * @return array Array of NPC configurations
     */
    public function generateNPCDistributions($totalNpcs, $config)
    {
        $tribeDistribution = $this->parseDistribution($config['tribe_distribution'] ?? []);
        $difficultyDistribution = $this->parseDistribution($config['difficulty_distribution'] ?? []);
        $personalityDistribution = $this->parseDistribution($config['personality_distribution'] ?? []);

        $npcs = [];
        for ($i = 0; $i < $totalNpcs; $i++) {
            $npcs[] = [
                'tribe' => $this->selectFromDistribution($tribeDistribution),
                'difficulty' => $this->selectFromDistribution($difficultyDistribution),
                'personality' => $this->selectFromDistribution($personalityDistribution)
            ];
        }

        return $npcs;
    }

    /**
     * Parse distribution percentages into weighted array
     *
     * @param array $distribution Distribution config [key => percentage]
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
    public function selectFromDistribution($distribution)
    {
        if (empty($distribution)) {
            return null;
        }
        return $distribution[array_rand($distribution)];
    }

    /**
     * Organize NPCs into batches
     *
     * @param int $instantCount Instant spawn count
     * @param array $progressiveBatches Progressive batch definitions
     * @param array $npcDistributions All NPC configurations
     * @return array Organized batches
     */
    private function organizeBatches($instantCount, $progressiveBatches, $npcDistributions)
    {
        $batches = [];
        $npcIndex = 0;

        if ($instantCount > 0) {
            $batches[0] = [
                'batch_number' => 0,
                'type' => 'instant',
                'npcs_count' => $instantCount,
                'scheduled_at' => date('Y-m-d H:i:s'),
                'npcs' => array_slice($npcDistributions, 0, $instantCount)
            ];
            $npcIndex = $instantCount;
        }

        foreach ($progressiveBatches as $batch) {
            $batches[$batch['batch_number']] = [
                'batch_number' => $batch['batch_number'],
                'type' => 'progressive',
                'npcs_count' => $batch['npcs_count'],
                'days_offset' => $batch['days_offset'],
                'scheduled_at' => $batch['scheduled_at'],
                'npcs' => array_slice($npcDistributions, $npcIndex, $batch['npcs_count'])
            ];
            $npcIndex += $batch['npcs_count'];
        }

        return $batches;
    }

    /**
     * Save batch schedules to database
     *
     * @param int $worldId World ID
     * @param array $batches Organized batches
     * @return void
     */
    public function saveBatchSchedules($worldId, $batches)
    {
        foreach ($batches as $batch) {
            $stmt = $this->db->prepare("
                INSERT INTO spawn_batches (
                    world_id,
                    batch_number,
                    npcs_to_spawn,
                    scheduled_at,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->execute([
                $worldId,
                $batch['batch_number'],
                $batch['npcs_count'],
                $batch['scheduled_at']
            ]);
        }
    }

    /**
     * Load spawn preset by key
     *
     * @param string $presetKey Preset key
     * @return array|null Preset data
     */
    public function loadSpawnPreset($presetKey)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM spawn_presets WHERE preset_key = ?
        ");
        $stmt->execute([$presetKey]);
        return $stmt->fetch();
    }

    /**
     * Get all available presets
     *
     * @return array Array of presets
     */
    public function getAllPresets()
    {
        $stmt = $this->db->query("
            SELECT id, name, preset_key, total_npcs, is_default, is_system
            FROM spawn_presets
            ORDER BY total_npcs ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Preview spawn plan without saving
     *
     * @param string $presetKey Preset key
     * @param array $config Additional configuration
     * @return array Preview data
     */
    public function previewSpawnPlan($presetKey, $config = [])
    {
        $preset = $this->loadSpawnPreset($presetKey);
        
        if (!$preset) {
            throw new \Exception("Spawn preset not found: {$presetKey}");
        }

        return $this->createSpawnPlan(0, $preset, $config, true);
    }

    /**
     * Get batch statistics for a world
     *
     * @param int $worldId World ID
     * @return array Batch statistics
     */
    public function getBatchStatistics($worldId)
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_batches,
                SUM(npcs_to_spawn) as total_planned,
                SUM(npcs_spawned) as total_spawned,
                SUM(CASE WHEN status = 'pending' THEN npcs_to_spawn ELSE 0 END) as pending_spawns,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_batches,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_batches
            FROM spawn_batches
            WHERE world_id = ?
        ");
        $stmt->execute([$worldId]);
        return $stmt->fetch();
    }
}
