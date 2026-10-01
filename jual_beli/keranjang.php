<?php
require_once __DIR__ . '/../includes/auth.php';
require_jual_beli();

$base = '../';
$active_menu = 'market';
$page_title = 'Keranjang - Management';
$page_header = 'Keranjang';

$me = current_user();

// Ambil data produk untuk semua item di keranjang (harga & stok selalu dari DB)
function load_cart_items($conn) {
    $items = [];
    foreach (cart_get() as $pid => $qty) {
        $pid = (int)$pid;
        $stmt = mysqli_prepare($conn, "SELECT id, nama_produk, kategori, foto, harga_jual, stok FROM produk WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $pid);
        mysqli_stmt_execute($stmt);
        $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($p) {
            $p['stok'] = (int)$p['stok'];
            $p['qty'] = (int)$qty;
            $p['subtotal'] = (float)$p['harga_jual'] * (int)$qty;
            $items[] = $p;
        } else {
            // produk sudah dihapus admin -> buang dari keranjang
            $c = cart_get(); unset($c[$pid]); $_SESSION['cart'] = $c;
        }
    }
    return $items;
}

// Sesuaikan isi keranjang dengan stok terbaru.
// Qty > stok dipotong jadi stok, produk yang stoknya 0 dibuang dari keranjang.
// Return: array pesan pemberitahuan.
function cart_sync_stok($conn) {
    $notes = [];
    $cart = cart_get();
    foreach (load_cart_items($conn) as $it) {
        $pid = (int)$it['id'];
        if ($it['stok'] <= 0) {
            unset($cart[$pid]);
            $notes[] = '"' . $it['nama_produk'] . '" habis dan dihapus dari keranjang.';
        } elseif ($it['qty'] > $it['stok']) {
            $cart[$pid] = $it['stok'];
            $notes[] = 'Jumlah "' . $it['nama_produk'] . '" disesuaikan jadi ' . $it['stok'] . ' (stok tersisa).';
        }
    }
    $_SESSION['cart'] = $cart;
    return $notes;
}

// ---------------------------------------------------
// Auto-update qty (dipanggil via fetch dari JS saat qty berubah)
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json; charset=utf-8');
    $pid = (int)($_POST['produk_id'] ?? 0);
    $q   = (int)($_POST['qty'] ?? 0);
    $cart = cart_get();
    $msg = '';

    if (!isset($cart[$pid])) {
        echo json_encode(['ok' => false, 'msg' => 'Item tidak ada di keranjang.']);
        exit;
    }

    $stmt = mysqli_prepare($conn, "SELECT stok FROM produk WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $pid);
    mysqli_stmt_execute($stmt);
    $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $stok = $p ? (int)$p['stok'] : 0;

    if ($stok <= 0) {
        unset($cart[$pid]);
        $_SESSION['cart'] = $cart;
        echo json_encode(['ok' => false, 'reload' => true]);
        exit;
    }
    if ($q < 1) $q = 1;
    if ($q > $stok) { $q = $stok; $msg = 'Stok tersisa hanya ' . $stok . '.'; }
    $cart[$pid] = $q;
    $_SESSION['cart'] = $cart;

    $total = 0; $sub = 0; $harga = 0;
    foreach (load_cart_items($conn) as $it) {
        $total += $it['subtotal'];
        if ((int)$it['id'] === $pid) { $sub = $it['subtotal']; }
    }
    echo json_encode([
        'ok'        => true,
        'qty'       => $q,
        'msg'       => $msg,
        'subtotal'  => rupiah($sub),
        'total'     => rupiah($total),
        'cartCount' => cart_count(),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Terapkan perubahan qty dari form (fallback tanpa JS)
    $cart = cart_get();
    foreach (($_POST['qty'] ?? []) as $pid => $q) {
        $pid = (int)$pid;
        if (!isset($cart[$pid])) continue;
        $q = (int)$q;
        if ($q <= 0) unset($cart[$pid]);
        else $cart[$pid] = min(999, $q);
    }
    // 2) Hapus satu item
    if (isset($_POST['remove'])) {
        unset($cart[(int)$_POST['remove']]);
    }
    $_SESSION['cart'] = $cart;

    // 3) Konfirmasi pesanan
    if (isset($_POST['konfirmasi'])) {
        $notes = cart_sync_stok($conn);
        if ($notes) {
            flash_set(implode(' ', $notes) . ' Cek lagi lalu konfirmasi.', 'danger');
            header('Location: keranjang.php');
            exit;
        }

        $items = load_cart_items($conn);
        if (empty($items)) {
            flash_set('Keranjang masih kosong.', 'danger');
            header('Location: keranjang.php');
            exit;
        }

        $catatan = trim($_POST['catatan'] ?? '');
        if (strlen($catatan) > 255) $catatan = substr($catatan, 0, 255);
        $uid = (int)$me['id'];

        mysqli_begin_transaction($conn);
        try {
            // Kunci baris produk & cek stok sekali lagi (cegah dua orang rebutan stok)
            $total = 0;
            $stmtStok = mysqli_prepare($conn, "SELECT stok FROM produk WHERE id = ? FOR UPDATE");
            $stmtKurang = mysqli_prepare($conn, "UPDATE produk SET stok = stok - ? WHERE id = ?");
            foreach ($items as $it) {
                $pid = (int)$it['id'];
                $qty = (int)$it['qty'];
                mysqli_stmt_bind_param($stmtStok, 'i', $pid);
                mysqli_stmt_execute($stmtStok);
                $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtStok));
                if (!$row || (int)$row['stok'] < $qty) {
                    throw new Exception('Stok "' . $it['nama_produk'] . '" tidak mencukupi.');
                }
                mysqli_stmt_bind_param($stmtKurang, 'ii', $qty, $pid);
                mysqli_stmt_execute($stmtKurang);
                $total += $it['subtotal'];
            }

            $stmt = mysqli_prepare($conn, "INSERT INTO pesanan (user_id, total, catatan, status) VALUES (?, ?, ?, 'menunggu')");
            mysqli_stmt_bind_param($stmt, 'ids', $uid, $total, $catatan);
            mysqli_stmt_execute($stmt);
            $pesananId = mysqli_insert_id($conn);

            $stmtItem = mysqli_prepare($conn, "INSERT INTO pesanan_item (pesanan_id, produk_id, nama_produk, harga, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($items as $it) {
                $pid = (int)$it['id'];
                $nama = $it['nama_produk'];
                $harga = (float)$it['harga_jual'];
                $qty = (int)$it['qty'];
                $sub = (float)$it['subtotal'];
                mysqli_stmt_bind_param($stmtItem, 'iisdid', $pesananId, $pid, $nama, $harga, $qty, $sub);
                mysqli_stmt_execute($stmtItem);
            }
            mysqli_commit($conn);
        } catch (Throwable $ex) {
            mysqli_rollback($conn);
            $m = ($ex instanceof Exception && strpos($ex->getMessage(), 'Stok') === 0)
                ? $ex->getMessage() . ' Silakan cek keranjang.'
                : 'Gagal membuat pesanan, coba lagi.';
            flash_set($m, 'danger');
            header('Location: keranjang.php');
            exit;
        }

        $_SESSION['cart'] = [];
        flash_set('Pesanan #' . $pesananId . ' berhasil dikonfirmasi. Tunggu diproses oleh staff.');
        header('Location: status_transaksi.php');
        exit;
    }

    header('Location: keranjang.php');
    exit;
}

// Saat halaman dibuka: sesuaikan dengan stok terbaru
$notes = cart_sync_stok($conn);
if ($notes) flash_set(implode(' ', $notes), 'danger');

$items = load_cart_items($conn);
$total = 0;
foreach ($items as $it) $total += $it['subtotal'];

include __DIR__ . '/../includes/header.php';
?>

<?php if (empty($items)): ?>
    <div class="panel">
        <div class="empty-state">
            Keranjang kamu masih kosong.<br>
            <a class="btn btn-primary" style="margin-top:14px;" href="market.php">Belanja di Market</a>
        </div>
    </div>
<?php else: ?>
<form method="POST" action="keranjang.php">
    <!-- tombol default (kalau user tekan Enter) = simpan qty, BUKAN hapus -->
    <button type="submit" name="update_cart" value="1" class="visually-hidden" tabindex="-1" aria-hidden="true">Perbarui</button>

    <div class="panel">
        <div class="panel-header"><h3>Barang di Keranjang</h3></div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Harga</th>
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
                        <td><?= rupiah($it['harga_jual']) ?></td>
                        <td>
                            <div class="qty-stepper">
                                <button type="button" data-step="-1">−</button>
                                <input type="number" name="qty[<?= (int)$it['id'] ?>]" value="<?= (int)$it['qty'] ?>" min="1" max="<?= (int)$it['stok'] ?>" data-cart-qty="<?= (int)$it['id'] ?>">
                                <button type="button" data-step="1">+</button>
                            </div>
                            <div class="hint" style="margin-top:4px;">Stok: <?= (int)$it['stok'] ?></div>
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
                <label>Catatan pesanan (opsional)</label>
                <input type="text" name="catatan" maxlength="255" placeholder="Contoh: ambil jam 8 malam">
            </div>
            <div class="total-row">
                <span>Total Pesanan</span>
                <strong id="cartTotal"><?= rupiah($total) ?></strong>
            </div>
            <div class="hint" id="cartMsg" style="min-height:18px; margin-top:8px;">Jumlah otomatis tersimpan saat kamu ubah.</div>
            <div class="form-actions" style="margin-top:16px;">
                <button type="submit" name="konfirmasi" value="1" class="btn btn-success">✔ Konfirmasi Pesanan</button>
                <a class="btn btn-outline" href="market.php">Lanjut Belanja</a>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
