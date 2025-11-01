#!/usr/bin/env php
<?php
/**
 * Worker Health Check
 * Verifies worker heartbeat in Redis
 * 
 * Usage: worker-healthcheck.php <worker_name> [max_age_seconds]
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: worker-healthcheck.php <worker_name> [max_age_seconds]\n");
    exit(1);
}

$workerName = $argv[1];
$maxAge = isset($argv[2]) ? (int)$argv[2] : 600;

$redisHost = getenv('REDIS_HOST') ?: 'redis';
$redisPort = (int)(getenv('REDIS_PORT') ?: 6379);
$redisPassword = getenv('REDIS_PASSWORD');

try {
    $redis = new Redis();
    $redis->connect($redisHost, $redisPort, 3);
    
    if ($redisPassword) {
        $redis->auth($redisPassword);
    }
    
    $lastHeartbeat = $redis->get("worker:heartbeat:{$workerName}");
    $redis->close();
    
    if ($lastHeartbeat === false) {
        fwrite(STDERR, "Worker {$workerName} has no heartbeat\n");
        exit(1);
    }
    
    $age = time() - (int)$lastHeartbeat;
    if ($age > $maxAge) {
        fwrite(STDERR, "Worker {$workerName} heartbeat is stale ({$age}s old, max {$maxAge}s)\n");
        exit(1);
    }
    
    exit(0);
    
} catch (\Exception $e) {
    fwrite(STDERR, "Worker health check failed: " . $e->getMessage() . "\n");
    exit(1);
}
