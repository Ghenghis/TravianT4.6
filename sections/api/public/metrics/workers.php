<?php
/**
 * Worker Metrics Endpoint for Prometheus
 * Returns worker heartbeat and stats in Prometheus format
 */

require_once __DIR__ . '/../../include/bootstrap.php';

use Predis\Client;

header('Content-Type: text/plain; charset=utf-8');

try {
    $redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
    $redisPort = getenv('REDIS_PORT') ?: 6379;
    $redisPassword = getenv('REDIS_PASSWORD');
    
    $redisConfig = [
        'scheme' => 'tcp',
        'host'   => $redisHost,
        'port'   => $redisPort,
    ];
    
    if ($redisPassword) {
        $redisConfig['password'] = $redisPassword;
    }
    
    $redis = new Client($redisConfig);
    $redis->ping();
    
    $workers = [
        'automation-worker' => ['heartbeat_key' => 'worker:automation:heartbeat', 'threshold' => 700],
        'ai-decision-worker' => ['heartbeat_key' => 'worker:ai-decision:heartbeat', 'threshold' => 700],
        'spawn-scheduler-worker' => ['heartbeat_key' => 'worker:spawn-scheduler:heartbeat', 'threshold' => 2000],
    ];
    
    echo "# HELP worker_heartbeat_timestamp Unix timestamp of last worker heartbeat\n";
    echo "# TYPE worker_heartbeat_timestamp gauge\n";
    
    echo "# HELP worker_status Worker status (1=healthy, 0=unhealthy)\n";
    echo "# TYPE worker_status gauge\n";
    
    foreach ($workers as $name => $config) {
        $timestamp = $redis->get($config['heartbeat_key']);
        $status = 0;
        
        if ($timestamp !== false && $timestamp !== null) {
            $age = time() - (int)$timestamp;
            $status = ($age < $config['threshold']) ? 1 : 0;
            
            echo "worker_heartbeat_timestamp{worker=\"$name\"} $timestamp\n";
        } else {
            echo "worker_heartbeat_timestamp{worker=\"$name\"} 0\n";
        }
        
        echo "worker_status{worker=\"$name\"} $status\n";
    }
    
    // Get worker stats from Redis (if available)
    foreach ($workers as $name => $config) {
        $statsKey = str_replace(':heartbeat', ':stats', $config['heartbeat_key']);
        $stats = $redis->hgetall($statsKey);
        
        if ($stats && is_array($stats) && count($stats) > 0) {
            foreach ($stats as $metric => $value) {
                echo "worker_{$metric}{worker=\"$name\"} $value\n";
            }
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo "# ERROR: " . $e->getMessage() . "\n";
}
