<?php
/**
 * BENGKEL MANAGEMENT PRO - USER CLASS
 * Version: 3.1.0
 * Advanced User Management with Role-Based Access Control
 */

class User {
    private $db;
    private $table = 'users';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // ========================================
    // AUTHENTICATION METHODS
    // ========================================
    
    public function login($email, $password, $remember = false) {
        try {
            debugLog(['email' => $email, 'remember' => $remember, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'], 'USER_LOGIN_START');
            
            $user = $this->db->selectOne(
                "SELECT * FROM {$this->table} WHERE email = :email AND status = 'active'",
                ['email' => $email]
            );
            
            if (!$user) {
                debugLog(['email' => $email, 'reason' => 'user_not_found'], 'USER_LOGIN_FAILED');
                throw new Exception('Invalid email or password');
            }
            
            debugLog(['user_id' => $user['id'], 'email' => $email], 'USER_LOGIN_USER_FOUND');
            
            if (!password_verify($password, $user['password_hash'])) {
                debugLog(['user_id' => $user['id'], 'reason' => 'invalid_password'], 'USER_LOGIN_FAILED');
                // Log failed login attempt
                $this->logLoginAttempt($user['id'], false, $_SERVER['REMOTE_ADDR']);
                throw new Exception('Invalid email or password');
            }
            
            debugLog(['user_id' => $user['id']], 'USER_LOGIN_PASSWORD_VERIFIED');
            
            // Update last login
            $this->updateLastLogin($user['id']);
            
            // Log successful login
            $this->logLoginAttempt($user['id'], true, $_SERVER['REMOTE_ADDR']);
            
            // Set session
            $this->setUserSession($user, $remember);
            
            debugLog(['user_id' => $user['id'], 'session_set' => true], 'USER_LOGIN_SESSION_SET');
            
            // Remove sensitive data
            unset($user['password_hash']);
            
            debugLog(['user_id' => $user['id']], 'USER_LOGIN_SUCCESS');
            return $user;
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'email' => $email], 'USER_LOGIN_ERROR');
            throw $e;
        }
    }
    
