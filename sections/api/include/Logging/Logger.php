<?php
namespace App\Logging;

use Monolog\Logger as Monolog;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;

class Logger {
    private static ?Monolog $instance = null;
    
    /**
     * Get logger instance
     */
    public static function getInstance(): Monolog {
        if (self::$instance === null) {
            self::$instance = self::createLogger();
        }
        return self::$instance;
    }
    
    /**
     * Create configured logger
     */
    private static function createLogger(): Monolog {
        $logger = new Monolog('travian');
        
        // Determine log file path with fallback
        $logDir = '/var/log/travian';
        if (!is_dir($logDir)) {
            // Try to create directory
            if (!@mkdir($logDir, 0755, true)) {
                // Fallback to /tmp if can't create /var/log/travian
                $logDir = '/tmp/travian-logs';
                @mkdir($logDir, 0755, true);
            }
        }
        $logFile = $logDir . '/app.log';
        
        // Add JSON handler for structured logs
        try {
            $streamHandler = new StreamHandler($logFile, Monolog::DEBUG);
            $streamHandler->setFormatter(new JsonFormatter());
            $logger->pushHandler($streamHandler);
        } catch (\Exception $e) {
            // Last resort: log to stdout only
            error_log("Failed to create log file handler: " . $e->getMessage());
        }
        
        // Add console handler for development
        if (getenv('APP_ENV') !== 'production') {
            $consoleHandler = new StreamHandler('php://stdout', Monolog::DEBUG);
            $consoleHandler->setFormatter(new JsonFormatter());
            $logger->pushHandler($consoleHandler);
        }
        
        return $logger;
    }
    
    /**
     * Log with correlation context
     */
    public static function logWithContext(string $level, string $message, array $context = []): void {
        $logger = self::getInstance();
        
        // Add standard context
        $fullContext = array_merge([
            'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? self::generateRequestId(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'session_id' => session_id(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => date('c'),
        ], $context);
        
        $logger->log($level, $message, $fullContext);
    }
    
    /**
     * Security event logging
     */
    public static function logSecurityEvent(string $event, array $details = []): void {
        self::logWithContext('warning', 'SECURITY_EVENT', [
            'event_type' => $event,
            'details' => $details
        ]);
    }
    
    /**
     * Generate request ID
     */
    private static function generateRequestId(): string {
        return bin2hex(random_bytes(16));
    }
}
