#!/usr/bin/env php
<?php
require_once __DIR__ . '/../../sections/api/include/bootstrap.php';

while (true) {
    echo "[" . date('Y-m-d H:i:s') . "] AI decision worker starting...\n";
    
    try {
        updateWorkerHeartbeat('ai_decision');
        require __DIR__ . '/../ai-decision-worker.php';
        echo "[" . date('Y-m-d H:i:s') . "] AI decision worker completed\n";
    } catch (\Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    }
    
    sleep(300);
}

function updateWorkerHeartbeat($workerName) {
    if (!getenv('REDIS_HOST')) return;
    
    try {
        $redis = new Redis();
        $redis->connect(getenv('REDIS_HOST'), (int)getenv('REDIS_PORT') ?: 6379);
        if (getenv('REDIS_PASSWORD')) {
            $redis->auth(getenv('REDIS_PASSWORD'));
        }
        $redis->setex("worker:heartbeat:{$workerName}", 600, time());
        $redis->close();
    } catch (\Exception $e) {
        error_log("Failed to update worker heartbeat: " . $e->getMessage());
    }
}
