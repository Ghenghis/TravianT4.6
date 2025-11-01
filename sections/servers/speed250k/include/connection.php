<?php
/** Speed 250000x | Schema: speed250k */
global $connection;
$connection = [
    'speed' => '250000', 'round_length' => '1', 'worldId' => 'speed250k', 'worldUniqueId' => '5',
    'title' => 'Speed Server 250000x', 'serverName' => 'Speed 250000x', 'version' => 'T4.6',
    'gameWorldUrl' => getenv('REPL_SLUG') ? 'https://' . getenv('REPL_SLUG') . '.' . getenv('REPL_OWNER') . '.repl.co/' : 'http://localhost:5000/',
    'secure_hash_code' => md5('speed250k_replit_2025'), 'auto_reinstall' => '0', 'auto_reinstall_start_after' => '86400', 'engine_filename' => 'speed250k.service',
    'database' => ['driver' => 'pgsql', 'hostname' => getenv('PGHOST'), 'port' => getenv('PGPORT'),
        'username' => getenv('PGUSER'), 'password' => getenv('PGPASSWORD'), 'database' => getenv('PGDATABASE'),
        'schema' => 'speed250k', 'charset' => 'utf8', 'sslmode' => 'require'],
];
