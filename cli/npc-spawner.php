#!/usr/bin/env php
<?php
/**
 * NPC Spawner CLI Tool
 * 
 * Standalone NPC spawner for testing
 * 
 * Usage:
 *   php cli/npc-spawner.php                                    # Interactive
 *   php cli/npc-spawner.php --world-id=1 --count=10            # Batch spawn
 *   php cli/npc-spawner.php --world-id=1 --tribe=romans        # Single NPC
 */

require_once __DIR__ . '/../sections/api/include/bootstrap.php';

use Services\NPCInitializerService;
use Services\MapPlacementService;
use Database\DB;

class NPCSpawnerCLI
{
    private $npcInitializer;
    private $mapPlacement;
    private $db;
    
    public function __construct()
    {
        $this->npcInitializer = new NPCInitializerService();
        $this->mapPlacement = new MapPlacementService();
        $this->db = DB::getInstance();
    }
    
    public function run($args)
    {
        $this->printHeader();
        
        $config = $this->getConfiguration($args);
        
        if (!$this->validateWorld($config['world_id'])) {
            $this->output("Error: World ID {$config['world_id']} does not exist", 'error');
            return;
        }
        
        $locations = $this->getLocations($config);
        
        if (empty($locations)) {
            $this->output("Error: Could not generate spawn locations", 'error');
            return;
        }
        
        $this->spawnNPCs($config, $locations);
    }
    
    private function getConfiguration($args)
    {
        if (!empty($args['world-id'])) {
            $count = (int)($args['count'] ?? 1);
            
            return [
                'world_id' => (int)$args['world-id'],
                'count' => $count,
                'tribe' => $args['tribe'] ?? 'random',
                'difficulty' => $args['difficulty'] ?? 'random',
                'personality' => $args['personality'] ?? 'random',
                'auto_place' => isset($args['auto-place']),
                'algorithm' => $args['algorithm'] ?? 'random_scatter',
                'x' => isset($args['x']) ? (int)$args['x'] : null,
                'y' => isset($args['y']) ? (int)$args['y'] : null
            ];
        }
        
        $this->output("\n=== NPC Spawn Configuration ===\n", 'info');
        
        return [
            'world_id' => (int)$this->prompt("World ID"),
            'count' => (int)$this->prompt("Number of NPCs to spawn", '1'),
            'tribe' => $this->selectTribe(),
            'difficulty' => $this->selectDifficulty(),
            'personality' => $this->selectPersonality(),
            'auto_place' => true,
            'algorithm' => 'random_scatter'
        ];
    }
    
    private function getLocations($config)
    {
        if ($config['auto_place']) {
            $this->output("\nGenerating spawn locations...", 'info');
            $settings = ['center_exclusion_radius' => 50, 'max_spawn_radius' => 400];
            
            try {
                return $this->mapPlacement->generateSpawnLocations(
                    $config['world_id'],
                    $config['count'],
                    $config['algorithm'],
                    $settings
                );
            } catch (\Exception $e) {
                $this->output("Error generating locations: " . $e->getMessage(), 'error');
                return [];
            }
        }
        
        if ($config['x'] !== null && $config['y'] !== null) {
            return [['x' => $config['x'], 'y' => $config['y']]];
        }
        
        $x = (int)$this->prompt("X coordinate");
        $y = (int)$this->prompt("Y coordinate");
        return [['x' => $x, 'y' => $y]];
    }
    
