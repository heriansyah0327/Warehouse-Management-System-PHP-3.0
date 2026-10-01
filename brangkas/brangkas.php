<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();   // semua role (admin, staff, homies) boleh lihat — read-only

$base = '../';
$active_menu = 'brangkas';
$page_title = 'Brangkas - Management';
$page_header = 'Brangkas';

// ---------------------------------------------------
// Uang Merah & Uang Putih = produk dengan nama persis ini (tidak peduli
// kategorinya). Nilainya diambil dari kolom stok. Kalau nama produk di
// database beda, ganti di 2 baris ini aja.
// ---------------------------------------------------
$UANG_MERAH = 'Uang Merah';
$UANG_PUTIH = 'Uang Putih';
$uangKeys   = [strtolower($UANG_MERAH), strtolower($UANG_PUTIH)];

// Formatnya ngikutin referensi: $5,777,467
function brangkas_dollar($n) {
    return '$' . number_format((int)$n, 0, '.', ',');
}

// ---------------------------------------------------
// Total uang (ditampilin terus di atas, tidak ikut filter kategori)
// ---------------------------------------------------
$uang = [
    $uangKeys[0] => ['stok' => 0, 'foto' => null],
    $uangKeys[1] => ['stok' => 0, 'foto' => null],
];
$resUang = mysqli_query($conn, "SELECT nama_produk, foto, stok FROM produk WHERE LOWER(TRIM(nama_produk)) IN ('" . $uangKeys[0] . "', '" . $uangKeys[1] . "')");
if ($resUang) {
    while ($r = mysqli_fetch_assoc($resUang)) {
        $k = strtolower(trim($r['nama_produk']));
        if (!isset($uang[$k])) continue;
        $uang[$k]['stok'] += (int)$r['stok'];
        if (!$uang[$k]['foto'] && $r['foto']) $uang[$k]['foto'] = $r['foto'];
    }
}

// ---------------------------------------------------
// Daftar item gudang. Uang Merah & Uang Putih SELALU dikeluarkan dari
// grid (di "Semua" maupun di filter kategori) karena sudah ada di atas.
// ---------------------------------------------------
$kategoriList  = kategori_options();
$kategoriAktif = $_GET['kategori'] ?? '';
if (!in_array($kategoriAktif, $kategoriList, true)) $kategoriAktif = '';

$notMoney = "LOWER(TRIM(nama_produk)) NOT IN ('" . $uangKeys[0] . "', '" . $uangKeys[1] . "')";

$items = [];
if ($kategoriAktif !== '') {
    $stmt = mysqli_prepare($conn, "SELECT nama_produk, kategori, foto, stok FROM produk WHERE kategori = ? AND $notMoney ORDER BY nama_produk ASC");
    mysqli_stmt_bind_param($stmt, 's', $kategoriAktif);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
} else {
    $res = mysqli_query($conn, "SELECT nama_produk, kategori, foto, stok FROM produk WHERE $notMoney ORDER BY nama_produk ASC");
}
if ($res) while ($row = mysqli_fetch_assoc($res)) $items[] = $row;

include __DIR__ . '/../includes/header.php';
?>

<p class="vault-note">Stok yang ada di gudang saat ini. Halaman ini hanya untuk melihat, tidak bisa diubah dari sini.</p>

<div class="vault-money-grid">
    <?php
    $cards = [
        ['cls' => 'merah', 'label' => $UANG_MERAH, 'key' => $uangKeys[0], 'icon' => '💸'],
        ['cls' => 'putih', 'label' => $UANG_PUTIH, 'key' => $uangKeys[1], 'icon' => '💵'],
    ];
    foreach ($cards as $c):
        $u = $uang[$c['key']];
    ?>
        <div class="vault-money <?= e($c['cls']) ?>">
            <div class="vm-img">
                <?php if ($u['foto']): ?>
                    <img src="<?= e($base) ?>assets/uploads/<?= e($u['foto']) ?>" alt="<?= e($c['label']) ?>">
                <?php else: ?>
                    <?= $c['icon'] ?>
                <?php endif; ?>
            </div>
            <div>
                <div class="vm-label"><?= e($c['label']) ?></div>
                <div class="vm-value"><?= e(brangkas_dollar($u['stok'])) ?></div>
                <div class="vm-sub">Total stok <?= e($c['label']) ?> di gudang</div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="market-bar">
    <div class="search-box">
        <input type="text" placeholder="Cari nama item..." data-card-search="#vaultGrid">
        <span class="icon">🔍</span>
    </div>
</div>

<div class="chip-row">
    <a class="chip <?= $kategoriAktif === '' ? 'active' : '' ?>" href="brangkas.php">Semua</a>
    <?php foreach ($kategoriList as $k): ?>
        <a class="chip <?= $kategoriAktif === $k ? 'active' : '' ?>" href="brangkas.php?kategori=<?= urlencode($k) ?>"><?= e($k) ?></a>
    <?php endforeach; ?>
</div>

<?php if (empty($items)): ?>
    <div class="panel"><div class="empty-state">Belum ada item di kategori ini.</div></div>
<?php else: ?>
    <div class="vault-grid" id="vaultGrid">
        <?php foreach ($items as $it): ?>
            <?php $stok = (int)$it['stok']; ?>
            <div class="vault-card <?= $stok <= 0 ? 'is-habis' : '' ?>" data-search-item>
                <div class="vc-img">
                    <?php if ($it['foto']): ?>
                        <img src="<?= e($base) ?>assets/uploads/<?= e($it['foto']) ?>" alt="<?= e($it['nama_produk']) ?>">
                    <?php else: ?>
                        <span class="pc-noimg">📦</span>
                    <?php endif; ?>
                </div>
                <span class="vc-cat"><?= e($it['kategori']) ?></span>
                <div class="vc-name"><?= e($it['nama_produk']) ?></div>
                <?php if ($stok <= 0): ?>
                    <div class="vc-habis">Stok habis</div>
                <?php else: ?>
                    <div class="vc-qty"><?= number_format($stok, 0, '.', ',') ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
