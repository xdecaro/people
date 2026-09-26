<?php

declare(strict_types=1);

$root = getcwd();
if (!is_file($root . '/includes/defines.php') || !is_file($root . '/includes/framework.php')) { fwrite(STDERR, "Run this probe from Joomla root.\n"); exit(1); }
$_SERVER['HTTP_HOST']='localhost'; define('_JEXEC',1); define('JPATH_BASE',$root); require JPATH_BASE.'/includes/defines.php'; require JPATH_BASE.'/includes/framework.php';
define('JPATH_COMPONENT',JPATH_ADMINISTRATOR.'/components/com_xdecaropeople'); define('JPATH_COMPONENT_ADMINISTRATOR',JPATH_COMPONENT);
$container=\Joomla\CMS\Factory::getContainer();
$container->alias('session','session.cli')->alias('JSession','session.cli')->alias(\Joomla\CMS\Session\Session::class,'session.cli')->alias(\Joomla\Session\Session::class,'session.cli')->alias(\Joomla\Session\SessionInterface::class,'session.cli');
$app=$container->get('JApplicationAdministrator'); \Joomla\CMS\Factory::$application=$app; $app->createExtensionNamespaceMap();
$db=$container->get(\Joomla\Database\DatabaseInterface::class);
$adminId=(int)$db->setQuery($db->getQuery(true)->select('id')->from('#__users')->where('username='.$db->quote('admin')),0,1)->loadResult();
$app->loadIdentity($container->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($adminId));
$component=$app->bootComponent('com_xdecaropeople'); $backup=$component->getBackupService(); $restore=$component->getRestoreService();

$uuid='99999999-9999-4999-8999-999999999991';
$db->setQuery($db->getQuery(true)->delete('#__xdecaropeople_people')->where('uuid='.$db->quote($uuid)))->execute();
$now=\Joomla\CMS\Factory::getDate()->toSql();
$marker=(object)['uuid'=>$uuid,'display_name'=>'Restore Baseline','first_name'=>'Restore','last_name'=>'Baseline','person_status'=>'active','state'=>1,'access'=>1,'created'=>$now,'created_by'=>$adminId];
$db->insertObject('#__xdecaropeople_people',$marker,'id');
$baselineId=(int)$marker->id;
$baseline=$backup->create($adminId,'full-restore-baseline');

$db->setQuery($db->getQuery(true)->update('#__xdecaropeople_people')->set('display_name='.$db->quote('Mutated Name'))->where('id='.$baselineId))->execute();
$extra=(object)['uuid'=>'99999999-9999-4999-8999-999999999992','display_name'=>'Extra After Backup','first_name'=>'Extra','last_name'=>'After Backup','person_status'=>'active','state'=>1,'access'=>1,'created'=>$now,'created_by'=>$adminId];
$db->insertObject('#__xdecaropeople_people',$extra,'id');

$result=$restore->restoreFull($baseline['path'],$adminId);
if (empty($result['safety_backup_uuid']) || empty($result['integrity_ok'])) { fwrite(STDERR,"Full restore did not return safety backup/integrity.\n"); exit(1); }
$name=(string)$db->setQuery($db->getQuery(true)->select('display_name')->from('#__xdecaropeople_people')->where('uuid='.$db->quote($uuid)),0,1)->loadResult();
if ($name!=='Restore Baseline') { fwrite(STDERR,"Full restore did not restore baseline person data.\n"); exit(1); }
$extraCount=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_people')->where('uuid='.$db->quote('99999999-9999-4999-8999-999999999992')))->loadResult();
if ($extraCount!==0) { fwrite(STDERR,"Full restore left post-backup person data.\n"); exit(1); }
$safetyCount=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_backups')->where('uuid='.$db->quote($result['safety_backup_uuid'])))->loadResult();
if ($safetyCount!==1) { fwrite(STDERR,"Safety backup metadata was not created.\n"); exit(1); }

$stateBefore=(string)$db->setQuery($db->getQuery(true)->select('display_name')->from('#__xdecaropeople_people')->where('uuid='.$db->quote($uuid)),0,1)->loadResult();
$backupsBefore=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_backups'))->loadResult();
$bad=sys_get_temp_dir().'/people-full-fail-'.bin2hex(random_bytes(4)).'.zip'; copy($baseline['path'],$bad);
$zip=new \ZipArchive(); $zip->open($bad);
$data=json_decode((string)$zip->getFromName('data.json'),true,512,JSON_THROW_ON_ERROR);
$duplicate=$data['tables']['#__xdecaropeople_people'][0];
$data['tables']['#__xdecaropeople_people'][]=$duplicate;
$dataJson=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$manifest=json_decode((string)$zip->getFromName('manifest.json'),true,512,JSON_THROW_ON_ERROR);
$manifest['payload_sha256']=hash('sha256',$dataJson);
$manifest['table_counts']['#__xdecaropeople_people']=count($data['tables']['#__xdecaropeople_people']);
$manifest['people_count']=count($data['tables']['#__xdecaropeople_people']);
$manifestJson=json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$zip->addFromString('data.json',$dataJson); $zip->addFromString('manifest.json',$manifestJson);
$zip->addFromString('SHA256SUMS.txt',hash('sha256',$dataJson).'  data.json'."\n".hash('sha256',$manifestJson).'  manifest.json'."\n"); $zip->close();
$failed=false; try { $restore->restoreFull($bad,$adminId); } catch (\Throwable) { $failed=true; }
@unlink($bad);
if (!$failed) { fwrite(STDERR,"Invalid duplicate restore should fail.\n"); exit(1); }
$stateAfter=(string)$db->setQuery($db->getQuery(true)->select('display_name')->from('#__xdecaropeople_people')->where('uuid='.$db->quote($uuid)),0,1)->loadResult();
if ($stateAfter!==$stateBefore) { fwrite(STDERR,"Failed restore did not rollback current People state.\n"); exit(1); }
$backupsAfter=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_backups'))->loadResult();
if ($backupsAfter!==$backupsBefore+1) { fwrite(STDERR,"Failed restore must still keep its pre-restore safety backup.\n"); exit(1); }
$restoreActions=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_maintenance_log')->where("action='restore_full'"))->loadResult();
if ($restoreActions<1) { fwrite(STDERR,"Successful full restore was not logged.\n"); exit(1); }

echo "People 1.7.28 full restore runtime OK\n";
