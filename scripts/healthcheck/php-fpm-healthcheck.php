#!/usr/bin/env php
<?php
/**
 * PHP-FPM Health Check
 * Verifies that PHP-FPM is actually running and accepting FastCGI connections
 */

$host = '127.0.0.1';
$port = 9000;
$timeout = 3;

// Try to connect to PHP-FPM FastCGI port
$socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

if ($socket === false) {
    // Connection failed - PHP-FPM is not running or not accepting connections
    fwrite(STDERR, "PHP-FPM health check failed: Cannot connect to {$host}:{$port} - {$errstr} ({$errno})\n");
    exit(1);
}

// Connection successful - PHP-FPM is running
fclose($socket);
exit(0);
