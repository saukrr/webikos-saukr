<?php
/**
 * Authentication Controller
 * Handles login, registration, and logout operations
 */

require_once __DIR__ . '/../config/config.php';

handleCors();

// Handle API requests
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$controller = new AuthController();

switch ($action) {
    case 'login':
        $controller->login();
        break;
    case 'register':
        $controller->register();
        break;
    case 'logout':
        $controller->logout();
        break;
    case 'getCurrentUser':
        $controller->getCurrentUser();
        break;
    case 'checkEmail':
        $controller->checkEmailAvailability();
        break;
    case 'checkUsername':
        $controller->checkUsernameAvailability();
        break;
    case 'getFlashMessage':
        $controller->getFlashMessage();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

class AuthController {
    private $userModel;
    private $sessionModel;
    private $rateLimit;
    
    public function __construct() {
        $this->userModel = new User();
        $this->sessionModel = new Session();
        $this->rateLimit = new RateLimit();
    }
    
    /**
     * Handle user login
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['error' => 'Method not allowed'], 405);
        }
        
        // CSRF protection
        if (!CSRF::validate($_POST[CSRF_TOKEN_NAME] ?? '')) {
            jsonResponse(['error' => 'Invalid CSRF token'], 403);
        }
        
        // Rate limiting
        if (!$this->rateLimit->check('login', RATE_LIMIT_LOGIN, RATE_LIMIT_WINDOW)) {
            jsonResponse(['error' => 'Too many login attempts. Please try again later.'], 429);
        }
        
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);
        
        // Validation
        if (empty($email) || empty($password)) {
            jsonResponse(['error' => 'Email and password are required'], 400);
        }
        
        if (!isValidEmail($email)) {
            jsonResponse(['error' => 'Invalid email format'], 400);
        }
        
        try {
            // Find user
            $user = $this->userModel->findByEmail($email);
            
            if (!$user) {
                $this->logLoginAttempt($email, false);
                jsonResponse(['error' => 'Invalid credentials'], 401);
            }
            
            // Check if account is locked
            if ($this->userModel->isAccountLocked($user)) {
                jsonResponse(['error' => 'Account is temporarily locked due to too many failed attempts'], 423);
            }
            
            // Verify password
            if (!$this->userModel->verifyPassword($user, $password)) {
                $this->userModel->incrementFailedAttempts($email);
                $this->logLoginAttempt($email, false);
                jsonResponse(['error' => 'Invalid credentials'], 401);
            }
            
            // Check if email is verified
            if (!$user['email_verified']) {
                jsonResponse(['error' => 'Please verify your email address before logging in'], 403);
            }
            
            // Successful login
            $this->userModel->updateLastLogin($user['id']);
            $this->logLoginAttempt($email, true);
            
            // Create session
            $sessionToken = $this->sessionModel->create($user['id']);
            
            // Set remember me cookie if requested
            if ($rememberMe) {
                $cookieExpire = time() + (30 * 24 * 60 * 60); // 30 days
                setcookie('remember_token', $sessionToken, $cookieExpire, '/', '', true, true);
            }
            
            jsonResponse([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name']
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            jsonResponse(['error' => 'An error occurred during login'], 500);
        }
    }
    
    /**
     * Handle user registration
     */
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['error' => 'Method not allowed'], 405);
        }
        
        // CSRF protection
        if (!CSRF::validate($_POST[CSRF_TOKEN_NAME] ?? '')) {
            jsonResponse(['error' => 'Invalid CSRF token'], 403);
        }
        
        // Rate limiting
        if (!$this->rateLimit->check('register', RATE_LIMIT_REGISTER, RATE_LIMIT_WINDOW)) {
            jsonResponse(['error' => 'Too many registration attempts. Please try again later.'], 429);
        }
        
        $userData = [
            'username' => sanitizeInput($_POST['username'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
            'first_name' => sanitizeInput($_POST['first_name'] ?? ''),
            'last_name' => sanitizeInput($_POST['last_name'] ?? ''),
            'terms_accepted' => isset($_POST['terms_accepted'])
        ];
        
        // Validation
        $errors = $this->validateRegistration($userData);
        if (!empty($errors)) {
            jsonResponse(['error' => 'Validation failed', 'details' => $errors], 400);
        }
        
        try {
            // Check if user already exists
            if ($this->userModel->emailExists($userData['email'])) {
                jsonResponse(['error' => 'Email address is already registered'], 409);
            }
            
            if ($this->userModel->usernameExists($userData['username'])) {
                jsonResponse(['error' => 'Username is already taken'], 409);
            }
            
            // Create user
            $userId = $this->userModel->create($userData);
            
            // Send verification email (implement email sending)
            // $this->sendVerificationEmail($userData['email'], $verificationToken);
            
            jsonResponse([
                'success' => true,
                'message' => 'Registration successful. Please check your email to verify your account.',
                'user_id' => $userId
            ]);
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            jsonResponse(['error' => 'An error occurred during registration'], 500);
        }
    }
    
    /**
     * Handle user logout
     */
    public function logout() {
        try {
            $this->sessionModel->destroy();
            
            // Clear remember me cookie
            if (isset($_COOKIE['remember_token'])) {
                setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            }
            
            jsonResponse([
                'success' => true,
                'message' => 'Logout successful'
            ]);
            
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            jsonResponse(['error' => 'An error occurred during logout'], 500);
        }
    }
    
    /**
     * Validate registration data
     */
    private function validateRegistration($data) {
        $errors = [];
        
        if (empty($data['username'])) {
            $errors['username'] = 'Username is required';
        } elseif (strlen($data['username']) < 3) {
            $errors['username'] = 'Username must be at least 3 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            $errors['username'] = 'Username can only contain letters, numbers, and underscores';
        }
        
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!isValidEmail($data['email'])) {
            $errors['email'] = 'Invalid email format';
        }
        
        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (!isValidPassword($data['password'])) {
            $errors['password'] = 'Password must be at least 8 characters and contain uppercase, lowercase, and number';
        }
        
        if ($data['password'] !== $data['password_confirm']) {
            $errors['password_confirm'] = 'Passwords do not match';
        }
        
        if (!$data['terms_accepted']) {
            $errors['terms'] = 'You must accept the terms and conditions';
        }
        
        return $errors;
    }
    
    /**
     * Log login attempt
     */
    private function logLoginAttempt($email, $success) {
        try {
            $sql = "INSERT INTO login_attempts (ip_address, email, success, user_agent) VALUES (?, ?, ?, ?)";
            $db = Database::getInstance();
            $db->execute($sql, [getClientIP(), $email, $success ? 1 : 0, getUserAgent()]);
        } catch (Exception $e) {
            error_log("Failed to log login attempt: " . $e->getMessage());
        }
    }
    
    /**
     * Get current user info
     */
    public function getCurrentUser() {
        $session = $this->sessionModel->getCurrentSession();

        if (!$session) {
            jsonResponse(['error' => 'Not authenticated'], 401);
        }

        $user = $this->userModel->findById($session['user_id']);

        if (!$user) {
            jsonResponse(['error' => 'User not found'], 404);
        }

        // Remove sensitive data
        unset($user['password_hash'], $user['password_reset_token'], $user['email_verification_token']);

        jsonResponse([
            'success' => true,
            'user' => $user
        ]);
    }

    /**
     * Check email availability
     */
    public function checkEmailAvailability() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['error' => 'Method not allowed'], 405);
        }

        $email = sanitizeInput($_POST['email'] ?? '');

        if (empty($email)) {
            jsonResponse(['error' => 'Email is required'], 400);
        }

        if (!isValidEmail($email)) {
            jsonResponse(['error' => 'Invalid email format'], 400);
        }

        try {
            $exists = $this->userModel->emailExists($email);
            jsonResponse(['exists' => $exists]);
        } catch (Exception $e) {
            error_log("Email check error: " . $e->getMessage());
            jsonResponse(['error' => 'Check failed'], 500);
        }
    }

    /**
     * Check username availability
     */
    public function checkUsernameAvailability() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['error' => 'Method not allowed'], 405);
        }

        $username = sanitizeInput($_POST['username'] ?? '');

        if (empty($username)) {
            jsonResponse(['error' => 'Username is required'], 400);
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username) || strlen($username) < 3) {
            jsonResponse(['error' => 'Invalid username format'], 400);
        }

        try {
            $exists = $this->userModel->usernameExists($username);
            jsonResponse(['exists' => $exists]);
        } catch (Exception $e) {
            error_log("Username check error: " . $e->getMessage());
            jsonResponse(['error' => 'Check failed'], 500);
        }
    }

    /**
     * Get flash message
     */
    public function getFlashMessage() {
        $flash = getFlashMessage();

        if ($flash) {
            jsonResponse([
                'message' => $flash['message'],
                'type' => $flash['type']
            ]);
        } else {
            jsonResponse(['message' => null]);
        }
    }
}
?>
