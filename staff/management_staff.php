<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$base = '../';
$active_menu = 'staff';
$page_title = 'Management Staff - Management';
$page_header = 'Management Staff';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $username  = trim($_POST['username'] ?? '');
    $password  = trim($_POST['password'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role      = $_POST['role'] ?? 'staff';

    if (!in_array($role, ['admin', 'staff'], true)) {
        $role = 'staff';
    }

    if ($username === '' || $password === '' || $full_name === '') {
        $errors[] = 'Semua kolom wajib diisi.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    } else {
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($check, 's', $username);
        mysqli_stmt_execute($check);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($check))) {
            $errors[] = 'Username sudah dipakai, pilih username lain.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssss', $username, $hash, $full_name, $role);
            mysqli_stmt_execute($stmt);
            flash_set('Akun ' . role_label($role) . ' "' . $full_name . '" berhasil ditambahkan.');
            header('Location: management_staff.php');
            exit;
        }
    }
}

$users = [];
$res = mysqli_query($conn, "SELECT * FROM users WHERE role IN ('admin','staff') ORDER BY role ASC, full_name ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $users[] = $row;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-toolbar">
        <div class="search-box">
            <input type="text" placeholder="Cari staff..." data-table-search="#staffTable">
            <span class="icon">🔍</span>
        </div>
        <button class="btn btn-primary" data-open-modal="modalTambahStaff">+ Tambah Akun</button>
    </div>

    <table class="data-table" id="staffTable">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Lengkap</th>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>Tindakan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">Belum ada akun.</td></tr>
            <?php else: foreach ($users as $i => $u): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($u['full_name']) ?></td>
                    <td><?= e($u['username']) ?></td>
                    <td><span class="badge badge-<?= e($u['role']) ?>"><?= e(role_label($u['role'])) ?></span></td>
                    <td><span class="badge badge-<?= e($u['status']) ?>"><?= ucfirst($u['status']) ?></span></td>
                    <td class="aksi-cell">
                        <a class="btn-icon edit" href="edit_staff.php?id=<?= (int)$u['id'] ?>" title="Edit">✏️</a>
                        <?php if ((int)$u['id'] !== (int)current_user()['id']): ?>
                            <button type="button" class="btn-icon delete" title="Hapus"
                                data-delete-url="hapus_staff.php?id=<?= (int)$u['id'] ?>"
                                data-delete-label="akun &quot;<?= e($u['full_name']) ?>&quot;">🗑️</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    <div class="table-footer">Jumlah akun: <?= count($users) ?></div>
</div>

<div class="modal-overlay" id="modalTambahStaff">
    <div class="modal-box">
        <button class="modal-close" data-close-modal="modalTambahStaff">✕</button>
        <span class="modal-tag">Staff</span>
        <h2>Tambah Akun Staff / Admin</h2>

        <?php if ($errors): ?>
            <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
        <?php endif; ?>

        <form method="POST" action="management_staff.php">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Nama Lengkap *</label>
                    <input type="text" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="staff" <?= (($_POST['role'] ?? '') === 'staff') ? 'selected' : '' ?>>Staff</option>
                        <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" required>
                </div>
                <div class="form-group full">
                    <label>Password *</label>
                    <input type="password" name="password" placeholder="Minimal 6 karakter" required>
                </div>
            </div>
            <div class="form-actions" style="margin-top:18px;">
                <button type="submit" name="add_staff" value="1" class="btn btn-success">Simpan</button>
                <button type="button" class="btn btn-outline" data-close-modal="modalTambahStaff">Batalkan</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
