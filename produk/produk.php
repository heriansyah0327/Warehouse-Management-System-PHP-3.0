<?php
require_once __DIR__ . '/../includes/auth.php';
require_management();

$base = '../';
$active_menu = 'produk';
$page_title = 'Produk - Management';
$page_header = 'Semua Produk';

$errors = [];

// ---------------------------------------------------
// Tambah produk baru
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_produk'])) {
    $nama       = trim($_POST['nama_produk'] ?? '');
    $kategori   = trim($_POST['kategori'] ?? '');
    $hargaBeli  = (float)str_replace(['.', ','], '', $_POST['harga_beli'] ?? '0');
    $hargaJual  = (float)str_replace(['.', ','], '', $_POST['harga_jual'] ?? '0');
    $stok       = max(0, (int)($_POST['stok'] ?? 0));
    $foto       = null;

    if ($nama === '') {
        $errors[] = 'Nama produk wajib diisi.';
    }
    if (!in_array($kategori, kategori_options(), true)) {
        $errors[] = 'Pilih kategori: ' . implode(', ', kategori_options()) . '.';
    }

    // Upload foto (opsional)
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
        $stmt = mysqli_prepare($conn, "INSERT INTO produk (nama_produk, kategori, foto, stok, harga_beli, harga_jual) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sssidd', $nama, $kategori, $foto, $stok, $hargaBeli, $hargaJual);
        mysqli_stmt_execute($stmt);
        flash_set('Produk "' . $nama . '" berhasil ditambahkan.');
        header('Location: produk.php');
        exit;
    }
}

// ---------------------------------------------------
// Ambil data produk
// ---------------------------------------------------
$produkList = [];
$res = mysqli_query($conn, "SELECT * FROM produk ORDER BY created_at DESC");
while ($row = mysqli_fetch_assoc($res)) {
    $produkList[] = $row;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-toolbar">
        <div class="search-box">
            <input type="text" placeholder="Cari produk..." data-table-search="#produkTable">
            <span class="icon">🔍</span>
        </div>
        <button class="btn btn-primary" data-open-modal="modalTambahProduk">+ Buat Produk Baru</button>
    </div>

    <table class="data-table" id="produkTable">
        <thead>
            <tr>
                <th>Foto</th>
                <th>Nama Produk</th>
                <th>Kategori</th>
                <th>Stok</th>
                <th>Harga Beli</th>
                <th>Harga Jual</th>
                <th>Tindakan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($produkList)): ?>
                <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">Belum ada produk. Klik "Buat Produk Baru" untuk mulai.</td></tr>
            <?php else: foreach ($produkList as $p): ?>
                <tr>
                    <td>
                        <div class="thumb">
                            <?php if ($p['foto']): ?>
                                <img src="<?= e($base) ?>assets/uploads/<?= e($p['foto']) ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>No Image<?php endif; ?>
                        </div>
                    </td>
                    <td><?= e($p['nama_produk']) ?></td>
                    <td><?= e($p['kategori'] ?: '-') ?></td>
                    <td>
                        <?php if ((int)$p['stok'] <= 0): ?>
                            <span class="badge badge-dibatalkan">Habis</span>
                        <?php else: ?>
                            <strong><?= (int)$p['stok'] ?></strong>
                        <?php endif; ?>
                    </td>
                    <td><?= rupiah($p['harga_beli']) ?></td>
                    <td><?= rupiah($p['harga_jual']) ?></td>
                    <td class="aksi-cell">
                        <a class="btn-icon edit" href="edit_produk.php?id=<?= (int)$p['id'] ?>" title="Edit">✏️</a>
                        <button type="button" class="btn-icon delete" title="Hapus"
                            data-delete-url="hapus_produk.php?id=<?= (int)$p['id'] ?>"
                            data-delete-label="produk &quot;<?= e($p['nama_produk']) ?>&quot;">🗑️</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="table-footer">Menampilkan <?= count($produkList) ?> produk</div>
</div>

<!-- Modal Tambah Produk -->
<div class="modal-overlay" id="modalTambahProduk">
    <div class="modal-box">
        <button class="modal-close" data-close-modal="modalTambahProduk">✕</button>
        <span class="modal-tag">Produk</span>
        <h2>Buat Produk Baru</h2>

        <?php if ($errors): ?>
            <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
        <?php endif; ?>

        <form method="POST" action="produk.php" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Nama Produk *</label>
                    <input type="text" name="nama_produk" placeholder="Wajib diisi" required>
                </div>
                <div class="form-group full">
                    <label>Kategori Produk</label>
                    <select name="kategori" required>
                        <option value="">-- Pilih kategori --</option>
                        <?php foreach (kategori_options() as $k): ?>
                            <option value="<?= e($k) ?>" <?= (($_POST['kategori'] ?? '') === $k) ? 'selected' : '' ?>><?= e($k) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Stok (Quantity) *</label>
                    <input type="number" name="stok" min="0" value="<?= e($_POST['stok'] ?? '0') ?>" required>
                    <span class="hint">Jumlah barang yang tersedia. Kalau 0, produk tidak bisa dipesan.</span>
                </div>
                <div class="form-group">
                    <label>Harga Beli</label>
                    <input type="text" name="harga_beli" placeholder="0">
                    <span class="hint">Harga per unit</span>
                </div>
                <div class="form-group">
                    <label>Harga Jual</label>
                    <input type="text" name="harga_jual" placeholder="0">
                    <span class="hint">Harga per unit</span>
                </div>
                <div class="form-group full">
                    <label>Foto Produk</label>
                    <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp">
                </div>
            </div>
            <div class="form-actions" style="margin-top:18px;">
                <button type="submit" name="add_produk" value="1" class="btn btn-success">Simpan</button>
                <button type="button" class="btn btn-outline" data-close-modal="modalTambahProduk">Batalkan</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
