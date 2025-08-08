<?php
/**
 * Rate Limiting Middleware
 * Prevents brute force attacks and API abuse
 */

class RateLimit {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Check if action is within rate limit
     */
    public function check($action, $maxAttempts, $windowSeconds) {
        $ipAddress = getClientIP();
        
        try {
            // Clean old entries
            $this->cleanOldEntries($windowSeconds);
            
            // Get current attempts
            $sql = "SELECT attempts, window_start FROM rate_limits WHERE ip_address = ? AND action_type = ?";
            $result = $this->db->fetchOne($sql, [$ipAddress, $action]);
            
            if (!$result) {
                // First attempt
                $this->recordAttempt($ipAddress, $action);
                return true;
            }
            
            $windowStart = strtotime($result['window_start']);
            $currentTime = time();
            
            // Check if we're still in the same window
            if (($currentTime - $windowStart) < $windowSeconds) {
                if ($result['attempts'] >= $maxAttempts) {
                    return false; // Rate limit exceeded
                }
                
                // Increment attempts
                $this->incrementAttempts($ipAddress, $action);
                return true;
            } else {
                // New window, reset counter
                $this->resetAttempts($ipAddress, $action);
                return true;
            }
            
        } catch (Exception $e) {
            error_log("Rate limit check failed: " . $e->getMessage());
            return true; // Allow on error to prevent blocking legitimate users
        }
    }
    
    /**
     * Record new attempt
     */
    private function recordAttempt($ipAddress, $action) {
        $sql = "INSERT INTO rate_limits (ip_address, action_type, attempts, window_start) 
                VALUES (?, ?, 1, NOW()) 
                ON DUPLICATE KEY UPDATE 
                attempts = 1, window_start = NOW()";
        
        $this->db->execute($sql, [$ipAddress, $action]);
    }
    
    /**
     * Increment attempts
     */
    private function incrementAttempts($ipAddress, $action) {
        $sql = "UPDATE rate_limits SET attempts = attempts + 1 WHERE ip_address = ? AND action_type = ?";
        $this->db->execute($sql, [$ipAddress, $action]);
    }
    
    /**
     * Reset attempts for new window
     */
    private function resetAttempts($ipAddress, $action) {
        $sql = "UPDATE rate_limits SET attempts = 1, window_start = NOW() WHERE ip_address = ? AND action_type = ?";
        $this->db->execute($sql, [$ipAddress, $action]);
    }
    
    /**
     * Clean old entries
     */
    private function cleanOldEntries($windowSeconds) {
        $sql = "DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL ? SECOND)";
        $this->db->execute($sql, [$windowSeconds * 2]); // Keep entries for 2x window for analysis
    }
    
    /**
     * Get remaining attempts
     */
    public function getRemainingAttempts($action, $maxAttempts, $windowSeconds) {
        $ipAddress = getClientIP();
        
        $sql = "SELECT attempts, window_start FROM rate_limits WHERE ip_address = ? AND action_type = ?";
        $result = $this->db->fetchOne($sql, [$ipAddress, $action]);
        
        if (!$result) {
            return $maxAttempts;
        }
        
        $windowStart = strtotime($result['window_start']);
        $currentTime = time();
        
        if (($currentTime - $windowStart) >= $windowSeconds) {
            return $maxAttempts; // New window
        }
        
        return max(0, $maxAttempts - $result['attempts']);
    }
    
    /**
     * Get time until reset
     */
    public function getTimeUntilReset($action, $windowSeconds) {
        $ipAddress = getClientIP();
        
        $sql = "SELECT window_start FROM rate_limits WHERE ip_address = ? AND action_type = ?";
        $result = $this->db->fetchOne($sql, [$ipAddress, $action]);
        
        if (!$result) {
            return 0;
        }
        
        $windowStart = strtotime($result['window_start']);
        $resetTime = $windowStart + $windowSeconds;
        
        return max(0, $resetTime - time());
    }
    
    /**
     * Block IP address temporarily
     */
    public function blockIP($ipAddress, $duration = 3600) {
        $sql = "INSERT INTO rate_limits (ip_address, action_type, attempts, window_start) 
                VALUES (?, 'blocked', 999999, DATE_ADD(NOW(), INTERVAL ? SECOND)) 
                ON DUPLICATE KEY UPDATE 
                attempts = 999999, window_start = DATE_ADD(NOW(), INTERVAL ? SECOND)";
        
        $this->db->execute($sql, [$ipAddress, $duration, $duration]);
    }
    
    /**
     * Check if IP is blocked
     */
    public function isBlocked($ipAddress = null) {
        if ($ipAddress === null) {
            $ipAddress = getClientIP();
        }
        
        $sql = "SELECT window_start FROM rate_limits WHERE ip_address = ? AND action_type = 'blocked'";
        $result = $this->db->fetchOne($sql, [$ipAddress]);
        
        if (!$result) {
            return false;
        }
        
        return strtotime($result['window_start']) > time();
    }
    
    /**
     * Unblock IP address
     */
    public function unblockIP($ipAddress) {
        $sql = "DELETE FROM rate_limits WHERE ip_address = ? AND action_type = 'blocked'";
        $this->db->execute($sql, [$ipAddress]);
    }
    
    /**
     * Get rate limit statistics
     */
    public function getStats($hours = 24) {
        $sql = "SELECT 
                    action_type,
                    COUNT(*) as total_attempts,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    AVG(attempts) as avg_attempts_per_ip
                FROM rate_limits 
                WHERE window_start > DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY action_type";
        
        return $this->db->fetchAll($sql, [$hours]);
    }
}
?>
