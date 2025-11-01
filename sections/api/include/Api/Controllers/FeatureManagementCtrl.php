<?php

namespace Api\Controllers;

use Api\ApiAbstractCtrl;
use Services\FeatureGateService;
use Database\DB;

class FeatureManagementCtrl extends ApiAbstractCtrl
{
    private $db;
    private $featureGate;
    
    public function __construct(&$response, &$payload)
    {
        parent::__construct($response, $payload);
        $this->db = DB::getInstance();
        $this->featureGate = new FeatureGateService();
    }
    
    public function listFeatures()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM feature_flags 
                WHERE scope = 'server'
                ORDER BY flag_key
            ");
            $stmt->execute();
            $features = $stmt->fetchAll();
            
            foreach ($features as &$feature) {
                if (!empty($feature['payload_json'])) {
                    $feature['payload'] = is_string($feature['payload_json']) 
                        ? json_decode($feature['payload_json'], true) 
                        : $feature['payload_json'];
                }
            }
            
            $this->success(['features' => $features], 'Feature flags retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'LIST_FEATURES_FAILED');
        }
    }
    
    public function getFeature()
    {
        try {
            if (empty($this->payload['flagKey'])) {
                return $this->error(400, 'Missing flagKey', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                SELECT * FROM feature_flags 
                WHERE flag_key = ? AND scope = 'server'
            ");
            $stmt->execute([$this->payload['flagKey']]);
            $feature = $stmt->fetch();
            
            if (!$feature) {
                return $this->error(404, 'Feature flag not found', 'NOT_FOUND');
            }
            
            if (!empty($feature['payload_json'])) {
                $feature['payload'] = is_string($feature['payload_json']) 
                    ? json_decode($feature['payload_json'], true) 
                    : $feature['payload_json'];
            }
            
            $this->success($feature, 'Feature flag retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'GET_FEATURE_FAILED');
        }
    }
    
    public function toggleFeature()
    {
        try {
            if (empty($this->payload['flag_key'])) {
                return $this->error(400, 'Missing flag_key', 'INVALID_INPUT');
            }
            
            if (!isset($this->payload['enabled'])) {
                return $this->error(400, 'Missing enabled field', 'INVALID_INPUT');
            }
            
            $enabled = (bool)$this->payload['enabled'];
            $adminId = $this->payload['adminId'] ?? 1;
            
            $result = $this->featureGate->toggleServerFlag(
                $this->payload['flag_key'],
                $enabled,
                $adminId
            );
            
            if (!$result) {
                return $this->error(400, 'Failed to toggle feature flag. It may be locked or not exist.', 'TOGGLE_FAILED');
            }
            
            $stmt = $this->db->prepare("
                SELECT * FROM feature_flags 
                WHERE flag_key = ? AND scope = 'server'
            ");
            $stmt->execute([$this->payload['flag_key']]);
            $feature = $stmt->fetch();
            
            $this->success($feature, 'Feature flag toggled successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'TOGGLE_FEATURE_FAILED');
        }
    }
    
    public function updateConfig()
    {
        try {
            if (empty($this->payload['flagKey'])) {
                return $this->error(400, 'Missing flagKey', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['payload_json'])) {
                return $this->error(400, 'Missing payload_json', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                SELECT is_locked FROM feature_flags 
                WHERE flag_key = ? AND scope = 'server'
            ");
            $stmt->execute([$this->payload['flagKey']]);
            $feature = $stmt->fetch();
            
            if (!$feature) {
                return $this->error(404, 'Feature flag not found', 'NOT_FOUND');
            }
            
            if ($feature['is_locked'] == 1) {
                return $this->error(400, 'Cannot update locked feature flag', 'LOCKED_FLAG');
            }
            
            $payloadJson = is_array($this->payload['payload_json']) 
                ? json_encode($this->payload['payload_json']) 
                : $this->payload['payload_json'];
            
            $stmt = $this->db->prepare("
                UPDATE feature_flags 
                SET payload_json = ?, updated_at = NOW() 
                WHERE flag_key = ? AND scope = 'server'
            ");
            $stmt->execute([$payloadJson, $this->payload['flagKey']]);
            
            $stmt = $this->db->prepare("
                SELECT * FROM feature_flags 
                WHERE flag_key = ? AND scope = 'server'
            ");
            $stmt->execute([$this->payload['flagKey']]);
            $updatedFeature = $stmt->fetch();
            
            $this->success($updatedFeature, 'Feature flag config updated successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'UPDATE_CONFIG_FAILED');
        }
    }
    
    public function resetDefaults()
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE feature_flags 
                SET enabled = 1, updated_at = NOW() 
                WHERE scope = 'server' AND is_locked = 0
            ");
            $stmt->execute();
            $resetCount = $stmt->rowCount();
            
            $this->featureGate->clearCache();
            
            $this->success([
                'reset_count' => $resetCount,
                'message' => "Reset {$resetCount} non-locked feature flags to default state (enabled)"
            ], 'Feature flags reset to defaults successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'RESET_DEFAULTS_FAILED');
        }
    }
}
