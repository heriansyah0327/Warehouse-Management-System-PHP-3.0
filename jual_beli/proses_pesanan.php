<?php
require_once __DIR__ . '/../includes/auth.php';
require_management();   // hanya admin & staff

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kelola_pemesanan.php');
    exit;
}

$id   = (int)($_POST['id'] ?? 0);
$aksi = $_POST['aksi'] ?? '';
$map  = ['selesai' => 'selesai', 'tolak' => 'ditolak'];

if ($id <= 0 || !isset($map[$aksi])) {
    flash_set('Permintaan tidak valid.', 'danger');
    header('Location: kelola_pemesanan.php');
    exit;
}

$statusBaru = $map[$aksi];

// Hanya pesanan yang masih "menunggu" yang bisa diubah
mysqli_begin_transaction($conn);
try {
    $stmt = mysqli_prepare($conn, "UPDATE pesanan SET status = ? WHERE id = ? AND status = 'menunggu'");
    mysqli_stmt_bind_param($stmt, 'si', $statusBaru, $id);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) > 0) {
        // Pesanan ditolak -> stok barang dikembalikan
        if ($statusBaru === 'ditolak') restore_stok_pesanan($conn, $id);
        mysqli_commit($conn);
        flash_set('Pesanan #' . $id . ' ' . ($statusBaru === 'ditolak' ? 'ditolak. Stok barang dikembalikan.' : 'ditandai selesai.'));
    } else {
        mysqli_rollback($conn);
        flash_set('Pesanan #' . $id . ' sudah diproses sebelumnya atau tidak ditemukan.', 'danger');
    }
} catch (Throwable $ex) {
    mysqli_rollback($conn);
    flash_set('Gagal memproses pesanan, coba lagi.', 'danger');
}

header('Location: kelola_pemesanan.php');
exit;
