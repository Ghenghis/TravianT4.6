<?php

namespace Database;

use PDO;

/**
 * Global Database Connection (PostgreSQL)
 * 
 * DUAL-DATABASE ARCHITECTURE:
 * ----------------------------
 * This class manages the GLOBAL PostgreSQL database connection for:
 *   - User accounts and authentication (users, activation, passwordRecovery)
 *   - Game server registry (gameServers)
 *   - AI-NPC system (ai_players, ai_configs, ai_actions, etc.)
 *   - Global configuration (configurations, banIP, email_blacklist)
 * 
 * For GAME WORLD data (villages, alliances, marketplace, etc.), use:
 *   - ServerDB.php -> Connects to MySQL game world databases
 *   - DatabaseBridge.php -> Unified interface for cross-database operations
 * 
 * Architecture Strategy:
 *   - PostgreSQL (this class): Global data + AI-NPC tables
 *   - MySQL (ServerDB): Per-world game data (travian_testworld, travian_demo)
 */
class DB extends PDO
{
    private static $instance;

    public function __construct()
    {
        global $globalConfig;
        $db = [
            'host' => $globalConfig['dataSources']['globalDB']['hostname'],
            'user' => $globalConfig['dataSources']['globalDB']['username'],
            'pass' => $globalConfig['dataSources']['globalDB']['password'],
            'name' => $globalConfig['dataSources']['globalDB']['database'],
            'port' => $globalConfig['dataSources']['globalDB']['port'] ?? 5432,
            'charset' => $globalConfig['dataSources']['globalDB']['charset'],
        ];
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $dsn = 'pgsql:host=' . $db['host'] . ';dbname=' . $db['name'] . ';port=' . ($db['port'] ?? 5432) . ';sslmode=require';
        parent::__construct($dsn, $db['user'], $db['pass'], $options);
        $this->exec("SET NAMES 'UTF8'");
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get database configuration
     * Used for creating separate connections (e.g., status tracking)
     * 
     * @return array Database configuration
     */
    public function getConfig()
    {
        global $globalConfig;
        return [
            'host' => $globalConfig['dataSources']['globalDB']['hostname'],
            'username' => $globalConfig['dataSources']['globalDB']['username'],
            'password' => $globalConfig['dataSources']['globalDB']['password'],
            'database' => $globalConfig['dataSources']['globalDB']['database'],
            'port' => $globalConfig['dataSources']['globalDB']['port'] ?? 5432,
            'charset' => $globalConfig['dataSources']['globalDB']['charset'],
        ];
    }
}