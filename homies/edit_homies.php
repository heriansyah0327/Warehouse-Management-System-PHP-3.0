<?php
require_once __DIR__ . '/../includes/auth.php';
require_management();

$base = '../';
$active_menu = 'homies';
$page_title = 'Edit Homies - Management';
$page_header = 'Edit Akun Homies';

$id = (int)($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? AND role = 'homies' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$homies = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$homies) {
    flash_set('Data homies tidak ditemukan.', 'danger');
    header('Location: management_homies.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $discord_id       = trim($_POST['discord_id'] ?? '');
    $status    = $_POST['status'] ?? $homies['status'];
    $password  = trim($_POST['password'] ?? '');

    if (!in_array($status, ['aktif', 'nonaktif'], true)) $status = $homies['status'];

    if ($full_name === '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    }

    if (!$errors) {
        if ($password !== '') {
            if (strlen($password) < 6) {
                $errors[] = 'Password baru minimal 6 karakter.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt2 = mysqli_prepare($conn, "UPDATE users SET full_name=?, phone=?, discord_id=?, status=?, password=? WHERE id=?");
                mysqli_stmt_bind_param($stmt2, 'sssssi', $full_name, $phone, $discord_id, $status, $hash, $id);
            }
        } else {
            $stmt2 = mysqli_prepare($conn, "UPDATE users SET full_name=?, phone=?, discord_id=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt2, 'ssssi', $full_name, $phone, $discord_id, $status, $id);
        }

        if (!$errors) {
            mysqli_stmt_execute($stmt2);
            flash_set('Data homies "' . $full_name . '" berhasil diperbarui.');
            header('Location: management_homies.php');
            exit;
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-header"><h3>Edit: <?= e($homies['full_name']) ?> (<?= e($homies['username']) ?>)</h3></div>
    <div style="padding:20px;">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
        <?php endif; ?>

        <form method="POST" action="edit_homies.php?id=<?= (int)$homies['id'] ?>">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Nama Lengkap *</label>
                    <input type="text" name="full_name" value="<?= e($_POST['full_name'] ?? $homies['full_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>No. HP</label>
                    <input type="tel" name="phone" value="<?= e($_POST['phone'] ?? $homies['phone']) ?>">
                </div>
                <div class="form-group">
                    <label>Discord ID</label>
                    <input type="text" name="discord_id" value="<?= e($_POST['discord_id'] ?? $homies['discord_id']) ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="aktif" <?= $homies['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= $homies['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Password Baru</label>
                    <input type="password" name="password" placeholder="Kosongkan jika tidak ingin mengganti password">
                </div>
            </div>
            <div class="form-actions" style="margin-top:18px;">
                <button type="submit" class="btn btn-success">Simpan Perubahan</button>
                <a class="btn btn-outline" href="management_homies.php">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
