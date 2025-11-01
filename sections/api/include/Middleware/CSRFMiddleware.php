<?php
namespace App\Middleware;

use App\Security\CSRFTokenManager;

class CSRFMiddleware {
    private CSRFTokenManager $tokenManager;
    private array $exemptPaths = [];
    
    public function __construct() {
        $this->tokenManager = new CSRFTokenManager();
        
        $this->exemptPaths = [
            '/v1/health',
            '/v1/loadConfig',
            '/v1/servers',
        ];
    }
    
    public function handle(): bool {
        // TEMPORARILY DISABLED FOR DEBUGGING - TODO: Re-enable with proper token management
        return true;
        
        /*
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return true;
        }
        
        foreach ($this->exemptPaths as $exemptPath) {
            if (str_starts_with($path, $exemptPath)) {
                return true;
            }
        }
        
        if (!$this->tokenManager->validateToken()) {
            $this->tokenManager->logFailure();
            
            http_response_code(403);
            echo json_encode([
                'error' => 'CSRF token validation failed',
                'code' => 'CSRF_VALIDATION_FAILED'
            ]);
            return false;
        }
        
        return true;
        */
    }
}
