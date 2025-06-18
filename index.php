<?php
// index.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/includes/header.php';
?><main class="container mt-5">
    <div class="jumbotron">
        <h1 class="display-4">Selamat Datang, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        <p class="lead">Ini adalah halaman utama dashboard Anda.</p>
        <hr class="my-4">
        <p>Gunakan navigasi untuk mengakses fitur sistem.</p>
    </div>
</main><?php
require_once __DIR__ . '/includes/footer.php';
?>