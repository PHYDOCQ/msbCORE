<?php
// login.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

debugLog(['page' => 'login', 'method' => $_SERVER['REQUEST_METHOD']], 'LOGIN_PAGE_ACCESS');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debugLog(['action' => 'login_attempt', 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'], 'LOGIN_POST');
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        debugLog(['username' => $username, 'has_password' => !empty($password)], 'LOGIN_CREDENTIALS');
        
        try {
            $db = Database::getInstance();
            $user = $db->selectOne(
                "SELECT id, username, email, password_hash, role, status FROM users WHERE (username = ? OR email = ?) AND status = 'active'",
                [$username, $username]
            );

            if ($user && password_verify($password, $user['password_hash'])) {
                debugLog(['user_id' => $user['id'], 'username' => $user['username']], 'LOGIN_SUCCESS');
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Update last login
                $db->update(
                    'users',
                    ['last_login' => date('Y-m-d H:i:s')],
                    'id = ?',
                    [$user['id']]
                );
                
                header('Location: index.php');
                exit();
            } else {
                debugLog(['username' => $username, 'reason' => 'invalid_credentials'], 'LOGIN_FAILED');
                $error = 'Username atau password salah';
            }
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'username' => $username], 'LOGIN_ERROR');
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    } else {
        debugLog(['missing_username' => empty($username), 'missing_password' => empty($password)], 'LOGIN_VALIDATION_ERROR');
        $error = 'Lengkapi semua field';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    
    <!-- Meta tags -->
    <meta name="description" content="<?php echo APP_NAME; ?> - Sistem manajemen bengkel profesional">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
</head>
<body>
    <!-- Toast Container -->
    <div class="toast-container"></div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-message">Processing...</div>
        </div>
    </div>
    
    <div class="login-container">
        <div class="login-card fade-in">
            <div class="login-header">
                <div class="text-center mb-3">
                    <i class="fas fa-tools fa-3x text-primary mb-3"></i>
                </div>
                <h1 class="login-title"><?php echo APP_NAME; ?></h1>
                <p class="login-subtitle">Masuk ke sistem manajemen bengkel</p>
            </div>
            
            <form method="post" class="login-form" id="loginForm" novalidate>
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-user me-2"></i>Username atau Email
                    </label>
                    <input 
                        type="text" 
                        id="username"
                        name="username" 
                        class="form-control" 
                        placeholder="Masukkan username atau email"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        required
                        autocomplete="username"
                    >
                    <div class="invalid-feedback"></div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                    <div class="position-relative">
                        <input 
                            type="password" 
                            id="password"
                            name="password" 
                            class="form-control" 
                            placeholder="Masukkan password"
                            required
                            autocomplete="current-password"
                        >
                        <button 
                            type="button" 
                            class="btn btn-link position-absolute end-0 top-50 translate-middle-y pe-3" 
                            id="togglePassword"
                            style="border: none; background: none; z-index: 10;"
                        >
                            <i class="fas fa-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback"></div>
                </div>
                
                <div class="form-group mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me">
                        <label class="form-check-label" for="rememberMe">
                            Ingat saya
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="login-btn" id="loginBtn">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    <span class="btn-text">Masuk</span>
                    <span class="btn-loading d-none">
                        <i class="fas fa-spinner fa-spin me-2"></i>Memproses...
                    </span>
                </button>
            </form>
            
            <div class="text-center mt-4">
                <small class="text-muted">
                    <i class="fas fa-shield-alt me-1"></i>
                    Sistem aman dan terpercaya
                </small>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        // Show error message if exists
        <?php if ($error): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showError('<?php echo addslashes($error); ?>', {
                title: 'Login Gagal',
                duration: 6000,
                sound: true
            });
        });
        <?php endif; ?>
        
        // Enhanced login form handling
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const togglePassword = document.getElementById('togglePassword');
            const passwordField = document.getElementById('password');
            const togglePasswordIcon = document.getElementById('togglePasswordIcon');
            
            // Password visibility toggle
            togglePassword.addEventListener('click', function() {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                
                if (type === 'text') {
                    togglePasswordIcon.classList.remove('fa-eye');
                    togglePasswordIcon.classList.add('fa-eye-slash');
                } else {
                    togglePasswordIcon.classList.remove('fa-eye-slash');
                    togglePasswordIcon.classList.add('fa-eye');
                }
            });
            
            // Simple form validation without preventing submission
            loginForm.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;
                
                // Basic validation
                if (!username || !password) {
                    e.preventDefault();
                    alert('Mohon lengkapi username dan password');
                    return false;
                }
                
                // Show loading state
                setLoadingState(true);
                
                // Let the form submit normally (no preventDefault)
                console.log('Form submitting with username:', username);
                return true;
            });
            
            // Real-time validation
            document.getElementById('username').addEventListener('input', function() {
                if (this.value.trim()) {
                    clearFieldError('username');
                }
            });
            
            document.getElementById('password').addEventListener('input', function() {
                if (this.value.length >= 3) {
                    clearFieldError('password');
                }
            });
            
            function showFieldError(fieldId, message) {
                const field = document.getElementById(fieldId);
                const feedback = field.parentNode.querySelector('.invalid-feedback');
                
                field.classList.add('is-invalid');
                field.classList.remove('is-valid');
                feedback.textContent = message;
            }
            
            function clearFieldError(fieldId) {
                const field = document.getElementById(fieldId);
                const feedback = field.parentNode.querySelector('.invalid-feedback');
                
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
                feedback.textContent = '';
            }
            
            function setLoadingState(loading) {
                const btnText = loginBtn.querySelector('.btn-text');
                const btnLoading = loginBtn.querySelector('.btn-loading');
                
                if (loading) {
                    btnText.classList.add('d-none');
                    btnLoading.classList.remove('d-none');
                    loginBtn.disabled = true;
                    document.getElementById('loadingOverlay').classList.add('show');
                } else {
                    btnText.classList.remove('d-none');
                    btnLoading.classList.add('d-none');
                    loginBtn.disabled = false;
                    document.getElementById('loadingOverlay').classList.remove('show');
                }
            }
            
            // Auto-focus on username field
            document.getElementById('username').focus();
            
            // Add subtle animations
            setTimeout(() => {
                document.querySelector('.login-card').classList.add('zoom-in');
            }, 100);
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Enter key to submit
            if (e.key === 'Enter' && !e.shiftKey) {
                const activeElement = document.activeElement;
                if (activeElement.tagName === 'INPUT') {
                    document.getElementById('loginForm').dispatchEvent(new Event('submit'));
                }
            }
        });
        
        // Connection status monitoring
        window.addEventListener('online', function() {
            showSuccess('Koneksi internet tersambung kembali', {
                title: 'Online',
                duration: 3000
            });
        });
        
        window.addEventListener('offline', function() {
            showWarning('Koneksi internet terputus', {
                title: 'Offline',
                duration: 0 // Don't auto-dismiss
            });
        });
    </script>
</body>
</html>
