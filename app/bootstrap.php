<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
function respond(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
set_exception_handler(function(Throwable $e): never {
    error_log((string)$e);
    respond(['error'=>'تعذر إتمام العملية. تحقق من الاتصال أو حاول لاحقًا.'], 500);
});
$path = getenv('TRANSACTIONFLOW_CONFIG') ?: dirname(__DIR__).'/config.local.php';
if (!is_file($path)) respond(['error'=>'يلزم إعداد الاتصال بقاعدة البيانات. راجع دليل التشغيل.'],503);
$config = require $path;
session_set_cookie_params(['httponly'=>true,'secure'=>$config['secure_cookie'] ?? false,'samesite'=>'Strict','path'=>'/']);
ini_set('session.use_strict_mode','1');
session_start();
if (isset($_SESSION['last_seen']) && time()-$_SESSION['last_seen']>1800) $_SESSION=[];
$_SESSION['last_seen']=time();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$db = new PDO($config['dsn'],$config['user'],$config['password'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false,
]);
$db->exec("SET time_zone = '+00:00'");
function body(): array {
 if ((int)($_SERVER['CONTENT_LENGTH']??0)>20000) respond(['error'=>'حجم الطلب أكبر من المسموح.'],413);
 try { $data=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR); }
 catch(JsonException) {respond(['error'=>'صيغة البيانات غير صحيحة.'],400);}
 if (!is_array($data)) respond(['error'=>'البيانات غير صحيحة.'],400);
 return $data;
}
function csrf(): void {
 if (!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??'')) respond(['error'=>'انتهت صلاحية الطلب. حدّث الصفحة.'],403);
}
function user(): array {
 if (!isset($_SESSION['user'])) respond(['error'=>'يرجى تسجيل الدخول.'],401);
 return $_SESSION['user'];
}
function audit(PDO $db,string $action,?int $id,array $details=[]): void {
 $db->prepare('INSERT INTO audit_log(user_id,action,transaction_id,details) VALUES(?,?,?,?)')
 ->execute([user()['id'],$action,$id,json_encode($details,JSON_UNESCAPED_UNICODE)]);
}
