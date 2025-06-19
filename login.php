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
                "SELECT id, username, email, password, role, status FROM users WHERE (username = :username OR email = :username) AND status = 'active'",
                ['username' => $username]
            );

            if ($user && password_verify($password, $user['password'])) {
                debugLog(['user_id' => $user['id'], 'username' => $user['username']], 'LOGIN_SUCCESS');
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Update last login
                $db->update(
                    'users',
                    ['last_login_at' => date('Y-m-d H:i:s')],
                    'id = :id',
                    ['id' => $user['id']]
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
    <title>Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
        <form method="post">
            <label>Username</label>
            <input type="text" name="username" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <button type="submit">Masuk</button>
        </form>
    </div>
</body>
</html>
