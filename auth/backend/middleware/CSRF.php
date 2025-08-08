<?php
/**
 * CSRF Protection Middleware
 * Prevents Cross-Site Request Forgery attacks
 */

class CSRF {
    
    /**
     * Generate CSRF token
     */
    public static function generate() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = generateSecureToken(32);
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Get CSRF token
     */
    public static function getToken() {
        return $_SESSION['csrf_token'] ?? null;
    }
    
    /**
     * Validate CSRF token
     */
    public static function validate($token) {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate CSRF input field
     */
    public static function field() {
        $token = self::generate();
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Generate CSRF meta tag
     */
    public static function metaTag() {
        $token = self::generate();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Regenerate CSRF token
     */
    public static function regenerate() {
        $_SESSION['csrf_token'] = generateSecureToken(32);
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Clear CSRF token
     */
    public static function clear() {
        unset($_SESSION['csrf_token']);
    }
    
    /**
     * Middleware function to check CSRF token
     */
    public static function middleware() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            
            if (!self::validate($token)) {
                http_response_code(403);
                if (self::isAjaxRequest()) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'CSRF token mismatch']);
                } else {
                    echo 'CSRF token mismatch';
                }
                exit;
            }
        }
    }
    
    /**
     * Check if request is AJAX
     */
    private static function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
?>
