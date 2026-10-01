<?php
require_once __DIR__ . '/../includes/work.php';
require_login();
ensure_work_schema($conn);

$base = '../';
$active_menu = 'kerjaan';
$page_title = 'Kerjaan - Work Management';
$page_header = 'Kerjaan';
$isManagement = has_role('admin', 'staff');
$uid = (int)current_user()['id'];

$createTaskError = '';
$createTaskSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buat_task'])) {
    if (!$isManagement) {
        flash_set('Hanya Admin/Staff yang bisa membuat task.', 'danger');
        header('Location: kerjaan.php'); exit;
    }
    $created = work_create_task($conn, $_POST, $uid);
    if ($created['ok']) {
        flash_set('Task "' . $created['title'] . '" berhasil dibuat.');
        header('Location: kerjaan.php'); exit;
    }
    $createTaskError = $created['error'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_task'])) {
    if (!$isManagement) {
        flash_set('Hanya Admin/Staff yang bisa menghapus task.', 'danger');
        header('Location: kerjaan.php'); exit;
    }
    $taskId = (int)$_POST['hapus_task'];
    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn, "SELECT id, title FROM work_tasks WHERE id = ? AND status = 'aktif' FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 'i', $taskId); mysqli_stmt_execute($stmt);
        $task = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if (!$task) throw new Exception('Task tidak ditemukan atau sudah dihapus.');

        // Pengajuan yang masih "menunggu" ikut dibatalkan & stok produknya dikembalikan.
        // Pengajuan yang sudah di-ACC / dilaporkan / selesai dibiarkan supaya riwayat & kerja user tidak hilang.
        $stc = mysqli_prepare($conn, "SELECT id FROM work_task_claims WHERE task_id = ? AND status = 'menunggu' FOR UPDATE");
        mysqli_stmt_bind_param($stc, 'i', $taskId); mysqli_stmt_execute($stc);
        $pending = mysqli_fetch_all(mysqli_stmt_get_result($stc), MYSQLI_ASSOC);
        $cancel = mysqli_prepare($conn, "UPDATE work_task_claims SET status = 'dibatalkan' WHERE id = ? AND status = 'menunggu'");
        foreach ($pending as $pc) {
            $cid = (int)$pc['id'];
            mysqli_stmt_bind_param($cancel, 'i', $cid); mysqli_stmt_execute($cancel);
            work_restore_stock($conn, $cid);
        }

        // Soft delete: task hilang dari halaman Kerjaan, tapi riwayat claim tetap utuh.
        $del = mysqli_prepare($conn, "UPDATE work_tasks SET status = 'dibatalkan' WHERE id = ? AND status = 'aktif'");
        mysqli_stmt_bind_param($del, 'i', $taskId); mysqli_stmt_execute($del);
        if (mysqli_stmt_affected_rows($del) < 1) throw new Exception('Task gagal dihapus, coba lagi.');

        mysqli_commit($conn);
        $msg = 'Task "' . $task['title'] . '" berhasil dihapus.';
        if ($pending) $msg .= ' ' . count($pending) . ' pengajuan yang masih menunggu ikut dibatalkan (stok dikembalikan).';
        flash_set($msg);
    } catch (Throwable $ex) {
        mysqli_rollback($conn);
        flash_set($ex->getMessage(), 'danger');
    }
    header('Location: kerjaan.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ambil_task'])) {
        $taskId = (int)$_POST['ambil_task'];
    $qty = (int)($_POST['qty'] ?? 0);
    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn, "SELECT id, type, quota_remaining, min_qty, deadline, status FROM work_tasks WHERE id = ? FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 'i', $taskId); mysqli_stmt_execute($stmt);
        $task = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if (!$task || $task['status'] !== 'aktif') throw new Exception('Task sudah tidak aktif.');
        if (strtotime($task['deadline']) <= time()) throw new Exception('Deadline task sudah lewat.');
        $min = work_min_qty($task);
        if ($qty < $min) throw new Exception('Minimal ambil ' . number_format($min, 0, ',', '.') . '.');
        if ($qty > (int)$task['quota_remaining']) throw new Exception('Sisa kuota task hanya ' . number_format((int)$task['quota_remaining'], 0, ',', '.') . '.');
        if ((int)$task['quota_remaining'] - $qty < 0) throw new Exception('Kuota task tidak mencukupi.');

        $stmt2 = mysqli_prepare($conn, "UPDATE work_tasks SET quota_remaining = quota_remaining - ? WHERE id = ? AND quota_remaining >= ? AND status = 'aktif'");
        mysqli_stmt_bind_param($stmt2, 'iii', $qty, $taskId, $qty); mysqli_stmt_execute($stmt2);
        if (mysqli_stmt_affected_rows($stmt2) < 1) throw new Exception('Kuota task baru saja berubah. Coba lagi.');

        $stmt3 = mysqli_prepare($conn, "INSERT INTO work_task_claims (task_id, user_id, qty, status) VALUES (?, ?, ?, 'menunggu')");
        mysqli_stmt_bind_param($stmt3, 'iii', $taskId, $uid, $qty); mysqli_stmt_execute($stmt3);
        $claimId = mysqli_insert_id($conn);

        // Stok produk ikut berkurang saat task diambil (dikembalikan otomatis kalau ditolak/dibatalkan).
        work_deduct_stock($conn, $claimId, work_stock_needs($conn, $taskId, $task['type'], $qty));
        mysqli_commit($conn);
        flash_set('Task berhasil diambil sebanyak ' . number_format($qty, 0, ',', '.') . '. Menunggu ACC admin/staff.');
    } catch (Throwable $ex) {
        mysqli_rollback($conn);
        flash_set($ex->getMessage(), 'danger');
    }
    header('Location: kerjaan.php'); exit;
}

