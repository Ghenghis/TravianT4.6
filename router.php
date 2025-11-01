<?php
// Router script for PHP built-in server
// This handles routing for both static files and PHP scripts

$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Remove query string
if (false !== $pos = strpos($requestUri, '?')) {
    $requestPath = substr($requestUri, 0, $pos);
}

// Route to API
if (strpos($requestPath, '/v1/') === 0) {
    $_SERVER['SCRIPT_NAME'] = '/sections/api/index.php';
    require __DIR__ . '/sections/api/index.php';
    return true;
}

// Route ALL .php requests to game engine (supports full Travian game)
// This captures any PHP file: activate.php, login.php, game.php, dorf1.php, hero.php, logout.php, quest.php, etc.
if (preg_match('#\.php($|\?)#', $requestPath)) {
    // For now, route all game requests to speed500k world
    // In future, detect world from session/cookie
    chdir(__DIR__ . '/sections/servers/speed500k/public');
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/sections/servers/speed500k/public/index.php';
    
    // Pass the original request to the game engine for internal routing
    $_SERVER['REQUEST_URI'] = $requestUri;
    $_SERVER['PHP_SELF'] = $requestPath;
    
    require __DIR__ . '/sections/servers/speed500k/public/index.php';
    return true;
}

// Check if it's a file in the Angular browser directory
$filePath = __DIR__ . '/angularIndex/browser' . $requestPath;

// Special case for root - serve modified index.html
if ($requestPath === '/' || $requestPath === '') {
    header('Content-Type: text/html');
    $indexContent = file_get_contents(__DIR__ . '/angularIndex/browser/index.html');
    
    // Inject a script to override the API URL detection
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $apiUrl = $protocol . '://' . $host . '/v1/';
    
    $script = '<script>window.TRAVIAN_API_URL = "' . $apiUrl . '";</script>';
    $indexContent = str_replace('</head>', $script . '</head>', $indexContent);
    
    echo $indexContent;
    return true;
}

// Serve static files
if (file_exists($filePath) && is_file($filePath)) {
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    $mimeTypes = [
        'html' => 'text/html',
        'js' => 'application/javascript',
        'css' => 'text/css',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'json' => 'application/json',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
    ];
    
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    
    readfile($filePath);
    return true;
}

// For all other routes (SPA routing), serve the Angular index.html with injected config
header('Content-Type: text/html');
$indexContent = file_get_contents(__DIR__ . '/angularIndex/browser/index.html');

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$apiUrl = $protocol . '://' . $host . '/v1/';

$script = '<script>window.TRAVIAN_API_URL = "' . $apiUrl . '";</script>';
$indexContent = str_replace('</head>', $script . '</head>', $indexContent);

echo $indexContent;
return true;
