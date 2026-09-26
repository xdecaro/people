<?php
declare(strict_types=1);
$root=dirname(__DIR__); $p=$root.'/component/admin/src/Service/RestoreService.php';
if(!is_file($p)){fwrite(STDERR,"RestoreService missing\n");exit(1);} $s=file_get_contents($p);
foreach(['function restoreFull(','pre_restore','transactionStart','transactionCommit','transactionRollback','restore_full'] as $n){if(!str_contains((string)$s,$n)){fwrite(STDERR,"Full restore contract missing: {$n}\n");exit(1);}}
foreach(['#__xdecaropeople_backups','#__xdecaropeople_maintenance_log'] as $n){if(str_contains((string)$s,"delete(".$n)){fwrite(STDERR,"Operational table must not be replaced by restore.\n");exit(1);}}
echo "People 1.7.28 full restore contract OK\n";
