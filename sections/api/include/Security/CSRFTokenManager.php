<?php
namespace App\Security;

class CSRFTokenManager {
    private const TOKEN_LENGTH = 32;
    private const COOKIE_NAME = 'XSRF-TOKEN';
    private const HEADER_NAME = 'X-CSRF-Token';
    
    public function generateToken(): string {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['csrf_token'] = $token;
        
        $this->setCookie($token);
        
        return $token;
    }
    
    public function validateToken(): bool {
        $headerToken = $this->getHeaderToken();
        $cookieToken = $this->getCookieToken();
        $sessionToken = $this->getSessionToken();
        
        if ($headerToken && $cookieToken && hash_equals($headerToken, $cookieToken)) {
            return true;
        }
        
        if ($headerToken && $sessionToken && hash_equals($headerToken, $sessionToken)) {
            return true;
        }
        
        return false;
    }
    
    private function getHeaderToken(): ?string {
        return $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
    
    private function getCookieToken(): ?string {
        return $_COOKIE[self::COOKIE_NAME] ?? null;
    }
    
    private function getSessionToken(): ?string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['csrf_token'] ?? null;
    }
    
    private function setCookie(string $token): void {
        $secure = getenv('APP_ENV') === 'production';
        $sameSite = getenv('APP_ENV') === 'production' ? 'None' : 'Lax';
        
        setcookie(
            self::COOKIE_NAME,
            $token,
            [
                'expires' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => false,
                'samesite' => $sameSite
            ]
        );
    }
    
    public function logFailure(): void {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'none',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'none'
        ];
        
        error_log('[CSRF FAILED] ' . json_encode($logData), 3, '/var/log/travian/csrf-failures.log');
    }
}
