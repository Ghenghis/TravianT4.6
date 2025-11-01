<?php
/**
 * Speed 10000x World Connection Configuration (PostgreSQL)
 * Speed: 10000x | Schema: speed10k
 */

global $connection;
$connection = [
    'speed' => '10000',
    'round_length' => '1',
    'worldId' => 'speed10k',
    'worldUniqueId' => '3',
    'title' => 'Speed Server 10000x',
    'serverName' => 'Speed 10000x',
    'version' => 'T4.6',
    'gameWorldUrl' => getenv('REPL_SLUG') ? 'https://' . getenv('REPL_SLUG') . '.' . getenv('REPL_OWNER') . '.repl.co/' : 'http://localhost:5000/',
    'secure_hash_code' => md5('speed10k_replit_2025'),
    'auto_reinstall' => '0',
    'auto_reinstall_start_after' => '86400',
    'engine_filename' => 'speed10k.service',
    
    'database' => [
        'driver' => 'pgsql',
        'hostname' => getenv('PGHOST'),
        'port' => getenv('PGPORT'),
        'username' => getenv('PGUSER'),
        'password' => getenv('PGPASSWORD'),
        'database' => getenv('PGDATABASE'),
        'schema' => 'speed10k',
        'charset' => 'utf8',
        'sslmode' => 'require',
    ],
];
