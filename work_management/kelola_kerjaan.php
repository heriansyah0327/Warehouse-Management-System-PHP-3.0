<?php
require_once __DIR__ . '/../includes/work.php';
require_management();
ensure_work_schema($conn);

$base = '../';
$active_menu = 'kelola_kerjaan';
$page_title = 'Kelola Kerjaan - Management';
$page_header = 'Kelola Kerjaan';

$statusList = ['menunggu','diacc','dilaporkan','selesai','ditolak','dibatalkan'];
$filter = $_GET['status'] ?? '';
if (!in_array($filter, $statusList, true)) $filter = '';

$sql = "SELECT c.*, t.title, t.type, t.deadline, u.full_name AS pengaju, u.username,
               (SELECT COUNT(*) FROM work_claim_result_items ri WHERE ri.claim_id = c.id) AS result_count
        FROM work_task_claims c
        JOIN work_tasks t ON t.id = c.task_id
        JOIN users u ON u.id = c.user_id";
if ($filter !== '') {
    $stmt = mysqli_prepare($conn, $sql . " WHERE c.status = ? ORDER BY FIELD(c.status,'menunggu','dilaporkan','diacc','selesai','ditolak','dibatalkan'), c.created_at DESC, c.id DESC");
    mysqli_stmt_bind_param($stmt, 's', $filter); mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt);
} else {
    $res = mysqli_query($conn, $sql . " ORDER BY FIELD(c.status,'menunggu','dilaporkan','diacc','selesai','ditolak','dibatalkan'), c.created_at DESC, c.id DESC");
}
$claims=[]; while($r=mysqli_fetch_assoc($res)) $claims[]=$r;

