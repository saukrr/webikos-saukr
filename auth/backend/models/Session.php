<?php
/**
 * Session Model
 * Handles user session management
 */

class Session {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create new session
     */
    public function create($userId, $sessionData = []) {
        $sessionToken = generateSecureToken(64);
        $csrfToken = generateSecureToken(32);
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        
        $sql = "INSERT INTO user_sessions (user_id, session_token, csrf_token, ip_address, user_agent, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $params = [
            $userId,
            $sessionToken,
            $csrfToken,
            getClientIP(),
            getUserAgent(),
            $expiresAt
        ];
        
        try {
            $this->db->execute($sql, $params);
            
            // Set session data
            $_SESSION['user_id'] = $userId;
            $_SESSION['session_token'] = $sessionToken;
            $_SESSION['csrf_token'] = $csrfToken;
            $_SESSION['logged_in'] = true;
            
            return $sessionToken;
            
        } catch (Exception $e) {
            throw new Exception("Failed to create session: " . $e->getMessage());
        }
    }
    
    /**
     * Validate session
     */
    public function validate($sessionToken) {
        $sql = "SELECT s.*, u.id as user_id, u.username, u.email, u.is_active 
                FROM user_sessions s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.session_token = ? AND s.expires_at > NOW() AND u.is_active = 1";
        
        $session = $this->db->fetchOne($sql, [$sessionToken]);
        
        if ($session) {
            // Update last activity
            $this->updateActivity($sessionToken);
            return $session;
        }
        
        return false;
    }
    
    /**
     * Update session activity
     */
    public function updateActivity($sessionToken) {
        $sql = "UPDATE user_sessions SET last_activity = NOW() WHERE session_token = ?";
        $this->db->execute($sql, [$sessionToken]);
    }
    
    /**
     * Destroy session
     */
    public function destroy($sessionToken = null) {
        if ($sessionToken === null) {
            $sessionToken = $_SESSION['session_token'] ?? null;
        }
        
        if ($sessionToken) {
            $sql = "DELETE FROM user_sessions WHERE session_token = ?";
            $this->db->execute($sql, [$sessionToken]);
        }
        
        // Clear PHP session
        session_unset();
        session_destroy();
        
        // Start new session for flash messages
        session_start();
        session_regenerate_id(true);
    }
    
    /**
     * Destroy all user sessions
     */
    public function destroyAllUserSessions($userId) {
        $sql = "DELETE FROM user_sessions WHERE user_id = ?";
        $this->db->execute($sql, [$userId]);
    }
    
    /**
     * Clean expired sessions
     */
    public function cleanExpired() {
        $sql = "DELETE FROM user_sessions WHERE expires_at < NOW()";
        $stmt = $this->db->execute($sql);
        return $stmt->rowCount();
    }
    
    /**
     * Get user sessions
     */
    public function getUserSessions($userId) {
        $sql = "SELECT session_token, ip_address, user_agent, created_at, last_activity, expires_at 
                FROM user_sessions 
                WHERE user_id = ? AND expires_at > NOW() 
                ORDER BY last_activity DESC";
        
        return $this->db->fetchAll($sql, [$userId]);
    }
    
    /**
     * Get current session info
     */
    public function getCurrentSession() {
        if (!isset($_SESSION['session_token'])) {
            return null;
        }
        
        return $this->validate($_SESSION['session_token']);
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            return false;
        }
        
        if (!isset($_SESSION['session_token'])) {
            return false;
        }
        
        $session = $this->validate($_SESSION['session_token']);
        return $session !== false;
    }
    
    /**
     * Get current user ID
     */
    public function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Regenerate session token
     */
    public function regenerate() {
        if (!isset($_SESSION['session_token'])) {
            return false;
        }
        
        $oldToken = $_SESSION['session_token'];
        $newToken = generateSecureToken(64);
        $newCsrfToken = generateSecureToken(32);
        
        $sql = "UPDATE user_sessions SET session_token = ?, csrf_token = ? WHERE session_token = ?";
        $stmt = $this->db->execute($sql, [$newToken, $newCsrfToken, $oldToken]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['session_token'] = $newToken;
            $_SESSION['csrf_token'] = $newCsrfToken;
            return true;
        }
        
        return false;
    }
    
    /**
     * Extend session expiration
     */
    public function extend($sessionToken = null) {
        if ($sessionToken === null) {
            $sessionToken = $_SESSION['session_token'] ?? null;
        }
        
        if (!$sessionToken) {
            return false;
        }
        
        $newExpiration = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        $sql = "UPDATE user_sessions SET expires_at = ? WHERE session_token = ?";
        
        $stmt = $this->db->execute($sql, [$newExpiration, $sessionToken]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get session statistics
     */
    public function getStats($userId) {
        $sql = "SELECT 
                    COUNT(*) as total_sessions,
                    COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as active_sessions,
                    MAX(last_activity) as last_activity
                FROM user_sessions 
                WHERE user_id = ?";
        
        return $this->db->fetchOne($sql, [$userId]);
    }
    
    /**
     * Check for suspicious activity
     */
    public function checkSuspiciousActivity($userId) {
        // Check for multiple IPs in short time
        $sql = "SELECT COUNT(DISTINCT ip_address) as ip_count 
                FROM user_sessions 
                WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        
        $result = $this->db->fetchOne($sql, [$userId]);
        
        return $result['ip_count'] > 3; // More than 3 different IPs in 1 hour
    }
}
?>
