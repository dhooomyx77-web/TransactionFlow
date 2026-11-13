<?php
require dirname(__DIR__).'/app/bootstrap.php';
$method=$_SERVER['REQUEST_METHOD'];
$action=$_GET['action']??'transactions';
if ($method==='GET' && $action==='session') respond(['user'=>$_SESSION['user']??null,'csrf'=>$_SESSION['csrf']]);
if ($method==='POST' && $action==='login') {
 csrf(); $data=body();
 $name=is_string($data['username']??null)?trim($data['username']):'';
 $password=is_string($data['password']??null)?$data['password']:'';
 if(strlen($name)>80 || strlen($password)>1024) respond(['error'=>'بيانات الدخول غير صحيحة.'],422);
 $q=$db->prepare('SELECT * FROM users WHERE username=?'); $q->execute([$name]); $u=$q->fetch();
 $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
 $valid=password_verify($password,$u['password_hash']??$dummy);
 if (!$u || !$valid || ($u['locked_until'] && strtotime($u['locked_until'])>time())) {
  if($u) $db->prepare('UPDATE users SET failed_attempts=failed_attempts+1, locked_until=CASE WHEN failed_attempts>=5 THEN DATE_ADD(NOW(),INTERVAL 15 MINUTE) ELSE locked_until END WHERE id=?')->execute([$u['id']]);
  respond(['error'=>'بيانات الدخول غير صحيحة أو الحساب مقفل مؤقتًا.'],401);
 }
 $db->prepare('UPDATE users SET failed_attempts=0,locked_until=NULL WHERE id=?')->execute([$u['id']]);
 session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32));
 $_SESSION['user']=['id'=>(int)$u['id'],'username'=>$u['username'],'role'=>$u['role']];
 respond(['user'=>$_SESSION['user'],'csrf'=>$_SESSION['csrf']]);
}
$u=user();
if ($method!=='GET') csrf();
if ($method==='POST' && $action==='logout') {$_SESSION=[]; session_destroy(); respond(['ok'=>true]);}
if ($action==='audit' && $method==='GET') {
 if($u['role']!=='admin') respond(['error'=>'ليست لديك صلاحية.'],403);
 respond(['items'=>$db->query('SELECT a.id,a.action,a.transaction_id,a.created_at,u.username FROM audit_log a JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 200')->fetchAll()]);
}
if (!in_array($action,['transactions','export'],true)) respond(['error'=>'المسار غير موجود.'],404);
$statuses=['new','under_review','incomplete','completed'];
if ($method==='GET') {
 $search=trim((string)($_GET['q']??'')); $status=(string)($_GET['status']??'');
 if(mb_strlen($search)>100 || ($status!=='' && !in_array($status,$statuses,true))) respond(['error'=>'معايير البحث غير صحيحة.'],422);
 $where=[];$params=[];
 if($search!=='') {$where[]="(transaction_number LIKE ? ESCAPE '=' OR applicant_name LIKE ? ESCAPE '=')";$term='%'.strtr($search,['='=>'==','%'=>'=%','_'=>'=_']).'%';$params=[$term,$term];}
 if($status!=='') {$where[]='status=?';$params[]=$status;}
 $clause=$where?' WHERE '.implode(' AND ',$where):'';
 if($action==='export') {
  $q=$db->prepare('SELECT transaction_number,applicant_name,transaction_type,status,notes,created_at FROM transactions'.$clause.' ORDER BY id DESC');$q->execute($params);
  header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="transactions.csv"');
  $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
  fputcsv($out,['رقم المعاملة','صاحب المعاملة','نوع المعاملة','الحالة','ملاحظات','تاريخ الإنشاء (UTC)'],',','"','');
  $labels=array_combine($statuses,['جديدة','قيد المراجعة','ناقصة','مكتملة']);
  while($r=$q->fetch()){ $r['status']=$labels[$r['status']];$r=array_map(fn($v)=>preg_match('/^[\s\x{FEFF}]*[=+@\-]/u',(string)$v)?"'".$v:(string)$v,array_values($r));fputcsv($out,$r,',','"',''); }exit;
 }
 $page=max(1,(int)($_GET['page']??1));$size=15;
 $q=$db->prepare('SELECT COUNT(*) FROM transactions'.$clause);$q->execute($params);$total=(int)$q->fetchColumn();
 $page=min($page,max(1,(int)ceil($total/$size)));$offset=($page-1)*$size;
 $q=$db->prepare('SELECT * FROM transactions'.$clause." ORDER BY id DESC LIMIT $size OFFSET $offset");$q->execute($params);
 $stats=array_fill_keys($statuses,0);foreach($db->query('SELECT status,COUNT(*) n FROM transactions GROUP BY status') as $r) $stats[$r['status']]=(int)$r['n'];
 respond(['items'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/$size)),'stats'=>$stats]);
}
if(!in_array($method,['POST','PUT','DELETE'],true)) {header('Allow: GET, POST, PUT, DELETE');respond(['error'=>'طريقة غير مسموحة.'],405);}
if($u['role']==='viewer' || ($method==='DELETE' && $u['role']!=='admin')) respond(['error'=>'ليست لديك صلاحية لهذه العملية.'],403);
$data=body();$id=filter_var($data['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if($method!=='POST' && !$id) respond(['error'=>'معرف غير صحيح.'],422);
if($method!=='DELETE') {
 $fields=['transaction_number'=>30,'applicant_name'=>100,'transaction_type'=>100,'notes'=>5000];$clean=[];
 foreach($fields as $key=>$limit) {
  if(!is_string($data[$key]??'')) respond(['error'=>'نوع الحقل غير صحيح.'],422);
  $clean[$key]=trim($data[$key]??'');
  if(($key!=='notes' && $clean[$key]==='') || mb_strlen($clean[$key])>$limit) respond(['error'=>'تحقق من الحقول المطلوبة وأطوال النصوص.'],422);
 }
 if(!is_string($data['status']??null) || !in_array($data['status'],$statuses,true)) respond(['error'=>'حالة المعاملة غير صحيحة.'],422);
 $clean['status']=$data['status'];
}
try {
 $db->beginTransaction();
 if($method!=='POST') {
  $q=$db->prepare('SELECT * FROM transactions WHERE id=? FOR UPDATE');$q->execute([$id]);$before=$q->fetch();
  if(!$before){$db->rollBack();respond(['error'=>'المعاملة غير موجودة.'],404);}
  if((int)($data['version']??0)!==(int)$before['version']){$db->rollBack();respond(['error'=>'عُدلت المعاملة من جلسة أخرى. أعد تحميل القائمة.'],409);}
 }
 if($method==='POST') {
  $q=$db->prepare('INSERT INTO transactions(transaction_number,applicant_name,transaction_type,notes,status) VALUES(?,?,?,?,?)');$q->execute(array_values($clean));$id=(int)$db->lastInsertId();
 } elseif($method==='PUT') {
  $q=$db->prepare('UPDATE transactions SET transaction_number=?,applicant_name=?,transaction_type=?,notes=?,status=?,version=version+1 WHERE id=?');$q->execute([...array_values($clean),$id]);
 } else $db->prepare('DELETE FROM transactions WHERE id=?')->execute([$id]);
 audit($db,['POST'=>'create','PUT'=>'update','DELETE'=>'delete'][$method],$id,['before'=>$before??null,'after'=>$clean??null]);
 $db->commit();respond(['ok'=>true,'id'=>$id],$method==='POST'?201:200);
} catch(PDOException $e) {
 if($db->inTransaction())$db->rollBack();
 if(($e->errorInfo[1]??0)===1062) respond(['error'=>'رقم المعاملة مستخدم مسبقًا.'],409);
 throw $e;
}
