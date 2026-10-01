<?php
require_once __DIR__ . '/../includes/auth.php';
require_management();

$base = '../';
$active_menu = 'homies';
$page_title = 'Management Homies - Management';
$page_header = 'Management Homies';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_homies'])) {
    $username  = trim($_POST['username'] ?? '');
    $password  = trim($_POST['password'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $discord_id       = trim($_POST['discord_id'] ?? '');

    if ($username === '' || $password === '' || $full_name === '') {
        $errors[] = 'Nama, username, dan password wajib diisi.';
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
            $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, full_name, phone, discord_id, role) VALUES (?, ?, ?, ?, ?, 'homies')");
            mysqli_stmt_bind_param($stmt, 'sssss', $username, $hash, $full_name, $phone, $discord_id);
            mysqli_stmt_execute($stmt);
            flash_set('Homies "' . $full_name . '" berhasil ditambahkan dan sudah bisa login.');
            header('Location: management_homies.php');
            exit;
        }
    }
}

$homiesList = [];
$res = mysqli_query($conn, "SELECT * FROM users WHERE role = 'homies' ORDER BY full_name ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $homiesList[] = $row;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-toolbar">
        <div class="search-box">
            <input type="text" placeholder="Cari homies..." data-table-search="#homiesTable">
            <span class="icon">🔍</span>
        </div>
        <button class="btn btn-primary" data-open-modal="modalTambahHomies">+ Tambah Homies</button>
    </div>

    <table class="data-table" id="homiesTable">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama</th>
                <th>Username</th>
                <th>No. HP</th>
                <th>Discord ID</th>
                <th>Status</th>
                <th>Tindakan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($homiesList)): ?>
                <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">Belum ada data homies.</td></tr>
            <?php else: foreach ($homiesList as $i => $h): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($h['full_name']) ?></td>
                    <td><?= e($h['username']) ?></td>
                    <td><?= e($h['phone'] ?: '-') ?></td>
                    <td><?= e($h['discord_id'] ?: '-') ?></td>
                    <td><span class="badge badge-<?= e($h['status']) ?>"><?= ucfirst($h['status']) ?></span></td>
                    <td class="aksi-cell">
                        <a class="btn-icon edit" href="edit_homies.php?id=<?= (int)$h['id'] ?>" title="Edit">✏️</a>
                        <button type="button" class="btn-icon delete" title="Hapus"
                            data-delete-url="hapus_homies.php?id=<?= (int)$h['id'] ?>"
                            data-delete-label="homies &quot;<?= e($h['full_name']) ?>&quot;">🗑️</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    <div class="table-footer">Jumlah homies: <?= count($homiesList) ?></div>
</div>

<div class="modal-overlay" id="modalTambahHomies">
    <div class="modal-box">
        <button class="modal-close" data-close-modal="modalTambahHomies">✕</button>
        <span class="modal-tag">Homies</span>
        <h2>Tambah Akun Homies</h2>
        <p class="hint" style="margin-top:-10px; margin-bottom:16px;">Homies punya akun login sendiri, sama seperti Staff.</p>

        <?php if ($errors): ?>
            <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
        <?php endif; ?>

        <form method="POST" action="management_homies.php">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Nama Lengkap *</label>
                    <input type="text" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>No. HP</label>
                    <input type="tel" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Discord ID</label>
                    <input type="text" name="discord_id" value="<?= e($_POST['discord_id'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" placeholder="Minimal 6 karakter" required>
                </div>
            </div>
            <div class="form-actions" style="margin-top:18px;">
                <button type="submit" name="add_homies" value="1" class="btn btn-success">Simpan</button>
                <button type="button" class="btn btn-outline" data-close-modal="modalTambahHomies">Batalkan</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