$sql = "SELECT t.* FROM work_tasks t WHERE t.status = 'aktif' ORDER BY t.created_at DESC, t.id DESC";
$res = mysqli_query($conn, $sql);
$tasks = [];
while ($r = mysqli_fetch_assoc($res)) $tasks[] = $r;

$reqBy = [];
if ($tasks) {
    $ids = implode(',', array_map('intval', array_column($tasks, 'id')));
    $rr = mysqli_query($conn, "SELECT * FROM work_task_requirements WHERE task_id IN ($ids) ORDER BY FIELD(section,'penjualan','tools','bahan_baku'), id ASC");
    while ($r = mysqli_fetch_assoc($rr)) $reqBy[(int)$r['task_id']][] = $r;
}

$produkNarko = [];
$produkSpesial = [];
if ($isManagement) {
    $resNarko = mysqli_query($conn, "SELECT id, nama_produk, stok FROM produk WHERE kategori = 'Narko' ORDER BY nama_produk ASC");
    while ($r = mysqli_fetch_assoc($resNarko)) $produkNarko[] = $r;
    $resSpesial = mysqli_query($conn, "SELECT id, nama_produk, stok FROM produk WHERE kategori = 'Spesial' ORDER BY nama_produk ASC");
    while ($r = mysqli_fetch_assoc($resSpesial)) $produkSpesial[] = $r;
}

$postedToolsIds = is_array($_POST['tools_product_id'] ?? null) ? $_POST['tools_product_id'] : [];
$postedToolsQtys = is_array($_POST['tools_qty'] ?? null) ? $_POST['tools_qty'] : [];
$postedBahanIds = is_array($_POST['bahan_baku_product_id'] ?? null) ? $_POST['bahan_baku_product_id'] : [];
$postedBahanQtys = is_array($_POST['bahan_baku_qty'] ?? null) ? $_POST['bahan_baku_qty'] : [];
$modalOpen = $createTaskError !== '';

include __DIR__ . '/../includes/header.php';
?>

<div class="work-page-head">
    <div>
        <div class="muted">Ambil task dulu sebelum kerja — baik task digaji maupun task wajib.</div>
    </div>
    <?php if ($isManagement): ?><button type="button" class="btn btn-primary" data-open-modal="modalBuatTask">＋ Buat Task</button><?php endif; ?>
</div>

<?php if (empty($tasks)): ?>
    <div class="panel"><div class="empty-state">Belum ada task aktif.</div></div>
