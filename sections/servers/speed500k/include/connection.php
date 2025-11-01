<?php
/** Speed 500000x | Schema: speed500k */
global $connection;
$connection = [
    'speed' => '500000', 'round_length' => '1', 'worldId' => 'speed500k', 'worldUniqueId' => '6',
    'title' => 'Speed Server 500000x', 'serverName' => 'Speed 500000x', 'version' => 'T4.6',
    'gameWorldUrl' => getenv('REPL_SLUG') ? 'https://' . getenv('REPL_SLUG') . '.' . getenv('REPL_OWNER') . '.repl.co/' : 'http://localhost:5000/',
    'secure_hash_code' => md5('speed500k_replit_2025'), 'auto_reinstall' => '0', 'auto_reinstall_start_after' => '86400', 'engine_filename' => 'speed500k.service',
    'database' => ['driver' => 'pgsql', 'hostname' => getenv('PGHOST'), 'port' => getenv('PGPORT'),
        'username' => getenv('PGUSER'), 'password' => getenv('PGPASSWORD'), 'database' => getenv('PGDATABASE'),
        'schema' => 'speed500k', 'charset' => 'utf8', 'sslmode' => 'require'],
];
