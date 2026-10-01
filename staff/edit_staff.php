<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$base = '../';
$active_menu = 'staff';
$page_title = 'Edit Staff - Management';
$page_header = 'Edit Akun Staff';

$id = (int)($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? AND role IN ('admin','staff') LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$user) {
    flash_set('Akun tidak ditemukan.', 'danger');
    header('Location: management_staff.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $role      = $_POST['role'] ?? $user['role'];
    $status    = $_POST['status'] ?? $user['status'];
    $password  = trim($_POST['password'] ?? '');

    if (!in_array($role, ['admin', 'staff'], true)) $role = $user['role'];
    if (!in_array($status, ['aktif', 'nonaktif'], true)) $status = $user['status'];

    if ($full_name === '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    }

    if (!$errors) {
        if ($password !== '') {
            if (strlen($password) < 6) {
                $errors[] = 'Password baru minimal 6 karakter.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt2 = mysqli_prepare($conn, "UPDATE users SET full_name=?, role=?, status=?, password=? WHERE id=?");
                mysqli_stmt_bind_param($stmt2, 'ssssi', $full_name, $role, $status, $hash, $id);
            }
        } else {
            $stmt2 = mysqli_prepare($conn, "UPDATE users SET full_name=?, role=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt2, 'sssi', $full_name, $role, $status, $id);
        }

        if (!$errors) {
            mysqli_stmt_execute($stmt2);
            flash_set('Akun "' . $full_name . '" berhasil diperbarui.');
            header('Location: management_staff.php');
            exit;
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-header"><h3>Edit: <?= e($user['full_name']) ?> (<?= e($user['username']) ?>)</h3></div>
    <div style="padding:20px;">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
        <?php endif; ?>

        <form method="POST" action="edit_staff.php?id=<?= (int)$user['id'] ?>">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Nama Lengkap *</label>
                    <input type="text" name="full_name" value="<?= e($_POST['full_name'] ?? $user['full_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" <?= (int)$user['id'] === (int)current_user()['id'] ? 'disabled' : '' ?>>
                        <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" <?= (int)$user['id'] === (int)current_user()['id'] ? 'disabled' : '' ?>>
                        <option value="aktif" <?= $user['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= $user['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Password Baru</label>
                    <input type="password" name="password" placeholder="Kosongkan jika tidak ingin mengganti password">
                </div>
            </div>
            <div class="form-actions" style="margin-top:18px;">
                <button type="submit" class="btn btn-success">Simpan Perubahan</button>
                <a class="btn btn-outline" href="management_staff.php">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
