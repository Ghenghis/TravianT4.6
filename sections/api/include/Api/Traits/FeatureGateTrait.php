<?php

namespace Api\Traits;

use Services\FeatureGateService;

/**
 * FeatureGateTrait
 * 
 * Mixin trait for API controllers to easily check feature flags.
 * Automatically extracts player context from payload.
 * 
 * Usage:
 * ```php
 * class FarmingCtrl extends ApiAbstractCtrl {
 *     public function executeFarmlist() {
 *         if (!$this->requireFeature('farming')) {
 *             return; // Error already set
 *         }
 *         
 *         // Execute farming logic...
 *     }
 * }
 * ```
 */
trait FeatureGateTrait
{
    private $featureGateService;
    
    /**
     * Initialize feature gate service
     *
     * @return void
     */
    protected function initFeatureGate()
    {
        $this->featureGateService = new FeatureGateService();
    }
    
    /**
     * Check if a feature is enabled
     *
     * @param string $featureKey Feature key
     * @param int|null $playerId Override player ID (uses current player if null)
     * @return bool True if enabled, false otherwise
     */
    protected function checkFeature($featureKey, $playerId = null)
    {
        if ($playerId === null) {
            $playerId = $this->getCurrentPlayerId();
        }
        
        $playerType = $this->getCurrentPlayerType();
        
        return $this->featureGateService->isEnabled($featureKey, $playerId, $playerType);
    }
    
    /**
     * Require a feature to be enabled, set error if not
     *
     * @param string $featureKey Feature key
     * @param string|null $errorMessage Custom error message
     * @return bool True if enabled, false if disabled (error set)
     */
    protected function requireFeature($featureKey, $errorMessage = null)
    {
        if ($this->checkFeature($featureKey)) {
            return true;
        }
        
        if ($errorMessage === null) {
            $errorMessage = "Feature '{$featureKey}' is not available";
        }
        
        $this->setError(403, $errorMessage, 'FEATURE_DISABLED');
        
        return false;
    }
    
    /**
     * Get all enabled features for current player
     *
     * @return array Array of enabled feature keys
     */
    protected function getEnabledFeatures()
    {
        $playerId = $this->getCurrentPlayerId();
        $playerType = $this->getCurrentPlayerType();
        
        return $this->featureGateService->getEnabledFeatures($playerId, $playerType);
    }
    
    /**
     * Get current player ID from payload
     *
     * @return int|null Player ID or null
     */
    protected function getCurrentPlayerId()
    {
        if (isset($this->payload['playerId'])) {
            return (int)$this->payload['playerId'];
        }
        
        if (isset($this->payload['player_id'])) {
            return (int)$this->payload['player_id'];
        }
        
        if (isset($this->payload['userId'])) {
            return (int)$this->payload['userId'];
        }
        
        return null;
    }
    
    /**
     * Get current player type from payload
     *
     * @return string Player type ('human' or 'npc')
     */
    protected function getCurrentPlayerType()
    {
        if (isset($this->payload['playerType'])) {
            return $this->payload['playerType'];
        }
        
        if (isset($this->payload['player_type'])) {
            return $this->payload['player_type'];
        }
        
        return 'human';
    }
    
    /**
     * Set error response
     *
     * @param int $code HTTP status code
     * @param string $message Error message
     * @param string|null $errorCode Error code
     * @return void
     */
    protected function setError($code, $message, $errorCode = null)
    {
        $this->response['status'] = 'error';
        $this->response['code'] = $code;
        $this->response['message'] = $message;
        
        if ($errorCode !== null) {
            $this->response['error_code'] = $errorCode;
        }
    }
}
