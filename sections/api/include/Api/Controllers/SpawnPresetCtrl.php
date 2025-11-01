<?php

namespace Api\Controllers;

use Api\ApiAbstractCtrl;
use Database\DB;

class SpawnPresetCtrl extends ApiAbstractCtrl
{
    private $db;
    
    public function __construct(&$response, &$payload)
    {
        parent::__construct($response, $payload);
        $this->db = DB::getInstance();
    }
    
    public function listPresets()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM spawn_presets 
                ORDER BY total_npcs ASC
            ");
            $stmt->execute();
            $presets = $stmt->fetchAll();
            
            foreach ($presets as &$preset) {
                if (!empty($preset['config_json'])) {
                    $preset['config'] = is_string($preset['config_json']) 
                        ? json_decode($preset['config_json'], true) 
                        : $preset['config_json'];
                }
            }
            
            $this->success(['presets' => $presets], 'Presets retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'LIST_PRESETS_FAILED');
        }
    }
    
    public function getPreset()
    {
        try {
            if (empty($this->payload['presetId'])) {
                return $this->error(400, 'Missing presetId', 'INVALID_INPUT');
            }
            
            $presetId = (int)$this->payload['presetId'];
            if ($presetId <= 0) {
                return $this->error(400, 'Invalid presetId', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                SELECT * FROM spawn_presets WHERE id = ?
            ");
            $stmt->execute([$presetId]);
            $preset = $stmt->fetch();
            
            if (!$preset) {
                return $this->error(404, 'Preset not found', 'NOT_FOUND');
            }
            
            if (!empty($preset['config_json'])) {
                $preset['config'] = is_string($preset['config_json']) 
                    ? json_decode($preset['config_json'], true) 
                    : $preset['config_json'];
            }
            
            $this->success($preset, 'Preset retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'GET_PRESET_FAILED');
        }
    }
    
    public function createPreset()
    {
        try {
            if (empty($this->payload['preset_key'])) {
                return $this->error(400, 'Missing preset_key', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['preset_name'])) {
                return $this->error(400, 'Missing preset_name', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['config_json'])) {
                return $this->error(400, 'Missing config_json', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['total_npcs'])) {
                return $this->error(400, 'Missing total_npcs', 'INVALID_INPUT');
            }
            
            $totalNpcs = (int)$this->payload['total_npcs'];
            if ($totalNpcs <= 0) {
                return $this->error(400, 'Invalid total_npcs', 'INVALID_INPUT');
            }
            
            $configJson = is_array($this->payload['config_json']) 
                ? json_encode($this->payload['config_json']) 
                : $this->payload['config_json'];
            
            $stmt = $this->db->prepare("
                INSERT INTO spawn_presets (
                    preset_key, name, description, 
                    config_json, total_npcs, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $this->payload['preset_key'],
                $this->payload['preset_name'],
                $this->payload['description'] ?? null,
                $configJson,
                $totalNpcs
            ]);
            
            $presetId = (int)$this->db->lastInsertId();
            
            $this->success([
                'preset_id' => $presetId,
                'preset_key' => $this->payload['preset_key']
            ], 'Preset created successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'CREATE_PRESET_FAILED');
        }
    }
    
    public function updatePreset()
    {
        try {
            if (empty($this->payload['presetId'])) {
                return $this->error(400, 'Missing presetId', 'INVALID_INPUT');
            }
            
            $presetId = (int)$this->payload['presetId'];
            if ($presetId <= 0) {
                return $this->error(400, 'Invalid presetId', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("SELECT id FROM spawn_presets WHERE id = ?");
            $stmt->execute([$presetId]);
            if (!$stmt->fetch()) {
                return $this->error(404, 'Preset not found', 'NOT_FOUND');
            }
            
            $updates = [];
            $params = [];
            
            if (isset($this->payload['preset_name'])) {
                $updates[] = "name = ?";
                $params[] = $this->payload['preset_name'];
            }
            
            if (isset($this->payload['description'])) {
                $updates[] = "description = ?";
                $params[] = $this->payload['description'];
            }
            
            if (isset($this->payload['config_json'])) {
                $updates[] = "config_json = ?";
                $configJson = is_array($this->payload['config_json']) 
                    ? json_encode($this->payload['config_json']) 
                    : $this->payload['config_json'];
                $params[] = $configJson;
            }
            
            if (isset($this->payload['total_npcs'])) {
                $updates[] = "total_npcs = ?";
                $params[] = (int)$this->payload['total_npcs'];
            }
            
            if (empty($updates)) {
                return $this->error(400, 'No fields to update', 'INVALID_INPUT');
            }
            
            $updates[] = "updated_at = NOW()";
            $params[] = $presetId;
            
            $sql = "UPDATE spawn_presets SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $this->success(['preset_id' => $presetId], 'Preset updated successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'UPDATE_PRESET_FAILED');
        }
    }
    
    public function deletePreset()
    {
        try {
            if (empty($this->payload['presetId'])) {
                return $this->error(400, 'Missing presetId', 'INVALID_INPUT');
            }
            
            $presetId = (int)$this->payload['presetId'];
            if ($presetId <= 0) {
                return $this->error(400, 'Invalid presetId', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM world_spawn_settings 
                WHERE spawn_preset_id = ?
            ");
            $stmt->execute([$presetId]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                return $this->error(400, 'Cannot delete preset: it is being used by ' . $result['count'] . ' world(s)', 'PRESET_IN_USE');
            }
            
            $stmt = $this->db->prepare("DELETE FROM spawn_presets WHERE id = ?");
            $stmt->execute([$presetId]);
            
            if ($stmt->rowCount() === 0) {
                return $this->error(404, 'Preset not found', 'NOT_FOUND');
            }
            
            $this->success(['preset_id' => $presetId], 'Preset deleted successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'DELETE_PRESET_FAILED');
        }
    }
}
