<?php
require_once __DIR__ . '/../includes/auth.php';
require_management();

$id = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT nama_produk, foto FROM produk WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$produk = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($produk) {
    $del = mysqli_prepare($conn, "DELETE FROM produk WHERE id = ?");
    mysqli_stmt_bind_param($del, 'i', $id);
    mysqli_stmt_execute($del);

    if ($produk['foto']) {
        $path = __DIR__ . '/../assets/uploads/' . $produk['foto'];
        if (is_file($path)) unlink($path);
    }

    flash_set('Produk "' . $produk['nama_produk'] . '" berhasil dihapus.');
} else {
    flash_set('Produk tidak ditemukan.', 'danger');
}

header('Location: produk.php');
exit;
