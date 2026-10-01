<?php
require_once __DIR__ . '/../includes/work.php';
require_login();
ensure_work_schema($conn);
$base='../';$active_menu='status_kerjaan';$page_title='Status Kerjaan - Work Management';$page_header='Status Kerjaan';$uid=(int)current_user()['id'];

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['batalkan'])){
 $id=(int)$_POST['batalkan'];mysqli_begin_transaction($conn);try{
  $st=mysqli_prepare($conn,"SELECT task_id,qty FROM work_task_claims WHERE id=? AND user_id=? AND status='menunggu' FOR UPDATE");mysqli_stmt_bind_param($st,'ii',$id,$uid);mysqli_stmt_execute($st);$c=mysqli_fetch_assoc(mysqli_stmt_get_result($st));if(!$c)throw new Exception('Kerjaan tidak bisa dibatalkan.');
  $up=mysqli_prepare($conn,"UPDATE work_task_claims SET status='dibatalkan' WHERE id=? AND user_id=? AND status='menunggu'");mysqli_stmt_bind_param($up,'ii',$id,$uid);mysqli_stmt_execute($up);if(mysqli_stmt_affected_rows($up)<1)throw new Exception('Kerjaan sudah diproses.');work_restore_quota($conn,(int)$c['task_id'],(int)$c['qty']);work_restore_stock($conn,$id);mysqli_commit($conn);
 }catch(Throwable $e){mysqli_rollback($conn);}header('Location: status_kerjaan.php');exit;
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['lapor'])){
 $id=(int)$_POST['lapor'];$bukti=trim($_POST['bukti_link']??'');$uang=(int)($_POST['hasil_uang']??0);
 $st=mysqli_prepare($conn,"SELECT c.id,t.type FROM work_task_claims c JOIN work_tasks t ON t.id=c.task_id WHERE c.id=? AND c.user_id=? AND c.status='diacc'");mysqli_stmt_bind_param($st,'ii',$id,$uid);mysqli_stmt_execute($st);$claim=mysqli_fetch_assoc(mysqli_stmt_get_result($st));$error='';
 if(!$claim)$error='Kerjaan belum di-ACC, sudah dilaporkan, atau bukan punyamu.';
 elseif($bukti===''||!preg_match('#^https?://#i',$bukti)||!filter_var($bukti,FILTER_VALIDATE_URL)||strlen($bukti)>500)$error='Link bukti wajib diisi dan harus link valid (https://...).';
 elseif($claim['type']==='penjualan' && $uang<1)$error='Jumlah uang hasil penjualan wajib diisi.';
 $items=[];
 if($error==='' && $claim['type']==='pemrosesan'){
  $pids=$_POST['hasil_produk_id']??[];$qtys=$_POST['hasil_qty']??[];$seen=[];
  if(!is_array($pids)||!is_array($qtys)){$pids=[];$qtys=[];}
  for($i=0;$i<count($pids);$i++){
   $pid=(int)$pids[$i];$qty=(int)($qtys[$i]??0);if($pid<=0&&$qty<=0)continue;if($pid<=0||$qty<1){$error='Isi produk dan jumlah hasil pemrosesan dengan benar.';break;}
   $q=mysqli_prepare($conn,"SELECT id,nama_produk FROM produk WHERE id=? AND kategori IN ('Narko','Spesial') LIMIT 1");mysqli_stmt_bind_param($q,'i',$pid);mysqli_stmt_execute($q);$p=mysqli_fetch_assoc(mysqli_stmt_get_result($q));if(!$p){$error='Produk hasil harus kategori Narko atau Spesial.';break;}if(isset($seen[$pid])){$error='Produk hasil yang sama tidak boleh dipilih dua kali.';break;}$seen[$pid]=1;$items[]=['produk_id'=>(int)$p['id'],'nama_produk'=>$p['nama_produk'],'qty'=>$qty];
  }
  if($error===''&&empty($items))$error='Isi minimal 1 hasil pemrosesan.';
 }
 if($error===''){
  mysqli_begin_transaction($conn);try{
   $up=mysqli_prepare($conn,"UPDATE work_task_claims SET status='dilaporkan', hasil_uang=?, bukti_link=?, lapor_at=NOW() WHERE id=? AND user_id=? AND status='diacc'");$uangDb=($claim['type']==='penjualan')?$uang:null;mysqli_stmt_bind_param($up,'isii',$uangDb,$bukti,$id,$uid);mysqli_stmt_execute($up);if(mysqli_stmt_affected_rows($up)<1)throw new Exception('Kerjaan sudah berubah status.');
   if($claim['type']==='pemrosesan'){$ri=mysqli_prepare($conn,"INSERT INTO work_claim_result_items (claim_id,produk_id,nama_produk,qty) VALUES (?,?,?,?)");foreach($items as $it){mysqli_stmt_bind_param($ri,'iisi',$id,$it['produk_id'],$it['nama_produk'],$it['qty']);mysqli_stmt_execute($ri);}}
   mysqli_commit($conn);
  }catch(Throwable $e){mysqli_rollback($conn);}
 }
 header('Location: status_kerjaan.php');exit;
}

