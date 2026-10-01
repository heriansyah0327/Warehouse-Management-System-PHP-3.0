<?php
require_once __DIR__ . '/../includes/auth.php';
require_management();

$id = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT full_name FROM users WHERE id = ? AND role = 'homies' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$homies = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($homies) {
    $del = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
    mysqli_stmt_bind_param($del, 'i', $id);
    mysqli_stmt_execute($del);
    flash_set('Homies "' . $homies['full_name'] . '" berhasil dihapus.');
} else {
    flash_set('Data homies tidak ditemukan.', 'danger');
}

header('Location: management_homies.php');
exit;
