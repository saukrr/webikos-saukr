<?php
/**
 * Authentication Middleware
 * Handles authentication checks and user session validation
 */

class Auth {
    private static $sessionModel;
    private static $userModel;
    
    /**
     * Initialize middleware
     */
    public static function init() {
        if (self::$sessionModel === null) {
            self::$sessionModel = new Session();
        }
        if (self::$userModel === null) {
            self::$userModel = new User();
        }
    }
    
    /**
     * Check if user is authenticated
     */
    public static function check() {
        self::init();
        
        // Check session
        if (self::$sessionModel->isLoggedIn()) {
            return true;
        }
        
        // Check remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            return self::loginFromRememberToken($_COOKIE['remember_token']);
        }
        
        return false;
    }
    
    /**
     * Require authentication
     */
    public static function require($redirectUrl = null) {
        if (!self::check()) {
            if (self::isAjaxRequest()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Authentication required']);
                exit;
            } else {
                $redirectUrl = $redirectUrl ?: '/auth/frontend/pages/login.html';
                header("Location: $redirectUrl");
                exit;
            }
        }
    }
    
    /**
     * Get current user
     */
    public static function user() {
        self::init();
        
        if (!self::check()) {
            return null;
        }
        
        $userId = self::$sessionModel->getCurrentUserId();
        return self::$userModel->findById($userId);
    }
    
    /**
     * Get current user ID
     */
    public static function id() {
        self::init();
        return self::$sessionModel->getCurrentUserId();
    }
    
    /**
     * Check if user is guest (not authenticated)
     */
    public static function guest() {
        return !self::check();
    }
    
    /**
     * Login user
     */
    public static function login($user, $remember = false) {
        self::init();
        
        $sessionToken = self::$sessionModel->create($user['id']);
        
        if ($remember) {
            $cookieExpire = time() + (30 * 24 * 60 * 60); // 30 days
            setcookie('remember_token', $sessionToken, $cookieExpire, '/', '', true, true);
        }
        
        return $sessionToken;
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        self::init();
        
        self::$sessionModel->destroy();
        
        // Clear remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    }
    
    /**
     * Login from remember token
     */
    private static function loginFromRememberToken($token) {
        self::init();
        
        $session = self::$sessionModel->validate($token);
        
        if ($session) {
            // Regenerate session for security
            self::$sessionModel->regenerate();
            return true;
        } else {
            // Invalid token, clear cookie
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            return false;
        }
    }
    
    /**
     * Check if request is AJAX
     */
    private static function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Middleware for protecting routes
     */
    public static function middleware() {
        self::require();
    }
    
    /**
     * Guest middleware (redirect if authenticated)
     */
    public static function guestMiddleware($redirectUrl = null) {
        if (self::check()) {
            $redirectUrl = $redirectUrl ?: '/dashboard.php';
            header("Location: $redirectUrl");
            exit;
        }
    }
    
    /**
     * Check user permissions
     */
    public static function can($permission, $resource = null) {
        $user = self::user();
        
        if (!$user) {
            return false;
        }
        
        // Implement your permission logic here
        // This is a basic example
        switch ($permission) {
            case 'admin':
                return $user['username'] === 'admin';
            case 'edit_profile':
                return true; // All authenticated users can edit their profile
            case 'delete_user':
                return $user['username'] === 'admin';
            default:
                return false;
        }
    }
    
    /**
     * Require specific permission
     */
    public static function requirePermission($permission, $resource = null) {
        if (!self::can($permission, $resource)) {
            if (self::isAjaxRequest()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Insufficient permissions']);
                exit;
            } else {
                http_response_code(403);
                echo 'Access denied';
                exit;
            }
        }
    }
    
    /**
     * Attempt login with credentials
     */
    public static function attempt($credentials, $remember = false) {
        self::init();
        
        $email = $credentials['email'] ?? '';
        $password = $credentials['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            return false;
        }
        
        $user = self::$userModel->findByEmail($email);
        
        if (!$user || !self::$userModel->verifyPassword($user, $password)) {
            return false;
        }
        
        if (self::$userModel->isAccountLocked($user)) {
            return false;
        }
        
        if (!$user['email_verified']) {
            return false;
        }
        
        self::login($user, $remember);
        self::$userModel->updateLastLogin($user['id']);
        
        return true;
    }
    
    /**
     * Get session information
     */
    public static function getSessionInfo() {
        self::init();
        return self::$sessionModel->getCurrentSession();
    }
    
    /**
     * Extend current session
     */
    public static function extendSession() {
        self::init();
        return self::$sessionModel->extend();
    }
}
?>
