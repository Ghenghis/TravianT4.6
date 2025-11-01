<?php
namespace App\Security;

class OutputEncoder {
    /**
     * Encode for HTML context
     */
    public static function html(?string $input): string {
        if ($input === null) {
            return '';
        }
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Encode for HTML attribute context
     */
    public static function attr(?string $input): string {
        if ($input === null) {
            return '';
        }
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Encode for JavaScript context
     */
    public static function js(?string $input): string {
        if ($input === null) {
            return '';
        }
        return json_encode($input, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
    
    /**
     * Encode for URL context
     */
    public static function url(?string $input): string {
        if ($input === null) {
            return '';
        }
        return rawurlencode($input);
    }
    
    /**
     * Encode for JSON API response
     */
    public static function json($input): string {
        return json_encode($input, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Strip all HTML tags (for plain text)
     */
    public static function plainText(?string $input): string {
        if ($input === null) {
            return '';
        }
        return strip_tags($input);
    }
}
