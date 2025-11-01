<?php
/**
 * Test World Connection Configuration (PostgreSQL)
 * Speed: 100x | Schema: testworld
 */

global $connection;
$connection = [
    // Game Settings
    'speed' => '100',
    'round_length' => '365',
    'worldId' => 'testworld',
    'worldUniqueId' => '1',
    'title' => 'Test Server 100x',
    'serverName' => 'Test Server',
    'version' => 'T4.6',
    'gameWorldUrl' => getenv('REPL_SLUG') ? 'https://' . getenv('REPL_SLUG') . '.' . getenv('REPL_OWNER') . '.repl.co/' : 'http://localhost:5000/',
    'secure_hash_code' => md5('testworld_replit_2025'),
    'auto_reinstall' => '0',
    'auto_reinstall_start_after' => '86400',
    'engine_filename' => 'testworld.service',
    
    // MySQL Configuration for Game World (Docker)
    'database' => [
        'driver' => 'mysql',
        'hostname' => getenv('MYSQL_HOST') ?: 'mysql',
        'port' => getenv('MYSQL_PORT') ?: 3306,
        'username' => getenv('MYSQL_USER') ?: 'travian_user',
        'password' => getenv('MYSQL_PASSWORD'),
        'database' => 'travian_testworld',
        'charset' => 'utf8mb4',
    ],
];
