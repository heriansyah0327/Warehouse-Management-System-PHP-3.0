<?php
require_once __DIR__ . '/../includes/auth.php';
require_management();   // hanya admin & staff

$base = '../';
$active_menu = 'kelola_jual_beli';
$page_title = 'Jual Beli - Management';
$page_header = 'Jual Beli';

$statusList = ['menunggu', 'selesai', 'dibatalkan', 'ditolak'];
$filter = $_GET['status'] ?? '';
if (!in_array($filter, $statusList, true)) $filter = '';

$sql = "SELECT p.*, u.full_name, u.username, u.phone
        FROM pesanan p JOIN users u ON u.id = p.user_id";
if ($filter !== '') {
    $stmt = mysqli_prepare($conn, $sql . " WHERE p.status = ? ORDER BY p.created_at DESC, p.id DESC");
    mysqli_stmt_bind_param($stmt, 's', $filter);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
} else {
    $res = mysqli_query($conn, $sql . " ORDER BY FIELD(p.status,'menunggu','selesai','dibatalkan','ditolak'), p.created_at DESC, p.id DESC");
}

$orders = [];
while ($row = mysqli_fetch_assoc($res)) {
    $st = mysqli_prepare($conn, "SELECT nama_produk, harga, qty, subtotal FROM pesanan_item WHERE pesanan_id = ? ORDER BY id ASC");
    $oid = (int)$row['id'];
    mysqli_stmt_bind_param($st, 'i', $oid);
    mysqli_stmt_execute($st);
    $rItems = mysqli_stmt_get_result($st);
    $items = [];
    while ($it = mysqli_fetch_assoc($rItems)) {
        $items[] = [
            'nama'     => $it['nama_produk'],
            'harga'    => rupiah($it['harga']),
            'qty'      => (int)$it['qty'],
            'subtotal' => rupiah($it['subtotal']),
        ];
    }
    $row['items'] = $items;
    $orders[] = $row;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-tabs">
    <a class="page-tab active" href="kelola_pemesanan.php">🧾 Kelola Pemesanan</a>
    <a class="page-tab" href="kelola_jual_barang.php">🧳 Kelola Jual Barang</a>
</div>

<div class="chip-row">
    <a class="chip <?= $filter === '' ? 'active' : '' ?>" href="kelola_pemesanan.php">Semua</a>
    <?php foreach ($statusList as $s): ?>
        <a class="chip <?= $filter === $s ? 'active' : '' ?>" href="kelola_pemesanan.php?status=<?= e($s) ?>"><?= e(status_label($s)) ?></a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <div class="panel-toolbar">
        <div class="search-box">
            <input type="text" placeholder="Cari pesanan / homies..." data-table-search="#pemesananTable">
            <span class="icon">🔍</span>
        </div>
    </div>

    <table class="data-table" id="pemesananTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tanggal</th>
                <th>Homies</th>
                <th>Item</th>
                <th>Total</th>
                <th>Status</th>
                <th>Tindakan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">Belum ada pesanan.</td></tr>
            <?php else: foreach ($orders as $o):
                $detail = [
                    'id'       => (int)$o['id'],
                    'tanggal'  => date('d M Y, H:i', strtotime($o['created_at'])),
                    'homies'   => $o['full_name'],
                    'username' => $o['username'],
                    'phone'    => $o['phone'] ?: '-',
                    'catatan'  => $o['catatan'] ?: '',
                    'status'   => $o['status'],
                    'status_label' => status_label($o['status']),
                    'total'    => rupiah($o['total']),
                    'items'    => $o['items'],
                ];
            ?>
                <tr>
                    <td>#<?= (int)$o['id'] ?></td>
                    <td><?= e(date('d M Y, H:i', strtotime($o['created_at']))) ?></td>
                    <td><?= e($o['full_name']) ?> <span class="muted">(<?= e($o['username']) ?>)</span></td>
                    <td><?= array_sum(array_column($o['items'], 'qty')) ?> pcs</td>
                    <td><?= rupiah($o['total']) ?></td>
                    <td><span class="badge badge-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
                    <td>
                        <button type="button" class="btn btn-outline" data-order-detail="<?= e(json_encode($detail, JSON_UNESCAPED_UNICODE)) ?>">Detail Pesanan</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    <div class="table-footer">Menampilkan <?= count($orders) ?> pesanan</div>
</div>

<!-- Popup Detail Pesanan -->
<div class="modal-overlay" id="modalDetailPesanan">
    <div class="modal-box">
        <button class="modal-close" data-close-modal="modalDetailPesanan">✕</button>
        <span class="modal-tag">Pemesanan</span>
        <h2 id="odTitle">Detail Pesanan</h2>

        <div class="od-meta" id="odMeta"></div>

        <table class="data-table od-table">
            <thead>
                <tr><th>Produk</th><th>Harga</th><th>Jml</th><th>Subtotal</th></tr>
            </thead>
            <tbody id="odItems"></tbody>
        </table>

        <div class="total-row" style="margin:14px 0;">
            <span>Total</span> <strong id="odTotal"></strong>
        </div>

        <!-- Aksi hanya muncul kalau status masih Menunggu -->
        <form method="POST" action="proses_pesanan.php" id="odActions" >
            <input type="hidden" name="id" id="odId" value="">
            <div class="form-actions">
                <button type="submit" name="aksi" value="selesai" class="btn btn-success">✔ Selesai</button>
                <button type="submit" name="aksi" value="tolak" class="btn btn-danger">✕ Tolak Pesanan</button>
            </div>
        </form>
        <div id="odDone" class="hint" style="display:none;"></div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
