<?php
require_once __DIR__ . '/work.php';
ensure_work_schema($conn);

$isManagement = has_role('admin', 'staff');
$isHomies     = has_role('homies');

$mgmtActive     = in_array($active_menu, ['produk', 'kelola_jual_beli', 'kelola_kerjaan', 'staff', 'homies', 'request_password'], true);
$workActive     = in_array($active_menu, ['kerjaan', 'status_kerjaan'], true);
$jualBeliActive = in_array($active_menu, ['market', 'jual_barang', 'status_transaksi'], true);

// Jumlah pesanan + pengajuan Jual Barang yang masih menunggu (badge di menu Management > Jual Beli)
$pendingCount = 0;
$resetCount = 0;   // permintaan reset password yang menunggu
$workPendingCount = 0;
if ($isManagement) {
    $qp = mysqli_query($conn, "SELECT COUNT(*) c FROM pesanan WHERE status = 'menunggu'");
    if ($qp) $pendingCount = (int)mysqli_fetch_assoc($qp)['c'];

    $qjb = mysqli_query($conn, "SELECT COUNT(*) c FROM jual_barang WHERE status = 'menunggu'");
    if ($qjb) $pendingCount += (int)mysqli_fetch_assoc($qjb)['c'];

    $qr = mysqli_query($conn, "SELECT COUNT(*) c FROM password_reset_request WHERE status = 'menunggu'");
    if ($qr) $resetCount = (int)mysqli_fetch_assoc($qr)['c'];

    $qw = mysqli_query($conn, "SELECT COUNT(*) c FROM work_task_claims WHERE status IN ('menunggu','dilaporkan')");
    if ($qw) $workPendingCount = (int)mysqli_fetch_assoc($qw)['c'];
}
?>
<aside class="sidebar">
    <div class="brand">
        <span>📦 Management</span>
        <button type="button" class="sidebar-close" data-sidebar-close aria-label="Tutup menu">✕</button>
    </div>
    <nav>
        <a class="nav-item <?= $active_menu === 'dashboard' ? 'active' : '' ?>" href="<?= e($base) ?>dashboard.php">
            🏠 Dashboard
        </a>

        <!-- Brangkas: lihat stok gudang (semua role, read-only) -->
        <a class="nav-item <?= $active_menu === 'brangkas' ? 'active' : '' ?>" href="<?= e($base) ?>brangkas/brangkas.php">
            🔐 Brangkas
        </a>

        <?php if ($isManagement): ?>

            <!-- Dropdown: Management (admin & staff saja) -->
            <div class="nav-group <?= $mgmtActive ? 'open' : '' ?>">
                <button type="button" class="nav-group-toggle" data-nav-toggle aria-expanded="<?= $mgmtActive ? 'true' : 'false' ?>">
                    <span>🗂️ Management</span>
                    <span class="chev">▾</span>
                </button>
                <div class="nav-children">
                    <a class="nav-item <?= $active_menu === 'produk' ? 'active' : '' ?>" href="<?= e($base) ?>produk/produk.php">
                        📦 Produk
                    </a>
                    <a class="nav-item <?= $active_menu === 'kelola_jual_beli' ? 'active' : '' ?>" href="<?= e($base) ?>jual_beli/kelola_pemesanan.php">
                        🧾 Jual Beli
                        <?php if ($pendingCount > 0): ?><span class="nav-badge"><?= $pendingCount ?></span><?php endif; ?>
                    </a>
                    <a class="nav-item <?= $active_menu === 'kelola_kerjaan' ? 'active' : '' ?>" href="<?= e($base) ?>work_management/kelola_kerjaan.php">
                        🛠️ Kelola Kerjaan
                        <?php if ($workPendingCount > 0): ?><span class="nav-badge"><?= $workPendingCount ?></span><?php endif; ?>
                    </a>
                    <?php if (has_role('admin')): ?>
                    <a class="nav-item <?= $active_menu === 'staff' ? 'active' : '' ?>" href="<?= e($base) ?>staff/management_staff.php">
                        🧑‍💼 Management Staff
                    </a>
                    <?php endif; ?>
                    <a class="nav-item <?= $active_menu === 'homies' ? 'active' : '' ?>" href="<?= e($base) ?>homies/management_homies.php">
                        🧑‍🤝‍🧑 Management Homies
                    </a>
                    <a class="nav-item <?= $active_menu === 'request_password' ? 'active' : '' ?>" href="<?= e($base) ?>homies/request_password.php">
                        🔑 Request Password
                        <?php if ($resetCount > 0): ?><span class="nav-badge"><?= $resetCount ?></span><?php endif; ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isManagement || $isHomies): ?>
            <!-- Dropdown: Jual Beli (admin, staff, homies) -->
            <div class="nav-group <?= $jualBeliActive ? 'open' : '' ?>">
                <button type="button" class="nav-group-toggle" data-nav-toggle aria-expanded="<?= $jualBeliActive ? 'true' : 'false' ?>">
                    <span>🛍️ Jual Beli</span>
                    <span class="chev">▾</span>
                </button>
                <div class="nav-children">
                    <a class="nav-item <?= $active_menu === 'market' ? 'active' : '' ?>" href="<?= e($base) ?>jual_beli/market.php">
                        🏪 Market
                    </a>
                    <a class="nav-item <?= $active_menu === 'jual_barang' ? 'active' : '' ?>" href="<?= e($base) ?>jual_beli/jual_barang.php">
                        🧳 Jual Barang
                    </a>
                    <a class="nav-item <?= $active_menu === 'status_transaksi' ? 'active' : '' ?>" href="<?= e($base) ?>jual_beli/status_transaksi.php">
                        📋 Status Transaksi
                    </a>
                </div>
            </div>

        <?php endif; ?>

        <!-- Dropdown: Work Management (semua role) -->
        <div class="nav-group <?= $workActive ? 'open' : '' ?>">
            <button type="button" class="nav-group-toggle" data-nav-toggle aria-expanded="<?= $workActive ? 'true' : 'false' ?>">
                <span>🛠️ Work Management</span>
                <span class="chev">▾</span>
            </button>
            <div class="nav-children">
                <a class="nav-item <?= $active_menu === 'kerjaan' ? 'active' : '' ?>" href="<?= e($base) ?>work_management/kerjaan.php">
                    📊 Kerjaan
                </a>
                <a class="nav-item <?= $active_menu === 'status_kerjaan' ? 'active' : '' ?>" href="<?= e($base) ?>work_management/status_kerjaan.php">
                    📋 Status Kerjaan
                </a>
            </div>
        </div>
    </nav>

    <!-- Info akun + aksi (tampil di HP saja; di desktop ada di topbar) -->
    <div class="sidebar-user">
        <div class="su-name"><?= e($me['full_name']) ?></div>
        <div class="su-role"><?= e(role_label($me['role'])) ?></div>
        <a class="nav-item" href="<?= e($base) ?>ganti_password.php">🔑 Ganti Password</a>
        <a class="nav-item" href="<?= e($base) ?>logout.php">🚪 Keluar</a>
    </div>
</aside>