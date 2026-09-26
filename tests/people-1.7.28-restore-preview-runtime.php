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
$q = $db->getQuery(true)->select('id')->from('#__users')->where($db->quoteName('username').'='.$db->quote('admin'));
$adminId = (int) $db->setQuery($q,0,1)->loadResult();
$app->loadIdentity($container->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($adminId));
$component = $app->bootComponent('com_xdecaropeople');
$backup = $component->getBackupService();
$restore = $component->getRestoreService();

$before = [];
foreach (\xdecaro\Component\People\Administrator\Service\BackupService::BACKUP_TABLES as $table) {
    $q = $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName($table));
    $before[$table] = (int) $db->setQuery($q)->loadResult();
}
$created = $backup->create($adminId, 'restore_preview_fixture');
$valid = $restore->preview((string) $created['path'], $adminId);
if (($valid['compatible'] ?? false) !== true || ($valid['blocking_errors'] ?? []) !== []) { fwrite(STDERR, "Valid backup did not preview successfully.\n"); exit(1); }
foreach (['manifest','counts','people_active','people_trashed','payload_sha256'] as $key) if (!array_key_exists($key,$valid)) { fwrite(STDERR,"Preview missing {$key}.\n"); exit(1); }
foreach ($before as $table => $count) {
    $q = $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName($table));
    if ((int) $db->setQuery($q)->loadResult() !== $count) { fwrite(STDERR, "Preview modified functional table {$table}.\n"); exit(1); }
}

file_put_contents('/tmp/people-corrupt.zip', 'not a zip');
if (($restore->preview('/tmp/people-corrupt.zip',$adminId)['compatible'] ?? true) !== false) { fwrite(STDERR,"Corrupt ZIP accepted.\n"); exit(1); }

$readZip = static function (string $path): array {
    $z = new \ZipArchive(); $z->open($path);
    $out=[]; for($i=0;$i<$z->numFiles;$i++){ $name=$z->getNameIndex($i); $out[$name]=$z->getFromName($name); } $z->close(); return $out;
};
$writeZip = static function (string $path, array $entries): void {
    $z=new \ZipArchive(); $z->open($path,\ZipArchive::CREATE|\ZipArchive::OVERWRITE); foreach($entries as $name=>$data)$z->addFromString($name,(string)$data); $z->close();
};
$entries = $readZip((string) $created['path']);
$evil = $entries; $evil['../evil.php'] = '<?php echo 1;'; $writeZip('/tmp/people-evil.zip',$evil);
if (($restore->preview('/tmp/people-evil.zip',$adminId)['compatible'] ?? true) !== false) { fwrite(STDERR,"Path traversal/extra file accepted.\n"); exit(1); }

$data = json_decode((string)$entries['data.json'],true,512,JSON_THROW_ON_ERROR);
$manifest = json_decode((string)$entries['manifest.json'],true,512,JSON_THROW_ON_ERROR);
$data['tables']['#__foreign_table'] = [];
$dataJson = \xdecaro\Component\People\Administrator\Service\BackupService::canonicalJson($data);
$manifest['payload_sha256'] = hash('sha256',$dataJson);
$manifestJson = \xdecaro\Component\People\Administrator\Service\BackupService::canonicalJson($manifest);
$writeZip('/tmp/people-foreign.zip',[
    'manifest.json'=>$manifestJson,'data.json'=>$dataJson,
    'SHA256SUMS.txt'=>hash('sha256',$dataJson)."  data.json\n".hash('sha256',$manifestJson)."  manifest.json\n"
]);
if (($restore->preview('/tmp/people-foreign.zip',$adminId)['compatible'] ?? true) !== false) { fwrite(STDERR,"Foreign table accepted.\n"); exit(1); }

$manifest = json_decode((string)$entries['manifest.json'],true,512,JSON_THROW_ON_ERROR); $manifest['schema_version']='99.0.0';
$manifestJson = \xdecaro\Component\People\Administrator\Service\BackupService::canonicalJson($manifest);
$writeZip('/tmp/people-schema.zip',[
    'manifest.json'=>$manifestJson,'data.json'=>$entries['data.json'],
    'SHA256SUMS.txt'=>hash('sha256',(string)$entries['data.json'])."  data.json\n".hash('sha256',$manifestJson)."  manifest.json\n"
]);
if (($restore->preview('/tmp/people-schema.zip',$adminId)['compatible'] ?? true) !== false) { fwrite(STDERR,"Unsupported schema accepted.\n"); exit(1); }

file_put_contents('/tmp/people-large.zip', str_repeat('x', 1024 * 1024 + 5));
$smallLimitRestore = new \xdecaro\Component\People\Administrator\Service\RestoreService($db, $component->getMaintenanceLogService(), 1);
$large = $smallLimitRestore->preview('/tmp/people-large.zip',$adminId);
if (($large['compatible'] ?? true) !== false || !in_array('file_too_large',$large['blocking_errors'] ?? [],true)) { fwrite(STDERR,"Oversized restore file was not rejected first.\n"); exit(1); }

echo "People 1.7.28 restore preview runtime OK\n";
