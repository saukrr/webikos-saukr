<?php
/**
 * User Model
 * Handles user-related database operations
 */

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create new user
     */
    public function create($userData) {
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, email_verification_token) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $params = [
            $userData['username'],
            $userData['email'],
            password_hash($userData['password'], PASSWORD_DEFAULT),
            $userData['first_name'] ?? null,
            $userData['last_name'] ?? null,
            generateSecureToken()
        ];
        
        try {
            $this->db->execute($sql, $params);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            throw new Exception("Failed to create user: " . $e->getMessage());
        }
    }
    
    /**
     * Find user by email
     */
    public function findByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = ? AND is_active = 1";
        return $this->db->fetchOne($sql, [$email]);
    }
    
    /**
     * Find user by username
     */
    public function findByUsername($username) {
        $sql = "SELECT * FROM users WHERE username = ? AND is_active = 1";
        return $this->db->fetchOne($sql, [$username]);
    }
    
    /**
     * Find user by ID
     */
    public function findById($id) {
        $sql = "SELECT * FROM users WHERE id = ? AND is_active = 1";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Verify user password
     */
    public function verifyPassword($user, $password) {
        return password_verify($password, $user['password_hash']);
    }
    
    /**
     * Update last login time
     */
    public function updateLastLogin($userId) {
        $sql = "UPDATE users SET last_login = NOW(), failed_login_attempts = 0, locked_until = NULL WHERE id = ?";
        $this->db->execute($sql, [$userId]);
    }
    
    /**
     * Increment failed login attempts
     */
    public function incrementFailedAttempts($email) {
        $sql = "UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE email = ?";
        $this->db->execute($sql, [$email]);
        
        // Lock account if too many failed attempts
        $user = $this->findByEmail($email);
        if ($user && $user['failed_login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $this->lockAccount($user['id']);
        }
    }
    
    /**
     * Lock user account
     */
    public function lockAccount($userId) {
        $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
        $sql = "UPDATE users SET locked_until = ? WHERE id = ?";
        $this->db->execute($sql, [$lockUntil, $userId]);
    }
    
    /**
     * Check if account is locked
     */
    public function isAccountLocked($user) {
        if (!$user['locked_until']) {
            return false;
        }
        
        return strtotime($user['locked_until']) > time();
    }
    
    /**
     * Verify email address
     */
    public function verifyEmail($token) {
        $sql = "UPDATE users SET email_verified = 1, email_verification_token = NULL 
                WHERE email_verification_token = ? AND email_verified = 0";
        
        $stmt = $this->db->execute($sql, [$token]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Update user profile
     */
    public function updateProfile($userId, $data) {
        $allowedFields = ['first_name', 'last_name', 'profile_picture'];
        $updateFields = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updateFields)) {
            return false;
        }
        
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $stmt = $this->db->execute($sql, $params);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Change password
     */
    public function changePassword($userId, $newPassword) {
        $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $this->db->execute($sql, [$hashedPassword, $userId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Set password reset token
     */
    public function setPasswordResetToken($email) {
        $token = generateSecureToken();
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        $sql = "UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE email = ?";
        $stmt = $this->db->execute($sql, [$token, $expires, $email]);
        
        if ($stmt->rowCount() > 0) {
            return $token;
        }
        
        return false;
    }
    
    /**
     * Reset password with token
     */
    public function resetPassword($token, $newPassword) {
        $sql = "SELECT id FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW()";
        $user = $this->db->fetchOne($sql, [$token]);
        
        if (!$user) {
            return false;
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?";
        
        $stmt = $this->db->execute($sql, [$hashedPassword, $user['id']]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Check if email exists
     */
    public function emailExists($email) {
        $sql = "SELECT id FROM users WHERE email = ?";
        $result = $this->db->fetchOne($sql, [$email]);
        return !empty($result);
    }
    
    /**
     * Check if username exists
     */
    public function usernameExists($username) {
        $sql = "SELECT id FROM users WHERE username = ?";
        $result = $this->db->fetchOne($sql, [$username]);
        return !empty($result);
    }
    
    /**
     * Create or update social user
     */
    public function createOrUpdateSocialUser($providerData) {
        $this->db->beginTransaction();
        
        try {
            // Check if user exists by email
            $user = $this->findByEmail($providerData['email']);
            
            if (!$user) {
                // Create new user
                $userData = [
                    'username' => $providerData['username'] ?? $providerData['email'],
                    'email' => $providerData['email'],
                    'password' => generateSecureToken(), // Random password for social users
                    'first_name' => $providerData['first_name'] ?? '',
                    'last_name' => $providerData['last_name'] ?? ''
                ];
                
                $userId = $this->create($userData);
                
                // Mark email as verified for social logins
                $sql = "UPDATE users SET email_verified = 1 WHERE id = ?";
                $this->db->execute($sql, [$userId]);
            } else {
                $userId = $user['id'];
            }
            
            // Create or update social provider record
            $sql = "INSERT INTO social_providers (user_id, provider_name, provider_id, provider_email, provider_data) 
                    VALUES (?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    provider_email = VALUES(provider_email), 
                    provider_data = VALUES(provider_data)";
            
            $this->db->execute($sql, [
                $userId,
                $providerData['provider'],
                $providerData['provider_id'],
                $providerData['email'],
                json_encode($providerData)
            ]);
            
            $this->db->commit();
            return $userId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
?>