<?php else: ?>
<div class="work-task-grid">
<?php foreach ($tasks as $t):
    $req = $reqBy[(int)$t['id']] ?? [];
    $min = work_min_qty($t);
    $sisa = (int)$t['quota_remaining'];
    $deadlinePast = strtotime($t['deadline']) <= time();
    $canTake = !$deadlinePast && $sisa >= $min;
    $tools = []; $bahan = []; $salesReq = null;
    foreach ($req as $r) {
        if ($r['section'] === 'tools') $tools[] = $r;
        elseif ($r['section'] === 'bahan_baku') $bahan[] = $r;
        else $salesReq = $r;
    }
?>
    <div class="work-task-card <?= $sisa < $min || $deadlinePast ? 'is-closed' : '' ?>">
        <div class="work-task-pin">📌</div>
        <div class="work-task-head">
            <span class="work-task-type"><?= e(strtoupper(work_type_label($t['type']))) ?></span>
            <?php if ($isManagement): ?>
            <form method="POST" class="work-task-delete-form" onsubmit="return confirm('Hapus task &quot;<?= e(addslashes($t['title'])) ?>&quot;? Pengajuan yang masih menunggu akan dibatalkan dan stok dikembalikan.');">
                <input type="hidden" name="hapus_task" value="<?= (int)$t['id'] ?>">
                <button type="submit" class="btn-icon delete" title="Hapus task">🗑️</button>
            </form>
            <?php endif; ?>
        </div>
        <h3><?= e($t['title']) ?></h3>

        <?php if ($t['type'] === 'penjualan'): ?>
            <div class="work-requirement-box compact">
                <div class="work-req-title">KATEGORI NARKO</div>
                <div class="work-req-item"><span><?= e($salesReq['nama_produk'] ?? '-') ?></span><strong>1 pilihan</strong></div>
            </div>
        <?php else: ?>
            <div class="work-requirement-box compact">
                <div class="work-req-title">TOOLS TERKAIT</div>
                <?php foreach ($tools as $r): ?><div class="work-req-item"><span><?= e($r['nama_produk']) ?></span><strong>Butuh: <?= (int)$r['qty'] ?></strong></div><?php endforeach; ?>
            </div>
            <div class="work-requirement-box compact">
                <div class="work-req-title">BAHAN BAKU</div>
                <?php foreach ($bahan as $r): ?><div class="work-req-item"><span><?= e($r['nama_produk']) ?></span><strong>Butuh: <?= (int)$r['qty'] ?></strong></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="work-quota">Sisa kuota: <strong><?= number_format($sisa, 0, ',', '.') ?> / <?= number_format((int)$t['quota_total'], 0, ',', '.') ?></strong> <span>(min. <?= number_format($min, 0, ',', '.') ?> per pengambilan)</span></div>
        <div class="work-deadline">◷ Deadline: <?= e(work_format_date($t['deadline'])) ?></div>

        <?php if ($canTake): ?>
            <form method="POST" class="work-take-form">
                <input type="hidden" name="ambil_task" value="<?= (int)$t['id'] ?>">
                <input type="number" name="qty" min="<?= $min ?>" max="<?= $sisa ?>" step="1" placeholder="Jumlah yang diambil (min. <?= $min ?>)" required>
                <button class="btn btn-primary" type="submit">AMBIL TASK</button>
            </form>
        <?php elseif ($deadlinePast): ?>
            <button class="btn btn-outline work-disabled" disabled>DEADLINE LEWAT</button>
        <?php else: ?>
            <button class="btn btn-outline work-disabled" disabled><?= $sisa > 0 ? 'SISA &lt; MINIMUM' : 'KUOTA HABIS' ?></button>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($isManagement): ?>
