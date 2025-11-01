<?php

namespace Services;

use Database\DB;

/**
 * CollisionDetectorService
 * 
 * Validates spawn locations to prevent collisions with existing villages and NPCs.
 * Ensures minimum distance requirements are maintained between spawned entities.
 */
class CollisionDetectorService
{
    private $db;
    private $minDistanceBetweenNPCs = 3;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    /**
     * Check if a location is valid for spawning an NPC
     *
     * @param int $worldId World ID
     * @param int $x X coordinate
     * @param int $y Y coordinate
     * @return bool True if valid, false if collision detected
     */
    public function isLocationValid($worldId, $x, $y)
    {
        if ($this->hasVillageAt($x, $y)) {
            return false;
        }

        if ($this->hasNPCSpawnAt($worldId, $x, $y)) {
            return false;
        }

        if ($this->isTooCloseToOtherNPCs($worldId, $x, $y)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a village already exists at coordinates (uses Travian wdata table)
     *
     * @param int $x X coordinate
     * @param int $y Y coordinate
     * @return bool True if village exists
     */
    public function hasVillageAt($x, $y)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM wdata 
            WHERE x = ? AND y = ? AND occupied = 1
        ");
        $stmt->execute([$x, $y]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }

    /**
     * Check if an NPC spawn already exists at coordinates
     *
     * @param int $worldId World ID
     * @param int $x X coordinate
     * @param int $y Y coordinate
     * @return bool True if NPC spawn exists
     */
    public function hasNPCSpawnAt($worldId, $x, $y)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM world_npc_spawns 
            WHERE world_id = ? AND spawn_x = ? AND spawn_y = ?
        ");
        $stmt->execute([$worldId, $x, $y]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }

    /**
     * Check if location is too close to other NPCs (minimum distance check)
     *
     * @param int $worldId World ID
     * @param int $x X coordinate
     * @param int $y Y coordinate
     * @return bool True if too close, false if acceptable distance
     */
    public function isTooCloseToOtherNPCs($worldId, $x, $y)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM world_npc_spawns
            WHERE world_id = ?
            AND ABS(spawn_x - ?) <= ?
            AND ABS(spawn_y - ?) <= ?
        ");
        $stmt->execute([
            $worldId,
            $x,
            $this->minDistanceBetweenNPCs,
            $y,
            $this->minDistanceBetweenNPCs
        ]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }

    /**
     * Set minimum distance between NPCs
     *
     * @param int $distance Minimum distance in tiles
     * @return void
     */
    public function setMinDistance($distance)
    {
        $this->minDistanceBetweenNPCs = max(1, (int)$distance);
    }
}
