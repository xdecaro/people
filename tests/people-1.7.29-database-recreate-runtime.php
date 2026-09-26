<?php

declare(strict_types=1);

$root=getcwd();
if(!is_file($root.'/includes/defines.php')||!is_file($root.'/includes/framework.php')){fwrite(STDERR,"Run from Joomla root.\n");exit(1);}
$_SERVER['HTTP_HOST']='localhost'; define('_JEXEC',1); define('JPATH_BASE',$root);
require JPATH_BASE.'/includes/defines.php'; require JPATH_BASE.'/includes/framework.php';
define('JPATH_COMPONENT',JPATH_ADMINISTRATOR.'/components/com_xdecaropeople'); define('JPATH_COMPONENT_ADMINISTRATOR',JPATH_COMPONENT);
$container=\Joomla\CMS\Factory::getContainer();
$container->alias('session','session.cli')->alias('JSession','session.cli')->alias(\Joomla\CMS\Session\Session::class,'session.cli')->alias(\Joomla\Session\Session::class,'session.cli')->alias(\Joomla\Session\SessionInterface::class,'session.cli');
$app=$container->get('JApplicationAdministrator'); \Joomla\CMS\Factory::$application=$app; $app->createExtensionNamespaceMap();
$db=$container->get(\Joomla\Database\DatabaseInterface::class);
$adminId=(int)$db->setQuery($db->getQuery(true)->select('id')->from('#__users')->where('username='.$db->quote('admin')),0,1)->loadResult();
$app->loadIdentity($container->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($adminId));
$component=$app->bootComponent('com_xdecaropeople'); $maintenance=$component->getDatabaseMaintenanceService(); $definition=$component->getDatabaseSchemaDefinition();
$now=\Joomla\CMS\Factory::getDate()->toSql();

$row=(object)['uuid'=>'77777777-7777-4777-8777-777777777771','display_name'=>'Recreate Test','first_name'=>'Recreate','last_name'=>'Test','person_status'=>'active','state'=>1,'access'=>1,'created'=>$now,'created_by'=>$adminId]; $db->insertObject('#__xdecaropeople_people',$row,'id');
$db->setQuery('ALTER TABLE `#__xdecaropeople_people` ADD COLUMN `legacy_runtime_probe` VARCHAR(20) NULL')->execute();
$db->setQuery('CREATE TABLE IF NOT EXISTS `#__outside_people_guard` (`id` INT NOT NULL PRIMARY KEY, `value` VARCHAR(20) NOT NULL) ENGINE=InnoDB')->execute();
$db->setQuery("REPLACE INTO `#__outside_people_guard` (`id`,`value`) VALUES (1,'keep')")->execute();

$wrong=false; try{$maintenance->recreateFunctionalDatabase($adminId,'ricrea');}catch(\RuntimeException){$wrong=true;}
if(!$wrong){fwrite(STDERR,"Wrong RICREA confirmation accepted.\n");exit(1);}
$columns=$db->getTableColumns('#__xdecaropeople_people',false); if(!isset($columns['legacy_runtime_probe'])){fwrite(STDERR,"Wrong confirmation changed schema.\n");exit(1);}

$preBackups=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_backups'))->loadResult();
$preLogs=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_maintenance_log'))->loadResult();
$result=$maintenance->recreateFunctionalDatabase($adminId,'RICREA');
if(empty($result['safety_backup_uuid'])){fwrite(STDERR,"Missing recreate safety backup UUID.\n");exit(1);}
$expected=$definition->functionalTables(); $actual=$result['recreated_tables']??[]; if($actual!==$expected){fwrite(STDERR,"Recreated table list mismatch.\n");exit(1);}
foreach($expected as $table){
  $count=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from($table))->loadResult(); if($count!==0){fwrite(STDERR,"Recreated functional table is not empty: {$table}.\n");exit(1);}
}
$columns=$db->getTableColumns('#__xdecaropeople_people',false); if(isset($columns['legacy_runtime_probe'])){fwrite(STDERR,"Recreate retained structural drift.\n");exit(1);}
$inspection=$component->getDatabaseSchemaInspector()->inspect(); if(empty($inspection['ok'])){fwrite(STDERR,"Recreated schema is not canonical.\n");exit(1);}
$postBackups=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_backups'))->loadResult();
$postLogs=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_maintenance_log'))->loadResult();
if($postBackups<=$preBackups||$postLogs<=$preLogs){fwrite(STDERR,"Maintenance data not preserved/appended.\n");exit(1);}
$guard=(string)$db->setQuery($db->getQuery(true)->select('value')->from('#__outside_people_guard')->where('id=1'))->loadResult(); if($guard!=='keep'){fwrite(STDERR,"External guard table was modified.\n");exit(1);}
$log=(string)$db->setQuery($db->getQuery(true)->select('metadata')->from('#__xdecaropeople_maintenance_log')->where('action='.$db->quote('database_recreate'))->order('id DESC'),0,1)->loadResult(); if(!str_contains($log,(string)$result['safety_backup_uuid'])){fwrite(STDERR,"database_recreate audit missing safety backup UUID.\n");exit(1);}

echo "People 1.7.29 database recreate runtime OK\n";
