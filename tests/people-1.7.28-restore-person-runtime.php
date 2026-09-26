<?php

declare(strict_types=1);

$root=getcwd();
if(!is_file($root.'/includes/defines.php')){fwrite(STDERR,"Run from Joomla root.\n");exit(1);} $_SERVER['HTTP_HOST']='localhost'; define('_JEXEC',1); define('JPATH_BASE',$root); require JPATH_BASE.'/includes/defines.php'; require JPATH_BASE.'/includes/framework.php'; define('JPATH_COMPONENT',JPATH_ADMINISTRATOR.'/components/com_xdecaropeople'); define('JPATH_COMPONENT_ADMINISTRATOR',JPATH_COMPONENT);
$c=\Joomla\CMS\Factory::getContainer(); $c->alias('session','session.cli')->alias('JSession','session.cli')->alias(\Joomla\CMS\Session\Session::class,'session.cli')->alias(\Joomla\Session\Session::class,'session.cli')->alias(\Joomla\Session\SessionInterface::class,'session.cli'); $app=$c->get('JApplicationAdministrator'); \Joomla\CMS\Factory::$application=$app; $app->createExtensionNamespaceMap(); $db=$c->get(\Joomla\Database\DatabaseInterface::class); $adminId=(int)$db->setQuery($db->getQuery(true)->select('id')->from('#__users')->where('username='.$db->quote('admin')),0,1)->loadResult(); $app->loadIdentity($c->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($adminId));
$component=$app->bootComponent('com_xdecaropeople'); $backup=$component->getBackupService(); $restore=$component->getRestoreService(); $trash=$component->getPersonTrashService(); $now=\Joomla\CMS\Factory::getDate()->toSql();

$uuid='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1';
$row=(object)['uuid'=>$uuid,'display_name'=>'Restore Person Source','first_name'=>'Restore','last_name'=>'Person Source','person_status'=>'active','state'=>1,'access'=>1,'created'=>$now,'created_by'=>$adminId]; $db->insertObject('#__xdecaropeople_people',$row,'id'); $sourceId=(int)$row->id; $sourceBackup=$backup->create($adminId,'person-restore-source');
$db->setQuery($db->getQuery(true)->delete('#__xdecaropeople_people')->where('id='.(int)$sourceId))->execute();
$result=$restore->restorePerson($sourceBackup['path'],$uuid,$adminId,false);
if(($result['uuid']??'')!==$uuid || (int)($result['id']??0)!==$sourceId){fwrite(STDERR,"Free original ID must be reused with same UUID.\n");exit(1);}

$db->setQuery($db->getQuery(true)->delete('#__xdecaropeople_people')->where('uuid='.$db->quote($uuid)))->execute();
$occupier=(object)['id'=>$sourceId,'uuid'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2','display_name'=>'ID Occupier','first_name'=>'ID','last_name'=>'Occupier','person_status'=>'active','state'=>1,'access'=>1,'created'=>$now,'created_by'=>$adminId]; $db->insertObject('#__xdecaropeople_people',$occupier);
$result=$restore->restorePerson($sourceBackup['path'],$uuid,$adminId,false);
if(($result['uuid']??'')!==$uuid || (int)($result['id']??0)===$sourceId){fwrite(STDERR,"ID collision must allocate a new ID while preserving UUID.\n");exit(1);}
$restoredId=(int)$result['id'];

$blocked=false; try{$restore->restorePerson($sourceBackup['path'],$uuid,$adminId,false);}catch(\RuntimeException $e){$blocked=$e->getCode()===409;} if(!$blocked){fwrite(STDERR,"Existing UUID must require overwrite.\n");exit(1);}
$db->setQuery($db->getQuery(true)->update('#__xdecaropeople_people')->set('display_name='.$db->quote('Changed Existing'))->where('id='.$restoredId))->execute();
$result=$restore->restorePerson($sourceBackup['path'],$uuid,$adminId,true); $name=(string)$db->setQuery($db->getQuery(true)->select('display_name')->from('#__xdecaropeople_people')->where('id='.$restoredId),0,1)->loadResult();
if((int)$result['id']!==$restoredId || $name!=='Restore Person Source'){fwrite(STDERR,"Overwrite must preserve current local ID and restore source values.\n");exit(1);}

$trash->trash([$restoredId],$adminId); $result=$restore->restorePerson($sourceBackup['path'],$uuid,$adminId,false); $state=(int)$db->setQuery($db->getQuery(true)->select('state')->from('#__xdecaropeople_people')->where('id='.$restoredId),0,1)->loadResult();
if(($result['action']??'')!=='restored_from_trash' || $state===-2){fwrite(STDERR,"Existing trashed UUID must prefer trash restore.\n");exit(1);}
$logged=(int)$db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_maintenance_log')->where("action='restore_person'"))->loadResult(); if($logged<3){fwrite(STDERR,"Single-person restores must be logged.\n");exit(1);} echo "People 1.7.28 single-person restore runtime OK\n";
