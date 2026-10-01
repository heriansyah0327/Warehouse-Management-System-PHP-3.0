<?php
require_once __DIR__ . '/../includes/auth.php';
require_jual_beli();

$base = '../';
$active_menu = 'status_transaksi';
$page_title = 'Status Transaksi - Management';
$page_header = 'Status Transaksi';

$uid = (int)current_user()['id'];

// ---------------------------------------------------
// Batalkan pesanan sendiri (hanya yang masih "menunggu")
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batalkan'])) {
    $oid = (int)$_POST['batalkan'];

    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn, "UPDATE pesanan SET status = 'dibatalkan' WHERE id = ? AND user_id = ? AND status = 'menunggu'");
        mysqli_stmt_bind_param($stmt, 'ii', $oid, $uid);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            restore_stok_pesanan($conn, $oid);   // stok balik lagi
            mysqli_commit($conn);
            flash_set('Pesanan #' . $oid . ' berhasil dibatalkan.');
        } else {
            mysqli_rollback($conn);
            flash_set('Pesanan #' . $oid . ' tidak bisa dibatalkan (sudah diproses atau bukan pesananmu).', 'danger');
        }
    } catch (Throwable $ex) {
        mysqli_rollback($conn);
        flash_set('Gagal membatalkan pesanan, coba lagi.', 'danger');
    }
    header('Location: status_transaksi.php');
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT * FROM pesanan WHERE user_id = ? ORDER BY created_at DESC, id DESC");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$pesananList = [];
while ($row = mysqli_fetch_assoc($res)) {
    $st = mysqli_prepare($conn, "SELECT nama_produk, harga, qty, subtotal FROM pesanan_item WHERE pesanan_id = ? ORDER BY id ASC");
    $pid = (int)$row['id'];
    mysqli_stmt_bind_param($st, 'i', $pid);
    mysqli_stmt_execute($st);
    $rItems = mysqli_stmt_get_result($st);
    $row['items'] = [];
    while ($it = mysqli_fetch_assoc($rItems)) $row['items'][] = $it;
    $pesananList[] = $row;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-tabs">
    <a class="page-tab active" href="status_transaksi.php">📋 Status Pesanan</a>
    <a class="page-tab" href="status_jual_barang.php">🧳 Status Jual Barang</a>
</div>

<div class="panel">
    <div class="panel-toolbar">
        <div class="search-box">
            <input type="text" placeholder="Cari pesanan..." data-table-search="#pesananTable">
            <span class="icon">🔍</span>
        </div>
    </div>

    <table class="data-table" id="pesananTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tanggal</th>
                <th>Item</th>
                <th>Subtotal</th>
                <th>Status</th>
                <th>Tindakan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pesananList)): ?>
                <tr>
                    <td colspan="6" style="text-align:center; color:var(--text-muted);">
                        Kamu belum punya pesanan. <a href="market.php">Belanja di Market</a>
                    </td>
                </tr>
            <?php else: foreach ($pesananList as $o):
                $detail = [
                    'id'           => (int)$o['id'],
                    'tanggal'      => date('d M Y, H:i', strtotime($o['created_at'])),
                    'catatan'      => $o['catatan'] ?: '',
                    'status'       => $o['status'],
                    'status_label' => status_label($o['status']),
                    'total'        => rupiah($o['total']),
                    'items'        => array_map(function ($it) {
                        return [
                            'nama'     => $it['nama_produk'],
                            'harga'    => rupiah($it['harga']),
                            'qty'      => (int)$it['qty'],
                            'subtotal' => rupiah($it['subtotal']),
                        ];
                    }, $o['items']),
                ];
            ?>
                <tr>
                    <td>#<?= (int)$o['id'] ?></td>
                    <td><?= e(date('d M Y, H:i', strtotime($o['created_at']))) ?></td>
                    <td><?= array_sum(array_column($o['items'], 'qty')) ?> pcs</td>
                    <td><?= rupiah($o['total']) ?></td>
                    <td><span class="badge badge-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
                    <td>
                        <button type="button" class="btn btn-outline" data-pesanan-user-detail="<?= e(json_encode($detail, JSON_UNESCAPED_UNICODE)) ?>">Detail</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    <div class="table-footer">Menampilkan <?= count($pesananList) ?> pesanan</div>
</div>

<!-- Popup Detail Pesanan -->
<div class="modal-overlay" id="modalDetailPesananUser">
    <div class="modal-box">
        <button class="modal-close" data-close-modal="modalDetailPesananUser">✕</button>
        <span class="modal-tag">Pesanan</span>
        <h2 id="psuTitle">Detail Pesanan</h2>

        <div class="od-meta" id="psuMeta"></div>

        <table class="data-table od-table">
            <thead>
                <tr><th>Produk</th><th>Harga</th><th>Jml</th><th>Subtotal</th></tr>
            </thead>
            <tbody id="psuItems"></tbody>
        </table>

        <div class="total-row" style="margin:14px 0;">
            <span>Total</span> <strong id="psuTotal"></strong>
        </div>

        <!-- Aksi hanya muncul kalau status masih Menunggu -->
        <form method="POST" action="status_transaksi.php" id="psuActions" data-confirm="Yakin mau membatalkan pesanan ini?">
            <input type="hidden" name="batalkan" id="psuId" value="">
            <div class="form-actions">
                <button type="submit" class="btn btn-danger">✕ Batalkan Pesanan</button>
            </div>
        </form>
        <div id="psuDone" class="hint" style="display:none;"></div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