<!-- Modal Buat Task -->
<div class="modal-overlay <?= $modalOpen ? 'show' : '' ?>" id="modalBuatTask">
    <div class="modal-box work-create-modal">
        <button class="modal-close" data-close-modal="modalBuatTask" type="button">✕</button>
        <span class="modal-tag">Work Management</span>
        <h2>Buat Task</h2>

        <?php if ($createTaskError): ?>
            <div class="alert alert-danger"><?= e($createTaskError) ?></div>
        <?php endif; ?>

        <form method="POST" action="kerjaan.php" id="workTaskForm">
            <input type="hidden" name="buat_task" value="1">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Title / Judul Task *</label>
                    <input type="text" name="title" maxlength="255" required placeholder="Contoh: JUALAN WEED" value="<?= e($_POST['title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Tipe *</label>
                    <select name="type" id="workType" required>
                        <option value="">Pilih tipe...</option>
                        <option value="penjualan" <?= (($_POST['type'] ?? '') === 'penjualan') ? 'selected' : '' ?>>Penjualan</option>
                        <option value="pemrosesan" <?= (($_POST['type'] ?? '') === 'pemrosesan') ? 'selected' : '' ?>>Pemrosesan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Jumlah Barang Tersedia *</label>
                    <input type="number" name="quota_total" min="1" required placeholder="Contoh: 20000" value="<?= e($_POST['quota_total'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Minimal Ambil *</label>
                    <input type="number" name="min_qty" id="workMinQty" min="1" required placeholder="Contoh: 1000" value="<?= e($_POST['min_qty'] ?? '') ?>">
                    <span class="hint">Jumlah minimum yang boleh diambil per pengambilan.</span>
                </div>
                <div class="form-group">
                    <label>Deadline *</label>
                    <input type="date" name="deadline" required min="<?= date('Y-m-d') ?>" value="<?= e($_POST['deadline'] ?? '') ?>">
                </div>
            </div>

            <div id="salesFields" class="work-requirement-box" style="display:none; margin-top:20px;">
                <div class="work-req-title">Kategori Narko</div>
                <div class="hint" style="margin-bottom:10px;">Pilih satu barang untuk requirement task penjualan.</div>
                <div class="work-radio-list">
                    <?php foreach ($produkNarko as $p): ?>
                        <label class="work-radio-option">
                            <input type="radio" name="sales_product_id" value="<?= (int)$p['id'] ?>" <?= ((int)($_POST['sales_product_id'] ?? 0) === (int)$p['id']) ? 'checked' : '' ?>>
                            <span><?= e($p['nama_produk']) ?> (Stok : <?= number_format((int)$p['stok'], 0, ',', '.') ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="processFields" style="display:none; margin-top:20px;">
                <div class="work-requirement-box">
                    <div class="work-req-title">Tools Terkait</div>
                    <div class="hint" style="margin-bottom:10px;">Requirement tools dipisah dari bahan baku.</div>
                    <div id="toolsRows"></div>
                    <button type="button" class="btn btn-outline" data-add-work-row="tools">＋ Tambah Tools</button>
                </div>

                <div class="work-requirement-box" style="margin-top:14px;">
                    <div class="work-req-title">Bahan Baku</div>
                    <div class="hint" style="margin-bottom:10px;">Hanya produk kategori Spesial.</div>
                    <div id="bahanRows"></div>
                    <button type="button" class="btn btn-outline" data-add-work-row="bahan_baku">＋ Tambah Bahan Baku</button>
                </div>
            </div>

            <div class="form-actions" style="margin-top:18px;">
                <button type="submit" class="btn btn-success">Simpan</button>
                <button type="button" class="btn btn-outline" data-close-modal="modalBuatTask">Batalkan</button>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
    var type = document.getElementById('workType');
    var sales = document.getElementById('salesFields');
    var process = document.getElementById('processFields');
    var minInput = document.getElementById('workMinQty');
    var defaults = {penjualan: <?= WORK_SALES_MIN_QTY ?>, pemrosesan: <?= WORK_PROCESS_MIN_QTY ?>};
    if (!type) return;
    var spesial = <?= json_encode($produkSpesial, JSON_UNESCAPED_UNICODE) ?>;

    function setEnabled(container, enabled){
        if (!container) return;
        container.querySelectorAll('input, select, textarea').forEach(function(el){
            el.disabled = !enabled;
        });
    }

    function refresh(){
        var v = type.value;
        var isSales = v === 'penjualan';
        var isProcess = v === 'pemrosesan';
        sales.style.display = isSales ? 'block' : 'none';
        process.style.display = isProcess ? 'block' : 'none';
        // Field pada section yang sedang disembunyikan harus disabled,
        // supaya browser tidak menggagalkan submit karena required field tersembunyi.
        setEnabled(sales, isSales);
        setEnabled(process, isProcess);
        // Isi otomatis dengan default tipe kalau masih kosong; tetap bisa diubah manual.
        if (minInput && minInput.value === '' && defaults[v]) minInput.value = defaults[v];
    }
    type.addEventListener('change', refresh);

    function escapeHtml(s){
        return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]; });
    }
    var posted = {
        tools: <?= json_encode(array_map(null, array_values($postedToolsIds), array_values($postedToolsQtys)), JSON_UNESCAPED_UNICODE) ?>,
        bahan_baku: <?= json_encode(array_map(null, array_values($postedBahanIds), array_values($postedBahanQtys)), JSON_UNESCAPED_UNICODE) ?>
    };
    function labelOf(p){
        return p.nama_produk + ' (Stok : ' + Number(p.stok || 0).toLocaleString('id-ID') + ')';
    }
    function nameById(id){
        for (var i = 0; i < spesial.length; i++) if (String(spesial[i].id) === String(id)) return labelOf(spesial[i]);
        return '';
    }
    function row(section, pid, qty){
        var wrap = document.createElement('div');
        wrap.className = 'work-req-row';
        wrap.innerHTML =
            '<div class="combo">' +
                '<input type="text" class="combo-input" placeholder="Cari produk Spesial..." autocomplete="off" required>' +
                '<input type="hidden" name="'+section+'_product_id[]" class="combo-id">' +
                '<div class="combo-list"></div>' +
            '</div>' +
            '<input type="number" name="'+section+'_qty[]" min="1" value="'+(qty || 1)+'" required placeholder="Jumlah">' +
            '<button type="button" class="btn-icon delete" title="Hapus">🗑️</button>';
        var input = wrap.querySelector('.combo-input');
        var hid = wrap.querySelector('.combo-id');
        var list = wrap.querySelector('.combo-list');

        function validity(){
            input.setCustomValidity(hid.value ? '' : 'Pilih produk dari daftar.');
        }
        function render(q){
            q = (q || '').toLowerCase();
            list.innerHTML = '';
            var n = 0;
            spesial.forEach(function(p){
                if (q && p.nama_produk.toLowerCase().indexOf(q) === -1) return;
                var it = document.createElement('div');
                it.className = 'combo-item';
                it.textContent = labelOf(p);
                it.addEventListener('mousedown', function(ev){
                    ev.preventDefault();
                    input.value = labelOf(p); hid.value = p.id;
                    list.style.display = 'none'; validity();
                });
                list.appendChild(it); n++;
            });
            if (!n) { var e = document.createElement('div'); e.className = 'combo-empty'; e.textContent = 'Produk tidak ditemukan'; list.appendChild(e); }
            list.style.display = 'block';
        }
        input.addEventListener('focus', function(){ render(hid.value ? '' : input.value); });
        input.addEventListener('input', function(){ hid.value = ''; validity(); render(input.value); });
        input.addEventListener('blur', function(){
            list.style.display = 'none';
            if (!hid.value) { input.value = ''; }
            validity();
        });
        wrap.querySelector('button').onclick = function(){ wrap.remove(); };

        if (pid && nameById(pid)) { input.value = nameById(pid); hid.value = pid; }
        validity();
        document.getElementById(section === 'tools' ? 'toolsRows' : 'bahanRows').appendChild(wrap);
    }
    document.querySelectorAll('[data-add-work-row]').forEach(function(btn){
        btn.addEventListener('click', function(){ row(btn.getAttribute('data-add-work-row')); });
    });
    ['tools', 'bahan_baku'].forEach(function(sec){
        if (posted[sec].length) posted[sec].forEach(function(r){ row(sec, r[0], r[1]); });
        else row(sec);
    });
    refresh();

    document.getElementById('workTaskForm').addEventListener('submit', function(e){
        var v = type.value;
        if (v === 'penjualan' && !document.querySelector('input[name="sales_product_id"]:checked')) {
            e.preventDefault();
            alert('Pilih satu produk Narko.');
        }
        if (v === 'pemrosesan') {
            if (!document.querySelector('#toolsRows input[name="tools_product_id[]"]')) {
                e.preventDefault(); alert('Minimal 1 Tools Terkait.'); return;
            }
            if (!document.querySelector('#bahanRows input[name="bahan_baku_product_id[]"]')) {
                e.preventDefault(); alert('Minimal 1 Bahan Baku.'); return;
            }
        }
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>