<?php
// Ganti password sendiri.
// Mode biasa : dibuka dari tombol "Ganti Password" di topbar (wajib isi password lama).
// Mode paksa : akun baru direset admin/staff (must_change_password = 1). Semua halaman lain
//              diblokir oleh require_login() sampai password baru dibuat.
require_once __DIR__ . '/includes/auth.php';
require_login();

$base = '';
$active_menu = 'ganti_password';
$page_title = 'Ganti Password - Management';
$page_header = 'Ganti Password';

$forced = !empty($_SESSION['must_change_password']);
$me     = current_user();
$uid    = (int)$me['id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old     = trim($_POST['old_password'] ?? '');
    $new     = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $currentHash = $row['password'] ?? '';

    if (!$forced && !password_verify($old, $currentHash)) {
        $errors[] = 'Password lama salah.';
    } elseif (strlen($new) < 6) {
        $errors[] = 'Password baru minimal 6 karakter.';
    } elseif ($new !== $confirm) {
        $errors[] = 'Konfirmasi password baru tidak sama.';
    } elseif (password_verify($new, $currentHash)) {
        $errors[] = 'Password baru tidak boleh sama dengan password yang sekarang.';
    }

    if (!$errors) {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $up = mysqli_prepare($conn, "UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
        mysqli_stmt_bind_param($up, 'si', $hash, $uid);
        mysqli_stmt_execute($up);

        unset($_SESSION['must_change_password']);
        flash_set('Password berhasil diganti.');
        header('Location: dashboard.php');
        exit;
    }
}

// Form dipakai di dua layout (halaman biasa & halaman paksa)
ob_start();
?>
<?php if ($errors): ?>
    <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>
<form method="POST" action="ganti_password.php" autocomplete="off">
    <?php if (!$forced): ?>
    <div class="form-group" style="margin-bottom:14px;">
        <label for="old_password">Password Lama</label>
        <input type="password" id="old_password" name="old_password" required>
    </div>
    <?php endif; ?>
    <div class="form-group" style="margin-bottom:14px;">
        <label for="new_password">Password Baru</label>
        <input type="password" id="new_password" name="new_password" placeholder="Minimal 6 karakter" required <?= $forced ? 'autofocus' : '' ?>>
    </div>
    <div class="form-group" style="margin-bottom:18px;">
        <label for="confirm_password">Ulangi Password Baru</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-success">Simpan Password</button>
        <?php if (!$forced): ?><a class="btn btn-outline" href="dashboard.php">Batal</a><?php endif; ?>
    </div>
</form>
<?php
$formHtml = ob_get_clean();

if ($forced): ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Buat Password Baru - Management</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1>🔑 Buat Password Baru</h1>
        <p class="subtitle">Halo, <?= e($me['full_name']) ?>. Passwordmu baru direset, buat password baru dulu sebelum lanjut.</p>
        <?= $formHtml ?>
        <div class="login-links"><a href="logout.php">Keluar</a></div>
    </div>
</div>
</body>
</html>
<?php else:
    include __DIR__ . '/includes/header.php'; ?>

<div class="panel" style="max-width:480px;">
    <div class="panel-header"><h3>Ganti Password</h3></div>
    <div style="padding:20px;">
        <?= $formHtml ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php';
endif; ?>
