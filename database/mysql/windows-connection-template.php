<?php
/**
 * ============================================================================
 * TRAVIAN T4.6 - PER-WORLD DATABASE CONNECTION CONFIGURATION
 * ============================================================================
 * Version: 1.0
 * Purpose: Database connection settings for individual game world
 * Usage: This file is loaded by ServerDB::getInstance() to establish
 *        per-world database connections
 * 
 * TEMPLATE PLACEHOLDERS:
 * - {{DB_HOST}}     : MySQL host (e.g., localhost, mysql, 127.0.0.1)
 * - {{DB_NAME}}     : World database name (e.g., travian_world_speed10k)
 * - {{DB_USER}}     : MySQL username (e.g., travian)
 * - {{DB_PASSWORD}} : MySQL password
 * - {{WORLD_ID}}    : World identifier (e.g., speed10k, demo, dev)
 * - {{DRIVER}}      : Database driver (mysql or pgsql)
 * 
 * DEPLOYMENT STEPS:
 * 1. Copy this template for each game world
 * 2. Replace all {{PLACEHOLDERS}} with actual values
 * 3. Save to: sections/servers/<world_id>/config/connection.php
 * 4. Ensure file permissions are correct (readable by PHP process)
 * 5. Test connection using ServerDB::getInstance()
 * 
 * SECURITY NOTES:
 * - This file contains sensitive credentials - DO NOT commit to version control
 * - Add to .gitignore: sections/servers/*/config/connection.php
 * - Use environment variables for production deployments
 * - Restrict file permissions: chmod 600 connection.php
 * ============================================================================
 */

$connection = [
    'database' => [
        // Database Driver (mysql or pgsql)
        'driver' => '{{DRIVER}}',
        
        // MySQL Connection Settings
        'hostname' => '{{DB_HOST}}',
        'database' => '{{DB_NAME}}',
        'username' => '{{DB_USER}}',
        'password' => '{{DB_PASSWORD}}',
        
        // Optional: PostgreSQL-specific settings (if driver=pgsql)
        'port'     => 5432,          // PostgreSQL port
        'schema'   => '{{WORLD_ID}}', // Schema name for PostgreSQL isolation
        'sslmode'  => 'prefer',      // SSL mode: disable, allow, prefer, require
    ],
    
    // World Metadata
    'world' => [
        'id'   => '{{WORLD_ID}}',    // World identifier (speed10k, demo, etc.)
        'name' => 'Game World',      // Display name
        'type' => 'production',      // Environment: production, staging, dev
    ],
    
    // Connection Pool Settings
    'pool' => [
        'enabled'     => true,
        'max_connections' => 10,
        'idle_timeout'    => 300,    // Seconds
    ],
    
    // Query Performance
    'performance' => [
        'slow_query_log' => true,
        'slow_query_time' => 2,      // Log queries slower than 2 seconds
        'enable_cache'   => true,
    ],
    
    // Development/Debug Settings
    'debug' => [
        'sql_log'        => false,   // Log all SQL queries
        'error_reporting' => E_ALL,
        'display_errors'  => false,  // Set to true only in development
    ],
];

/**
 * EXAMPLE CONFIGURATIONS:
 * 
 * MySQL Configuration (Default):
 * ------------------------------
 * $connection = [
 *     'database' => [
 *         'driver'   => 'mysql',
 *         'hostname' => 'localhost',
 *         'database' => 'travian_world_speed10k',
 *         'username' => 'travian',
 *         'password' => 'YourSecurePasswordHere',
 *     ],
 *     'world' => [
 *         'id'   => 'speed10k',
 *         'name' => 'Speed Server 10k',
 *         'type' => 'production',
 *     ],
 * ];
 * 
 * PostgreSQL Configuration (Alternative):
 * ----------------------------------------
 * $connection = [
 *     'database' => [
 *         'driver'   => 'pgsql',
 *         'hostname' => 'localhost',
 *         'port'     => 5432,
 *         'database' => 'travian_global',
 *         'schema'   => 'speed10k',
 *         'username' => 'travian',
 *         'password' => 'YourSecurePasswordHere',
 *         'sslmode'  => 'require',
 *     ],
 *     'world' => [
 *         'id'   => 'speed10k',
 *         'name' => 'Speed Server 10k',
 *         'type' => 'production',
 *     ],
 * ];
 * 
 * Docker Environment Variables:
 * ------------------------------
 * $connection = [
 *     'database' => [
 *         'driver'   => getenv('DB_DRIVER') ?: 'mysql',
 *         'hostname' => getenv('MYSQL_HOST') ?: 'mysql',
 *         'database' => getenv('MYSQL_DATABASE') ?: 'travian_world_speed10k',
 *         'username' => getenv('MYSQL_USER') ?: 'travian',
 *         'password' => getenv('MYSQL_PASSWORD'),
 *     ],
 *     'world' => [
 *         'id'   => getenv('WORLD_ID') ?: 'speed10k',
 *         'name' => getenv('WORLD_NAME') ?: 'Speed Server 10k',
 *         'type' => getenv('ENVIRONMENT') ?: 'production',
 *     ],
 * ];
 */
