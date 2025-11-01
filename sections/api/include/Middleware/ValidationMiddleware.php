<?php
namespace App\Middleware;

class ValidationMiddleware {
    /**
     * Sanitize string input
     */
    public static function sanitizeString(string $input, int $maxLength = 255): string {
        $input = str_replace("\0", '', $input);
        $input = trim($input);
        $input = substr($input, 0, $maxLength);
        
        return $input;
    }
    
    /**
     * Sanitize email
     */
    public static function sanitizeEmail(string $email): ?string {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
    
    /**
     * Sanitize integer
     */
    public static function sanitizeInt($input): ?int {
        return filter_var($input, FILTER_VALIDATE_INT) !== false 
            ? (int)$input 
            : null;
    }
    
    /**
     * Sanitize boolean
     */
    public static function sanitizeBool($input): bool {
        return filter_var($input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
    
    /**
     * Sanitize URL
     */
    public static function sanitizeUrl(string $url): ?string {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
    
    /**
     * Validate and sanitize array of strings
     */
    public static function sanitizeStringArray(array $input, int $maxLength = 255): array {
        return array_map(
            fn($item) => self::sanitizeString((string)$item, $maxLength),
            $input
        );
    }
}
