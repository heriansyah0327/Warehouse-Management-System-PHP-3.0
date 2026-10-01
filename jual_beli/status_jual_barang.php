<?php
require_once __DIR__ . '/../includes/auth.php';
require_jual_beli();

$base = '../';
$active_menu = 'status_transaksi';   // satu menu dengan Status Transaksi
$page_title = 'Status Transaksi - Management';
$page_header = 'Status Transaksi';

$uid = (int)current_user()['id'];

// ---------------------------------------------------
// Batalkan pengajuan sendiri (hanya yang masih "menunggu"). Tidak ada stok
// yang perlu dikembalikan karena stok belum pernah ditambahkan.
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batalkan'])) {
    $jid = (int)$_POST['batalkan'];

    $stmt = mysqli_prepare($conn, "UPDATE jual_barang SET status = 'dibatalkan' WHERE id = ? AND user_id = ? AND status = 'menunggu'");
    mysqli_stmt_bind_param($stmt, 'ii', $jid, $uid);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) > 0) {
        flash_set('Pengajuan jual barang #' . $jid . ' berhasil dibatalkan.');
    } else {
        flash_set('Pengajuan #' . $jid . ' tidak bisa dibatalkan (sudah diproses atau bukan pengajuanmu).', 'danger');
    }
    header('Location: status_jual_barang.php');
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT j.*,
               (SELECT GROUP_CONCAT(CONCAT(i.nama_produk, ' x', i.qty) SEPARATOR ', ') FROM jual_barang_item i WHERE i.jual_barang_id = j.id) AS ringkasan,
               (SELECT COALESCE(SUM(i.qty),0) FROM jual_barang_item i WHERE i.jual_barang_id = j.id) AS total_qty,
               (SELECT COALESCE(SUM(i.qty * COALESCE(p.harga_beli,0)),0) FROM jual_barang_item i LEFT JOIN produk p ON p.id = i.produk_id WHERE i.jual_barang_id = j.id) AS total_harga
        FROM jual_barang j WHERE j.user_id = ? ORDER BY j.created_at DESC, j.id DESC");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$list = [];
while ($row = mysqli_fetch_assoc($res)) $list[] = $row;

// Item per pengajuan (untuk popup detail), lengkap dengan harga beli saat ini
$itemsBy = [];
if ($list) {
    $ids = implode(',', array_map('intval', array_column($list, 'id')));
    $ri = mysqli_query($conn, "SELECT i.*, COALESCE(p.harga_beli,0) AS harga_beli
                                FROM jual_barang_item i LEFT JOIN produk p ON p.id = i.produk_id
                                WHERE i.jual_barang_id IN ($ids) ORDER BY i.id ASC");
    while ($it = mysqli_fetch_assoc($ri)) $itemsBy[(int)$it['jual_barang_id']][] = $it;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-tabs">
    <a class="page-tab" href="status_transaksi.php">📋 Status Pesanan</a>
    <a class="page-tab active" href="status_jual_barang.php">🧳 Status Jual Barang</a>
</div>

<div class="panel">
    <div class="panel-toolbar">
        <div class="search-box">
            <input type="text" placeholder="Cari pengajuan..." data-table-search="#statusJualBarangTable">
            <span class="icon">🔍</span>
        </div>
    </div>

    <table class="data-table" id="statusJualBarangTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tanggal</th>
                <th>Produk</th>
                <th>Jumlah</th>
                <th>Subtotal</th>
                <th>Status</th>
                <th>Tindakan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($list)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; color:var(--text-muted);">
                        Kamu belum punya pengajuan jual barang. <a href="jual_barang.php">Jual Barang</a>
                    </td>
                </tr>
            <?php else: foreach ($list as $o):
                $detail = [
                    'id'         => (int)$o['id'],
                    'tanggal'    => date('d M Y, H:i', strtotime($o['created_at'])),
                    'items'      => array_map(function ($it) {
                        return [
                            'nama'     => $it['nama_produk'],
                            'harga'    => rupiah($it['harga_beli']),
                            'qty'      => (int)$it['qty'],
                            'subtotal' => rupiah($it['harga_beli'] * $it['qty']),
                        ];
                    }, $itemsBy[(int)$o['id']] ?? []),
                    'catatan'    => $o['catatan'] ?: '',
                    'status'     => $o['status'],
                    'status_label' => status_label_jual_barang($o['status']),
                    'total'      => rupiah($o['total_harga']),
                ];
            ?>
                <tr>
                    <td>#<?= (int)$o['id'] ?></td>
                    <td><?= e(date('d M Y, H:i', strtotime($o['created_at']))) ?></td>
                    <td><?= e($o['ringkasan'] ?? '-') ?></td>
                    <td><?= (int)$o['total_qty'] ?></td>
                    <td><?= rupiah($o['total_harga']) ?></td>
                    <td><span class="badge badge-<?= $o['status'] === 'diacc' ? 'selesai' : e($o['status']) ?>"><?= e(status_label_jual_barang($o['status'])) ?></span></td>
                    <td>
                        <button type="button" class="btn btn-outline" data-jualbarang-user-detail="<?= e(json_encode($detail, JSON_UNESCAPED_UNICODE)) ?>">Detail</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    <div class="table-footer">Menampilkan <?= count($list) ?> pengajuan</div>
</div>

<!-- Popup Detail Jual Barang -->
<div class="modal-overlay" id="modalDetailJualBarangUser">
    <div class="modal-box">
        <button class="modal-close" data-close-modal="modalDetailJualBarangUser">✕</button>
        <span class="modal-tag">Jual Barang</span>
        <h2 id="jbuTitle">Detail Jual Barang</h2>

        <div class="od-meta" id="jbuMeta"></div>

        <table class="data-table od-table">
            <thead>
                <tr><th>Produk</th><th>Harga</th><th>Jml</th><th>Subtotal</th></tr>
            </thead>
            <tbody id="jbuItem"></tbody>
        </table>

        <div class="total-row" style="margin:14px 0;">
            <span>Total</span> <strong id="jbuTotal"></strong>
        </div>

        <!-- Batalkan (status Menunggu) -->
        <form method="POST" action="status_jual_barang.php" id="jbuBatalForm" data-confirm="Yakin mau membatalkan pengajuan ini?">
            <input type="hidden" name="batalkan" id="jbuBatalId" value="">
            <div class="form-actions">
                <button type="submit" class="btn btn-danger">✕ Batalkan Pengajuan</button>
            </div>
        </form>

        <div id="jbuDone" class="hint" style="display:none;"></div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>