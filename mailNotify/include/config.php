<?php
global $indexConfig;
global $globalConfig;

// Replit environment adaptation
if (!defined('WORKING_USER')) {
    define('WORKING_USER', 'replit');
}

// Use relative path for Replit instead of /home/travian
$globalConfigPath = __DIR__ . '/../../sections/globalConfig.php';
if (!file_exists($globalConfigPath)) {
    // Fallback for traditional setup
    $globalConfigPath = '/home/travian/' . WORKING_USER . '/globalConfig.php';
}
require($globalConfigPath);
date_default_timezone_set($globalConfig['staticParameters']['default_timezone']);
$indexConfig = [
    'db' => [
        'host' => $globalConfig['dataSources']['globalDB']['hostname'],
        'user' => $globalConfig['dataSources']['globalDB']['username'],
        'pass' => $globalConfig['dataSources']['globalDB']['password'],
        'name' => $globalConfig['dataSources']['globalDB']['database'],
        'charset' => $globalConfig['dataSources']['globalDB']['charset'],
    ],
    'recaptcha' => [
        'site_key' => $globalConfig['staticParameters']['recaptcha_public_key'],
        'secret' => $globalConfig['staticParameters']['recaptcha_private_key'],
    ],
    'settings' => [
        'defaultLocaleName' => $globalConfig['staticParameters']['default_language'] == 'en' ? 'international' : $globalConfig['staticParameters']['default_language']
    ],
    'mail' => [
        'type' => 'smtp',
        'host' => 'smtp-relay.brevo.com',
        'port' => 587,
        'secure' => 'tls',  // STARTTLS encryption for port 587
        'username' => getenv('BREVO_USERNAME') ?: 'fnice0006@gmail.com',  // Your Brevo login email
        'password' => getenv('BREVO_SMTP_KEY') ?: '',  // SMTP key (not API key)
        'from_email' => 'noreply@travian.dev',
        'from_name' => 'Travian Game Server',
    ],
];
