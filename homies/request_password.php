<?php
require_once __DIR__ . '/../includes/auth.php';
require_management();   // admin & staff

$base = '../';
$active_menu = 'request_password';
$page_title = 'Request Password - Management';
$page_header = 'Request Reset Password';

$statusList = ['menunggu', 'disetujui', 'ditolak'];
$filter = $_GET['status'] ?? '';
if (!in_array($filter, $statusList, true)) $filter = '';

// ---------------------------------------------------
// Proses: setujui (password jadi default + wajib ganti) / tolak
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rid  = (int)($_POST['id'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';
    $me   = (int)current_user()['id'];

    if ($rid <= 0 || !in_array($aksi, ['setuju', 'tolak'], true)) {
        flash_set('Permintaan tidak valid.', 'danger');
        header('Location: request_password.php');
        exit;
    }

    $statusBaru = $aksi === 'setuju' ? 'disetujui' : 'ditolak';

    mysqli_begin_transaction($conn);
    try {
        // Hanya permintaan yang masih "menunggu" yang bisa diproses
        $stmt = mysqli_prepare($conn, "UPDATE password_reset_request SET status = ?, processed_by = ?, processed_at = NOW() WHERE id = ? AND status = 'menunggu'");
        mysqli_stmt_bind_param($stmt, 'sii', $statusBaru, $me, $rid);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $msg = 'Permintaan #' . $rid . ' ditolak.';

            if ($aksi === 'setuju') {
                $q = mysqli_prepare($conn, "SELECT u.id, u.username FROM password_reset_request r JOIN users u ON u.id = r.user_id WHERE r.id = ? AND u.role = 'homies' LIMIT 1");
                mysqli_stmt_bind_param($q, 'i', $rid);
                mysqli_stmt_execute($q);
                $target = mysqli_fetch_assoc(mysqli_stmt_get_result($q));

                if (!$target) {
                    throw new RuntimeException('Akun tidak ditemukan');
                }

                $hash = password_hash(DEFAULT_RESET_PASSWORD, PASSWORD_DEFAULT);
                $up = mysqli_prepare($conn, "UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?");
                $tid = (int)$target['id'];
                mysqli_stmt_bind_param($up, 'si', $hash, $tid);
                mysqli_stmt_execute($up);

                $msg = 'Disetujui. Password ' . $target['username'] . ' direset ke ' . DEFAULT_RESET_PASSWORD
                     . ', dia akan diminta buat password baru saat login. Kabari orangnya ya.';
            }

            mysqli_commit($conn);
            flash_set($msg);
        } else {
            mysqli_rollback($conn);
            flash_set('Permintaan #' . $rid . ' sudah diproses sebelumnya atau tidak ditemukan.', 'danger');
        }
    } catch (Throwable $ex) {
        mysqli_rollback($conn);
        flash_set('Gagal memproses permintaan, coba lagi.', 'danger');
    }

    header('Location: request_password.php' . ($filter !== '' ? '?status=' . $filter : ''));
    exit;
}

// ---------------------------------------------------
// Daftar permintaan
// ---------------------------------------------------
$sql = "SELECT r.*, u.full_name, u.username, u.phone, u.discord_id AS discord_akun,
               pb.full_name AS diproses_oleh
        FROM password_reset_request r
        JOIN users u ON u.id = r.user_id
        LEFT JOIN users pb ON pb.id = r.processed_by";

if ($filter !== '') {
    $stmt = mysqli_prepare($conn, $sql . " WHERE r.status = ? ORDER BY r.created_at DESC, r.id DESC LIMIT 200");
    mysqli_stmt_bind_param($stmt, 's', $filter);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
} else {
    $res = mysqli_query($conn, $sql . " ORDER BY FIELD(r.status,'menunggu','disetujui','ditolak'), r.created_at DESC, r.id DESC LIMIT 200");
}

$requests = [];
while ($row = mysqli_fetch_assoc($res)) $requests[] = $row;

include __DIR__ . '/../includes/header.php';
?>

<div class="chip-row">
    <a class="chip <?= $filter === '' ? 'active' : '' ?>" href="request_password.php">Semua</a>
    <?php foreach ($statusList as $s): ?>
        <a class="chip <?= $filter === $s ? 'active' : '' ?>" href="request_password.php?status=<?= e($s) ?>"><?= e(reset_status_label($s)) ?></a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <div class="panel-toolbar">
        <div class="search-box">
            <input type="text" placeholder="Cari homies..." data-table-search="#resetTable">
            <span class="icon">🔍</span>
        </div>
    </div>

    <table class="data-table" id="resetTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tanggal</th>
                <th>Homies</th>
                <th>Discord ID (diisi)</th>
                <th>Verifikasi</th>
                <th>Catatan</th>
                <th>Status</th>
                <th>Tindakan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($requests)): ?>
                <tr><td colspan="8" style="text-align:center; color:var(--text-muted);">Belum ada permintaan reset password.</td></tr>
            <?php else: foreach ($requests as $r):
                $akun  = trim((string)$r['discord_akun']);
                $input = trim((string)$r['discord_id_input']);
                if ($akun === '') {
                    $vLabel = 'Belum diisi di akun'; $vClass = 'nonaktif';
                } elseif (strcasecmp($akun, $input) === 0) {
                    $vLabel = 'Cocok'; $vClass = 'cocok';
                } else {
                    $vLabel = 'Beda'; $vClass = 'beda';
                }
            ?>
                <tr>
                    <td>#<?= (int)$r['id'] ?></td>
                    <td><?= e(date('d M Y, H:i', strtotime($r['created_at']))) ?></td>
                    <td><?= e($r['full_name']) ?> <span class="muted">(<?= e($r['username']) ?>)</span></td>
                    <td><?= e($input ?: '-') ?></td>
                    <td>
                        <span class="badge badge-<?= e($vClass) ?>"><?= e($vLabel) ?></span>
                        <?php if ($akun !== ''): ?><div class="muted">Di akun: <?= e($akun) ?></div><?php endif; ?>
                    </td>
                    <td><?= e($r['catatan'] ?: '-') ?></td>
                    <td><span class="badge badge-<?= e($r['status']) ?>"><?= e(reset_status_label($r['status'])) ?></span></td>
                    <td>
                        <?php if ($r['status'] === 'menunggu'): ?>
                            <div class="aksi-inline">
                                <form method="POST" action="request_password.php<?= $filter !== '' ? '?status=' . e($filter) : '' ?>"
                                      data-confirm="Setujui? Password <?= e($r['username']) ?> akan direset ke <?= e(DEFAULT_RESET_PASSWORD) ?> dan dia wajib buat password baru saat login.">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" name="aksi" value="setuju" class="btn btn-success">✔ Setujui</button>
                                </form>
                                <form method="POST" action="request_password.php<?= $filter !== '' ? '?status=' . e($filter) : '' ?>"
                                      data-confirm="Tolak permintaan reset password <?= e($r['username']) ?>?">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" name="aksi" value="tolak" class="btn btn-danger">✕ Tolak</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="muted">
                                <?= e($r['diproses_oleh'] ?: '-') ?>
                                <?php if ($r['processed_at']): ?>· <?= e(date('d M Y, H:i', strtotime($r['processed_at']))) ?><?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    <div class="table-footer">Menampilkan <?= count($requests) ?> permintaan</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