$where = ' WHERE c.user_id='.$uid;
$sql="SELECT c.*,t.title,t.type,t.deadline FROM work_task_claims c JOIN work_tasks t ON t.id=c.task_id $where ORDER BY c.created_at DESC,c.id DESC";
$res=mysqli_query($conn,$sql);$claims=[];while($r=mysqli_fetch_assoc($res))$claims[]=$r;
$resultBy=[];$reqBy=[];if($claims){$ids=implode(',',array_map('intval',array_column($claims,'id')));$tids=implode(',',array_map('intval',array_unique(array_column($claims,'task_id'))));$rq=mysqli_query($conn,"SELECT * FROM work_task_requirements WHERE task_id IN ($tids) ORDER BY FIELD(section,'penjualan','tools','bahan_baku'), id ASC");while($r=mysqli_fetch_assoc($rq))$reqBy[(int)$r['task_id']][]=$r;$rr=mysqli_query($conn,"SELECT * FROM work_claim_result_items WHERE claim_id IN ($ids) ORDER BY id ASC");while($r=mysqli_fetch_assoc($rr))$resultBy[(int)$r['claim_id']][]=$r;}
$produkHasil=[];$rh=mysqli_query($conn,"SELECT id,nama_produk,kategori FROM produk WHERE kategori IN ('Narko','Spesial') ORDER BY kategori,nama_produk");while($r=mysqli_fetch_assoc($rh))$produkHasil[]=$r;

include __DIR__.'/../includes/header.php';
?>
<div class="panel"><div class="panel-toolbar"><div class="search-box"><input type="text" placeholder="Cari ID / task..." data-table-search="#statusWorkTable"><span class="icon">🔍</span></div></div>
<table class="data-table" id="statusWorkTable"><thead><tr><th>ID</th><th>Tanggal</th><th>Tipe</th><th>Jumlah</th><th>Status</th><th>Detail</th></tr></thead><tbody>
<?php if(empty($claims)):?><tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Belum ada kerjaan.</td></tr><?php else:foreach($claims as $c):$results=$resultBy[(int)$c['id']]??[];$canReport=true;?>
<tr><td>#<?=(int)$c['id']?></td><td><?=e(date('d M Y, H:i',strtotime($c['created_at'])))?></td><td><?=e(work_type_label($c['type']))?></td><td><?=number_format((int)$c['qty'],0,',','.')?></td><td><span class="badge badge-<?=e($c['status'])?>"><?=e(status_label($c['status']))?></span></td><td><button type="button" class="btn btn-outline" data-status-work-detail="<?=e(json_encode(['id'=>(int)$c['id'],'title'=>$c['title'],'type'=>$c['type'],'type_label'=>work_type_label($c['type']),'tanggal'=>date('d M Y, H:i',strtotime($c['created_at'])),'qty'=>(int)$c['qty'],'status'=>$c['status'],'status_label'=>status_label($c['status']),'deadline'=>work_format_date($c['deadline']),'hasil_uang'=>($c['type']==='penjualan' && $c['hasil_uang']!==null)?rupiah($c['hasil_uang']):'','bukti_link'=>$c['bukti_link']?:'','requirements'=>array_map(function($r){return ['section'=>$r['section'],'nama'=>$r['nama_produk'],'qty'=>(int)$r['qty']];},$reqBy[(int)$c['task_id']]??[]),'results'=>array_map(function($r){return ['nama'=>$r['nama_produk'],'qty'=>(int)$r['qty']];},$results)],JSON_UNESCAPED_UNICODE))?>">Detail</button></td></tr>
<?php endforeach;endif;?></tbody></table><div class="table-footer">Menampilkan <?=count($claims)?> kerjaan</div></div>

