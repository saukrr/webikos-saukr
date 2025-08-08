<?php
/**
 * Application Configuration
 * Central configuration file for the authentication system
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Application settings
define('APP_NAME', 'Webikos Auth');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://webikos-saukr.vercel.app');

// Security settings
define('CSRF_TOKEN_NAME', '_token');
define('SESSION_LIFETIME', 3600 * 24); // 24 hours
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

// Rate limiting
define('RATE_LIMIT_LOGIN', 5); // attempts per window
define('RATE_LIMIT_REGISTER', 3); // attempts per window
define('RATE_LIMIT_WINDOW', 300); // 5 minutes

// Email settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', ''); // Configure with your email
define('SMTP_PASSWORD', ''); // Configure with your app password
define('FROM_EMAIL', 'noreply@webikos.com');
define('FROM_NAME', 'Webikos');

// Social authentication settings
define('GOOGLE_CLIENT_ID', ''); // Configure with your Google OAuth client ID
define('GOOGLE_CLIENT_SECRET', ''); // Configure with your Google OAuth client secret
define('GOOGLE_REDIRECT_URI', APP_URL . '/auth/callback/google.php');

define('DISCORD_CLIENT_ID', ''); // Configure with your Discord OAuth client ID
define('DISCORD_CLIENT_SECRET', ''); // Configure with your Discord OAuth client secret
define('DISCORD_REDIRECT_URI', APP_URL . '/auth/callback/discord.php');

// File upload settings
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('UPLOAD_PATH', __DIR__ . '/../../assets/uploads/');

// Timezone
date_default_timezone_set('Europe/Prague');

// Include required files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Session.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../middleware/CSRF.php';
require_once __DIR__ . '/../middleware/RateLimit.php';

/**
 * Generate secure random token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 */
function isValidPassword($password) {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return false;
    }
    
    // Check for at least one uppercase, one lowercase, one number
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
        return false;
    }
    
    return true;
}

/**
 * Get client IP address
 */
function getClientIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get user agent
 */
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

/**
 * Redirect with message
 */
function redirect($url, $message = null, $type = 'info') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    
    header("Location: $url");
    exit;
}

/**
 * Get and clear flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        
        return ['message' => $message, 'type' => $type];
    }
    
    return null;
}

/**
 * JSON response helper
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>