$reqBy=[]; $resultBy=[];
if ($claims) {
    $ids=implode(',',array_map('intval',array_column($claims,'id')));
    $rr=mysqli_query($conn,"SELECT * FROM work_task_requirements WHERE task_id IN (SELECT task_id FROM work_task_claims WHERE id IN ($ids)) ORDER BY FIELD(section,'penjualan','tools','bahan_baku'), id ASC");
    while($r=mysqli_fetch_assoc($rr)) $reqBy[(int)$r['task_id']][]=$r;
    $ri=mysqli_query($conn,"SELECT * FROM work_claim_result_items WHERE claim_id IN ($ids) ORDER BY id ASC");
    while($r=mysqli_fetch_assoc($ri)) $resultBy[(int)$r['claim_id']][]=$r;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="chip-row">
    <a class="chip <?= $filter===''?'active':'' ?>" href="kelola_kerjaan.php">Semua</a>
    <?php foreach($statusList as $s): ?><a class="chip <?= $filter===$s?'active':'' ?>" href="kelola_kerjaan.php?status=<?= e($s) ?>"><?= e(status_label($s)) ?></a><?php endforeach; ?>
</div>
<div class="panel">
    <div class="panel-toolbar"><div class="search-box"><input type="text" placeholder="Cari ID / pengaju / task..." data-table-search="#workClaimsTable"><span class="icon">🔍</span></div></div>
    <table class="data-table" id="workClaimsTable">
        <thead><tr><th>ID</th><th>Tanggal</th><th>Pengaju</th><th>Tipe</th><th>Jumlah</th><th>Status</th><th>Detail</th></tr></thead>
        <tbody>
        <?php if(empty($claims)): ?><tr><td colspan="7" style="text-align:center;color:var(--text-muted);">Belum ada kerjaan yang diambil.</td></tr>
        <?php else: foreach($claims as $c):
            $detail=['id'=>(int)$c['id'],'task_id'=>(int)$c['task_id'],'title'=>$c['title'],'tanggal'=>date('d M Y, H:i',strtotime($c['created_at'])),'pengaju'=>$c['pengaju'],'username'=>$c['username'],'type'=>$c['type'],'type_label'=>work_type_label($c['type']),'qty'=>(int)$c['qty'],'status'=>$c['status'],'status_label'=>status_label($c['status']),'deadline'=>work_format_date($c['deadline']),'hasil_uang'=>($c['type']==='penjualan' && $c['hasil_uang']!==null)?rupiah($c['hasil_uang']):'','bukti_link'=>$c['bukti_link']?:'','lapor_at'=>$c['lapor_at']?date('d M Y, H:i',strtotime($c['lapor_at'])):'','catatan'=>$c['catatan']?:'','requirements'=>array_map(function($r){return ['section'=>$r['section'],'nama'=>$r['nama_produk'],'qty'=>(int)$r['qty']];},$reqBy[(int)$c['task_id']]??[]),'results'=>array_map(function($r){return ['nama'=>$r['nama_produk'],'qty'=>(int)$r['qty']];},$resultBy[(int)$c['id']]??[])];
        ?>
            <tr><td>#<?= (int)$c['id'] ?></td><td><?= e(date('d M Y, H:i',strtotime($c['created_at']))) ?></td><td><?= e($c['pengaju']) ?> <span class="muted">(<?= e($c['username']) ?>)</span></td><td><?= e(work_type_label($c['type'])) ?></td><td><?= number_format((int)$c['qty'],0,',','.') ?></td><td><span class="badge badge-<?= e($c['status']) ?>"><?= e(status_label($c['status'])) ?></span></td><td><button type="button" class="btn btn-outline" data-work-detail='<?= e(json_encode($detail,JSON_UNESCAPED_UNICODE)) ?>'>Detail</button></td></tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <div class="table-footer">Menampilkan <?= count($claims) ?> kerjaan</div>
</div>

<div class="modal-overlay" id="modalWorkDetail"><div class="modal-box"><button class="modal-close" data-close-modal="modalWorkDetail">✕</button><span class="modal-tag" id="wkTag">Work</span><h2 id="wkTitle">Detail Kerjaan</h2><div class="od-meta" id="wkMeta"></div><div id="wkReq"></div><div id="wkReport" style="margin-top:14px;"></div><div class="form-actions" id="wkActions"></div></div></div>
<script>
(function(){
 document.querySelectorAll('[data-work-detail]').forEach(function(btn){btn.addEventListener('click',function(){
  var d;try{d=JSON.parse(btn.getAttribute('data-work-detail'));}catch(e){return;}
  document.getElementById('wkTag').textContent=d.type_label;document.getElementById('wkTitle').textContent='Detail '+d.type_label+' #'+d.id;
  document.getElementById('wkMeta').innerHTML='<strong>Task:</strong> '+esc(d.title)+'<br><strong>Tanggal:</strong> '+esc(d.tanggal)+'<br><strong>Pengaju:</strong> '+esc(d.pengaju)+'<br><strong>Deadline:</strong> '+esc(d.deadline)+'<br><strong>Status:</strong> '+esc(d.status_label);
  var req='<div class="work-detail-box"><strong>Requirement Task</strong><div class="work-detail-list"><div class="work-detail-head"><span>Produk</span><span>Jumlah</span></div>';
  if(d.type==='penjualan'){ d.requirements.forEach(function(r){req+='<div><span>'+esc(r.nama)+'</span><b>'+num(d.qty)+'</b></div>';}); }
  else { req+='<div><span>Jumlah Pemrosesan</span><b>'+num(d.qty)+'</b></div>'; d.requirements.forEach(function(r){req+='<div><span>'+esc(r.section==='tools'?'Tools':'Bahan')+': '+esc(r.nama)+'</span><b>'+num(r.qty)+'</b></div>';}); }
  req+='</div></div>'; document.getElementById('wkReq').innerHTML=req;
  var rep=''; if(d.type==='penjualan' && d.hasil_uang){rep+='<div class="work-detail-box"><strong>Hasil Penjualan</strong><div style="margin-top:8px;">'+esc(d.hasil_uang)+'</div></div>';} if(d.results.length){rep+='<div class="work-detail-box"><strong>Hasil Pemrosesan</strong><div class="work-detail-list">';d.results.forEach(function(r){rep+='<div><span>'+esc(r.nama)+'</span><b>× '+num(r.qty)+'</b></div>';});rep+='</div></div>';} if(d.bukti_link){ if(d.status==='selesai'){rep+='<div class="work-detail-box"><strong>Bukti / SS</strong><br><a href="'+escAttr(d.bukti_link)+'" target="_blank" rel="noopener"><img class="work-proof-img" src="'+escAttr(d.bukti_link)+'" alt="Bukti SS" referrerpolicy="no-referrer" onerror="this.outerHTML=\'Buka bukti\'"></a></div>';} else {rep+='<div class="work-detail-box"><strong>Bukti / SS</strong><br><a href="'+escAttr(d.bukti_link)+'" target="_blank" rel="noopener">Buka bukti</a></div>';} } document.getElementById('wkReport').innerHTML=rep;
  var a=''; if(d.status==='menunggu'){a+='<form method="POST" action="proses_kerjaan.php"><input type="hidden" name="id" value="'+d.id+'"><button class="btn btn-success" name="aksi" value="acc">✔ ACC</button> <button class="btn btn-danger" name="aksi" value="tolak">✕ Tolak</button></form>';} else if(d.status==='dilaporkan'){a+='<form method="POST" action="proses_kerjaan.php"><input type="hidden" name="id" value="'+d.id+'"><button class="btn btn-success" name="aksi" value="selesai">✔ Konfirmasi Selesai</button></form>';} document.getElementById('wkActions').innerHTML=a; document.getElementById('modalWorkDetail').classList.add('show');
 });});
 document.querySelectorAll('[data-close-modal="modalWorkDetail"]').forEach(function(x){x.addEventListener('click',function(){document.getElementById('modalWorkDetail').classList.remove('show');});});
 function esc(s){return String(s||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}function escAttr(s){return esc(s);}function num(n){return Number(n||0).toLocaleString('id-ID');}
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
