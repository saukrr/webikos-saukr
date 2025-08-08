<?php
/**
 * Social Authentication Controller
 * Handles OAuth integration with Google and Discord
 */

require_once __DIR__ . '/../config/config.php';

handleCors();

class SocialAuthController {
    private $userModel;
    private $sessionModel;
    
    public function __construct() {
        $this->userModel = new User();
        $this->sessionModel = new Session();
    }
    
    /**
     * Initiate social authentication
     */
    public function initiate() {
        $provider = $_GET['provider'] ?? '';
        $intent = $_GET['intent'] ?? 'login'; // login or register
        
        if (!in_array($provider, ['google', 'discord'])) {
            redirect('/auth/frontend/pages/login.html?error=1&message=' . urlencode('Nepodporovaný poskytovatel'));
        }
        
        // Store intent in session
        $_SESSION['social_auth_intent'] = $intent;
        $_SESSION['social_auth_provider'] = $provider;
        
        switch ($provider) {
            case 'google':
                $this->initiateGoogleAuth();
                break;
            case 'discord':
                $this->initiateDiscordAuth();
                break;
        }
    }
    
    /**
     * Handle OAuth callback
     */
    public function callback() {
        $provider = $_GET['provider'] ?? $_SESSION['social_auth_provider'] ?? '';
        
        if (!$provider) {
            redirect('/auth/frontend/pages/login.html?error=1&message=' . urlencode('Chybějící poskytovatel'));
        }
        
        try {
            switch ($provider) {
                case 'google':
                    $userData = $this->handleGoogleCallback();
                    break;
                case 'discord':
                    $userData = $this->handleDiscordCallback();
                    break;
                default:
                    throw new Exception('Nepodporovaný poskytovatel');
            }
            
            // Process user data
            $this->processUserData($userData, $provider);
            
        } catch (Exception $e) {
            error_log("Social auth error: " . $e->getMessage());
            redirect('/auth/frontend/pages/login.html?error=1&message=' . urlencode('Chyba při přihlašování: ' . $e->getMessage()));
        }
    }
    
    /**
     * Initiate Google OAuth
     */
    private function initiateGoogleAuth() {
        if (empty(GOOGLE_CLIENT_ID)) {
            throw new Exception('Google OAuth není nakonfigurován');
        }
        
        $params = [
            'client_id' => GOOGLE_CLIENT_ID,
            'redirect_uri' => GOOGLE_REDIRECT_URI,
            'scope' => 'openid email profile',
            'response_type' => 'code',
            'state' => generateSecureToken(32)
        ];
        
        $_SESSION['oauth_state'] = $params['state'];
        
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        header("Location: $authUrl");
        exit;
    }
    
    /**
     * Handle Google OAuth callback
     */
    private function handleGoogleCallback() {
        $code = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';
        
        // Verify state parameter
        if (empty($state) || $state !== ($_SESSION['oauth_state'] ?? '')) {
            throw new Exception('Neplatný state parametr');
        }
        
        unset($_SESSION['oauth_state']);
        
        if (empty($code)) {
            throw new Exception('Chybějící autorizační kód');
        }
        
        // Exchange code for access token
        $tokenData = $this->getGoogleAccessToken($code);
        
        // Get user info
        $userInfo = $this->getGoogleUserInfo($tokenData['access_token']);
        
        return [
            'provider' => 'google',
            'provider_id' => $userInfo['sub'],
            'email' => $userInfo['email'],
            'first_name' => $userInfo['given_name'] ?? '',
            'last_name' => $userInfo['family_name'] ?? '',
            'username' => $userInfo['email'],
            'profile_picture' => $userInfo['picture'] ?? '',
            'email_verified' => $userInfo['email_verified'] ?? false
        ];
    }
    
    /**
     * Get Google access token
     */
    private function getGoogleAccessToken($code) {
        $data = [
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => GOOGLE_REDIRECT_URI
        ];
        
        $response = $this->makeHttpRequest('https://oauth2.googleapis.com/token', $data);
        
        if (!$response || !isset($response['access_token'])) {
            throw new Exception('Nepodařilo se získat přístupový token od Google');
        }
        
        return $response;
    }
    
    /**
     * Get Google user info
     */
    private function getGoogleUserInfo($accessToken) {
        $url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $accessToken;
        
        $response = $this->makeHttpRequest($url, null, 'GET');
        
        if (!$response || !isset($response['email'])) {
            throw new Exception('Nepodařilo se získat informace o uživateli od Google');
        }
        
        return $response;
    }
    
