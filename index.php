<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, username, password, full_name, role, status, must_change_password FROM users WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Username atau password salah.';
        } elseif ($user['status'] === 'nonaktif') {
            $error = 'Akun ini sudah dinonaktifkan.';
        } else {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];

            // Password baru direset admin/staff -> wajib buat password baru dulu
            if ((int)$user['must_change_password'] === 1) {
                $_SESSION['must_change_password'] = true;
                header('Location: ganti_password.php');
                exit;
            }
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - Management</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1>📦 Management</h1>
        <p class="subtitle">Masuk untuk melanjutkan.</p>

        <?php if (isset($_GET['denied'])): ?>
            <div class="alert alert-danger">Akun kamu tidak punya akses ke halaman itu.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>


        <form method="POST" action="index.php">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Masuk</button>
        </form>
        <div class="login-links"><a href="lupa_password.php">Lupa password?</a></div>
    </div>
</div>
</body>
</html>
