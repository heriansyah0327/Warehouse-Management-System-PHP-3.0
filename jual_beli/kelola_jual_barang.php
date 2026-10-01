<?php
require_once __DIR__ . '/../includes/auth.php';
require_management();   // hanya admin & staff

$base = '../';
$active_menu = 'kelola_jual_beli';   // menu Management > Jual Beli
$page_title = 'Jual Beli - Management';
$page_header = 'Jual Beli';

$statusList = ['menunggu', 'diacc', 'ditolak', 'dibatalkan'];
$filter = $_GET['status'] ?? '';
if (!in_array($filter, $statusList, true)) $filter = '';

$sql = "SELECT j.*, u.full_name, u.username, u.phone,
               (SELECT GROUP_CONCAT(CONCAT(i.nama_produk, ' x', i.qty) SEPARATOR ', ') FROM jual_barang_item i WHERE i.jual_barang_id = j.id) AS ringkasan,
               (SELECT COALESCE(SUM(i.qty),0) FROM jual_barang_item i WHERE i.jual_barang_id = j.id) AS total_qty,
               (SELECT COALESCE(SUM(i.qty * COALESCE(p.harga_beli,0)),0) FROM jual_barang_item i LEFT JOIN produk p ON p.id = i.produk_id WHERE i.jual_barang_id = j.id) AS total_harga
        FROM jual_barang j JOIN users u ON u.id = j.user_id";
if ($filter !== '') {
    $stmt = mysqli_prepare($conn, $sql . " WHERE j.status = ? ORDER BY j.created_at DESC, j.id DESC");
    mysqli_stmt_bind_param($stmt, 's', $filter);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
} else {
    $res = mysqli_query($conn, $sql . " ORDER BY FIELD(j.status,'menunggu','diacc','ditolak','dibatalkan'), j.created_at DESC, j.id DESC");
}

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
    <a class="page-tab" href="kelola_pemesanan.php">🧾 Kelola Pemesanan</a>
    <a class="page-tab active" href="kelola_jual_barang.php">🧳 Kelola Jual Barang</a>
</div>

<div class="chip-row">
    <a class="chip <?= $filter === '' ? 'active' : '' ?>" href="kelola_jual_barang.php">Semua</a>
    <?php foreach ($statusList as $s): ?>
        <a class="chip <?= $filter === $s ? 'active' : '' ?>" href="kelola_jual_barang.php?status=<?= e($s) ?>"><?= e(status_label_jual_barang($s)) ?></a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <div class="panel-toolbar">
        <div class="search-box">
            <input type="text" placeholder="Cari pengajuan / pengaju..." data-table-search="#jualBarangTable">
            <span class="icon">🔍</span>
        </div>
    </div>

    <table class="data-table" id="jualBarangTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tanggal</th>
                <th>Pengaju</th>
                <th>Produk</th>
                <th>Jumlah</th>
                <th>Subtotal</th>
                <th>Status</th>
                <th>Tindakan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($list)): ?>
                <tr><td colspan="8" style="text-align:center; color:var(--text-muted);">Belum ada pengajuan jual barang.</td></tr>
            <?php else: foreach ($list as $o):
                $detail = [
                    'id'         => (int)$o['id'],
                    'tanggal'    => date('d M Y, H:i', strtotime($o['created_at'])),
                    'pengaju'    => $o['full_name'],
                    'username'   => $o['username'],
                    'phone'      => $o['phone'] ?: '-',
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
                    <td><?= e($o['full_name']) ?> <span class="muted">(<?= e($o['username']) ?>)</span></td>
                    <td><?= e($o['ringkasan'] ?? '-') ?></td>
                    <td><?= (int)$o['total_qty'] ?></td>
                    <td><?= rupiah($o['total_harga']) ?></td>
                    <td><span class="badge badge-<?= $o['status'] === 'diacc' ? 'selesai' : e($o['status']) ?>"><?= e(status_label_jual_barang($o['status'])) ?></span></td>
                    <td>
                        <button type="button" class="btn btn-outline" data-jualbarang-detail="<?= e(json_encode($detail, JSON_UNESCAPED_UNICODE)) ?>">Detail</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    <div class="table-footer">Menampilkan <?= count($list) ?> pengajuan</div>
</div>

<!-- Popup Detail Jual Barang -->
<div class="modal-overlay" id="modalDetailJualBarang">
    <div class="modal-box">
        <button class="modal-close" data-close-modal="modalDetailJualBarang">✕</button>
        <span class="modal-tag">Jual Barang</span>
        <h2 id="jbTitle">Detail Jual Barang</h2>

        <div class="od-meta" id="jbMeta"></div>

        <table class="data-table od-table">
            <thead>
                <tr><th>Produk</th><th>Harga</th><th>Jml</th><th>Subtotal</th></tr>
            </thead>
            <tbody id="jbItem"></tbody>
        </table>

        <div class="total-row" style="margin:14px 0;">
            <span>Total</span> <strong id="jbTotal"></strong>
        </div>

        <!-- ACC / Tolak (status Menunggu). ACC otomatis nambah stok. -->
        <form method="POST" action="proses_jual_barang.php" id="jbAccForm">
            <input type="hidden" name="id" id="jbAccId" value="">
            <div class="form-actions">
                <button type="submit" name="aksi" value="acc" class="btn btn-success">✔ ACC &amp; Tambah Stok</button>
                <button type="submit" name="aksi" value="tolak" class="btn btn-danger">✕ Tolak</button>
            </div>
        </form>

        <div id="jbDone" class="hint" style="display:none;"></div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>