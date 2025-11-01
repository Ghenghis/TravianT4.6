<?php
namespace App\Middleware;

use App\Logging\Logger;

class LoggingMiddleware {
    /**
     * Initialize request logging
     */
    public static function initialize(): void {
        // Generate request ID if not present
        if (!isset($_SERVER['HTTP_X_REQUEST_ID'])) {
            $_SERVER['HTTP_X_REQUEST_ID'] = bin2hex(random_bytes(16));
        }
        
        // Log incoming request
        Logger::logWithContext('info', 'REQUEST_START', [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        // Register shutdown function to log request completion
        register_shutdown_function([self::class, 'logRequestEnd']);
    }
    
    /**
     * Log request completion
     */
    public static function logRequestEnd(): void {
        $status = http_response_code();
        
        Logger::logWithContext('info', 'REQUEST_END', [
            'status_code' => $status,
            'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? 0)
        ]);
    }
}