    private function spawnNPCs($config, $locations)
    {
        $this->output(sprintf("\nSpawning %d NPC(s)...\n", $config['count']), 'info');
        
        $spawned = 0;
        $errors = 0;
        
        for ($i = 0; $i < $config['count']; $i++) {
            try {
                $location = $locations[$i] ?? $locations[0];
                
                $npcConfig = [
                    'tribe' => $this->randomValue(['romans', 'gauls', 'teutons'], $config['tribe']),
                    'difficulty' => $this->randomValue(['easy', 'medium', 'hard', 'expert'], $config['difficulty']),
                    'personality' => $this->randomValue(['aggressive', 'economic', 'balanced', 'diplomat', 'assassin'], $config['personality'])
                ];
                
                $result = $this->npcInitializer->createNPC($config['world_id'], $npcConfig, $location);
                
                $this->output(sprintf(
                    "  ✓ NPC #%d created (ID: %d, Name: %s, Tribe: %s, Difficulty: %s, Location: %d,%d)",
                    $i + 1,
                    $result['npc_id'],
                    $result['name'],
                    $npcConfig['tribe'],
                    $npcConfig['difficulty'],
                    $location['x'],
                    $location['y']
                ), 'success');
                
                $spawned++;
                
            } catch (\Exception $e) {
                $this->output(sprintf("  ✗ NPC #%d failed: %s", $i + 1, $e->getMessage()), 'error');
                $errors++;
            }
        }
        
        $this->output(sprintf("\n✓ Spawn Complete: %d/%d NPCs created", $spawned, $config['count']), $spawned > 0 ? 'success' : 'error');
        if ($errors > 0) {
            $this->output(sprintf("  Errors: %d", $errors), 'warning');
        }
    }
    
    private function validateWorld($worldId)
    {
        $result = $this->db->query("SELECT id FROM worlds WHERE id = ? LIMIT 1", [$worldId]);
        return !empty($result);
    }
    
    private function selectTribe()
    {
        $tribes = ['romans', 'gauls', 'teutons', 'random'];
        $this->output("\nTribes: 1) Romans, 2) Gauls, 3) Teutons, 4) Random", 'info');
        $choice = (int)$this->prompt("Select tribe (1-4)", '4');
        return $tribes[$choice - 1] ?? 'random';
    }
    
    private function selectDifficulty()
    {
        $difficulties = ['easy', 'medium', 'hard', 'expert', 'random'];
        $this->output("\nDifficulties: 1) Easy, 2) Medium, 3) Hard, 4) Expert, 5) Random", 'info');
        $choice = (int)$this->prompt("Select difficulty (1-5)", '5');
        return $difficulties[$choice - 1] ?? 'random';
    }
    
    private function selectPersonality()
    {
        $personalities = ['aggressive', 'economic', 'balanced', 'diplomat', 'assassin', 'random'];
        $this->output("\nPersonalities: 1) Aggressive, 2) Economic, 3) Balanced, 4) Diplomat, 5) Assassin, 6) Random", 'info');
        $choice = (int)$this->prompt("Select personality (1-6)", '6');
        return $personalities[$choice - 1] ?? 'random';
    }
    
    private function randomValue($options, $value)
    {
        return $value === 'random' ? $options[array_rand($options)] : $value;
    }
    
    private function printHeader()
    {
        $this->output("\n╔════════════════════════════════════════╗", 'info');
        $this->output("║  Travian T4.6 - NPC Spawner CLI        ║", 'info');
        $this->output("║  Individual NPC Creation Tool          ║", 'info');
        $this->output("╚════════════════════════════════════════╝\n", 'info');
    }
    
    private function prompt($question, $default = null)
    {
        $prompt = $default ? "$question [$default]: " : "$question: ";
        echo $prompt;
        $input = trim(fgets(STDIN));
        return $input ?: $default;
    }
    
    private function output($message, $color = null)
    {
        $colors = [
            'success' => "\033[0;32m",
            'error' => "\033[0;31m",
            'warning' => "\033[0;33m",
            'info' => "\033[0;36m",
            'reset' => "\033[0m"
        ];
        
        $colorCode = $colors[$color] ?? '';
        $reset = $color ? $colors['reset'] : '';
        
        echo "$colorCode$message$reset\n";
    }
}

$args = [];
foreach ($argv as $arg) {
    if (strpos($arg, '--') === 0) {
        $parts = explode('=', substr($arg, 2), 2);
        $args[$parts[0]] = $parts[1] ?? true;
    }
}

$cli = new NPCSpawnerCLI();
$cli->run($args);
