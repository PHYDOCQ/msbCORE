<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../config/security.php';

class Auth {
    private $db;
    private $maxLoginAttempts = 5;
    private $lockoutTime = 900; // 15 minutes
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function login($username, $password, $rememberMe = false) {
        try {
            // Rate limiting check
            $identifier = $username . '_' . ($_SERVER['REMOTE_ADDR'] ?? '');
            if (!Security::rateLimitCheck($identifier, $this->maxLoginAttempts, $this->lockoutTime)) {
                Security::logSecurityEvent('rate_limit_exceeded', ['username' => $username]);
                throw new Exception('Too many login attempts. Please try again later.');
            }
            
            // Check if user exists and is active
            $query = "SELECT id, username, email, password_hash, full_name, role, is_active, 
                            failed_login_attempts, last_failed_login, account_locked_until
                     FROM users 
                     WHERE (username = ? OR email = ?) AND is_active = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                Security::logSecurityEvent('login_failed', ['username' => $username, 'reason' => 'user_not_found']);
                throw new Exception('Invalid username or password.');
            }
            
            // Check if account is locked
            if ($user['account_locked_until'] && strtotime($user['account_locked_until']) > time()) {
                Security::logSecurityEvent('login_failed', ['username' => $username, 'reason' => 'account_locked']);
                throw new Exception('Account is temporarily locked. Please try again later.');
            }
            
            // Verify password
            if (!Security::verifyPassword($password, $user['password_hash'])) {
                $this->handleFailedLogin($user['id']);
                Security::logSecurityEvent('login_failed', ['username' => $username, 'reason' => 'invalid_password']);
                throw new Exception('Invalid username or password.');
            }
            
            // Reset failed login attempts on successful login
            $this->resetFailedLoginAttempts($user['id']);
            
            // Update login tracking
            $this->updateLoginTracking($user['id']);
            
            // Set session variables
            $this->setUserSession($user);
            
            // Handle remember me
            if ($rememberMe) {
                $this->setRememberMeCookie($user['id']);
            }
            
            Security::logSecurityEvent('login_success', ['user_id' => $user['id'], 'username' => $username]);
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ]
            ];
            
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'username' => $username], 'AUTH_ERROR');
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function logout() {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            
            // Clear remember me cookie
            if (isset($_COOKIE['remember_token'])) {
                $this->clearRememberMeCookie();
            }
            
            // Destroy session
            session_destroy();
            
            // Clear session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            Security::logSecurityEvent('logout', ['user_id' => $userId]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage()], 'AUTH_ERROR');
            return ['success' => false, 'message' => 'Logout failed'];
        }
    }
    
    public function isLoggedIn() {
        debugLog([
            'session_logged_in' => $_SESSION['logged_in'] ?? 'not_set',
            'session_user_id' => $_SESSION['user_id'] ?? 'not_set',
            'session_last_activity' => $_SESSION['last_activity'] ?? 'not_set',
            'current_time' => time(),
            'session_lifetime' => SESSION_LIFETIME,
            'has_remember_cookie' => isset($_COOKIE['remember_token'])
        ], 'AUTH_IS_LOGGED_IN_CHECK');
        
        // Check session
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            debugLog(['status' => 'session_logged_in_true'], 'AUTH_SESSION_CHECK');
            // Check session expiry
            if (isset($_SESSION['last_activity'])) {
                if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
                    $this->logout();
                    return false;
                }
                $_SESSION['last_activity'] = time();
            }
            return true;
        }
        
        // Check remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            return $this->validateRememberMeToken($_COOKIE['remember_token']);
        }
        
        return false;
    }
    
    public function requireLogin($redirectTo = '/login.php') {
        debugLog([
            'method' => 'requireLogin',
            'current_url' => $_SERVER['REQUEST_URI'] ?? '',
            'session_data' => $_SESSION,
            'is_logged_in' => $this->isLoggedIn()
        ], 'AUTH_REQUIRE_LOGIN');
        
        if (!$this->isLoggedIn()) {
            debugLog(['reason' => 'not_logged_in', 'redirecting_to' => $redirectTo], 'AUTH_REDIRECT');
            
            if ($this->isAjaxRequest()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Authentication required']);
                exit;
            } else {
                $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
                header('Location: ' . $redirectTo . '?redirect=' . urlencode($currentUrl));
                exit;
            }
        } else {
            debugLog(['status' => 'authenticated', 'user_id' => $_SESSION['user_id'] ?? null], 'AUTH_PASSED');
        }
    }
    
    public function hasRole($roles) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        return in_array($_SESSION['role'] ?? '', $roles);
    }
    
    public function requireRole($roles, $errorMessage = 'Access denied') {
        $this->requireLogin();
        
        if (!$this->hasRole($roles)) {
            Security::logSecurityEvent('access_denied', [
                'user_id' => $_SESSION['user_id'],
                'required_roles' => $roles,
                'user_role' => $_SESSION['role'] ?? 'unknown'
            ]);
            
            if ($this->isAjaxRequest()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $errorMessage]);
                exit;
            } else {
                $_SESSION['error_message'] = $errorMessage;
                header('Location: /index.php');
                exit;
            }
        }
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ];
    }
    
    private function handleFailedLogin($userId) {
        $currentTime = date('Y-m-d H:i:s');
        $lockoutUntil = date('Y-m-d H:i:s', time() + $this->lockoutTime);
        
        // First get current failed attempts
        $checkQuery = "SELECT failed_login_attempts FROM users WHERE id = ?";
        $stmt = $this->db->prepare($checkQuery);
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        $currentAttempts = $result ? (int)$result['failed_login_attempts'] : 0;
        
        // Update with database-agnostic query
        if ($currentAttempts + 1 >= $this->maxLoginAttempts) {
            $query = "UPDATE users 
                     SET failed_login_attempts = failed_login_attempts + 1,
                         last_failed_login = ?,
                         account_locked_until = ?
                     WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$currentTime, $lockoutUntil, $userId]);
        } else {
            $query = "UPDATE users 
                     SET failed_login_attempts = failed_login_attempts + 1,
                         last_failed_login = ?
                     WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$currentTime, $userId]);
        }
        
        debugLog(['user_id' => $userId, 'attempts' => 'incremented'], 'AUTH_FAILED_LOGIN');
    }
    
    private function resetFailedLoginAttempts($userId) {
        $query = "UPDATE users 
                 SET failed_login_attempts = 0,
                     last_failed_login = NULL,
                     account_locked_until = NULL
                 WHERE id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$userId]);
    }
    
    private function updateLoginTracking($userId) {
        $currentTime = date('Y-m-d H:i:s');
        $query = "UPDATE users 
                 SET last_login_at = ?,
                     login_count = login_count + 1,
                     last_login_ip = ?
                 WHERE id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$currentTime, $_SERVER['REMOTE_ADDR'] ?? '', $userId]);
    }
    
    private function setUserSession($user) {
        session_regenerate_id(true);
        
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();
    }
    
    private function setRememberMeCookie($userId) {
        $token = Security::generateToken(64);
        $hash = hash('sha256', $token);
        $expiry = time() + (30 * 24 * 3600); // 30 days
        
        // Store token hash in database (database-agnostic)
        $expiryDate = date('Y-m-d H:i:s', $expiry);
        
        // First try to update existing token
        $updateQuery = "UPDATE remember_tokens SET token_hash = ?, expires_at = ? WHERE user_id = ?";
        $stmt = $this->db->prepare($updateQuery);
        $stmt->execute([$hash, $expiryDate, $userId]);
        
        // If no rows affected, insert new token
        if ($stmt->rowCount() === 0) {
            $insertQuery = "INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($insertQuery);
            $stmt->execute([$userId, $hash, $expiryDate]);
        }
        
        // Set cookie
        setcookie('remember_token', $token, $expiry, '/', '', false, true);
        
        debugLog(['user_id' => $userId, 'expiry' => date('Y-m-d H:i:s', $expiry)], 'REMEMBER_ME_SET');
    }
    
    private function clearRememberMeCookie() {
        if (isset($_COOKIE['remember_token'])) {
            $hash = hash('sha256', $_COOKIE['remember_token']);
            
            // Remove token from database
            $query = "DELETE FROM remember_tokens WHERE token_hash = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$hash]);
            
            // Clear cookie
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
    }
    
    private function validateRememberMeToken($token) {
        $hash = hash('sha256', $token);
        
        $currentTime = date('Y-m-d H:i:s');
        $query = "SELECT rt.user_id, u.username, u.email, u.full_name, u.role 
                 FROM remember_tokens rt
                 JOIN users u ON rt.user_id = u.id
                 WHERE rt.token_hash = ? AND rt.expires_at > ? AND u.is_active = 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$hash, $currentTime]);
        $user = $stmt->fetch();
        
        if ($user) {
            $this->setUserSession($user);
            debugLog(['user_id' => $user['user_id']], 'REMEMBER_ME_LOGIN');
            return true;
        }
        
        // Invalid or expired token
        $this->clearRememberMeCookie();
        return false;
    }
    
    private function isAjaxRequest() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

// Global auth instance
$auth = new Auth();
?>