<div class="modal-overlay" id="modalStatusWork"><div class="modal-box"><button class="modal-close" data-close-modal="modalStatusWork">✕</button><span class="modal-tag" id="swTag">Work</span><h2 id="swTitle">Detail Kerjaan</h2><div class="od-meta" id="swMeta"></div><div id="swReq"></div><div id="swReport"></div><div class="form-actions" id="swActions"></div></div></div>
<div class="modal-overlay" id="modalReportWork"><div class="modal-box"><button class="modal-close" data-close-modal="modalReportWork">✕</button><span class="modal-tag">Laporan</span><h2 id="reportWorkTitle">Lapor Hasil Kerjaan</h2><form method="POST" action="status_kerjaan.php" id="reportWorkForm"><input type="hidden" name="lapor" id="reportWorkId"><div id="reportSalesFields"><div class="form-group"><label>Jumlah uang yang didapat (Rp)</label><input type="number" name="hasil_uang" min="1" step="1" placeholder="Contoh: 1500000"></div></div><div id="reportProcessFields" style="display:none;"><div class="work-requirement-box"><div class="work-req-title">Hasil Pemrosesan</div><div id="resultRows"></div><button type="button" class="btn btn-outline" id="addResultRow">＋ Tambah Hasil</button></div></div><div class="form-group" style="margin-top:14px;"><label>Link bukti (SS)</label><input type="url" name="bukti_link" required placeholder="https://..."><div class="hint">Upload SS ke Discord / hosting gambar, lalu tempel link-nya di sini.</div></div><div class="form-actions"><button class="btn btn-success" type="submit">📤 Kirim Laporan</button><button class="btn btn-outline" type="button" data-close-modal="modalReportWork">Batal</button></div></form></div></div>
<script>
(function(){
 var produk=<?=json_encode($produkHasil,JSON_UNESCAPED_UNICODE)?>, currentType='';
 document.querySelectorAll('[data-status-work-detail]').forEach(function(btn){btn.addEventListener('click',function(){var d;try{d=JSON.parse(btn.getAttribute('data-status-work-detail'));}catch(e){return;}document.getElementById('swTag').textContent=d.type_label;document.getElementById('swTitle').textContent='Detail '+d.type_label+' #'+d.id;document.getElementById('swMeta').innerHTML='<strong>Task:</strong> '+esc(d.title)+'<br><strong>Tanggal:</strong> '+esc(d.tanggal)+'<br><strong>Jumlah:</strong> '+num(d.qty)+'<br><strong>Deadline:</strong> '+esc(d.deadline)+'<br><strong>Status:</strong> '+esc(d.status_label);var req='<div class="work-detail-box"><strong>Requirement Task</strong><div class="work-detail-list"><div class="work-detail-head"><span>Produk</span><span>Jumlah</span></div>';if(d.type==='penjualan'){d.requirements.forEach(function(r){req+='<div><span>'+esc(r.nama)+'</span><b>'+num(d.qty)+'</b></div>';});}else{req+='<div><span>Jumlah Pemrosesan</span><b>'+num(d.qty)+'</b></div>';d.requirements.forEach(function(r){req+='<div><span>'+esc(r.section==='tools'?'Tools':'Bahan')+': '+esc(r.nama)+'</span><b>'+num(r.qty)+'</b></div>';});}req+='</div></div>';document.getElementById('swReq').innerHTML=req;var rep='';if(d.type==='penjualan' && d.hasil_uang)rep+='<div class="work-detail-box"><strong>Hasil Penjualan</strong><div style="margin-top:8px;">'+esc(d.hasil_uang)+'</div></div>';if(d.results.length){rep+='<div class="work-detail-box"><strong>Hasil Pemrosesan</strong><div class="work-detail-list">';d.results.forEach(function(r){rep+='<div><span>'+esc(r.nama)+'</span><b>× '+num(r.qty)+'</b></div>';});rep+='</div></div>';}if(d.bukti_link){if(d.status==='selesai'){rep+='<div class="work-detail-box"><strong>Bukti / SS</strong><br><a href="'+esc(d.bukti_link)+'" target="_blank" rel="noopener"><img class="work-proof-img" src="'+esc(d.bukti_link)+'" alt="Bukti SS" referrerpolicy="no-referrer" onerror="this.outerHTML=\'Buka bukti\'"></a></div>';}else{rep+='<div class="work-detail-box"><strong>Bukti / SS</strong><br><a href="'+esc(d.bukti_link)+'" target="_blank" rel="noopener">Buka bukti</a></div>';}}document.getElementById('swReport').innerHTML=rep;var a='';if(d.status==='diacc' && true){a='<button class="btn btn-success" type="button" data-open-report="'+d.id+'" data-report-type="'+d.type+'">📤 Lapor Hasil</button>';}if(d.status==='menunggu' && true){a+='<form method="POST" action="status_kerjaan.php"><input type="hidden" name="batalkan" value="'+d.id+'"><button class="btn btn-danger" type="submit">✕ Batalkan</button></form>';}document.getElementById('swActions').innerHTML=a;document.getElementById('modalStatusWork').classList.add('show');bindReport();});});
 function bindReport(){document.querySelectorAll('[data-open-report]').forEach(function(b){b.onclick=function(){currentType=b.getAttribute('data-report-type');document.getElementById('reportWorkId').value=b.getAttribute('data-open-report');document.getElementById('reportSalesFields').style.display=currentType==='penjualan'?'block':'none';document.getElementById('reportProcessFields').style.display=currentType==='pemrosesan'?'block':'none';document.getElementById('reportWorkTitle').textContent=currentType==='penjualan'?'Lapor Hasil Penjualan':'Lapor Hasil Pemrosesan';document.getElementById('resultRows').innerHTML='';if(currentType==='pemrosesan')addResult();document.getElementById('modalStatusWork').classList.remove('show');document.getElementById('modalReportWork').classList.add('show');};});}
 function addResult(){var wrap=document.createElement('div');wrap.className='work-req-row';var opts='<option value="">Pilih produk...</option>';produk.forEach(function(p){opts+='<option value="'+p.id+'">'+esc(p.nama_produk)+' — '+esc(p.kategori)+'</option>';});wrap.innerHTML='<select name="hasil_produk_id[]" required>'+opts+'</select><input type="number" name="hasil_qty[]" min="1" value="1" required><button type="button" class="btn-icon delete">🗑️</button>';wrap.querySelector('button').onclick=function(){wrap.remove();};document.getElementById('resultRows').appendChild(wrap);}
 document.getElementById('addResultRow').onclick=addResult;
 document.querySelectorAll('[data-close-modal="modalStatusWork"],[data-close-modal="modalReportWork"]').forEach(function(x){x.onclick=function(){document.getElementById(x.getAttribute('data-close-modal')).classList.remove('show');};});
 function esc(s){return String(s||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}function num(n){return Number(n||0).toLocaleString('id-ID');}
})();
</script>
<?php include __DIR__.'/../includes/footer.php'; ?>