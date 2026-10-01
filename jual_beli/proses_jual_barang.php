<?php
require_once __DIR__ . '/../includes/auth.php';
require_management();   // hanya admin & staff

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kelola_jual_barang.php');
    exit;
}

$id   = (int)($_POST['id'] ?? 0);
$aksi = $_POST['aksi'] ?? '';
$uid  = (int)current_user()['id'];

if ($id <= 0 || !in_array($aksi, ['acc', 'tolak'], true)) {
    flash_set('Permintaan tidak valid.', 'danger');
    header('Location: kelola_jual_barang.php');
    exit;
}

// ---------------------------------------------------
// ACC: hanya dari status "menunggu" -> "diacc". Stok produk otomatis bertambah.
// ---------------------------------------------------
if ($aksi === 'acc') {
    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn, "UPDATE jual_barang SET status = 'diacc', acc_by = ?, acc_at = NOW() WHERE id = ? AND status = 'menunggu'");
        mysqli_stmt_bind_param($stmt, 'ii', $uid, $id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            tambah_stok_jual_barang($conn, $id);
            mysqli_commit($conn);
            flash_set('Jual barang #' . $id . ' berhasil di-ACC. Stok toko sudah ditambahkan.');
        } else {
            mysqli_rollback($conn);
            flash_set('Pengajuan #' . $id . ' sudah diproses sebelumnya atau tidak ditemukan.', 'danger');
        }
    } catch (Throwable $ex) {
        mysqli_rollback($conn);
        flash_set('Gagal meng-ACC pengajuan, coba lagi.', 'danger');
    }
    header('Location: kelola_jual_barang.php');
    exit;
}

// ---------------------------------------------------
// Tolak: hanya dari status "menunggu" -> "ditolak". Tidak ada stok yang perlu
// dikembalikan karena stok belum pernah ditambahkan.
// ---------------------------------------------------
if ($aksi === 'tolak') {
    $stmt = mysqli_prepare($conn, "UPDATE jual_barang SET status = 'ditolak' WHERE id = ? AND status = 'menunggu'");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) > 0) {
        flash_set('Jual barang #' . $id . ' ditolak.');
    } else {
        flash_set('Pengajuan #' . $id . ' sudah diproses sebelumnya atau tidak ditemukan.', 'danger');
    }
    header('Location: kelola_jual_barang.php');
    exit;
}
