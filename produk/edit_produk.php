<?php
require_once __DIR__ . '/../includes/auth.php';
require_management();

$base = '../';
$active_menu = 'produk';
$page_title = 'Edit Produk - Management';
$page_header = 'Edit Produk';

$id = (int)($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT * FROM produk WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$produk = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$produk) {
    flash_set('Produk tidak ditemukan.', 'danger');
    header('Location: produk.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama       = trim($_POST['nama_produk'] ?? '');
    $kategori   = trim($_POST['kategori'] ?? '');
    $hargaBeli  = (float)str_replace(['.', ','], '', $_POST['harga_beli'] ?? '0');
    $hargaJual  = (float)str_replace(['.', ','], '', $_POST['harga_jual'] ?? '0');
    $stok       = max(0, (int)($_POST['stok'] ?? 0));
    $foto       = $produk['foto'];

    if ($nama === '') {
        $errors[] = 'Nama produk wajib diisi.';
    }
    if (!in_array($kategori, kategori_options(), true)) {
        $errors[] = 'Pilih kategori: ' . implode(', ', kategori_options()) . '.';
    }

    if (!$errors && isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed, true)) {
            $destDir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            $fname = 'produk_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destDir . $fname)) {
                $foto = $fname;
            }
        }
    }

    if (!$errors) {
        $stmt2 = mysqli_prepare($conn, "UPDATE produk SET nama_produk=?, kategori=?, foto=?, stok=?, harga_beli=?, harga_jual=? WHERE id=?");
        mysqli_stmt_bind_param($stmt2, 'sssiddi', $nama, $kategori, $foto, $stok, $hargaBeli, $hargaJual, $id);
        mysqli_stmt_execute($stmt2);
        flash_set('Produk "' . $nama . '" berhasil diperbarui.');
        header('Location: produk.php');
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-header"><h3>Edit: <?= e($produk['nama_produk']) ?></h3></div>
    <div style="padding:20px;">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
        <?php endif; ?>

        <form method="POST" action="edit_produk.php?id=<?= (int)$produk['id'] ?>" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Nama Produk *</label>
                    <input type="text" name="nama_produk" value="<?= e($_POST['nama_produk'] ?? $produk['nama_produk']) ?>" required>
                </div>
                <div class="form-group full">
                    <label>Kategori Produk</label>
                    <?php $katNow = $_POST['kategori'] ?? $produk['kategori']; ?>
                    <select name="kategori" required>
                        <?php foreach (kategori_options() as $k): ?>
                            <option value="<?= e($k) ?>" <?= ($katNow === $k) ? 'selected' : '' ?>><?= e($k) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Stok (Quantity) *</label>
                    <input type="number" name="stok" min="0" value="<?= e($_POST['stok'] ?? (int)$produk['stok']) ?>" required>
                    <span class="hint">Kalau 0, produk tidak bisa dipesan.</span>
                </div>
                <div class="form-group">
                    <label>Harga Beli</label>
                    <input type="text" name="harga_beli" value="<?= e($_POST['harga_beli'] ?? (int)round($produk['harga_beli'])) ?>">
                </div>
                <div class="form-group">
                    <label>Harga Jual</label>
                    <input type="text" name="harga_jual" value="<?= e($_POST['harga_jual'] ?? (int)round($produk['harga_jual'])) ?>">
                </div>
                <div class="form-group full">
                    <label>Ganti Foto</label>
                    <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp">
                </div>
            </div>
            <div class="form-actions" style="margin-top:18px;">
                <button type="submit" class="btn btn-success">Simpan Perubahan</button>
                <a class="btn btn-outline" href="produk.php">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
