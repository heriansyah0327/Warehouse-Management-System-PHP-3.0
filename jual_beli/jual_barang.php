<?php
require_once __DIR__ . '/../includes/auth.php';
require_jual_beli();

$base = '../';
$active_menu = 'jual_barang';   // menu Jual Beli > Jual Barang
$page_title = 'Jual Barang - Management';
$page_header = 'Jual Barang';

$isManagement = has_role('admin', 'staff');
$minQty = JUALBARANG_MIN_QTY;

// ---------------------------------------------------
// Simpan whitelist (khusus admin/staff)
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_whitelist'])) {
    if (!$isManagement) {
        flash_set('Kamu tidak punya akses untuk mengatur whitelist.', 'danger');
        header('Location: jual_barang.php');
        exit;
    }

    $ids = array_map('intval', $_POST['whitelist'] ?? []);
    $ids = array_values(array_unique(array_filter($ids, fn($v) => $v > 0)));
    $uid = (int)current_user()['id'];

    mysqli_begin_transaction($conn);
    try {
        mysqli_query($conn, "DELETE FROM jual_barang_whitelist");
        if (!empty($ids)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO jual_barang_whitelist (produk_id, added_by) VALUES (?, ?)");
            foreach ($ids as $pid) {
                mysqli_stmt_bind_param($stmt, 'ii', $pid, $uid);
                mysqli_stmt_execute($stmt);
            }
        }
        mysqli_commit($conn);
        flash_set('Whitelist barang yang boleh dijual berhasil disimpan (' . count($ids) . ' produk).');
    } catch (Throwable $ex) {
        mysqli_rollback($conn);
        flash_set('Gagal menyimpan whitelist, coba lagi.', 'danger');
    }
    header('Location: jual_barang.php');
    exit;
}