    public function logout() {
        session_start();
        
        // Log logout
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'User logged out');
        }
        
        // Clear session
        session_destroy();
        
        // Clear remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
            $this->clearRememberToken($_COOKIE['remember_token']);
        }
        
        return true;
    }
    
    public function register($data) {
        $this->db->beginTransaction();
        
        try {
            // Validate required fields
            $requiredFields = ['name', 'email', 'password', 'role'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field {$field} is required");
                }
            }
            
            // Check if email already exists
            if ($this->emailExists($data['email'])) {
                throw new Exception('Email already registered');
            }
            
            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }
            
            // Hash password
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']); // Remove plain password
            
            // Set default values
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['status'] = 'active';
            $data['email_verified_at'] = null;
            
            // Insert user
            $userId = $this->db->insert($this->table, $data);
            
            // Create user profile
            $this->createUserProfile($userId, $data);
            
            // Send verification email if enabled
            if ($_ENV['EMAIL_VERIFICATION'] === 'true') {
                $this->sendVerificationEmail($userId);
            }
            
            $this->db->commit();
            
            // Log activity
            $this->logActivity($userId, 'register', 'User registered');
            
            return $userId;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            $user = $this->findById($userId);
            if (!$user) {
                throw new Exception('User not found');
            }
            
            if (!password_verify($currentPassword, $user['password_hash'])) {
                throw new Exception('Current password is incorrect');
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $this->db->update(
                $this->table,
                ['password_hash' => $hashedPassword, 'password_changed_at' => date('Y-m-d H:i:s')],
                'id = :id',
                ['id' => $userId]
            );
            
            // Log activity
            $this->logActivity($userId, 'password_change', 'Password changed');
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function resetPassword($email) {
        try {
            $user = $this->findByEmail($email);
            if (!$user) {
                throw new Exception('Email not found');
            }
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store reset token
            $this->db->insert('password_resets', [
                'email' => $email,
                'token' => hash('sha256', $token),
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Send reset email
            $this->sendPasswordResetEmail($email, $token);
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function verifyResetToken($token) {
        $hashedToken = hash('sha256', $token);
        
        $reset = $this->db->selectOne(
            "SELECT * FROM password_resets WHERE token = :token AND expires_at > NOW()",
            ['token' => $hashedToken]
        );
        
        return $reset !== false;
    }
    
    public function updatePasswordWithToken($token, $newPassword) {
        $this->db->beginTransaction();
        
        try {
            $hashedToken = hash('sha256', $token);
            
            $reset = $this->db->selectOne(
                "SELECT * FROM password_resets WHERE token = :token AND expires_at > NOW()",
                ['token' => $hashedToken]
            );
            
            if (!$reset) {
                throw new Exception('Invalid or expired reset token');
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $this->db->update(
                $this->table,
                [
                    'password_hash' => $hashedPassword,
                    'password_changed_at' => date('Y-m-d H:i:s')
                ],
                'email = :email',
                ['email' => $reset['email']]
            );
            
            // Delete used token
            $this->db->delete('password_resets', 'token = :token', ['token' => $hashedToken]);
            
            $this->db->commit();
            
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    // ========================================
    // USER CRUD METHODS
    // ========================================
    
    public function create($data) {
        $this->db->beginTransaction();
        
        try {
            // Validate and prepare data
            $data = $this->prepareUserData($data);
            
            // Insert user
            $userId = $this->db->insert($this->table, $data);
            
            // Create user profile
            $this->createUserProfile($userId, $data);
            
            $this->db->commit();
            
            return $userId;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function update($id, $data) {
        try {
            // Remove sensitive fields if not explicitly updating
            unset($data['password']);
            
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            $updated = $this->db->update(
                $this->table,
                $data,
                'id = :id',
                ['id' => $id]
            );
            
            if ($updated) {
                $this->logActivity($id, 'profile_update', 'Profile updated');
            }
            
            return $updated > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function delete($id) {
        try {
            // Soft delete
            $deleted = $this->db->update(
                $this->table,
                [
                    'status' => 'deleted',
                    'deleted_at' => date('Y-m-d H:i:s')
                ],
                'id = :id',
                ['id' => $id]
            );
            
            if ($deleted) {
                $this->logActivity($id, 'account_delete', 'Account deleted');
            }
            
            return $deleted > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function findById($id) {
        return $this->db->selectOne(
            "SELECT u.*, up.* FROM {$this->table} u 
             LEFT JOIN user_profiles up ON u.id = up.user_id 
             WHERE u.id = :id AND u.status != 'deleted'",
            ['id' => $id]
        );
    }
    
    public function findByEmail($email) {
        return $this->db->selectOne(
            "SELECT * FROM {$this->table} WHERE email = :email AND status != 'deleted'",
            ['email' => $email]
        );
    }
    
    public function getAll($filters = []) {
        $where = ["u.status != 'deleted'"];
        $params = [];
        
        if (!empty($filters['role'])) {
            $where[] = "u.role = :role";
            $params['role'] = $filters['role'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "u.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(u.name LIKE :search OR u.email LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT u.*, up.phone, up.address 
                FROM {$this->table} u
                LEFT JOIN user_profiles up ON u.id = up.user_id
                WHERE {$whereClause}
                ORDER BY u.created_at DESC";
        
        return $this->db->select($sql, $params);
    }
    
    public function paginate($page = 1, $perPage = 20, $filters = []) {
        $where = ["u.status != 'deleted'"];
        $params = [];
        
        if (!empty($filters['role'])) {
            $where[] = "u.role = :role";
            $params['role'] = $filters['role'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "u.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(u.name LIKE :search OR u.email LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT u.*, up.phone, up.address 
                FROM {$this->table} u
                LEFT JOIN user_profiles up ON u.id = up.user_id
                WHERE {$whereClause}
                ORDER BY u.created_at DESC";
        
        return $this->db->paginate($sql, $params, $page, $perPage);
    }
    
    // ========================================
    // ROLE AND PERMISSION METHODS
    // ========================================
    
    public function hasRole($userId, $role) {
        $user = $this->findById($userId);
        return $user && $user['role'] === $role;
    }
    
    public function hasPermission($userId, $permission) {
        $user = $this->findById($userId);
        if (!$user) return false;
        
        $permissions = $this->getRolePermissions($user['role']);
        return in_array($permission, $permissions);
    }
    
    public function getRolePermissions($role) {
        $rolePermissions = [
            'admin' => [
                'user_view', 'user_create', 'user_edit', 'user_delete',
                'workorder_view', 'workorder_create', 'workorder_edit', 'workorder_delete',
                'customer_view', 'customer_create', 'customer_edit', 'customer_delete',
                'vehicle_view', 'vehicle_create', 'vehicle_edit', 'vehicle_delete',
                'inventory_view', 'inventory_create', 'inventory_edit', 'inventory_delete',
                'service_view', 'service_create', 'service_edit', 'service_delete',
                'report_view', 'settings_manage'
            ],
            'manager' => [
                'user_view', 'user_edit',
                'workorder_view', 'workorder_create', 'workorder_edit',
                'customer_view', 'customer_create', 'customer_edit',
                'vehicle_view', 'vehicle_create', 'vehicle_edit',
                'inventory_view', 'inventory_edit',
                'service_view', 'service_edit',
                'report_view'
            ],
            'technician' => [
                'workorder_view', 'workorder_edit',
                'customer_view',
                'vehicle_view',
                'inventory_view',
                'service_view'
            ],
            'receptionist' => [
                'workorder_view', 'workorder_create',
                'customer_view', 'customer_create', 'customer_edit',
                'vehicle_view', 'vehicle_create', 'vehicle_edit',
                'service_view'
            ]
        ];
        
        return $rolePermissions[$role] ?? [];
    }
    
    public function updateRole($userId, $newRole) {
        try {
            $validRoles = ['admin', 'manager', 'technician', 'receptionist'];
            
            if (!in_array($newRole, $validRoles)) {
                throw new Exception('Invalid role');
            }
            
            $updated = $this->db->update(
                $this->table,
                ['role' => $newRole, 'updated_at' => date('Y-m-d H:i:s')],
                'id = :id',
                ['id' => $userId]
            );
            
            if ($updated) {
                $this->logActivity($userId, 'role_change', "Role changed to {$newRole}");
            }
            
            return $updated > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    // ========================================
    // SESSION AND AUTHENTICATION HELPERS
    // ========================================
    
    public function getCurrentUser() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        return $this->findById($_SESSION['user_id']);
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
    }
    
    public function requireRole($requiredRole) {
        $user = $this->getCurrentUser();
        if (!$user || !$this->hasRole($user['id'], $requiredRole)) {
            throw new Exception('Access denied');
        }
    }
    
    public function requirePermission($permission) {
        $user = $this->getCurrentUser();
        if (!$user || !$this->hasPermission($user['id'], $permission)) {
            throw new Exception('Access denied');
        }
    }
    
    private function setUserSession($user, $remember = false) {
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in_at'] = time();
        
        if ($remember) {
            $this->setRememberToken($user['id']);
        }
    }
    
    private function setRememberToken($userId) {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        
        // Store in database
        $this->db->insert('remember_tokens', [
            'user_id' => $userId,
            'token' => $hashedToken,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Set cookie
        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');
    }
    
    private function clearRememberToken($token) {
        $hashedToken = hash('sha256', $token);
        $this->db->delete('remember_tokens', 'token = :token', ['token' => $hashedToken]);
    }
    
    public function checkRememberToken() {
        if (!isset($_COOKIE['remember_token'])) {
            return false;
        }
        
        $token = $_COOKIE['remember_token'];
        $hashedToken = hash('sha256', $token);
        
        $tokenData = $this->db->selectOne(
            "SELECT rt.*, u.* FROM remember_tokens rt
             JOIN users u ON rt.user_id = u.id
             WHERE rt.token = :token AND rt.expires_at > NOW() AND u.status = 'active'",
            ['token' => $hashedToken]
        );
        
        if ($tokenData) {
            $this->setUserSession($tokenData);
            return true;
        }
        
        // Clear invalid token
        setcookie('remember_token', '', time() - 3600, '/');
        return false;
    }
    
    // ========================================
    // UTILITY METHODS
    // ========================================
    
    private function prepareUserData($data) {
        // Hash password if provided
        if (!empty($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']); // Remove plain password
        }
        
        // Set default values
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['status'] = $data['status'] ?? 'active';
        
        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        
        return $data;
    }
    
    private function createUserProfile($userId, $data) {
        $profileData = [
            'user_id' => $userId,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'avatar' => $data['avatar'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert('user_profiles', $profileData);
    }
    
    private function updateLastLogin($userId) {
        $this->db->update(
            $this->table,
            ['last_login_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $userId]
        );
    }
    
    private function logLoginAttempt($userId, $success, $ipAddress) {
        $this->db->insert('login_attempts', [
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'success' => $success ? 1 : 0,
            'attempted_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function logActivity($userId, $action, $description) {
        $this->db->insert('user_activities', [
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function emailExists($email, $excludeId = null) {
        $sql = "SELECT id FROM {$this->table} WHERE email = :email AND status != 'deleted'";
        $params = ['email' => $email];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $result = $this->db->selectOne($sql, $params);
        return $result !== false;
    }
    
    public function getStats() {
        $stats = [];
        
        // Total users by role
        $roles = $this->db->select(
            "SELECT role, COUNT(*) as count FROM {$this->table} 
             WHERE status = 'active' GROUP BY role"
        );
        
        foreach ($roles as $role) {
            $stats['by_role'][$role['role']] = $role['count'];
        }
        
        // Total active users
        $stats['total_active'] = $this->db->count($this->table, "status = 'active'");
        
        // Recent registrations (last 30 days)
        $stats['recent_registrations'] = $this->db->count(
            $this->table,
            "status = 'active' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        return $stats;
    }
    
    private function sendVerificationEmail($userId) {
        // Implementation for sending verification email
        // This would integrate with your email service
    }
    
    private function sendPasswordResetEmail($email, $token) {
        // Implementation for sending password reset email
        // This would integrate with your email service
    }
}
