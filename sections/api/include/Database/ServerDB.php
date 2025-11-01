<?php

namespace Database;
class ServerDB
{
    private static $connections = [];

    /**
     * @param $configFileLocation
     *
     * @return \PDO
     * @throws \Exception
     */
    public static function getInstance($configFileLocation)
    {
        $configKey = substr(md5($configFileLocation), 0, 5);
        if (isset(self::$connections[$configKey])) {
            return self::$connections[$configKey];
        }
        if (!is_file($configFileLocation)) {
            throw new \Exception("Configuration file not found!");
        }
        require($configFileLocation);
        if (!isset($connection)) {
            throw new \Exception("Invalid data was in connection file!");
        }
        
        // Detect driver (MySQL or PostgreSQL)
        $driver = isset($connection['database']['driver']) ? $connection['database']['driver'] : 'mysql';
        $options = [];
        
        if ($driver === 'pgsql') {
            // PostgreSQL connection with schema support
            $dsn = 'pgsql:host=' . $connection['database']['hostname'] . 
                   ';port=' . $connection['database']['port'] .
                   ';dbname=' . $connection['database']['database'];
            
            if (isset($connection['database']['sslmode'])) {
                $dsn .= ';sslmode=' . $connection['database']['sslmode'];
            }
            
            $db = self::$connections[$configKey] = new \PDO($dsn, $connection['database']['username'], $connection['database']['password'], $options);
            
            // Set search_path for per-world schema isolation
            if (isset($connection['database']['schema'])) {
                $db->exec("SET search_path TO " . $connection['database']['schema'] . ", public");
            }
            
            $db->exec("SET client_encoding TO 'UTF8'");
        } else {
            // MySQL connection (original behavior)
            $dsn = 'mysql:charset=utf8mb4;host=' . $connection['database']['hostname'] . ';dbname=' . $connection['database']['database'];
            $db = self::$connections[$configKey] = new \PDO($dsn, $connection['database']['username'], $connection['database']['password'], $options);
            $db->exec("set names utf8");
        }
        
        return $db;
    }
}