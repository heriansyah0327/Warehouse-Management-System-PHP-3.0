<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Password sementara setelah permintaan "Lupa Password" disetujui admin/staff.
// Homies WAJIB menggantinya saat login berikutnya.
const DEFAULT_RESET_PASSWORD = '123';

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function current_user() {
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'username'  => $_SESSION['username'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'role'      => $_SESSION['role'] ?? null,
    ];
}

function has_role(...$roles) {
    return in_array($_SESSION['role'] ?? null, $roles, true);
}

function base_url($path = '') {
    $appRootFs = realpath(__DIR__ . '/..');
    $docRoot   = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');

    if ($docRoot && $appRootFs && strpos($appRootFs, $docRoot) === 0) {
        $webRoot = str_replace('\\', '/', substr($appRootFs, strlen($docRoot)));
    } else {
        $webRoot = '';
    }

    return rtrim($webRoot, '/') . '/' . ltrim($path, '/');
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . base_url('index.php'));
        exit;
    }

    // Akun baru direset -> semua halaman diblokir sampai password diganti.
    if (!empty($_SESSION['must_change_password'])) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script !== 'ganti_password.php') {
            header('Location: ' . base_url('ganti_password.php'));
            exit;
        }
    }
}

// Kategori produk FIXED dari code (bukan input bebas).
function kategori_options() {
    return ['Senjata', 'Ammo', 'Attachment', 'Narko', 'Lainnya', 'Spesial'];
}

// Hanya admin & staff yang boleh masuk ke area Management
// (Produk, Jual Beli, Management Staff, Management Homies).
// Homies yang nyasar ke sini diarahkan ke Market.
function require_management() {
    require_login();
    if (!has_role('admin', 'staff')) {
        header('Location: ' . base_url('jual_beli/market.php'));
        exit;
    }
}

function require_admin() {
    require_login();
    if (!has_role('admin')) {
        header('Location: ' . base_url('dashboard.php'));
        exit;
    }
}

// Area Jual Beli (Market, Keranjang, Jual Barang, Status Transaksi) bisa dibuka semua role
// (admin, staff, homies) selama sudah login.
function require_jual_beli() {
    require_login();
}

// Keranjang belanja disimpan di session: [produk_id => qty]
function cart_get() {
    return $_SESSION['cart'] ?? [];
}

function cart_count() {
    return (int)array_sum(cart_get());
}

// Kembalikan stok produk dari semua item sebuah pesanan
// (dipanggil saat pesanan dibatalkan user / ditolak admin).
// Harus dipanggil di dalam transaksi yang sama dengan update status.
function restore_stok_pesanan($conn, $pesananId) {
    $pesananId = (int)$pesananId;
    $stmt = mysqli_prepare($conn, "UPDATE produk p JOIN pesanan_item i ON i.produk_id = p.id SET p.stok = p.stok + i.qty WHERE i.pesanan_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $pesananId);
    mysqli_stmt_execute($stmt);
}

function status_label($status) {
    switch ($status) {
        case 'menunggu':   return 'Menunggu';
        case 'diacc':      return 'Diacc';
        case 'dilaporkan': return 'Dilaporkan';
        case 'selesai':    return 'Selesai';
        case 'dibatalkan': return 'Dibatalkan';
        case 'ditolak':    return 'Ditolak';
        default:           return $status;
    }
}

// Kategori yang tidak dijual di Market (khusus dipakai di Work Management).
const KATEGORI_NON_MARKET = 'Spesial';

// ---------------------------------------------------
// Jual Barang: homies jual barang KE toko.
// Hanya produk yang di-whitelist admin/staff yang muncul (lintas kategori,
// tidak dibatasi Narko/Spesial). Tanpa batas minimal ambil (min 1).
// Stok TIDAK berkurang saat diajukan; baru BERTAMBAH saat admin/staff ACC.
// Tidak ada tahap lapor hasil — ACC = final. Keranjang session sendiri.
// ---------------------------------------------------
const JUALBARANG_MIN_QTY = 1;

function cart_jualbarang_get() {
    return $_SESSION['cart_jualbarang'] ?? [];
}

function cart_jualbarang_count() {
    return count(cart_jualbarang_get());   // jumlah jenis barang di keranjang
}

// Tambahkan stok produk dari semua item sebuah pengajuan jual barang
// (dipanggil saat admin/staff klik ACC). Harus dipanggil di dalam transaksi
// yang sama dengan update status.
function tambah_stok_jual_barang($conn, $jualBarangId) {
    $jualBarangId = (int)$jualBarangId;
    $stmt = mysqli_prepare($conn, "UPDATE produk p JOIN jual_barang_item i ON i.produk_id = p.id SET p.stok = p.stok + i.qty WHERE i.jual_barang_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $jualBarangId);
    mysqli_stmt_execute($stmt);
}

// Label status khusus Jual Barang: "diacc" adalah status final (tanpa tahap
// lapor hasil), jadi ditampilkan sebagai "Selesai", bukan "Diacc".
function status_label_jual_barang($status) {
    if ($status === 'diacc') return 'Selesai';
    return status_label($status);
}

function reset_status_label($status) {
    switch ($status) {
        case 'menunggu':   return 'Menunggu';
        case 'disetujui':  return 'Disetujui';
        case 'ditolak':    return 'Ditolak';
        default:           return $status;
    }
}

function role_label($role) {
    switch ($role) {
        case 'admin':  return 'Admin';
        case 'staff':  return 'Staff';
        case 'homies': return 'Homies';
        default:       return $role;
    }
}

function flash_set($msg, $type = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function flash_get() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}
