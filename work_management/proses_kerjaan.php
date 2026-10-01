<?php
require_once __DIR__ . '/../includes/work.php';
require_management();
ensure_work_schema($conn);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: kelola_kerjaan.php'); exit; }
$id=(int)($_POST['id']??0); $aksi=$_POST['aksi']??''; $uid=(int)current_user()['id'];
if($id<=0 || !in_array($aksi,['acc','tolak','selesai'],true)){flash_set('Permintaan tidak valid.','danger');header('Location: kelola_kerjaan.php');exit;}

if($aksi==='acc'){
 $stmt=mysqli_prepare($conn,"UPDATE work_task_claims c JOIN work_tasks t ON t.id=c.task_id SET c.status='diacc', c.acc_by=?, c.acc_at=NOW() WHERE c.id=? AND c.status='menunggu' AND t.status='aktif'");
 mysqli_stmt_bind_param($stmt,'ii',$uid,$id);mysqli_stmt_execute($stmt);
 flash_set(mysqli_stmt_affected_rows($stmt)>0?'Kerjaan berhasil di-ACC.':'Kerjaan sudah diproses atau task tidak aktif.',mysqli_stmt_affected_rows($stmt)>0?'success':'danger');header('Location: kelola_kerjaan.php');exit;
}
if($aksi==='tolak'){
 mysqli_begin_transaction($conn);try{
  $st=mysqli_prepare($conn,"SELECT task_id, qty FROM work_task_claims WHERE id=? AND status='menunggu' FOR UPDATE");mysqli_stmt_bind_param($st,'i',$id);mysqli_stmt_execute($st);$c=mysqli_fetch_assoc(mysqli_stmt_get_result($st));
  if(!$c) throw new Exception('Kerjaan sudah diproses atau tidak ditemukan.');
  $up=mysqli_prepare($conn,"UPDATE work_task_claims SET status='ditolak' WHERE id=? AND status='menunggu'");mysqli_stmt_bind_param($up,'i',$id);mysqli_stmt_execute($up);
  if(mysqli_stmt_affected_rows($up)<1) throw new Exception('Kerjaan sudah diproses.');
  work_restore_quota($conn,(int)$c['task_id'],(int)$c['qty']);work_restore_stock($conn,$id);mysqli_commit($conn);flash_set('Kerjaan ditolak, kuota dan stok produk dikembalikan.');
 }catch(Throwable $e){mysqli_rollback($conn);flash_set($e->getMessage(),'danger');}header('Location: kelola_kerjaan.php');exit;
}
if($aksi==='selesai'){
 mysqli_begin_transaction($conn);try{
  $st=mysqli_prepare($conn,"SELECT c.*,t.type FROM work_task_claims c JOIN work_tasks t ON t.id=c.task_id WHERE c.id=? AND c.status='dilaporkan' FOR UPDATE");mysqli_stmt_bind_param($st,'i',$id);mysqli_stmt_execute($st);$c=mysqli_fetch_assoc(mysqli_stmt_get_result($st));
  if(!$c) throw new Exception('Kerjaan belum dilaporkan atau sudah selesai.');
  if($c['type']==='penjualan'){
   if((int)$c['hasil_uang']<1) throw new Exception('Jumlah uang hasil penjualan belum valid.');
   if(!work_add_uang_merah($conn,(int)$c['hasil_uang'])) throw new Exception('Produk "Uang Merah" belum tersedia di database. Tambahkan produk itu dulu agar hasil penjualan bisa masuk otomatis.');
  }else{
   $ri=mysqli_query($conn,"SELECT produk_id, qty FROM work_claim_result_items WHERE claim_id=".(int)$id);
   $up=mysqli_prepare($conn,"UPDATE produk SET stok=stok+? WHERE id=?");
   $count=0; while($r=mysqli_fetch_assoc($ri)){ if(!$r['produk_id']) continue; mysqli_stmt_bind_param($up,'ii',$r['qty'],$r['produk_id']);mysqli_stmt_execute($up);$count++; }
   if($count<1) throw new Exception('Hasil pemrosesan belum ada.');
  }
  $upc=mysqli_prepare($conn,"UPDATE work_task_claims SET status='selesai', selesai_by=?, selesai_at=NOW() WHERE id=? AND status='dilaporkan'");mysqli_stmt_bind_param($upc,'ii',$uid,$id);mysqli_stmt_execute($upc);mysqli_commit($conn);flash_set('Kerjaan dikonfirmasi selesai.');
 }catch(Throwable $e){mysqli_rollback($conn);flash_set($e->getMessage(),'danger');}header('Location: kelola_kerjaan.php');exit;
}