    /**
     * Initiate Discord OAuth
     */
    private function initiateDiscordAuth() {
        if (empty(DISCORD_CLIENT_ID)) {
            throw new Exception('Discord OAuth není nakonfigurován');
        }
        
        $params = [
            'client_id' => DISCORD_CLIENT_ID,
            'redirect_uri' => DISCORD_REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'identify email',
            'state' => generateSecureToken(32)
        ];
        
        $_SESSION['oauth_state'] = $params['state'];
        
        $authUrl = 'https://discord.com/api/oauth2/authorize?' . http_build_query($params);
        header("Location: $authUrl");
        exit;
    }
    
    /**
     * Handle Discord OAuth callback
     */
    private function handleDiscordCallback() {
        $code = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';
        
        // Verify state parameter
        if (empty($state) || $state !== ($_SESSION['oauth_state'] ?? '')) {
            throw new Exception('Neplatný state parametr');
        }
        
        unset($_SESSION['oauth_state']);
        
        if (empty($code)) {
            throw new Exception('Chybějící autorizační kód');
        }
        
        // Exchange code for access token
        $tokenData = $this->getDiscordAccessToken($code);
        
        // Get user info
        $userInfo = $this->getDiscordUserInfo($tokenData['access_token']);
        
        return [
            'provider' => 'discord',
            'provider_id' => $userInfo['id'],
            'email' => $userInfo['email'],
            'first_name' => $userInfo['username'] ?? '',
            'last_name' => '',
            'username' => $userInfo['username'] . '_' . substr($userInfo['discriminator'], 0, 2),
            'profile_picture' => $userInfo['avatar'] ? 
                "https://cdn.discordapp.com/avatars/{$userInfo['id']}/{$userInfo['avatar']}.png" : '',
            'email_verified' => $userInfo['verified'] ?? false
        ];
    }
    
    /**
     * Get Discord access token
     */
    private function getDiscordAccessToken($code) {
        $data = [
            'client_id' => DISCORD_CLIENT_ID,
            'client_secret' => DISCORD_CLIENT_SECRET,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => DISCORD_REDIRECT_URI
        ];
        
        $response = $this->makeHttpRequest('https://discord.com/api/oauth2/token', $data);
        
        if (!$response || !isset($response['access_token'])) {
            throw new Exception('Nepodařilo se získat přístupový token od Discord');
        }
        
        return $response;
    }
    
    /**
     * Get Discord user info
     */
    private function getDiscordUserInfo($accessToken) {
        $headers = ['Authorization: Bearer ' . $accessToken];
        
        $response = $this->makeHttpRequest('https://discord.com/api/users/@me', null, 'GET', $headers);
        
        if (!$response || !isset($response['email'])) {
            throw new Exception('Nepodařilo se získat informace o uživateli od Discord');
        }
        
        return $response;
    }
    
    /**
     * Process user data after successful OAuth
     */
    private function processUserData($userData, $provider) {
        try {
            // Create or update user
            $userId = $this->userModel->createOrUpdateSocialUser($userData);
            
            // Create session
            $sessionToken = $this->sessionModel->create($userId);
            
            // Update last login
            $this->userModel->updateLastLogin($userId);
            
            // Redirect to success page
            $intent = $_SESSION['social_auth_intent'] ?? 'login';
            $message = $intent === 'register' ? 'Registrace úspěšná!' : 'Přihlášení úspěšné!';
            
            // Clean up session
            unset($_SESSION['social_auth_intent'], $_SESSION['social_auth_provider']);
            
            redirect('/dashboard.php?success=1&message=' . urlencode($message));
            
        } catch (Exception $e) {
            error_log("Error processing social user data: " . $e->getMessage());
            throw new Exception('Chyba při zpracování uživatelských dat');
        }
    }
    
    /**
     * Make HTTP request
     */
    private function makeHttpRequest($url, $data = null, $method = 'POST', $headers = []) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Webikos-Auth/1.0'
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception("HTTP request failed: $error");
        }
        
        if ($httpCode >= 400) {
            throw new Exception("HTTP request failed with status $httpCode");
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response");
        }
        
        return $decoded;
    }
}

// Handle requests
$action = $_GET['action'] ?? 'initiate';
$controller = new SocialAuthController();

switch ($action) {
    case 'initiate':
        $controller->initiate();
        break;
    case 'callback':
        $controller->callback();
        break;
    default:
        redirect('/auth/frontend/pages/login.html?error=1&message=' . urlencode('Neplatná akce'));
}
?>
