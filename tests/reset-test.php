<?php
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--reset-test')exit(1);
$config=require dirname(__DIR__).'/var/test-config.php';
if(!str_contains($config['dsn'],'dbname=transactionflow_test;'))exit("Refusing to reset non-test database.\n");
$db=new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('DELETE FROM audit_log');$db->exec('DELETE FROM transactions');$db->exec('UPDATE users SET failed_attempts=0,locked_until=NULL');
echo "Test fixtures reset.\n";
