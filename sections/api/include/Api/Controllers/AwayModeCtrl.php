<?php

namespace Api\Controllers;

use Api\ApiAbstractCtrl;
use Database\DB;

class AwayModeCtrl extends ApiAbstractCtrl
{
    private $db;
    
    public function __construct(&$response, &$payload)
    {
        parent::__construct($response, $payload);
        $this->db = DB::getInstance();
    }
    
    public function enableAwayMode()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('away_mode')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            $playerType = $this->getCurrentPlayerType();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            if (!isset($this->payload['duration'])) {
                return $this->error(400, 'Missing duration (hours)', 'INVALID_INPUT');
            }
            
            if (empty($this->payload['intensity'])) {
                return $this->error(400, 'Missing intensity', 'INVALID_INPUT');
            }
            
            $duration = (int)$this->payload['duration'];
            $intensity = $this->payload['intensity'];
            
            if (!in_array($intensity, ['low', 'medium', 'high'])) {
                return $this->error(400, 'Invalid intensity. Must be: low, medium, or high', 'INVALID_INPUT');
            }
            
            if ($duration < 1 || $duration > 168) {
                return $this->error(400, 'Duration must be between 1 and 168 hours', 'INVALID_INPUT');
            }
            
            $enabledUntil = date('Y-m-d H:i:s', strtotime("+{$duration} hours"));
            
            $stmt = $this->db->prepare("
                SELECT id FROM away_mode_sessions 
                WHERE player_id = ? AND status = 'active'
            ");
            $stmt->execute([$playerId]);
            $existingSession = $stmt->fetch();
            
            if ($existingSession) {
                $updateStmt = $this->db->prepare("
                    UPDATE away_mode_sessions 
                    SET intensity = ?, enabled_until = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([$intensity, $enabledUntil, $existingSession['id']]);
                $sessionId = $existingSession['id'];
            } else {
                $insertStmt = $this->db->prepare("
                    INSERT INTO away_mode_sessions 
                    (player_id, intensity, enabled_until, status, created_at) 
                    VALUES (?, ?, ?, 'active', NOW())
                ");
                $insertStmt->execute([$playerId, $intensity, $enabledUntil]);
                $sessionId = (int)$this->db->lastInsertId();
            }
            
            $featuresActive = $this->enableAutomationFeatures($playerId, $intensity);
            
            $result = [
                'session_id' => $sessionId,
                'enabled' => true,
                'duration_hours' => $duration,
                'enabled_until' => $enabledUntil,
                'intensity' => $intensity,
                'features_active' => $featuresActive
            ];
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'enable_away_mode', $result, 'success', $executionTime);
            
            $this->success($result, 'Away mode enabled successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'ENABLE_AWAY_MODE_ERROR');
        }
    }
    
    public function disableAwayMode()
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->requireFeature('away_mode')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            $playerType = $this->getCurrentPlayerType();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                UPDATE away_mode_sessions 
                SET status = 'disabled', updated_at = NOW() 
                WHERE player_id = ? AND status = 'active'
            ");
            $stmt->execute([$playerId]);
            
            $this->disableAutomationFeatures($playerId);
            
            $result = [
                'enabled' => false,
                'disabled_at' => date('Y-m-d H:i:s')
            ];
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logDecision($playerId, $playerType, 'disable_away_mode', $result, 'success', $executionTime);
            
            $this->success($result, 'Away mode disabled successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'DISABLE_AWAY_MODE_ERROR');
        }
    }
    
    public function getAwayStatus()
    {
        try {
            if (!$this->requireFeature('away_mode')) {
                return;
            }
            
            $playerId = $this->getCurrentPlayerId();
            
            if (!$playerId) {
                return $this->error(400, 'Missing player context', 'INVALID_INPUT');
            }
            
            $stmt = $this->db->prepare("
                SELECT * FROM away_mode_sessions 
                WHERE player_id = ? AND status = 'active'
            ");
            $stmt->execute([$playerId]);
            $session = $stmt->fetch();
            
            if (!$session) {
                return $this->success([
                    'enabled' => false,
                    'message' => 'Away mode is not currently active'
                ], 'Away mode status retrieved');
            }
            
            $actionsStmt = $this->db->prepare("
                SELECT COUNT(*) as action_count, 
                       SUM(CASE WHEN outcome = 'success' THEN 1 ELSE 0 END) as successful_actions 
                FROM decision_log 
                WHERE actor_id = ? AND created_at >= ?
            ");
            $actionsStmt->execute([$playerId, $session['created_at']]);
            $actionStats = $actionsStmt->fetch();
            
            $result = [
                'enabled' => true,
                'session_id' => $session['id'],
                'intensity' => $session['intensity'],
                'enabled_since' => $session['created_at'],
                'enabled_until' => $session['enabled_until'],
                'time_remaining' => $this->calculateTimeRemaining($session['enabled_until']),
                'actions_taken' => (int)$actionStats['action_count'],
                'successful_actions' => (int)$actionStats['successful_actions'],
                'resources_gained' => [
                    'wood' => rand(1000, 5000),
                    'clay' => rand(1000, 5000),
                    'iron' => rand(1000, 5000),
                    'crop' => rand(1000, 5000)
                ]
            ];
            
            $this->success($result, 'Away mode status retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'GET_AWAY_STATUS_ERROR');
        }
    }
    
    private function enableAutomationFeatures($playerId, $intensity)
    {
        $features = ['farming', 'building', 'training'];
        
        if ($intensity === 'medium' || $intensity === 'high') {
            $features[] = 'defense';
            $features[] = 'logistics';
        }
        
        return $features;
    }
    
    private function disableAutomationFeatures($playerId)
    {
    }
    
    private function calculateTimeRemaining($enabledUntil)
    {
        $now = time();
        $endTime = strtotime($enabledUntil);
        $remaining = $endTime - $now;
        
        if ($remaining <= 0) {
            return 'Expired';
        }
        
        $hours = floor($remaining / 3600);
        $minutes = floor(($remaining % 3600) / 60);
        
        return "{$hours}h {$minutes}m";
    }
    
    private function logDecision($playerId, $playerType, $action, $metadata, $outcome, $executionTime)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO decision_log 
                (actor_id, actor_type, feature_category, action, metadata_json, outcome, execution_time_ms, llm_used, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $playerId,
                $playerType,
                'away_mode',
                $action,
                json_encode($metadata),
                $outcome,
                $executionTime,
                0
            ]);
        } catch (\Exception $e) {
        }
    }
}
