<?php
namespace App\Middleware;

class CORSMiddleware {
    private array $allowedOrigins = [];
    
    public function __construct() {
        $domain = getenv('DOMAIN') ?: 'localhost';
        
        if (getenv('APP_ENV') === 'production') {
            $this->allowedOrigins = [
                "https://{$domain}",
                "https://www.{$domain}",
            ];
        } else {
            $this->allowedOrigins = [
                'http://localhost:4200',
                'http://localhost:5000',
                'http://localhost:3000',
            ];
        }
    }
    
    public function handle(): bool {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (empty($origin)) {
            return true;
        }
        
        // In development/non-production, allow ANY origin for Replit cloud environment
        if (getenv('APP_ENV') !== 'production') {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Accept,Authorization,Cache-Control,Content-Type,DNT,If-Modified-Since,Keep-Alive,Origin,User-Agent,X-Requested-With,X-CSRF-Token');
            header('Access-Control-Expose-Headers: Authorization,X-CSRF-Token');
            
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(204);
                exit;
            }
            
            return true;
        }
        
        // Production mode: strict origin checking
        if (!in_array($origin, $this->allowedOrigins)) {
            $domain = getenv('DOMAIN') ?: 'localhost';
            if (!preg_match("/^https:\/\/.*\.{$domain}$/", $origin)) {
                http_response_code(403);
                echo json_encode(['error' => 'CORS policy: Origin not allowed']);
                return false;
            }
        }
        
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Accept,Authorization,Cache-Control,Content-Type,DNT,If-Modified-Since,Keep-Alive,Origin,User-Agent,X-Requested-With,X-CSRF-Token');
        header('Access-Control-Expose-Headers: Authorization,X-CSRF-Token');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
        
        return true;
    }
}
