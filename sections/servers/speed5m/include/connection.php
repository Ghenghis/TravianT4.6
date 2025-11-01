<?php
/** Speed 5000000x | Schema: speed5m */
global $connection;
$connection = [
    'speed' => '5000000', 'round_length' => '1', 'worldId' => 'speed5m', 'worldUniqueId' => '7',
    'title' => 'Speed Server 5000000x', 'serverName' => 'Speed 5000000x', 'version' => 'T4.6',
    'gameWorldUrl' => getenv('REPL_SLUG') ? 'https://' . getenv('REPL_SLUG') . '.' . getenv('REPL_OWNER') . '.repl.co/' : 'http://localhost:5000/',
    'secure_hash_code' => md5('speed5m_replit_2025'), 'auto_reinstall' => '0', 'auto_reinstall_start_after' => '86400', 'engine_filename' => 'speed5m.service',
    'database' => ['driver' => 'pgsql', 'hostname' => getenv('PGHOST'), 'port' => getenv('PGPORT'),
        'username' => getenv('PGUSER'), 'password' => getenv('PGPASSWORD'), 'database' => getenv('PGDATABASE'),
        'schema' => 'speed5m', 'charset' => 'utf8', 'sslmode' => 'require'],
];
