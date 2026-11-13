<?php
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
if(($argv[1]??'')!=='--demo') exit("Demo records only: php scripts/seed.php --demo\n");
$config=require dirname(__DIR__).'/config.local.php';
$db=new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$names=['أحمد التجريبي','نورة التجريبية','خالد الافتراضي','سارة الافتراضية','محمد التجريبي','ريم التجريبية'];
$types=['طلب إداري','مراجعة بيانات','طلب اعتماد','خطاب رسمي'];$statuses=['new','under_review','incomplete','completed'];
$q=$db->prepare('INSERT IGNORE INTO transactions(transaction_number,applicant_name,transaction_type,status,notes) VALUES(?,?,?,?,?)');
for($i=1;$i<=24;$i++)$q->execute(['DEMO-'.str_pad((string)$i,4,'0',STR_PAD_LEFT),$names[($i-1)%6],$types[($i-1)%4],$statuses[($i-1)%4],'سجل وهمي للعرض والاختبار فقط.']);
echo "Demo records ready.\n";
