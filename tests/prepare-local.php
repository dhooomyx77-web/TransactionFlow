<?php
// Development helper. Explicitly opted into; never exposed under public/.
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--prepare')exit(1);
$root=dirname(__DIR__);$path=$root.'/config.local.php';
if(is_file($path))exit("Existing configuration preserved.\n");
$port=filter_var(getenv('TRANSACTIONFLOW_DB_PORT')?:3317,FILTER_VALIDATE_INT,['options'=>['min_range'=>1024,'max_range'=>65535]]);
if(!$port)exit("Invalid test database port.\n");
$db=new PDO("mysql:host=127.0.0.1;port=$port;charset=utf8mb4",'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec(file_get_contents($root.'/database/schema.sql'));
$password=bin2hex(random_bytes(24));
$account="'transactionflow'@".$db->quote(getenv('TRANSACTIONFLOW_DB_ACCOUNT_HOST')?:'127.0.0.1');
$db->exec('CREATE USER '.$account.' IDENTIFIED BY '.$db->quote($password));
$db->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON transactionflow.* TO '.$account);
$config=['dsn'=>"mysql:host=127.0.0.1;port=$port;dbname=transactionflow;charset=utf8mb4",'user'=>'transactionflow','password'=>$password,'secure_cookie'=>false];
file_put_contents($path,"<?php\nreturn ".var_export($config,true).";\n");
@mkdir($root.'/var',0700,true);
$login=bin2hex(random_bytes(12));
$q=$db->prepare('INSERT INTO users(username,password_hash,role) VALUES(?,?,?)');
foreach(['admin','editor','viewer'] as $role)$q->execute([$role,password_hash($login,PASSWORD_DEFAULT),$role]);
file_put_contents($root.'/var/local-access.txt',"Local demo only\nUsername: admin\nPassword: $login\nOther roles: editor / viewer (same local demo password)\n");
// Separate database for destructive tests.
$schema=str_replace('transactionflow','transactionflow_test',file_get_contents($root.'/database/schema.sql'));
$db->exec($schema);
$db->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON transactionflow_test.* TO '.$account);
$q=$db->prepare('INSERT INTO transactionflow_test.users(username,password_hash,role) VALUES(?,?,?)');
foreach(['admin','editor','viewer'] as $role)$q->execute([$role,password_hash($login,PASSWORD_DEFAULT),$role]);
$config['dsn']=str_replace('dbname=transactionflow;','dbname=transactionflow_test;',$config['dsn']);
file_put_contents($root.'/var/test-config.php',"<?php\nreturn ".var_export($config,true).";\n");
echo "Local database, restricted application account and demo users created. Credentials stored in ignored var/local-access.txt.\n";
