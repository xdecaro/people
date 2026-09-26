<?php

declare(strict_types=1);

$root = getcwd();
if (!is_file($root . '/includes/defines.php') || !is_file($root . '/includes/framework.php')) { fwrite(STDERR, "Run from Joomla root.\n"); exit(1); }
$_SERVER['HTTP_HOST'] = 'localhost';
define('_JEXEC', 1); define('JPATH_BASE', $root);
require JPATH_BASE . '/includes/defines.php'; require JPATH_BASE . '/includes/framework.php';
define('JPATH_COMPONENT', JPATH_ADMINISTRATOR . '/components/com_xdecaropeople');
define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_COMPONENT);

$container = \Joomla\CMS\Factory::getContainer();
$container->alias('session','session.cli')->alias('JSession','session.cli')->alias(\Joomla\CMS\Session\Session::class,'session.cli')->alias(\Joomla\Session\Session::class,'session.cli')->alias(\Joomla\Session\SessionInterface::class,'session.cli');
$app = $container->get('JApplicationAdministrator'); \Joomla\CMS\Factory::$application = $app; $app->createExtensionNamespaceMap();
$db = $container->get(\Joomla\Database\DatabaseInterface::class);
$adminId = (int) $db->setQuery($db->getQuery(true)->select('id')->from('#__users')->where('username=' . $db->quote('admin')),0,1)->loadResult();
$app->loadIdentity($container->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($adminId));
$component = $app->bootComponent('com_xdecaropeople');
$maintenance = $component->getDatabaseMaintenanceService();
$backup = $component->getBackupService();
$now = \Joomla\CMS\Factory::getDate()->toSql();

$row = (object)['uuid'=>'99999999-9999-4999-8999-999999999991','display_name'=>'Empty Test','first_name'=>'Empty','last_name'=>'Test','person_status'=>'active','state'=>1,'access'=>1,'created'=>$now,'created_by'=>$adminId];
$db->insertObject('#__xdecaropeople_people',$row,'id');
$history=(object)['person_id'=>(int)$row->id,'action'=>'create','changed_fields'=>'[]','actor_user_id'=>$adminId,'created'=>$now]; $db->insertObject('#__xdecaropeople_history',$history);
$ignore=(object)['signature'=>str_repeat('e',64),'match_type'=>'runtime-empty','record_ids'=>'['.(int)$row->id.']','created_by'=>$adminId,'created'=>$now]; $db->insertObject('#__xdecaropeople_duplicate_ignores',$ignore);
$merge=(object)['source_person_id'=>(int)$row->id,'source_uuid'=>$row->uuid,'target_person_id'=>(int)$row->id,'target_uuid'=>$row->uuid,'copied_fields'=>'[]','created_by'=>$adminId,'created'=>$now]; $db->insertObject('#__xdecaropeople_merges',$merge);

$wrongRejected=false;
try { $maintenance->emptyFunctionalData($adminId,'svuota'); } catch (\RuntimeException) { $wrongRejected=true; }
if (!$wrongRejected) { fwrite(STDERR,"Wrong SVUOTA confirmation was accepted.\n"); exit(1); }
$countPeople=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_people'))->loadResult();
if ($countPeople < 1) { fwrite(STDERR,"Wrong confirmation modified data.\n"); exit(1); }

$probe=$backup->create($adminId,'verify-runtime');
$verified=$backup->verify((string)$probe['uuid']);
if (($verified['uuid']??'') !== $probe['uuid']) { fwrite(STDERR,"Backup verify mismatch.\n"); exit(1); }

$preBackupCount=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_backups'))->loadResult();
$preLogCount=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_maintenance_log'))->loadResult();
$result=$maintenance->emptyFunctionalData($adminId,'SVUOTA');
if (empty($result['safety_backup_uuid'])) { fwrite(STDERR,"Missing safety backup UUID.\n"); exit(1); }
foreach (['#__xdecaropeople_people','#__xdecaropeople_history','#__xdecaropeople_duplicate_ignores','#__xdecaropeople_merges'] as $table) {
    $count=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from($table))->loadResult();
    if ($count !== 0) { fwrite(STDERR,"Functional table not empty: {$table}.\n"); exit(1); }
}
$postBackupCount=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_backups'))->loadResult();
$postLogCount=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_maintenance_log'))->loadResult();
if ($postBackupCount <= $preBackupCount) { fwrite(STDERR,"Safety backup metadata was not preserved.\n"); exit(1); }
if ($postLogCount <= $preLogCount) { fwrite(STDERR,"Maintenance log was not preserved/appended.\n"); exit(1); }
$reason=$db->setQuery($db->getQuery(true)->select('metadata')->from('#__xdecaropeople_maintenance_log')->where('action=' . $db->quote('database_empty'))->order('id DESC'),0,1)->loadResult();
if (!$reason || !str_contains((string)$reason,(string)$result['safety_backup_uuid'])) { fwrite(STDERR,"database_empty audit missing safety backup UUID.\n"); exit(1); }

echo "People 1.7.29 database empty runtime OK\n";
