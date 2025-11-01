<?php

namespace Services;

use Database\DB;
use Helpers\RedisCache;

/**
 * FeatureGateService
 * 
 * 3-tier feature flag resolution system:
 * 1. Server-wide flags (feature_flags table, scope='server')
 * 2. Per-player overrides (players.settings_json)
 * 3. AI-specific configs (ai_configs.llm_bias_json)
 * 
 * Resolution Logic:
 * - Check server flag first
 * - If locked (e.g., ai_npcs), return immediately
 * - If server disabled, cascade to all (return false)
 * - Check player settings for disabled_features
 * - For NPCs, check AI config feature_overrides
 * - Default: enabled if server allows
 */
class FeatureGateService
{
    private $db;
    private $cache;
    private $cacheEnabled;
    
    const CACHE_TTL = 300;
    const CACHE_PREFIX_SERVER_FLAG = 'server_flag_';
    const CACHE_PREFIX_PLAYER_SETTINGS = 'player_settings_';
    const CACHE_PREFIX_AI_CONFIG = 'ai_config_';
    
    const SUPPORTED_FEATURES = [
        'farming',
        'building',
        'training',
        'defense',
        'logistics',
        'market',
        'away_mode',
        'ai_npcs',
        'ai_workers',
        'ai_llm'
    ];
    
    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->cache = RedisCache::getInstance();
        $this->cacheEnabled = $this->cache->isAvailable();
    }
    
    /**
     * Check if a feature is enabled for a specific player
     *
     * @param string $featureKey Feature key (farming, building, etc.)
     * @param int|null $playerId Player ID (null for server-wide check)
     * @param string $playerType Player type ('human' or 'npc')
     * @return bool True if enabled, false otherwise
     */
    public function isEnabled($featureKey, $playerId = null, $playerType = 'human')
    {
        if (!in_array($featureKey, self::SUPPORTED_FEATURES)) {
            error_log("Unknown feature key: {$featureKey}");
            return false;
        }
        
        $serverFlag = $this->getServerFlag($featureKey);
        
        if (!$serverFlag) {
            return false;
        }
        
        if ($serverFlag['is_locked'] == 1) {
            return (bool)$serverFlag['enabled'];
        }
        
        if (!$serverFlag['enabled']) {
            return false;
        }
        
        if ($playerId === null) {
            return true;
        }
        
        $playerSettings = $this->getPlayerSettings($playerId);
        
        if ($playerSettings && isset($playerSettings['disabled_features'])) {
            if (in_array($featureKey, $playerSettings['disabled_features'])) {
                return false;
            }
        }
        
        if ($playerType === 'npc') {
            $aiConfig = $this->getAIConfig($playerId);
            
            if ($aiConfig && isset($aiConfig['llm_bias_json'])) {
                $llmBias = is_string($aiConfig['llm_bias_json']) 
                    ? json_decode($aiConfig['llm_bias_json'], true) 
                    : $aiConfig['llm_bias_json'];
                
                if (isset($llmBias['feature_overrides'][$featureKey])) {
                    return (bool)$llmBias['feature_overrides'][$featureKey];
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get all enabled features for a player
     *
     * @param int|null $playerId Player ID
     * @param string $playerType Player type ('human' or 'npc')
     * @return array Array of enabled feature keys
     */
    public function getEnabledFeatures($playerId = null, $playerType = 'human')
    {
        $enabled = [];
        
        foreach (self::SUPPORTED_FEATURES as $feature) {
            if ($this->isEnabled($feature, $playerId, $playerType)) {
                $enabled[] = $feature;
            }
        }
        
        return $enabled;
    }
    
    /**
     * Toggle a server-wide feature flag
     *
     * @param string $featureKey Feature key
     * @param bool $enabled Enable or disable
     * @param int $adminId Admin performing the action
     * @return bool Success status
     */
    public function toggleServerFlag($featureKey, $enabled, $adminId)
    {
        if (!in_array($featureKey, self::SUPPORTED_FEATURES)) {
            return false;
        }
        
        $serverFlag = $this->getServerFlag($featureKey, false);
        
        if (!$serverFlag) {
            return false;
        }
        
        if ($serverFlag['is_locked'] == 1) {
            error_log("Attempted to toggle locked feature: {$featureKey}");
            return false;
        }
        
        try {
            $stmt = $this->db->prepare(
                "UPDATE feature_flags 
                SET enabled = :enabled, updated_by = :updated_by, updated_at = NOW() 
                WHERE flag_key = :flag_key AND scope = 'server'"
            );
            
            $stmt->execute([
                'enabled' => $enabled ? 1 : 0,
                'updated_by' => $adminId,
                'flag_key' => $featureKey
            ]);
            
            $this->clearCachedServerFlag($featureKey);
            
            $this->logAuditAction($adminId, 'toggle_feature_flag', $featureKey, [
                'enabled' => $enabled,
                'feature' => $featureKey
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Failed to toggle server flag: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update player automation settings
     *
     * @param int $playerId Player ID
     * @param array $settings Settings to update
     * @return bool Success status
     */
    public function updatePlayerSettings($playerId, $settings)
    {
        try {
            $currentSettings = $this->getPlayerSettings($playerId, false);
            
            if ($currentSettings === null) {
                $currentSettings = [];
            }
            
            $newSettings = array_merge($currentSettings, $settings);
            
            if (isset($settings['disabled_features'])) {
                $newSettings['disabled_features'] = array_values(
                    array_unique($settings['disabled_features'])
                );
            }
            
            $stmt = $this->db->prepare(
                "UPDATE players 
                SET settings_json = :settings, updated_at = NOW() 
                WHERE id = :player_id"
            );
            
            $stmt->execute([
                'settings' => json_encode($newSettings),
                'player_id' => $playerId
            ]);
            
            $this->clearCachedPlayerSettings($playerId);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Failed to update player settings: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear all feature gate caches
     *
     * @return void
     */
    public function clearCache()
    {
        if (!$this->cacheEnabled) {
            return;
        }
        
        foreach (self::SUPPORTED_FEATURES as $feature) {
            $this->clearCachedServerFlag($feature);
        }
    }
    
    /**
     * Get server-wide feature flag
     *
     * @param string $featureKey Feature key
     * @param bool $useCache Whether to use cache
     * @return array|null Flag data or null
     */
    private function getServerFlag($featureKey, $useCache = true)
    {
        $cacheKey = self::CACHE_PREFIX_SERVER_FLAG . $featureKey;
        
        if ($useCache && $this->cacheEnabled) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM feature_flags 
                WHERE flag_key = :flag_key AND scope = 'server' 
                LIMIT 1"
            );
            
            $stmt->execute(['flag_key' => $featureKey]);
            $flag = $stmt->fetch();
            
            if (!$flag) {
                return null;
            }
            
            if ($this->cacheEnabled) {
                $this->cache->set($cacheKey, $flag, self::CACHE_TTL);
            }
            
            return $flag;
            
        } catch (\Exception $e) {
            error_log("Failed to get server flag: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get player settings
     *
     * @param int $playerId Player ID
     * @param bool $useCache Whether to use cache
     * @return array|null Settings or null
     */
    private function getPlayerSettings($playerId, $useCache = true)
    {
        $cacheKey = self::CACHE_PREFIX_PLAYER_SETTINGS . $playerId;
        
        if ($useCache && $this->cacheEnabled) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT settings_json FROM players WHERE id = :player_id LIMIT 1"
            );
            
            $stmt->execute(['player_id' => $playerId]);
            $result = $stmt->fetch();
            
            if (!$result || !$result['settings_json']) {
                return null;
            }
            
            $settings = is_string($result['settings_json']) 
                ? json_decode($result['settings_json'], true) 
                : $result['settings_json'];
            
            if ($this->cacheEnabled) {
                $this->cache->set($cacheKey, $settings, self::CACHE_TTL);
            }
            
            return $settings;
            
        } catch (\Exception $e) {
            error_log("Failed to get player settings: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get AI configuration
     *
     * @param int $npcPlayerId NPC player ID
     * @param bool $useCache Whether to use cache
     * @return array|null AI config or null
     */
    private function getAIConfig($npcPlayerId, $useCache = true)
    {
        $cacheKey = self::CACHE_PREFIX_AI_CONFIG . $npcPlayerId;
        
        if ($useCache && $this->cacheEnabled) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM ai_configs WHERE npc_player_id = :npc_player_id LIMIT 1"
            );
            
            $stmt->execute(['npc_player_id' => $npcPlayerId]);
            $config = $stmt->fetch();
            
            if (!$config) {
                return null;
            }
            
            if ($this->cacheEnabled) {
                $this->cache->set($cacheKey, $config, self::CACHE_TTL);
            }
            
            return $config;
            
        } catch (\Exception $e) {
            error_log("Failed to get AI config: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Clear cached server flag
     *
     * @param string $featureKey Feature key
     * @return void
     */
    private function clearCachedServerFlag($featureKey)
    {
        if ($this->cacheEnabled) {
            $cacheKey = self::CACHE_PREFIX_SERVER_FLAG . $featureKey;
            $this->cache->delete($cacheKey);
        }
    }
    
    /**
     * Clear cached player settings
     *
     * @param int $playerId Player ID
     * @return void
     */
    private function clearCachedPlayerSettings($playerId)
    {
        if ($this->cacheEnabled) {
            $cacheKey = self::CACHE_PREFIX_PLAYER_SETTINGS . $playerId;
            $this->cache->delete($cacheKey);
        }
    }
    
    /**
     * Log audit action
     *
     * @param int $adminId Admin ID
     * @param string $action Action performed
     * @param string $target Target of action
     * @param array $payload Action details
     * @return void
     */
    private function logAuditAction($adminId, $action, $target, $payload)
    {
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $stmt = $this->db->prepare(
                "INSERT INTO audit_log 
                (admin_id, action, target, payload_json, ip_address, user_agent, created_at) 
                VALUES (:admin_id, :action, :target, :payload, :ip_address, :user_agent, NOW())"
            );
            
            $stmt->execute([
                'admin_id' => $adminId,
                'action' => $action,
                'target' => $target,
                'payload' => json_encode($payload),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent
            ]);
            
        } catch (\Exception $e) {
            error_log("Failed to log audit action: " . $e->getMessage());
        }
    }
}
