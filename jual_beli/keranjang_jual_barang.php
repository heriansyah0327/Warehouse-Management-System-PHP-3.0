<?php
require_once __DIR__ . '/../includes/auth.php';
require_jual_beli();

$base = '../';
$active_menu = 'jual_barang';
$page_title = 'Keranjang Jual Barang - Management';
$page_header = 'Keranjang Jual Barang';

$me = current_user();
$minQty = JUALBARANG_MIN_QTY;
$maxQty = 999999;

// Data produk untuk semua item di keranjang. Hanya produk yang MASIH
// di-whitelist yang ditampilkan (whitelist bisa berubah kapan saja).
function load_jualbarang_items($conn) {
    $items = [];
    foreach (cart_jualbarang_get() as $pid => $qty) {
        $pid = (int)$pid;
        $stmt = mysqli_prepare($conn, "SELECT p.id, p.nama_produk, p.kategori, p.foto, p.stok, p.harga_beli FROM produk p JOIN jual_barang_whitelist w ON w.produk_id = p.id WHERE p.id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $pid);
        mysqli_stmt_execute($stmt);
        $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($p) {
            $p['stok'] = (int)$p['stok'];
            $p['qty'] = (int)$qty;
            $p['harga_beli'] = (float)$p['harga_beli'];
            $p['subtotal'] = $p['harga_beli'] * $p['qty'];
            $items[] = $p;
        } else {
            $c = cart_jualbarang_get(); unset($c[$pid]); $_SESSION['cart_jualbarang'] = $c;
        }
    }
    return $items;
}

// Buang item yang whitelist-nya sudah dicabut sejak dimasukkan ke keranjang.
function jualbarang_sync_whitelist($conn) {
    $notes = [];
    $cart = cart_jualbarang_get();
    $valid = load_jualbarang_items($conn);
    $validIds = array_column($valid, 'id');
    foreach (array_keys($cart) as $pid) {
        if (!in_array((int)$pid, $validIds, true)) {
            unset($cart[$pid]);
            $notes[] = 'Salah satu barang sudah tidak bisa dijual lagi dan dihapus dari keranjang.';
        }
    }
    $_SESSION['cart_jualbarang'] = $cart;
    return $notes;
}

// ---------------------------------------------------
// Auto-update qty (via fetch dari JS)
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json; charset=utf-8');
    $pid = (int)($_POST['produk_id'] ?? 0);
    $q   = (int)($_POST['qty'] ?? 0);
    $cart = cart_jualbarang_get();

    if (!isset($cart[$pid])) {
        echo json_encode(['ok' => false, 'msg' => 'Item tidak ada di keranjang.']);
        exit;
    }

    $msg = '';
    if ($q < $minQty) { $q = $minQty; $msg = 'Minimal ' . $minQty . '.'; }
    if ($q > $maxQty) { $q = $maxQty; $msg = 'Jumlah maksimal ' . $maxQty . '.'; }
    $cart[$pid] = $q;
    $_SESSION['cart_jualbarang'] = $cart;

    $total = 0; $sub = 0;
    foreach (load_jualbarang_items($conn) as $it) {
        $total += $it['subtotal'];
        if ((int)$it['id'] === $pid) { $sub = $it['subtotal']; }
    }

    echo json_encode([
        'ok'       => true,
        'qty'      => $q,
        'msg'      => $msg,
        'subtotal' => rupiah($sub),
        'total'    => rupiah($total),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Perubahan qty dari form (fallback tanpa JS)
    $cart = cart_jualbarang_get();
    foreach (($_POST['qty'] ?? []) as $pid => $q) {
        $pid = (int)$pid;
        if (!isset($cart[$pid])) continue;
        $cart[$pid] = max($minQty, min($maxQty, (int)$q));
    }
    // 2) Hapus satu item
    if (isset($_POST['remove'])) {
        unset($cart[(int)$_POST['remove']]);
    }
    $_SESSION['cart_jualbarang'] = $cart;

    // 3) Ajukan jual barang
    if (isset($_POST['konfirmasi'])) {
        $notes = jualbarang_sync_whitelist($conn);
        if ($notes) {
            flash_set(implode(' ', array_unique($notes)) . ' Cek lagi lalu ajukan.', 'danger');
            header('Location: keranjang_jual_barang.php');
            exit;
        }

        $items = load_jualbarang_items($conn);
        if (empty($items)) {
            flash_set('Keranjang jual barang masih kosong.', 'danger');
            header('Location: keranjang_jual_barang.php');
            exit;
        }

        $catatan = trim($_POST['catatan'] ?? '');
        if (strlen($catatan) > 255) $catatan = substr($catatan, 0, 255);
        $uid = (int)$me['id'];

        mysqli_begin_transaction($conn);
        try {
            // Stok TIDAK dikurangi di sini — baru bertambah saat admin/staff ACC.
            $stmt = mysqli_prepare($conn, "INSERT INTO jual_barang (user_id, catatan, status) VALUES (?, ?, 'menunggu')");
            mysqli_stmt_bind_param($stmt, 'is', $uid, $catatan);
            mysqli_stmt_execute($stmt);
            $jualBarangId = mysqli_insert_id($conn);

            $stmtItem = mysqli_prepare($conn, "INSERT INTO jual_barang_item (jual_barang_id, produk_id, nama_produk, qty) VALUES (?, ?, ?, ?)");
            foreach ($items as $it) {
                $pid = (int)$it['id'];
                $nama = $it['nama_produk'];
                $qty = (int)$it['qty'];
                mysqli_stmt_bind_param($stmtItem, 'iisi', $jualBarangId, $pid, $nama, $qty);
                mysqli_stmt_execute($stmtItem);
            }
            mysqli_commit($conn);
        } catch (Throwable $ex) {
            mysqli_rollback($conn);
            flash_set('Gagal membuat pengajuan, coba lagi.', 'danger');
            header('Location: keranjang_jual_barang.php');
            exit;
        }

        $_SESSION['cart_jualbarang'] = [];
        flash_set('Pengajuan jual barang #' . $jualBarangId . ' berhasil dibuat. Tunggu di-ACC oleh admin/staff.');
        header('Location: status_jual_barang.php');
        exit;
    }

    header('Location: keranjang_jual_barang.php');
    exit;
}

// Saat halaman dibuka: buang item yang sudah tidak di-whitelist
$notes = jualbarang_sync_whitelist($conn);
if ($notes) flash_set(implode(' ', array_unique($notes)), 'danger');

$items = load_jualbarang_items($conn);
$total = 0;
foreach ($items as $it) $total += $it['subtotal'];

include __DIR__ . '/../includes/header.php';
?>

<?php if (empty($items)): ?>
    <div class="panel">
        <div class="empty-state">
            Keranjang jual barang kamu masih kosong.<br>
            <a class="btn btn-primary" style="margin-top:14px;" href="jual_barang.php">Pilih Barang</a>
        </div>
    </div>
<?php else: ?>
<form method="POST" action="keranjang_jual_barang.php">
    <!-- tombol default (kalau user tekan Enter) = simpan qty, BUKAN hapus -->
    <button type="submit" name="update_cart" value="1" class="visually-hidden" tabindex="-1" aria-hidden="true">Perbarui</button>

    <div class="panel">
        <div class="panel-header"><h3>Barang yang Mau Dijual ke Toko</h3></div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Harga /pcs</th>
                    <th>Jumlah</th>
                    <th>Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td>
                            <div class="cart-prod">
                                <div class="thumb">
                                    <?php if ($it['foto']): ?>
                                        <img src="<?= e($base) ?>assets/uploads/<?= e($it['foto']) ?>" style="width:100%;height:100%;object-fit:cover;">
                                    <?php else: ?>📦<?php endif; ?>
                                </div>
                                <div>
                                    <div><?= e($it['nama_produk']) ?></div>
                                    <span class="pc-cat"><?= e($it['kategori']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?= rupiah($it['harga_beli']) ?></td>
                        <td>
                            <div class="qty-stepper">
                                <button type="button" data-step="-1">−</button>
                                <input type="number" name="qty[<?= (int)$it['id'] ?>]" value="<?= (int)$it['qty'] ?>" min="<?= $minQty ?>" max="<?= $maxQty ?>" data-cart-qty="<?= (int)$it['id'] ?>" data-cart-url="keranjang_jual_barang.php">
                                <button type="button" data-step="1">+</button>
                            </div>
                            <div class="hint" style="margin-top:4px;">Stok toko saat ini: <?= (int)$it['stok'] ?></div>
                        </td>
                        <td><strong data-cart-subtotal="<?= (int)$it['id'] ?>"><?= rupiah($it['subtotal']) ?></strong></td>
                        <td>
                            <button type="submit" name="remove" value="<?= (int)$it['id'] ?>" class="btn-icon delete" title="Hapus dari keranjang">🗑️</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <div style="padding:20px;">
            <div class="form-group" style="margin-bottom:16px;">
                <label>Catatan pengajuan (opsional)</label>
                <input type="text" name="catatan" maxlength="255" placeholder="Contoh: barang dianter besok">
            </div>
            <div class="total-row">
                <span>Total (perkiraan, berdasarkan harga beli saat ini)</span>
                <strong id="cartTotal"><?= rupiah($total) ?></strong>
            </div>
            <div class="hint" id="cartMsg" style="min-height:18px; margin-top:8px;">Jumlah otomatis tersimpan saat kamu ubah.</div>
            <div class="form-actions" style="margin-top:16px;">
                <button type="submit" name="konfirmasi" value="1" class="btn btn-success">📤 Ajukan Jual Barang</button>
                <a class="btn btn-outline" href="jual_barang.php">Tambah Barang</a>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>