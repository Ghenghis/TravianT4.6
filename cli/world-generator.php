#!/usr/bin/env php
<?php
/**
 * World Generator CLI Tool
 * 
 * Interactive world creator with auto-NPC spawning
 * 
 * Usage:
 *   php cli/world-generator.php                                  # Interactive mode
 *   php cli/world-generator.php --world-key=ts1 --preset=medium  # CLI args
 *   php cli/world-generator.php --list-presets                   # List presets
 *   php cli/world-generator.php --preview                        # Dry run
 */

require_once __DIR__ . '/../sections/api/include/bootstrap.php';

use Services\WorldOrchestratorService;
use Database\DB;

class WorldGeneratorCLI
{
    private $orchestrator;
    private $db;
    
    public function __construct()
    {
        $this->orchestrator = new WorldOrchestratorService();
        $this->db = DB::getInstance();
    }
    
    public function run($args)
    {
        $this->printHeader();
        
        if (isset($args['list-presets'])) {
            $this->listPresets();
            return;
        }
        
        $config = $this->getConfiguration($args);
        
        if (isset($args['preview']) || !empty($config['preview'])) {
            $this->previewSpawnPlan($config);
            return;
        }
        
        if (!$this->confirmCreation($config)) {
            $this->output("World creation cancelled.", 'warning');
            return;
        }
        
        $this->output("\nCreating world...", 'info');
        
        try {
            $result = $this->orchestrator->createWorld($config);
            $this->displayResult($result);
        } catch (\Exception $e) {
            $this->output("\n✗ World Creation Failed", 'error');
            $this->output("Error: " . $e->getMessage(), 'error');
        }
    }
    
    private function getConfiguration($args)
    {
        if (!empty($args['world-key']) && !empty($args['preset'])) {
            return [
                'world_key' => $args['world-key'],
                'world_name' => $args['world-name'] ?? $args['world-key'],
                'database_name' => $args['database'] ?? 's1_' . strtolower($args['world-key']),
                'speed' => (float)($args['speed'] ?? 1.0),
                'spawn_preset_key' => $args['preset'],
                'placement_algorithm' => $args['algorithm'] ?? 'quadrant_balanced',
                'preview' => isset($args['preview'])
            ];
        }
        
        $this->output("\n=== World Configuration ===\n", 'info');
        
        $config = [
            'world_key' => $this->prompt("World Key (e.g., ts1, ts2)"),
            'world_name' => $this->prompt("World Name (e.g., Test World 1)"),
            'database_name' => '',
            'speed' => (float)$this->prompt("Game Speed (default: 1.0)", '1.0'),
            'spawn_preset_key' => $this->selectPreset(),
            'placement_algorithm' => $this->selectAlgorithm()
        ];
        
        $config['database_name'] = 's1_' . strtolower($config['world_key']);
        
        return $config;
    }
    
    private function listPresets()
    {
        $presets = $this->db->query("SELECT * FROM spawn_presets ORDER BY total_npcs");
        
        $this->output("\n=== Available Spawn Presets ===\n", 'success');
        
        foreach ($presets as $preset) {
            $config = json_decode($preset['config_json'], true);
            $this->output(sprintf(
                "  [%s] %s (%d NPCs)\n    %s",
                $preset['preset_key'],
                $preset['preset_name'],
                $preset['total_npcs'],
                $config['description'] ?? 'No description'
            ));
        }
    }
    
    private function selectPreset()
    {
        $presets = $this->db->query("SELECT preset_key, preset_name, total_npcs FROM spawn_presets ORDER BY total_npcs");
        
        $this->output("\nAvailable Presets:", 'info');
        foreach ($presets as $i => $preset) {
            $this->output(sprintf("  %d) %s (%d NPCs)", $i + 1, $preset['preset_name'], $preset['total_npcs']));
        }
        
        $choice = (int)$this->prompt("Select preset (1-" . count($presets) . ")");
        return $presets[$choice - 1]['preset_key'] ?? 'medium';
    }
    
    private function selectAlgorithm()
    {
        $algorithms = [
            'quadrant_balanced' => 'Quadrant Balanced (even distribution across 4 quadrants)',
            'random_scatter' => 'Random Scatter (random across map with center exclusion)',
            'kingdom_clustering' => 'Kingdom Clustering (groups of 15 NPCs)'
        ];
        
        $this->output("\nPlacement Algorithms:", 'info');
        $keys = array_keys($algorithms);
        $i = 1;
        foreach ($algorithms as $key => $desc) {
            $this->output(sprintf("  %d) %s", $i, $desc));
            $i++;
        }
        
        $choice = (int)$this->prompt("Select algorithm (1-3)", '1');
        return $keys[$choice - 1] ?? 'quadrant_balanced';
    }
    
    private function previewSpawnPlan($config)
    {
        $this->output("\n=== Spawn Plan Preview ===\n", 'info');
        
        try {
            $preview = $this->orchestrator->previewSpawnPlan($config['spawn_preset_key'], $config);
            
            $this->output(sprintf("Preset: %s", $preview['preset_name']));
            $this->output(sprintf("Total NPCs: %d", $preview['total_npcs']));
            $this->output(sprintf("Instant Spawn: %d NPCs", $preview['instant_spawns']));
            $this->output(sprintf("Progressive Batches: %d", $preview['progressive_batches']));
            
            if (!empty($preview['batches_breakdown'])) {
                $this->output("\nBatch Breakdown:", 'info');
                foreach ($preview['batches_breakdown'] as $batch) {
                    $type = $batch['type'] === 'instant' ? 'Instant' : sprintf('Day %d', $batch['days_offset']);
                    $this->output(sprintf("  Batch %d (%s): %d NPCs", 
                        $batch['batch_number'], 
                        $type, 
                        $batch['npcs_count']
                    ));
                }
            }
        } catch (\Exception $e) {
            $this->output("Error: " . $e->getMessage(), 'error');
        }
    }
    
    private function confirmCreation($config)
    {
        $this->output("\n=== Configuration Summary ===", 'info');
        $this->output(sprintf("World Key: %s", $config['world_key']));
        $this->output(sprintf("World Name: %s", $config['world_name']));
        $this->output(sprintf("Speed: %.1fx", $config['speed']));
        $this->output(sprintf("Spawn Preset: %s", $config['spawn_preset_key']));
        $this->output(sprintf("Algorithm: %s", $config['placement_algorithm']));
        
        $response = $this->prompt("\nCreate world with this configuration? (yes/no)", 'yes');
        return strtolower($response) === 'yes' || strtolower($response) === 'y';
    }
    
    private function displayResult($result)
    {
        if ($result['success']) {
            $this->output("\n✓ World Created Successfully!", 'success');
            $this->output(sprintf("  World ID: %d", $result['world_id']));
            $this->output(sprintf("  World Key: %s", $result['world_key']));
            $this->output(sprintf("  NPCs Spawned: %d/%d", $result['instant_spawns'], $result['total_npcs_planned']));
            $this->output(sprintf("  Progressive Batches: %d scheduled", $result['progressive_batches']));
        } else {
            $this->output("\n✗ World Creation Failed", 'error');
            $this->output("Error: " . ($result['error'] ?? 'Unknown error'), 'error');
        }
    }
    
    private function printHeader()
    {
        $this->output("\n╔════════════════════════════════════════╗", 'info');
        $this->output("║  Travian T4.6 - World Generator CLI   ║", 'info');
        $this->output("║  AI-NPC Auto-Spawning System           ║", 'info');
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

$cli = new WorldGeneratorCLI();
$cli->run($args);