// ---------------------------------------------------
// Tambah ke keranjang jual barang (hanya produk yang di-whitelist)
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_cart'])) {
    $pid = (int)($_POST['produk_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 0);

    $stmt = mysqli_prepare($conn, "SELECT p.nama_produk FROM produk p JOIN jual_barang_whitelist w ON w.produk_id = p.id WHERE p.id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $pid);
    mysqli_stmt_execute($stmt);
    $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$p) {
        flash_set('Produk tidak ditemukan atau belum di-whitelist untuk Jual Barang.', 'danger');
    } elseif ($qty < $minQty) {
        flash_set('Jumlah minimal ' . $minQty . '.', 'danger');
    } else {
        $cart = cart_jualbarang_get();
        $cart[$pid] = (int)($cart[$pid] ?? 0) + $qty;
        $_SESSION['cart_jualbarang'] = $cart;
        flash_set($qty . 'x "' . $p['nama_produk'] . '" masuk ke keranjang jual barang.');
    }
    header('Location: jual_barang.php');
    exit;
}

// ---------------------------------------------------
// Daftar produk yang di-whitelist
// ---------------------------------------------------
$produkList = [];
$res = mysqli_query($conn, "SELECT p.* FROM produk p JOIN jual_barang_whitelist w ON w.produk_id = p.id ORDER BY p.nama_produk ASC");
while ($row = mysqli_fetch_assoc($res)) $produkList[] = $row;

$cartCount = cart_jualbarang_count();

// Semua produk (untuk modal whitelist, admin/staff saja)
$allProduk = [];
$whitelistedIds = [];
if ($isManagement) {
    $ra = mysqli_query($conn, "SELECT id, nama_produk, kategori FROM produk ORDER BY kategori ASC, nama_produk ASC");
    while ($row = mysqli_fetch_assoc($ra)) $allProduk[] = $row;

    $rw = mysqli_query($conn, "SELECT produk_id FROM jual_barang_whitelist");
    while ($row = mysqli_fetch_assoc($rw)) $whitelistedIds[(int)$row['produk_id']] = true;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="market-bar">
    <div class="search-box">
        <input type="text" placeholder="Cari produk..." data-card-search="#productGrid">
        <span class="icon">🔍</span>
    </div>
    <div style="display:flex; gap:10px;">
        <?php if ($isManagement): ?>
            <button type="button" class="btn btn-outline" data-open-modal="modalWhitelist">⚙️ Atur Barang yang Bisa Dijual</button>
        <?php endif; ?>
        <a class="btn btn-primary" href="keranjang_jual_barang.php">🛒 Keranjang<?php if ($cartCount > 0): ?> <span class="cart-pill"><?= $cartCount ?></span><?php endif; ?></a>
    </div>
</div>

<p class="hint" style="margin:0 0 14px;">Pengajuan dikirim dari keranjang, statusnya <strong>Menunggu</strong> sampai di-ACC admin/staff.</p>

<?php if (empty($produkList)): ?>
    <div class="panel"><div class="empty-state">Belum ada barang yang di-whitelist untuk Jual Barang.<?= $isManagement ? ' Klik "Atur Barang yang Bisa Dijual" untuk menambahkan.' : ' Minta admin/staff untuk menambahkannya.' ?></div></div>
<?php else: ?>
    <div class="product-grid" id="productGrid">
        <?php foreach ($produkList as $p): ?>
            <form method="POST" action="jual_barang.php" class="product-card" data-search-item>
                <input type="hidden" name="produk_id" value="<?= (int)$p['id'] ?>">
                <div class="pc-img">
                    <?php if ($p['foto']): ?>
                        <img src="<?= e($base) ?>assets/uploads/<?= e($p['foto']) ?>" alt="<?= e($p['nama_produk']) ?>">
                    <?php else: ?>
                        <span class="pc-noimg">📦</span>
                    <?php endif; ?>
                </div>
                <div class="pc-body">
                    <span class="pc-cat"><?= e($p['kategori']) ?></span>
                    <div class="pc-name"><?= e($p['nama_produk']) ?></div>
                    <div class="pc-stok">Stok toko saat ini: <?= (int)$p['stok'] ?></div>
                    <div class="pc-harga">Harga beli: <strong><?= rupiah($p['harga_beli']) ?></strong> /pcs</div>
                    <div class="pc-actions">
                        <div class="qty-stepper">
                            <button type="button" data-step="-1">−</button>
                            <input type="number" name="qty" value="<?= $minQty ?>" min="<?= $minQty ?>" max="999999">
                            <button type="button" data-step="1">+</button>
                        </div>
                        <button type="submit" name="add_cart" value="1" class="btn btn-primary">+ Keranjang</button>
                    </div>
                </div>
            </form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($isManagement): ?>
<!-- Popup Atur Whitelist Jual Barang -->
<div class="modal-overlay" id="modalWhitelist">
    <div class="modal-box">
        <button class="modal-close" data-close-modal="modalWhitelist">✕</button>
        <span class="modal-tag">Jual Barang</span>
        <h2>Atur Barang yang Bisa Dijual</h2>
        <p class="hint" style="margin-bottom:12px;">Centang produk yang boleh muncul di halaman Jual Barang. Produk yang tidak dicentang tidak akan tampil ke homies.</p>
        <form method="POST" action="jual_barang.php">
            <div class="form-group" style="margin-bottom:10px;">
                <input type="text" placeholder="Cari produk..." data-card-search="#wlList">
            </div>
            <div class="wl-list" id="wlList">
                <?php if (empty($allProduk)): ?>
                    <div class="wl-item">Belum ada produk.</div>
                <?php else: foreach ($allProduk as $p): ?>
                    <div class="wl-item" data-search-item>
                        <label>
                            <input type="checkbox" name="whitelist[]" value="<?= (int)$p['id'] ?>" <?= isset($whitelistedIds[(int)$p['id']]) ? 'checked' : '' ?>>
                            <span><?= e($p['nama_produk']) ?></span>
                            <span class="wl-cat"><?= e($p['kategori']) ?></span>
                        </label>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <div class="form-actions" style="margin-top:16px;">
                <button type="submit" name="save_whitelist" value="1" class="btn btn-success">💾 Simpan Whitelist</button>
                <button type="button" class="btn btn-outline" data-close-modal="modalWhitelist">Batal</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>