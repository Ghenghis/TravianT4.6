<?php

namespace Api\Controllers;

use Api\ApiAbstractCtrl;
use Database\DB;
use Helpers\RedisCache;

class MonitoringCtrl extends ApiAbstractCtrl
{
    private $db;
    private $cache;
    
    public function __construct(&$response, &$payload)
    {
        parent::__construct($response, $payload);
        $this->db = DB::getInstance();
        $this->cache = RedisCache::getInstance();
    }
    
    public function getSystemMetrics()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as active_npcs 
                FROM players 
                WHERE player_type = 'npc' AND is_active = 1
            ");
            $stmt->execute();
            $activeNpcs = $stmt->fetch()['active_npcs'];
            
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as pending_batches 
                FROM spawn_batches 
                WHERE status = 'pending'
            ");
            $stmt->execute();
            $pendingBatches = $stmt->fetch()['pending_batches'];
            
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as features_enabled 
                FROM feature_flags 
                WHERE enabled = 1 AND scope = 'server'
            ");
            $stmt->execute();
            $featuresEnabled = $stmt->fetch()['features_enabled'];
            
            $cacheStatus = $this->cache->isAvailable() ? 'connected' : 'disconnected';
            
            $stmt = $this->db->prepare("
                SELECT 
                    AVG(execution_time_ms) as avg_decision_time_ms,
                    COUNT(*) as total_decisions
                FROM decision_log 
                WHERE created_at >= NOW() - INTERVAL 1 HOUR
            ");
            $stmt->execute();
            $performance = $stmt->fetch();
            
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as automation_actions_24h 
                FROM decision_log 
                WHERE created_at >= NOW() - INTERVAL 24 HOUR
            ");
            $stmt->execute();
            $automationActions = $stmt->fetch()['automation_actions_24h'];
            
            $this->success([
                'active_npcs' => (int)$activeNpcs,
                'pending_batches' => (int)$pendingBatches,
                'features_enabled' => (int)$featuresEnabled,
                'cache_status' => $cacheStatus,
                'performance' => [
                    'avg_decision_time_ms' => $performance['avg_decision_time_ms'] ? round((float)$performance['avg_decision_time_ms'], 2) : 0,
                    'decisions_last_hour' => (int)$performance['total_decisions'],
                    'automation_actions_24h' => (int)$automationActions
                ]
            ], 'System metrics retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'GET_METRICS_FAILED');
        }
    }
    
    public function getDecisionLog()
    {
        try {
            $where = [];
            $params = [];
            
            if (!empty($this->payload['actor_type'])) {
                $validActorTypes = ['rule_based', 'llm'];
                if (!in_array($this->payload['actor_type'], $validActorTypes)) {
                    return $this->error(400, 'Invalid actor_type', 'INVALID_INPUT');
                }
                $where[] = "actor_type = ?";
                $params[] = $this->payload['actor_type'];
            }
            
            if (!empty($this->payload['feature_category'])) {
                $where[] = "feature_category = ?";
                $params[] = $this->payload['feature_category'];
            }
            
            if (!empty($this->payload['outcome'])) {
                $validOutcomes = ['executed', 'skipped', 'error'];
                if (!in_array($this->payload['outcome'], $validOutcomes)) {
                    return $this->error(400, 'Invalid outcome', 'INVALID_INPUT');
                }
                $where[] = "outcome = ?";
                $params[] = $this->payload['outcome'];
            }
            
            $limit = isset($this->payload['limit']) ? (int)$this->payload['limit'] : 1000;
            $offset = isset($this->payload['offset']) ? (int)$this->payload['offset'] : 0;
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $stmt = $this->db->prepare("
                SELECT * FROM decision_log 
                {$whereClause}
                ORDER BY created_at DESC 
                LIMIT {$limit} OFFSET {$offset}
            ");
            $stmt->execute($params);
            $decisions = $stmt->fetchAll();
            
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total FROM decision_log {$whereClause}
            ");
            $stmt->execute($params);
            $total = $stmt->fetch()['total'];
            
            foreach ($decisions as &$decision) {
                if (!empty($decision['decision_payload'])) {
                    $decision['payload'] = is_string($decision['decision_payload']) 
                        ? json_decode($decision['decision_payload'], true) 
                        : $decision['decision_payload'];
                }
            }
            
            $this->success([
                'decisions' => $decisions,
                'pagination' => [
                    'total' => (int)$total,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ], 'Decision log retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'GET_DECISION_LOG_FAILED');
        }
    }
    
    public function getAuditLog()
    {
        try {
            $limit = isset($this->payload['limit']) ? (int)$this->payload['limit'] : 500;
            $offset = isset($this->payload['offset']) ? (int)$this->payload['offset'] : 0;
            
            $stmt = $this->db->prepare("
                SELECT * FROM audit_log 
                ORDER BY created_at DESC 
                LIMIT {$limit} OFFSET {$offset}
            ");
            $stmt->execute();
            $auditLog = $stmt->fetchAll();
            
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM audit_log");
            $stmt->execute();
            $total = $stmt->fetch()['total'];
            
            foreach ($auditLog as &$log) {
                if (!empty($log['payload_json'])) {
                    $log['payload'] = is_string($log['payload_json']) 
                        ? json_decode($log['payload_json'], true) 
                        : $log['payload_json'];
                }
            }
            
            $this->success([
                'audit_log' => $auditLog,
                'pagination' => [
                    'total' => (int)$total,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ], 'Audit log retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'GET_AUDIT_LOG_FAILED');
        }
    }
    
    public function getPerformanceMetrics()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_decisions,
                    AVG(execution_time_ms) as avg_time_ms,
                    MIN(execution_time_ms) as min_time_ms,
                    MAX(execution_time_ms) as max_time_ms,
                    SUM(CASE WHEN outcome = 'executed' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN outcome = 'error' THEN 1 ELSE 0 END) as errors
                FROM decision_log
                WHERE created_at >= NOW() - INTERVAL 24 HOUR
            ");
            $stmt->execute();
            $metrics = $stmt->fetch();
            
            $stmt = $this->db->prepare("
                SELECT execution_time_ms 
                FROM decision_log 
                WHERE created_at >= NOW() - INTERVAL 24 HOUR 
                ORDER BY execution_time_ms DESC
            ");
            $stmt->execute();
            $times = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            $p95 = 0;
            $p99 = 0;
            if (count($times) > 0) {
                $p95Index = (int)(count($times) * 0.05);
                $p99Index = (int)(count($times) * 0.01);
                $p95 = $times[$p95Index] ?? 0;
                $p99 = $times[$p99Index] ?? 0;
            }
            
            $successRate = $metrics['total_decisions'] > 0 
                ? round(($metrics['successful'] / $metrics['total_decisions']) * 100, 2) 
                : 0;
            
            $this->success([
                'total_decisions_24h' => (int)$metrics['total_decisions'],
                'avg_time_ms' => $metrics['avg_time_ms'] ? round((float)$metrics['avg_time_ms'], 2) : 0,
                'min_time_ms' => (float)$metrics['min_time_ms'],
                'max_time_ms' => (float)$metrics['max_time_ms'],
                'p95_time_ms' => (float)$p95,
                'p99_time_ms' => (float)$p99,
                'success_rate' => $successRate,
                'successful_decisions' => (int)$metrics['successful'],
                'error_count' => (int)$metrics['errors']
            ], 'Performance metrics retrieved successfully');
            
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage(), 'GET_PERFORMANCE_FAILED');
        }
    }
}
