<?php
use Api\ApiDispatcher;
use Core\WebService;
require "include/bootstrap.php";

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    if ($errno === E_ERROR || $errno === E_PARSE || $errno === E_CORE_ERROR || $errno === E_COMPILE_ERROR) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    return true;
});

function handle_v1_api($section, $action)
{
    $response = ['success' => true, 'error' => ['errorType' => null, 'errorMsg' => null]];
    $payload = json_decode(file_get_contents('php://input'), true);
    try {
        new ApiDispatcher($response, $payload, $section, $action);
        http_response_code(200);
    } catch (Throwable $e) {
        $response['success'] = false;
        $response['error'] = [
            'errorType' => get_class($e),
            'errorMsg' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];
        error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        error_log("Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
    }
    header("Content-Type: application/json");
    header("Access-Control-Allow-Origin: *");
    echo json_encode($response);
    exit();
}
function handle_v1_api_action($action)
{
    handle_v1_api(null, $action);
}
$dispatcher = FastRoute\cachedDispatcher(function (FastRoute\RouteCollector $r) {
    $r->addRoute(['GET', 'POST', 'OPTIONS'], '/v1/{section}/{action}', 'handle_v1_api');
    $r->addRoute(['GET', 'POST', 'OPTIONS'], '/v1/{action}', 'handle_v1_api_action');
}, [
    'cacheFile' => __DIR__ . '/include/route.cache', /* required */
    'cacheDisabled' => true,     /* optional, enabled by default */
]);
// Fetch method and URI from somewhere
$httpMethod = strtoupper($_SERVER['REQUEST_METHOD']);
$uri = $_SERVER['REQUEST_URI'];
// Strip query string (?foo=bar) and decode URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);
$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
if ($httpMethod == 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Authorization, Accept, Client-Security-Token, Accept-Encoding');
    http_response_code(200);
    return;
}
switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        http_response_code(404);
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        // ... 405 Method Not Allowed
        http_response_code(405);
        break;
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        // ... call $handler with $vars
        call_user_func_array($handler, $vars);
        break;
}