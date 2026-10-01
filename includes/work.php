<?php
require_once __DIR__ . '/auth.php';

// Default minimal ambil (dipakai sebagai isian awal form & fallback task lama). Nilai sebenarnya diatur per task di kolom work_tasks.min_qty.
const WORK_SALES_MIN_QTY = 1000;
const WORK_PROCESS_MIN_QTY = 100;
const WORK_SALES_CATEGORY = 'Narko';
const WORK_PROCESS_CATEGORY = 'Spesial';

function ensure_work_schema($conn) {
    static $done = false;
    if ($done) return;

    $queries = [
        "CREATE TABLE IF NOT EXISTS work_tasks (
            id INT(11) NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            type ENUM('penjualan','pemrosesan') COLLATE utf8mb4_unicode_ci NOT NULL,
            quota_total INT(11) NOT NULL,
            quota_remaining INT(11) NOT NULL,
            min_qty INT(11) NOT NULL DEFAULT 1,
            deadline DATETIME NOT NULL,
            created_by INT(11) DEFAULT NULL,
            status ENUM('aktif','selesai','dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktif',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_work_tasks_status_deadline (status, deadline),
            KEY idx_work_tasks_creator (created_by),
            CONSTRAINT fk_work_tasks_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS work_task_requirements (
            id INT(11) NOT NULL AUTO_INCREMENT,
            task_id INT(11) NOT NULL,
            section ENUM('penjualan','tools','bahan_baku') COLLATE utf8mb4_unicode_ci NOT NULL,
            produk_id INT(11) DEFAULT NULL,
            nama_produk VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
            qty INT(11) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_work_req_task (task_id),
            KEY idx_work_req_product (produk_id),
            CONSTRAINT fk_work_req_task FOREIGN KEY (task_id) REFERENCES work_tasks(id) ON DELETE CASCADE,
            CONSTRAINT fk_work_req_product FOREIGN KEY (produk_id) REFERENCES produk(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS work_task_claims (
            id INT(11) NOT NULL AUTO_INCREMENT,
            task_id INT(11) NOT NULL,
            user_id INT(11) NOT NULL,
            qty INT(11) NOT NULL,
            status ENUM('menunggu','diacc','dilaporkan','selesai','ditolak','dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu',
            acc_by INT(11) DEFAULT NULL,
            acc_at TIMESTAMP NULL DEFAULT NULL,
            hasil_uang DECIMAL(14,2) DEFAULT NULL,
            bukti_link VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            lapor_at TIMESTAMP NULL DEFAULT NULL,
            selesai_by INT(11) DEFAULT NULL,
            selesai_at TIMESTAMP NULL DEFAULT NULL,
            catatan VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_work_claim_task (task_id),
            KEY idx_work_claim_user (user_id),
            KEY idx_work_claim_status (status),
            CONSTRAINT fk_work_claim_task FOREIGN KEY (task_id) REFERENCES work_tasks(id) ON DELETE CASCADE,
            CONSTRAINT fk_work_claim_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_work_claim_acc FOREIGN KEY (acc_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_work_claim_done FOREIGN KEY (selesai_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS work_claim_result_items (
            id INT(11) NOT NULL AUTO_INCREMENT,
            claim_id INT(11) NOT NULL,
            produk_id INT(11) DEFAULT NULL,
            nama_produk VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
            qty INT(11) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_work_result_claim (claim_id),
            KEY idx_work_result_product (produk_id),
            CONSTRAINT fk_work_result_claim FOREIGN KEY (claim_id) REFERENCES work_task_claims(id) ON DELETE CASCADE,
            CONSTRAINT fk_work_result_product FOREIGN KEY (produk_id) REFERENCES produk(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS work_claim_stock_log (
            id INT(11) NOT NULL AUTO_INCREMENT,
            claim_id INT(11) NOT NULL,
            produk_id INT(11) NOT NULL,
            qty INT(11) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_work_stocklog_claim (claim_id),
            CONSTRAINT fk_work_stocklog_claim FOREIGN KEY (claim_id) REFERENCES work_task_claims(id) ON DELETE CASCADE,
            CONSTRAINT fk_work_stocklog_product FOREIGN KEY (produk_id) REFERENCES produk(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($queries as $sql) {
        if (!mysqli_query($conn, $sql)) {
            die('Gagal menyiapkan database Work Management: ' . mysqli_error($conn));
        }
    }

    // Migrasi database lama: tambah kolom min_qty kalau belum ada, task lama diisi default sesuai tipenya.
    $col = mysqli_query($conn, "SHOW COLUMNS FROM work_tasks LIKE 'min_qty'");
    if ($col && mysqli_num_rows($col) === 0) {
        if (!mysqli_query($conn, "ALTER TABLE work_tasks ADD COLUMN min_qty INT(11) NOT NULL DEFAULT 1 AFTER quota_remaining")) {
            die('Gagal migrasi kolom min_qty: ' . mysqli_error($conn));
        }
        mysqli_query($conn, "UPDATE work_tasks SET min_qty = CASE WHEN type = 'penjualan' THEN " . WORK_SALES_MIN_QTY . " ELSE " . WORK_PROCESS_MIN_QTY . " END");
    }
    $done = true;
}


function work_create_task($conn, $post, $uid) {
    $title = trim($post['title'] ?? '');
    $type = $post['type'] ?? '';
    $quota = (int)($post['quota_total'] ?? 0);
    $deadlineRaw = trim($post['deadline'] ?? '');
    // Deadline hanya tanggal (Y-m-d dari input type=date); berlaku sampai akhir hari itu.
    $deadlineDate = $deadlineRaw !== '' ? DateTime::createFromFormat('!Y-m-d', $deadlineRaw) : false;
    $deadlineTs = $deadlineDate ? strtotime($deadlineDate->format('Y-m-d') . ' 23:59:59') : false;
    $uid = (int)$uid;
    $minQty = (int)($post['min_qty'] ?? 0);
    $error = '';

    if ($title === '' || strlen($title) > 255) $error = 'Judul task wajib diisi dan maksimal 255 karakter.';
    elseif (!in_array($type, ['penjualan','pemrosesan'], true)) $error = 'Tipe task tidak valid.';
    elseif ($quota < 1) $error = 'Jumlah barang tersedia minimal 1.';
    elseif ($minQty < 1) $error = 'Minimal ambil wajib diisi (minimal 1).';
    elseif ($minQty > $quota) $error = 'Minimal ambil (' . number_format($minQty, 0, ',', '.') . ') tidak boleh lebih besar dari jumlah barang tersedia (' . number_format($quota, 0, ',', '.') . ').';
    elseif ($deadlineTs === false) $error = 'Deadline wajib diisi.';
    elseif ($deadlineTs <= time()) $error = 'Deadline tidak boleh sebelum hari ini.';

    $requirements = [];
    if ($error === '' && $type === 'penjualan') {
        $pid = (int)($post['sales_product_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "SELECT id, nama_produk, kategori FROM produk WHERE id = ? AND kategori = 'Narko' LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $pid);
        mysqli_stmt_execute($stmt);
        $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if (!$p) $error = 'Pilih satu produk kategori Narko untuk task penjualan.';
        else $requirements[] = ['section' => 'penjualan', 'produk_id' => (int)$p['id'], 'nama_produk' => $p['nama_produk'], 'qty' => 1];
    }

    if ($error === '' && $type === 'pemrosesan') {
        $seen = [];
        foreach (['tools' => 'Tools Terkait', 'bahan_baku' => 'Bahan Baku'] as $section => $label) {
            $ids = $post[$section . '_product_id'] ?? [];
            $qtys = $post[$section . '_qty'] ?? [];
            if (!is_array($ids)) $ids = [];
            if (!is_array($qtys)) $qtys = [];
            for ($i = 0; $i < count($ids); $i++) {
                $pid = (int)$ids[$i];
                $qty = (int)($qtys[$i] ?? 0);
                if ($pid <= 0 && $qty <= 0) continue;
                if ($pid <= 0 || $qty < 1) { $error = $label . ' harus punya produk dan jumlah yang valid.'; break 2; }
                $stmt = mysqli_prepare($conn, "SELECT id, nama_produk, kategori FROM produk WHERE id = ? AND kategori = 'Spesial' LIMIT 1");
                mysqli_stmt_bind_param($stmt, 'i', $pid);
                mysqli_stmt_execute($stmt);
                $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                if (!$p) { $error = 'Semua requirement pemrosesan harus berasal dari kategori Spesial.'; break 2; }
                $key = $section . ':' . $pid;
                if (isset($seen[$key])) { $error = $p['nama_produk'] . ' dipilih lebih dari sekali pada ' . $label . '.'; break 2; }
                $seen[$key] = true;
                $requirements[] = ['section' => $section, 'produk_id' => (int)$p['id'], 'nama_produk' => $p['nama_produk'], 'qty' => $qty];
            }
        }
        $hasTools = false; $hasBahan = false;
        foreach ($requirements as $r) {
            if ($r['section'] === 'tools') $hasTools = true;
            if ($r['section'] === 'bahan_baku') $hasBahan = true;
        }
        if ($error === '' && (!$hasTools || !$hasBahan)) $error = 'Task pemrosesan wajib memiliki minimal 1 Tools Terkait dan 1 Bahan Baku.';
    }

    if ($error !== '') return ['ok' => false, 'error' => $error];

    $deadline = date('Y-m-d H:i:s', $deadlineTs);
    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn, "INSERT INTO work_tasks (title, type, quota_total, quota_remaining, min_qty, deadline, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssiiisi', $title, $type, $quota, $quota, $minQty, $deadline, $uid);
        mysqli_stmt_execute($stmt);
        $taskId = mysqli_insert_id($conn);

        $ri = mysqli_prepare($conn, "INSERT INTO work_task_requirements (task_id, section, produk_id, nama_produk, qty) VALUES (?, ?, ?, ?, ?)");
        foreach ($requirements as $r) {
            mysqli_stmt_bind_param($ri, 'isisi', $taskId, $r['section'], $r['produk_id'], $r['nama_produk'], $r['qty']);
            mysqli_stmt_execute($ri);
        }
        mysqli_commit($conn);
        return ['ok' => true, 'task_id' => $taskId, 'title' => $title];
    } catch (Throwable $ex) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'Gagal membuat task, coba lagi.'];
    }
}

// Format tanggal deadline (tanpa jam). Ubah di sini kalau mau format lain (mis. 'd/m/y').
function work_format_date($datetime) {
    return date('m/d/y', strtotime($datetime));
}

// Minimal ambil per task (diatur manual saat Buat Task). Fallback ke default tipe kalau kolom kosong.
function work_min_qty(array $task) {
    $min = (int)($task['min_qty'] ?? 0);
    if ($min >= 1) return $min;
    return ($task['type'] ?? '') === 'penjualan' ? WORK_SALES_MIN_QTY : WORK_PROCESS_MIN_QTY;
}

function work_type_label($type) {
    return $type === 'penjualan' ? 'Penjualan' : 'Pemrosesan';
}

function work_claim_status_label($status) {
    return status_label($status);
}

function work_task_status_label($status) {
    switch ($status) {
        case 'aktif': return 'Aktif';
        case 'selesai': return 'Selesai';
        case 'dibatalkan': return 'Dibatalkan';
        default: return $status;
    }
}

function work_restore_quota($conn, $taskId, $qty) {
    $stmt = mysqli_prepare($conn, "UPDATE work_tasks SET quota_remaining = LEAST(quota_total, quota_remaining + ?) WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $qty, $taskId);
    mysqli_stmt_execute($stmt);
}

function work_add_uang_merah($conn, $amount) {
    $amount = (int)$amount;
    if ($amount < 1) return false;
    $stmt = mysqli_prepare($conn, "SELECT id FROM produk WHERE LOWER(TRIM(nama_produk)) = 'uang merah' LIMIT 1 FOR UPDATE");
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$row) return false;
    $pid = (int)$row['id'];
    $up = mysqli_prepare($conn, "UPDATE produk SET stok = stok + ? WHERE id = ?");
    mysqli_stmt_bind_param($up, 'ii', $amount, $pid);
    mysqli_stmt_execute($up);
    return mysqli_stmt_affected_rows($up) > 0;
}


// -----------------------------------------------------
// Stok produk untuk ambil task
// -----------------------------------------------------

// Hitung produk apa saja & berapa banyak yang harus dipotong dari stok untuk satu pengambilan task.
// ATURAN POTONGAN ADA DI SINI (cukup ubah di fungsi ini kalau mau ganti):
//   - Penjualan : produk Narko pada task, dipotong sebanyak qty yang diambil.
//   - Pemrosesan: Tools Terkait  -> dipotong sebanyak "Butuh" (tetap, tidak dikali qty).
//                 Bahan Baku     -> dipotong sebanyak "Butuh" x qty yang diambil.
// Return: [produk_id => ['nama' => ..., 'qty' => ...]]
function work_stock_needs($conn, $taskId, $type, $claimQty) {
    $needs = [];
    $stmt = mysqli_prepare($conn, "SELECT section, produk_id, nama_produk, qty FROM work_task_requirements WHERE task_id = ? AND produk_id IS NOT NULL");
    mysqli_stmt_bind_param($stmt, 'i', $taskId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $pid = (int)$r['produk_id'];
        if ($type === 'penjualan') $need = (int)$claimQty;
        elseif ($r['section'] === 'bahan_baku') $need = (int)$r['qty'] * (int)$claimQty;
        else $need = (int)$r['qty'];
        if ($need < 1) continue;
        if (!isset($needs[$pid])) $needs[$pid] = ['nama' => $r['nama_produk'], 'qty' => 0];
        $needs[$pid]['qty'] += $need;
    }
    return $needs;
}

// Kurangi stok produk untuk satu claim + catat di work_claim_stock_log (supaya bisa dikembalikan persis).
// Wajib dipanggil di dalam transaksi. Melempar Exception kalau stok tidak cukup.
function work_deduct_stock($conn, $claimId, array $needs) {
    ksort($needs); // urutan lock konsisten, hindari deadlock
    $sel = mysqli_prepare($conn, "SELECT stok FROM produk WHERE id = ? FOR UPDATE");
    $upd = mysqli_prepare($conn, "UPDATE produk SET stok = stok - ? WHERE id = ?");
    $log = mysqli_prepare($conn, "INSERT INTO work_claim_stock_log (claim_id, produk_id, qty) VALUES (?, ?, ?)");
    foreach ($needs as $pid => $n) {
        mysqli_stmt_bind_param($sel, 'i', $pid);
        mysqli_stmt_execute($sel);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($sel));
        if (!$row) throw new Exception('Produk "' . $n['nama'] . '" tidak ditemukan.');
        if ((int)$row['stok'] < $n['qty']) {
            throw new Exception('Stok "' . $n['nama'] . '" tidak cukup. Dibutuhkan ' . number_format($n['qty'], 0, ',', '.') . ', stok tersisa ' . number_format((int)$row['stok'], 0, ',', '.') . '.');
        }
        mysqli_stmt_bind_param($upd, 'ii', $n['qty'], $pid);
        mysqli_stmt_execute($upd);
        mysqli_stmt_bind_param($log, 'iii', $claimId, $pid, $n['qty']);
        mysqli_stmt_execute($log);
    }
}

// Kembalikan stok yang dulu dipotong untuk claim ini (dipakai saat ditolak admin / dibatalkan user).
// Aman dipanggil berulang: log dihapus setelah dikembalikan. Claim lama (sebelum fitur ini) tidak punya log, jadi tidak ada yang dikembalikan.
// Wajib dipanggil di dalam transaksi.
function work_restore_stock($conn, $claimId) {
    $claimId = (int)$claimId;
    $res = mysqli_query($conn, "SELECT produk_id, qty FROM work_claim_stock_log WHERE claim_id = $claimId FOR UPDATE");
    $upd = mysqli_prepare($conn, "UPDATE produk SET stok = stok + ? WHERE id = ?");
    while ($r = mysqli_fetch_assoc($res)) {
        mysqli_stmt_bind_param($upd, 'ii', $r['qty'], $r['produk_id']);
        mysqli_stmt_execute($upd);
    }
    mysqli_query($conn, "DELETE FROM work_claim_stock_log WHERE claim_id = $claimId");
}