<?php
/**
 * Demo World Connection Configuration (PostgreSQL)
 * Speed: 5x | Schema: demo
 */

global $connection;
$connection = [
    'speed' => '5',
    'round_length' => '180',
    'worldId' => 'demo',
    'worldUniqueId' => '2',
    'title' => 'Demo Server 5x',
    'serverName' => 'Demo Server',
    'version' => 'T4.6',
    'gameWorldUrl' => getenv('REPL_SLUG') ? 'https://' . getenv('REPL_SLUG') . '.' . getenv('REPL_OWNER') . '.repl.co/' : 'http://localhost:5000/',
    'secure_hash_code' => md5('demo_replit_2025'),
    'auto_reinstall' => '0',
    'auto_reinstall_start_after' => '86400',
    'engine_filename' => 'demo.service',
    
    // MySQL Configuration for Game World (Docker)
    'database' => [
        'driver' => 'mysql',
        'hostname' => getenv('MYSQL_HOST') ?: 'mysql',
        'port' => getenv('MYSQL_PORT') ?: 3306,
        'username' => getenv('MYSQL_USER') ?: 'travian_user',
        'password' => getenv('MYSQL_PASSWORD'),
        'database' => 'travian_demo',
        'charset' => 'utf8mb4',
    ],
];
