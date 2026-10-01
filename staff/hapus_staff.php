<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);

if ($id === (int)current_user()['id']) {
    flash_set('Kamu tidak bisa menghapus akunmu sendiri.', 'danger');
    header('Location: management_staff.php');
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT full_name FROM users WHERE id = ? AND role IN ('admin','staff') LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($user) {
    $del = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
    mysqli_stmt_bind_param($del, 'i', $id);
    mysqli_stmt_execute($del);
    flash_set('Akun "' . $user['full_name'] . '" berhasil dihapus.');
} else {
    flash_set('Akun tidak ditemukan.', 'danger');
}

header('Location: management_staff.php');
exit;
