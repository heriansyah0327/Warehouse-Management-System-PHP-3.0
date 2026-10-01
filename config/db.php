<?php
// =====================================================
// Konfigurasi koneksi database
// Sesuaikan 4 baris di bawah dengan environment kamu (XAMPP/Laragon/hosting)
// =====================================================
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'heriansyah_management';

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conn) {
    die('Koneksi database gagal: ' . mysqli_connect_error() . '. Pastikan sudah import config/schema.sql.');
}

mysqli_set_charset($conn, 'utf8mb4');

// -----------------------------------------------------
// Auto-seed akun admin default kalau tabel users masih kosong.
// Dibuat lewat password_hash() PHP supaya hash pasti valid,
// tidak tergantung versi bcrypt di tools lain.
// -----------------------------------------------------
$check = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users");
if ($check) {
    $row = mysqli_fetch_assoc($check);
    if ((int)$row['total'] === 0) {
        $defaultHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, full_name, role) VALUES (?, ?, 'Administrator', 'admin')");
        $defaultUser = 'admin';
        mysqli_stmt_bind_param($stmt, 'ss', $defaultUser, $defaultHash);
        mysqli_stmt_execute($stmt);
    }
}
