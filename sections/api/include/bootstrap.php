<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/php_errors.log');

// Session configuration
$isProduction = getenv('APP_ENV') === 'production';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isProduction,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_name('TRAVIAN_SESSION');

// Configure Redis session handler at runtime
if (getenv('REDIS_HOST') && getenv('REDIS_PORT')) {
    $redisHost = getenv('REDIS_HOST');
    $redisPort = getenv('REDIS_PORT');
    $redisPassword = getenv('REDIS_PASSWORD');
    
    $redisPath = "tcp://{$redisHost}:{$redisPort}";
    if ($redisPassword) {
        $redisPath .= "?auth={$redisPassword}";
    }
    
    ini_set('session.save_handler', 'redis');
    ini_set('session.save_path', $redisPath);
}

use Core\WebService;
define("TEMPLATES_PATH", __DIR__ . DIRECTORY_SEPARATOR . 'Templates' . DIRECTORY_SEPARATOR);
define("FILTERING_PATH", __DIR__ . '/../../../filtering/');
require "vendor/autoload.php";
spl_autoload_register(function ($name) {
    $location = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $name) . '.php';
    if (is_file($location)) {
        require($location);
    } else {
        throw new Exception("Couldn't load $name.");
    }
});
require "config.php";
require "functions.php";

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize logging EARLY (before any other middleware)
require_once __DIR__ . '/Logging/Logger.php';
require_once __DIR__ . '/Middleware/LoggingMiddleware.php';

use App\Middleware\LoggingMiddleware;

// Initialize request logging
LoggingMiddleware::initialize();

require_once __DIR__ . '/Middleware/CORSMiddleware.php';
require_once __DIR__ . '/Middleware/CSRFMiddleware.php';
require_once __DIR__ . '/Security/CSRFTokenManager.php';

use App\Middleware\CORSMiddleware;
use App\Middleware\CSRFMiddleware;

$corsMiddleware = new CORSMiddleware();
if (!$corsMiddleware->handle()) {
    exit;
}

$csrfMiddleware = new CSRFMiddleware();
if (!$csrfMiddleware->handle()) {
    exit;
}

global $twig;
if(!is_writable(TEMPLATES_PATH . "Cache")){
    die("Cache dir not writable.");
}
$loader = new Twig_Loader_Filesystem(TEMPLATES_PATH);
$twig = new Twig_Environment($loader, array(
    'cache' => TEMPLATES_PATH . "Cache"
));
$function = new Twig_SimpleFunction('T', function ($t) {
    return T($t);
});
$twig->addFunction($function);
$twig->addGlobal('WEBSITE_INDEX_URL', WebService::getIndexUrl());
$twig->addGlobal('GPACK_URL', WebService::getProtocol() . '://gpack.' . WebService::getRealDomain());
