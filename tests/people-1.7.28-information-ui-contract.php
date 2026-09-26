<?php

declare(strict_types=1);
$root=dirname(__DIR__); $fail=[]; $read=static function(string $p)use($root,&$fail):string{$f=$root.'/'.$p;if(!is_file($f)){$fail[]="Missing {$p}";return '';}return (string)file_get_contents($f);}; $has=static function(string $n,string $h,string $m)use(&$fail):void{if(!str_contains($h,$n))$fail[]=$m;};
$model=$read('component/admin/src/Model/InformationModel.php'); $view=$read('component/admin/src/View/Information/HtmlView.php'); $tmpl=$read('component/admin/tmpl/information/default.php'); $controller=$read('component/admin/src/Controller/MaintenanceController.php'); $integrity=$read('component/admin/src/Service/IntegrityService.php'); $assets=$read('component/media/joomla.asset.json');
foreach(['getDiagnostics','getDatabaseSummary','getConnectedComponents','getBackups','getRecentTrashed','getMaintenanceActivity'] as $m)$has('function '.$m.'(',$model,"InformationModel missing {$m}");
foreach(['Prodotto e ambiente','Database People','Componenti collegati','Diagnostica e integrità','Gestione database','Backup disponibili','Cancellati','Attività manutenzione'] as $label)$has($label,$tmpl,"Information page missing section {$label}");
foreach(['createBackup','downloadBackup','deleteBackup','previewRestore','restoreFull','restorePerson','restoreTrash','purgePerson','checkIntegrity'] as $m)$has('function '.$m.'(',$controller,"MaintenanceController missing {$m}");
foreach(['function check(','uuid_missing','uuid_duplicates','orphan_history','broken_merges','backup_storage'] as $token)$has($token,$integrity,"IntegrityService missing {$token}");
$has('com_xdecaropeople.information',$assets,'Information CSS asset must be registered'); $has('useStyle(\'com_xdecaropeople.information\')',$view,'Information view must load information CSS');
if($fail){fwrite(STDERR,"People information UI contract FAILED\n- ".implode("\n- ",$fail)."\n");exit(1);} echo "People information UI contract PASS\n";
