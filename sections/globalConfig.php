<?php
global $globalConfig;
$globalConfig = [];
$globalConfig['staticParameters'] = [];
$globalConfig['staticParameters']['default_language'] = 'us';
$globalConfig['staticParameters']['default_timezone'] = 'Australia/Sydney';
$globalConfig['staticParameters']['default_direction'] = 'LTR';
$globalConfig['staticParameters']['default_dateFormat'] = 'y.m.d';
$globalConfig['staticParameters']['default_timeFormat'] = 'H:i';
$globalConfig['staticParameters']['indexUrl'] = 'https://www.YOUR_DOMAIN.com/';
$globalConfig['staticParameters']['forumUrl'] = 'https://forum.YOUR_DOMAIN.com/';
$globalConfig['staticParameters']['answersUrl'] = 'https://answers.travian.com/index.php';
$globalConfig['staticParameters']['helpUrl'] = 'https://help.YOUR_DOMAIN.com/';
$globalConfig['staticParameters']['adminEmail'] = '';
$globalConfig['staticParameters']['session_timeout'] = 6*3600;
$globalConfig['staticParameters']['default_payment_location'] = 2;
$globalConfig['staticParameters']['global_css_class'] = 'USERNAME_HERE';
$globalConfig['staticParameters']['gpacks'] = require(__DIR__ . "/gpack/gpack.php");
$globalConfig['staticParameters']['recaptcha_public_key'] = '';
$globalConfig['staticParameters']['recaptcha_private_key'] = '';
$globalConfig['cachingServers'] = ['memcached' => [['127.0.0.1', 11211],],];
$globalConfig['dataSources'] = [];

//
// DUAL-DATABASE ARCHITECTURE
// ===========================
// PostgreSQL: Global DB + AI-NPC Tables (users, gameServers, ai_players, etc.)
// MySQL: Game World Databases (villages, alliances, marketplace, etc.)
//

// PostgreSQL - Global Database (Replit)
$globalConfig['dataSources']['globalDB']['hostname'] = getenv('PGHOST') ?: 'localhost';
$globalConfig['dataSources']['globalDB']['username'] = getenv('PGUSER') ?: 'root';
$globalConfig['dataSources']['globalDB']['password'] = getenv('PGPASSWORD') ?: '';
$globalConfig['dataSources']['globalDB']['database'] = getenv('PGDATABASE') ?: 'main';
$globalConfig['dataSources']['globalDB']['port'] = getenv('PGPORT') ?: 5432;
$globalConfig['dataSources']['globalDB']['charset'] = 'utf8mb4';

// MySQL - Game World Databases (Docker)
$globalConfig['dataSources']['gameWorldDB']['hostname'] = getenv('MYSQL_HOST') ?: 'mysql';
$globalConfig['dataSources']['gameWorldDB']['username'] = getenv('MYSQL_USER') ?: 'travian_user';
$globalConfig['dataSources']['gameWorldDB']['password'] = getenv('MYSQL_PASSWORD') ?: '';
$globalConfig['dataSources']['gameWorldDB']['port'] = getenv('MYSQL_PORT') ?: 3306;
$globalConfig['dataSources']['gameWorldDB']['charset'] = 'utf8mb4';


