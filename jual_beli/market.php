<?php
require_once __DIR__ . '/../includes/auth.php';
require_jual_beli();

$base = '../';
$active_menu = 'market';
$page_title = 'Market - Management';
$page_header = 'Market';

// Kategori Spesial tidak dijual di Market (khusus Work Management).
$kategoriMarket = array_values(array_diff(kategori_options(), [KATEGORI_NON_MARKET]));

$kategoriAktif = $_GET['kategori'] ?? '';
if (!in_array($kategoriAktif, $kategoriMarket, true)) $kategoriAktif = '';
$qs = $kategoriAktif !== '' ? '?kategori=' . urlencode($kategoriAktif) : '';

// ---------------------------------------------------
// Tambah ke keranjang
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_cart'])) {
    $pid = (int)($_POST['produk_id'] ?? 0);
    $qty = max(1, min(999, (int)($_POST['qty'] ?? 1)));

    $stmt = mysqli_prepare($conn, "SELECT nama_produk, kategori, stok FROM produk WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $pid);
    mysqli_stmt_execute($stmt);
    $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($p && $p['kategori'] === KATEGORI_NON_MARKET) {
        flash_set('Produk kategori Spesial tidak dijual di Market.', 'danger');
    } elseif ($p) {
        $stok    = (int)$p['stok'];
        $cart    = cart_get();
        $dikeranjang = (int)($cart[$pid] ?? 0);

        if ($stok <= 0) {
            flash_set('"' . $p['nama_produk'] . '" sedang habis, tidak bisa dipesan.', 'danger');
        } elseif ($dikeranjang + $qty > $stok) {
            $sisa = $stok - $dikeranjang;
            if ($sisa <= 0) {
                flash_set('Stok "' . $p['nama_produk'] . '" hanya ' . $stok . ' dan semuanya sudah ada di keranjang kamu.', 'danger');
            } else {
                flash_set('Stok "' . $p['nama_produk'] . '" hanya ' . $stok . '. Kamu cuma bisa nambah ' . $sisa . ' lagi.', 'danger');
            }
        } else {
            $cart[$pid] = $dikeranjang + $qty;
            $_SESSION['cart'] = $cart;
            flash_set($qty . 'x "' . $p['nama_produk'] . '" masuk ke keranjang.');
        }
    } else {
        flash_set('Produk tidak ditemukan.', 'danger');
    }
    header('Location: market.php' . $qs);
    exit;
}

// ---------------------------------------------------
// Ambil produk (opsional filter kategori)
// ---------------------------------------------------
$produkList = [];
if ($kategoriAktif !== '') {
    $stmt = mysqli_prepare($conn, "SELECT * FROM produk WHERE kategori = ? ORDER BY nama_produk ASC");
    mysqli_stmt_bind_param($stmt, 's', $kategoriAktif);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
} else {
    $kat = KATEGORI_NON_MARKET;
    $stmt = mysqli_prepare($conn, "SELECT * FROM produk WHERE kategori <> ? ORDER BY nama_produk ASC");
    mysqli_stmt_bind_param($stmt, 's', $kat);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
}
while ($row = mysqli_fetch_assoc($res)) $produkList[] = $row;

$cartCount = cart_count();

include __DIR__ . '/../includes/header.php';
?>

<div class="market-bar">
    <div class="search-box">
        <input type="text" placeholder="Cari produk..." data-card-search="#productGrid">
        <span class="icon">🔍</span>
    </div>
    <a class="btn btn-primary" href="keranjang.php">🛒 Keranjang<?php if ($cartCount > 0): ?> <span class="cart-pill"><?= $cartCount ?></span><?php endif; ?></a>
</div>

<div class="chip-row">
    <a class="chip <?= $kategoriAktif === '' ? 'active' : '' ?>" href="market.php">Semua</a>
    <?php foreach ($kategoriMarket as $k): ?>
        <a class="chip <?= $kategoriAktif === $k ? 'active' : '' ?>" href="market.php?kategori=<?= urlencode($k) ?>"><?= e($k) ?></a>
    <?php endforeach; ?>
</div>

<?php if (empty($produkList)): ?>
    <div class="panel"><div class="empty-state">Belum ada produk di kategori ini.</div></div>
<?php else: ?>
    <div class="product-grid" id="productGrid">
        <?php foreach ($produkList as $p): ?>
            <?php $stok = (int)$p['stok']; ?>
            <form method="POST" action="market.php<?= e($qs) ?>" class="product-card <?= $stok <= 0 ? 'is-habis' : '' ?>" data-search-item>
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
                    <div class="pc-price"><?= rupiah($p['harga_jual']) ?></div>
                    <div class="pc-stok <?= $stok <= 0 ? 'habis' : ($stok <= 5 ? 'menipis' : '') ?>">
                        <?= $stok <= 0 ? 'Stok habis' : 'Stok: ' . $stok ?>
                    </div>
                    <div class="pc-actions">
                        <?php if ($stok <= 0): ?>
                            <button type="button" class="btn btn-outline" disabled>Stok Habis</button>
                        <?php else: ?>
                            <div class="qty-stepper">
                                <button type="button" data-step="-1">−</button>
                                <input type="number" name="qty" value="1" min="1" max="<?= $stok ?>">
                                <button type="button" data-step="1">+</button>
                            </div>
                            <button type="submit" name="add_cart" value="1" class="btn btn-primary">+ Keranjang</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
