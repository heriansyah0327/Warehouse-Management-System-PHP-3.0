<?php
// Halaman publik (tanpa login): homies kirim permintaan reset password.
// Permintaan muncul di Management > Request Password untuk diproses admin/staff.
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$sent  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $discord  = trim($_POST['discord_id'] ?? '');
    $catatan  = trim($_POST['catatan'] ?? '');

    if ($username === '' || $discord === '') {
        $error = 'Username dan Discord ID wajib diisi.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? AND role = 'homies' AND status = 'aktif' LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($user) {
            $uid = (int)$user['id'];

            // Cukup 1 permintaan menunggu per akun (cegah spam)
            $cek = mysqli_prepare($conn, "SELECT id FROM password_reset_request WHERE user_id = ? AND status = 'menunggu' LIMIT 1");
            mysqli_stmt_bind_param($cek, 'i', $uid);
            mysqli_stmt_execute($cek);
            $sudahAda = mysqli_fetch_assoc(mysqli_stmt_get_result($cek));

            if (!$sudahAda) {
                $ins = mysqli_prepare($conn, "INSERT INTO password_reset_request (user_id, discord_id_input, catatan) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($ins, 'iss', $uid, $discord, $catatan);
                mysqli_stmt_execute($ins);
            }
        }

        // Pesan sama apapun hasilnya, supaya orang luar tidak bisa menebak username yang terdaftar.
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lupa Password - Management</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1>🔑 Lupa Password</h1>

        <?php if ($sent): ?>
            <p class="subtitle">Permintaan terkirim.</p>
            <div class="alert alert-success">
                Kalau username kamu terdaftar, permintaan reset password sudah diteruskan ke Admin/Staff.
                Tunggu mereka menyetujui, lalu login dengan password sementara yang diberikan.
                Kamu akan diminta membuat password baru saat login.
            </div>
            <div class="login-links"><a href="index.php">← Kembali ke halaman login</a></div>
        <?php else: ?>
            <p class="subtitle">Kirim permintaan reset ke Admin/Staff.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="lupa_password.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label for="discord_id">Discord ID</label>
                    <input type="text" id="discord_id" name="discord_id" value="<?= e($_POST['discord_id'] ?? '') ?>" required>
                    <span class="hint">Dipakai Admin/Staff untuk memastikan ini benar kamu.</span>
                </div>
                <div class="form-group">
                    <label for="catatan">Catatan (opsional)</label>
                    <input type="text" id="catatan" name="catatan" maxlength="255" value="<?= e($_POST['catatan'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary">Kirim Permintaan</button>
            </form>
            <div class="login-links"><a href="index.php">← Kembali ke halaman login</a></div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
