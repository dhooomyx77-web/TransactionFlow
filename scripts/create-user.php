<?php
// CLI only. Password is read from stdin so it is absent from command history.
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
$config=require dirname(__DIR__).'/config.local.php';
$db=new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name=$argv[1]??'';$role=$argv[2]??'viewer';
if(!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/',$name)||!in_array($role,['admin','editor','viewer'],true))exit("Usage: php scripts/create-user.php username admin|editor|viewer\n");
echo "Password (12+ characters), via stdin: ";$password=rtrim(fgets(STDIN),"\r\n");
if(strlen($password)<12)exit("Password must contain at least 12 characters.\n");
$q=$db->prepare('INSERT INTO users(username,password_hash,role) VALUES(?,?,?)');
$q->execute([$name,password_hash($password,PASSWORD_DEFAULT),$role]);
echo "\nUser created.\n";
