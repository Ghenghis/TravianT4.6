<?php

namespace Services;

use Database\DB;

/**
 * MapPlacementService
 * 
 * Generates NPC spawn locations using various placement algorithms.
 * Ensures valid spawns using CollisionDetectorService.
 */
class MapPlacementService
{
    private $db;
    private $collisionDetector;

    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->collisionDetector = new CollisionDetectorService();
    }

    /**
     * Generate spawn locations based on algorithm
     *
     * @param int $worldId World ID
     * @param int $npcCount Number of NPCs to place
     * @param string $algorithm Algorithm to use
     * @param array $settings Placement settings
     * @return array Array of locations [{x, y}, ...]
     * @throws \Exception If algorithm not found
     */
    public function generateSpawnLocations($worldId, $npcCount, $algorithm, $settings = [])
    {
        $centerExclusionRadius = $settings['center_exclusion_radius'] ?? 50;
        $maxSpawnRadius = $settings['max_spawn_radius'] ?? 300;

        switch ($algorithm) {
            case 'quadrant_balanced':
                return $this->quadrantBalancedPlacement($worldId, $npcCount, $centerExclusionRadius, $maxSpawnRadius);
            
            case 'random_scatter':
                return $this->randomScatterPlacement($worldId, $npcCount, $centerExclusionRadius, $maxSpawnRadius);
            
            case 'kingdom_clustering':
                return $this->kingdomClusteringPlacement($worldId, $npcCount, $centerExclusionRadius, $maxSpawnRadius);
            
            default:
                throw new \Exception("Unknown placement algorithm: {$algorithm}");
        }
    }

    /**
     * Quadrant Balanced Placement
     * Divides NPCs evenly across 4 quadrants (NW, NE, SW, SE)
     *
     * @param int $worldId World ID
     * @param int $npcCount Total NPCs to spawn
     * @param int $centerExclusionRadius Exclusion radius around 0,0
     * @param int $maxSpawnRadius Maximum spawn radius
     * @return array Array of locations
     */
    private function quadrantBalancedPlacement($worldId, $npcCount, $centerExclusionRadius, $maxSpawnRadius)
    {
        $locations = [];
        $npcsPerQuadrant = ceil($npcCount / 4);
        
        $quadrants = [
            ['minX' => $centerExclusionRadius, 'maxX' => $maxSpawnRadius, 'minY' => $centerExclusionRadius, 'maxY' => $maxSpawnRadius],
            ['minX' => -$maxSpawnRadius, 'maxX' => -$centerExclusionRadius, 'minY' => $centerExclusionRadius, 'maxY' => $maxSpawnRadius],
            ['minX' => -$maxSpawnRadius, 'maxX' => -$centerExclusionRadius, 'minY' => -$maxSpawnRadius, 'maxY' => -$centerExclusionRadius],
            ['minX' => $centerExclusionRadius, 'maxX' => $maxSpawnRadius, 'minY' => -$maxSpawnRadius, 'maxY' => -$centerExclusionRadius],
        ];

        $placed = 0;
        foreach ($quadrants as $quadrant) {
            $quadrantPlaced = 0;
            $maxAttempts = $npcsPerQuadrant * 50;
            $attempts = 0;

            while ($quadrantPlaced < $npcsPerQuadrant && $placed < $npcCount && $attempts < $maxAttempts) {
                $x = rand($quadrant['minX'], $quadrant['maxX']);
                $y = rand($quadrant['minY'], $quadrant['maxY']);
                
                if ($this->collisionDetector->isLocationValid($worldId, $x, $y)) {
                    $locations[] = ['x' => $x, 'y' => $y];
                    $quadrantPlaced++;
                    $placed++;
                }
                $attempts++;
            }
        }

        return $locations;
    }

    /**
     * Random Scatter Placement
     * Random placement across map with center exclusion
     *
     * @param int $worldId World ID
     * @param int $npcCount Total NPCs to spawn
     * @param int $centerExclusionRadius Exclusion radius around 0,0
     * @param int $maxSpawnRadius Maximum spawn radius
     * @return array Array of locations
     */
    private function randomScatterPlacement($worldId, $npcCount, $centerExclusionRadius, $maxSpawnRadius)
    {
        $locations = [];
        $maxAttempts = $npcCount * 100;
        $attempts = 0;

        while (count($locations) < $npcCount && $attempts < $maxAttempts) {
            $x = rand(-$maxSpawnRadius, $maxSpawnRadius);
            $y = rand(-$maxSpawnRadius, $maxSpawnRadius);
            
            $distanceFromCenter = sqrt($x * $x + $y * $y);
            
            if ($distanceFromCenter < $centerExclusionRadius) {
                $attempts++;
                continue;
            }
            
            if ($this->collisionDetector->isLocationValid($worldId, $x, $y)) {
                $locations[] = ['x' => $x, 'y' => $y];
            }
            $attempts++;
        }

        return $locations;
    }

    /**
     * Kingdom Clustering Placement
     * Groups NPCs into kingdoms of ~15 NPCs each
     *
     * @param int $worldId World ID
     * @param int $npcCount Total NPCs to spawn
     * @param int $centerExclusionRadius Exclusion radius around 0,0
     * @param int $maxSpawnRadius Maximum spawn radius
     * @return array Array of locations
     */
    private function kingdomClusteringPlacement($worldId, $npcCount, $centerExclusionRadius, $maxSpawnRadius)
    {
        $locations = [];
        $npcsPerKingdom = 15;
        $kingdomCount = ceil($npcCount / $npcsPerKingdom);
        $clusterRadius = 20;

        $kingdomCenters = $this->generateKingdomCenters(
            $kingdomCount,
            $centerExclusionRadius,
            $maxSpawnRadius
        );

        foreach ($kingdomCenters as $center) {
            $kingdomSize = min($npcsPerKingdom, $npcCount - count($locations));
            $kingdomLocations = $this->placeNPCsAroundCenter(
                $worldId,
                $center['x'],
                $center['y'],
                $kingdomSize,
                $clusterRadius
            );
            
            $locations = array_merge($locations, $kingdomLocations);
            
            if (count($locations) >= $npcCount) {
                break;
            }
        }

        return array_slice($locations, 0, $npcCount);
    }

    /**
     * Generate kingdom center points
     *
     * @param int $count Number of kingdoms
     * @param int $minRadius Minimum radius from center
     * @param int $maxRadius Maximum radius from center
     * @return array Array of center points
     */
    private function generateKingdomCenters($count, $minRadius, $maxRadius)
    {
        $centers = [];
        $minDistanceBetweenKingdoms = 80;
        $maxAttempts = $count * 50;
        $attempts = 0;

        while (count($centers) < $count && $attempts < $maxAttempts) {
            $x = rand(-$maxRadius, $maxRadius);
            $y = rand(-$maxRadius, $maxRadius);
            
            $distanceFromCenter = sqrt($x * $x + $y * $y);
            
            if ($distanceFromCenter < $minRadius) {
                $attempts++;
                continue;
            }

            $validCenter = true;
            foreach ($centers as $existingCenter) {
                $distance = sqrt(
                    pow($x - $existingCenter['x'], 2) + 
                    pow($y - $existingCenter['y'], 2)
                );
                
                if ($distance < $minDistanceBetweenKingdoms) {
                    $validCenter = false;
                    break;
                }
            }

            if ($validCenter) {
                $centers[] = ['x' => $x, 'y' => $y];
            }
            $attempts++;
        }

        return $centers;
    }

    /**
     * Place NPCs around a kingdom center
     *
     * @param int $worldId World ID
     * @param int $centerX Center X coordinate
     * @param int $centerY Center Y coordinate
     * @param int $count Number of NPCs to place
     * @param int $radius Cluster radius
     * @return array Array of locations
     */
    private function placeNPCsAroundCenter($worldId, $centerX, $centerY, $count, $radius)
    {
        $locations = [];
        $maxAttempts = $count * 100;
        $attempts = 0;

        while (count($locations) < $count && $attempts < $maxAttempts) {
            $offsetX = rand(-$radius, $radius);
            $offsetY = rand(-$radius, $radius);
            
            $x = $centerX + $offsetX;
            $y = $centerY + $offsetY;
            
            if ($this->collisionDetector->isLocationValid($worldId, $x, $y)) {
                $locations[] = ['x' => $x, 'y' => $y];
            }
            $attempts++;
        }

        return $locations;
    }
}
