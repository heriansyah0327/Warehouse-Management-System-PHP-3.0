<?php
require_once __DIR__ . '/includes/auth.php';
require_login();   // semua role (admin, staff, homies) boleh lihat dashboard

$base = '';
$active_menu = 'dashboard';
$page_title = 'Dashboard - Management';
$page_header = 'Dashboard';

$me = current_user();
$isManagement = has_role('admin', 'staff');

// ---------------------------------------------------
// Top Spender: total belanja dari pesanan berstatus "selesai".
// (menunggu / dibatalkan / ditolak TIDAK dihitung)
// Minggu dihitung Senin-Minggu, sesuai jam server database.
// ---------------------------------------------------
function top_spender($conn, $whereWaktu, $limit = 10) {
    $sql = "SELECT u.id, u.full_name, u.username, u.role,
                   SUM(p.total) AS total_belanja, COUNT(p.id) AS jumlah_pesanan
            FROM pesanan p
            JOIN users u ON u.id = p.user_id
            WHERE p.status = 'selesai' AND $whereWaktu
            GROUP BY u.id, u.full_name, u.username, u.role
            ORDER BY total_belanja DESC, jumlah_pesanan DESC, u.full_name ASC
            LIMIT " . (int)$limit;
    $rows = [];
    $res = mysqli_query($conn, $sql);
    if ($res) while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    return $rows;
}

// ---------------------------------------------------
// Top Seller: total barang (qty) yang berhasil dijual ke toko lewat
// fitur Jual Barang, dihitung dari pengajuan berstatus "diacc" saja.
// Minggu dihitung Senin-Minggu, sesuai jam server database.
// ---------------------------------------------------
function top_seller($conn, $whereWaktu, $limit = 10) {
    $sql = "SELECT u.id, u.full_name, u.username, u.role,
                   SUM(jbi.qty * COALESCE(p.harga_beli, 0)) AS subtotal,
                   SUM(jbi.qty) AS total_qty,
                   COUNT(DISTINCT jb.id) AS jumlah_pengajuan
            FROM jual_barang jb
            JOIN jual_barang_item jbi ON jbi.jual_barang_id = jb.id
            LEFT JOIN produk p ON p.id = jbi.produk_id
            JOIN users u ON u.id = jb.user_id
            WHERE jb.status = 'diacc' AND $whereWaktu
            GROUP BY u.id, u.full_name, u.username, u.role
            ORDER BY subtotal DESC, total_qty DESC, u.full_name ASC
            LIMIT " . (int)$limit;
    $rows = [];
    $res = mysqli_query($conn, $sql);
    if ($res) while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    return $rows;
}

$topMinggu = top_spender($conn, "YEARWEEK(p.created_at, 1) = YEARWEEK(CURDATE(), 1)");
$topSeller = top_seller($conn, "YEARWEEK(jb.created_at, 1) = YEARWEEK(CURDATE(), 1)");

$boards = [
    ['judul' => '🔥 Top Spender Minggu Ini', 'sub' => 'Senin - Minggu', 'rows' => $topMinggu, 'type' => 'spender'],
    ['judul' => '📦 Top Seller Minggu Ini',  'sub' => 'Senin - Minggu', 'rows' => $topSeller, 'type' => 'seller'],
];
$medals = ['🥇', '🥈', '🥉'];

// Statistik (khusus admin & staff)
if ($isManagement) {
    $totalProduk  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM produk"))['c'];
    $totalStaff   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users WHERE role IN ('admin','staff')"))['c'];
    $totalHomies  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users WHERE role = 'homies'"))['c'];
    $totalPending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM pesanan WHERE status = 'menunggu'"))['c'];
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($isManagement): ?>
<div class="stat-grid">
    <div class="stat-card">
        <div class="label">Total Produk</div>
        <div class="value"><?= (int)$totalProduk ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Total Staff & Admin</div>
        <div class="value"><?= (int)$totalStaff ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Total Homies</div>
        <div class="value"><?= (int)$totalHomies ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Pesanan Menunggu</div>
        <div class="value"><?= (int)$totalPending ?></div>
    </div>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header"><h3>Selamat datang, <?= e($me['full_name']) ?> 👋</h3></div>
    <div style="padding:20px; font-size:14px; color:var(--text-muted);">
        Mau naik peringkat? Belanja di menu <strong>Jual Beli</strong> 😎
    </div>
</div>

<div class="board-grid">
    <?php foreach ($boards as $b): ?>
    <div class="panel board">
        <div class="panel-header">
            <div>
                <h3><?= $b['judul'] ?></h3>
                <div class="muted"><?= e($b['sub']) ?></div>
            </div>
        </div>
        <?php if (empty($b['rows'])): ?>
            <div class="empty-state"><?= $b['type'] === 'seller' ? 'Belum ada yang jual barang. Jadi yang pertama! 🏁' : 'Belum ada yang belanja. Jadi yang pertama! 🏁' ?></div>
        <?php else: ?>
            <ol class="board-list">
                <?php foreach ($b['rows'] as $i => $r): $isMe = ((int)$r['id'] === (int)$me['id']); ?>
                    <li class="board-item <?= $i === 0 ? 'first' : '' ?> <?= $isMe ? 'me' : '' ?>">
                        <span class="board-rank"><?= $medals[$i] ?? ($i + 1) ?></span>
                        <span class="board-name">
                            <?= e($r['full_name']) ?>
                            <?php if ($isMe): ?><span class="badge badge-staff">Kamu</span><?php endif; ?>
                            <?php if ($b['type'] === 'seller'): ?>
                                <span class="muted"><?= (int)$r['total_qty'] ?> barang</span>
                            <?php else: ?>
                                <span class="muted"><?= (int)$r['jumlah_pesanan'] ?> pesanan</span>
                            <?php endif; ?>
                        </span>
                        <?php if ($b['type'] === 'seller'): ?>
                            <strong class="board-total"><?= rupiah($r['subtotal']) ?></strong>
                        <?php else: ?>
                            <strong class="board-total"><?= rupiah($r['total_belanja']) ?></strong>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>