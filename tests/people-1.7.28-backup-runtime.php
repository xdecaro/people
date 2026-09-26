<?php

declare(strict_types=1);

$root = getcwd();
if (!is_file($root . '/includes/defines.php') || !is_file($root . '/includes/framework.php')) {
    fwrite(STDERR, "Run this probe from the Joomla root.\n");
    exit(1);
}

$_SERVER['HTTP_HOST'] = 'localhost';
define('_JEXEC', 1);
define('JPATH_BASE', $root);
require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';
define('JPATH_COMPONENT', JPATH_ADMINISTRATOR . '/components/com_xdecaropeople');
define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_COMPONENT);

$container = \Joomla\CMS\Factory::getContainer();
$container->alias('session', 'session.cli')->alias('JSession', 'session.cli')->alias(\Joomla\CMS\Session\Session::class, 'session.cli')->alias(\Joomla\Session\Session::class, 'session.cli')->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
$app = $container->get('JApplicationAdministrator');
\Joomla\CMS\Factory::$application = $app;
$app->createExtensionNamespaceMap();
$db = $container->get(\Joomla\Database\DatabaseInterface::class);
$q = $db->getQuery(true)->select('id')->from('#__users')->where('username=' . $db->quote('admin'));
$adminId = (int) $db->setQuery($q, 0, 1)->loadResult();
$app->loadIdentity($container->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($adminId));

$component = $app->bootComponent('com_xdecaropeople');
$backup = $component->getBackupService();
$storage = $component->getBackupStorageService();
$health = $storage->isHealthy();
if (empty($health['ok'])) {
    fwrite(STDERR, "Backup storage is not healthy: " . ($health['message'] ?? '') . "\n");
    exit(1);
}
if (str_starts_with(realpath($storage->resolvePrivateDirectory()) ?: '', realpath(JPATH_ROOT) . DIRECTORY_SEPARATOR)) {
    fwrite(STDERR, "Default backup storage must not be inside the web root.\n");
    exit(1);
}

$now = \Joomla\CMS\Factory::getDate()->toSql();
$people = [
    ['uuid'=>'88888888-8888-4888-8888-888888888881','display_name'=>'Backup One','first_name'=>'Backup','last_name'=>'One'],
    ['uuid'=>'88888888-8888-4888-8888-888888888882','display_name'=>'Backup Two','first_name'=>'Backup','last_name'=>'Two'],
];
$ids = [];
foreach ($people as $data) {
    $row = (object) ($data + ['person_status'=>'active','state'=>1,'access'=>1,'created'=>$now,'created_by'=>$adminId]);
    $db->insertObject('#__xdecaropeople_people', $row, 'id');
    $ids[] = (int) $row->id;
}
$historyRow = (object) ['person_id'=>$ids[0],'action'=>'create','changed_fields'=>'["created"]','actor_user_id'=>$adminId,'created'=>$now];
$ignoreRow = (object) ['signature'=>str_repeat('a',64),'match_type'=>'runtime','record_ids'=>json_encode($ids),'created_by'=>$adminId,'created'=>$now];
$mergeRow = (object) ['source_person_id'=>$ids[1],'source_uuid'=>$people[1]['uuid'],'target_person_id'=>$ids[0],'target_uuid'=>$people[0]['uuid'],'copied_fields'=>'[]','created_by'=>$adminId,'created'=>$now];
$db->insertObject('#__xdecaropeople_history', $historyRow);
$db->insertObject('#__xdecaropeople_duplicate_ignores', $ignoreRow);
$db->insertObject('#__xdecaropeople_merges', $mergeRow);

$first = $backup->create($adminId, 'runtime-test');
foreach (['uuid','path','payload_sha256','people_count'] as $key) {
    if (empty($first[$key]) && $key !== 'people_count') { fwrite(STDERR, "Missing backup result key {$key}.\n"); exit(1); }
}
if ((int) $first['people_count'] !== 2 || !is_file($first['path'])) {
    fwrite(STDERR, "Backup result count/file invalid.\n");
    exit(1);
}

$zip = new \ZipArchive();
if ($zip->open($first['path']) !== true) { fwrite(STDERR, "Backup ZIP cannot be opened.\n"); exit(1); }
$names = [];
for ($i=0; $i<$zip->numFiles; $i++) $names[] = $zip->getNameIndex($i);
sort($names);
$expectedNames = ['SHA256SUMS.txt','data.json','manifest.json'];
sort($expectedNames);
if ($names !== $expectedNames) { fwrite(STDERR, "Backup ZIP has unexpected entries.\n"); exit(1); }
$data = json_decode((string) $zip->getFromName('data.json'), true, 512, JSON_THROW_ON_ERROR);
$manifest = json_decode((string) $zip->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$sums = (string) $zip->getFromName('SHA256SUMS.txt');
$zip->close();

$expectedTables = ['#__xdecaropeople_duplicate_ignores','#__xdecaropeople_history','#__xdecaropeople_merges','#__xdecaropeople_people'];
$actualTables = array_keys($data['tables'] ?? []);
sort($actualTables);
sort($expectedTables);
if ($actualTables !== $expectedTables) { fwrite(STDERR, "Backup payload table whitelist mismatch.\n"); exit(1); }
if (isset($data['tables']['#__xdecaropeople_backups']) || isset($data['tables']['#__xdecaropeople_maintenance_log'])) { fwrite(STDERR, "Operational tables leaked into backup payload.\n"); exit(1); }
$payloadHash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
if (($manifest['payload_sha256'] ?? '') !== $payloadHash || !str_contains($sums, $payloadHash . '  data.json')) { fwrite(STDERR, "Payload checksum mismatch.\n"); exit(1); }

$second = $backup->create($adminId, 'runtime-test-repeat');
if (($second['payload_sha256'] ?? '') !== ($first['payload_sha256'] ?? '')) { fwrite(STDERR, "Canonical payload checksum must be stable.\n"); exit(1); }
$download = $backup->resolveDownload((string) $first['uuid']);
if (($download['path'] ?? '') !== $first['path']) { fwrite(STDERR, "Backup download resolution mismatch.\n"); exit(1); }
$backup->delete((string) $second['uuid'], $adminId);
if (is_file($second['path'])) { fwrite(STDERR, "Deleted backup file still exists.\n"); exit(1); }

$actions = $db->setQuery($db->getQuery(true)->select('action')->from('#__xdecaropeople_maintenance_log')->where("action IN ('backup_create','backup_download','backup_delete')"))->loadColumn();
foreach (['backup_create','backup_download','backup_delete'] as $action) if (!in_array($action, $actions, true)) { fwrite(STDERR, "Missing backup maintenance action {$action}.\n"); exit(1); }

echo "People 1.7.28 backup runtime OK\n";
