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
$container
    ->alias('session', 'session.cli')
    ->alias('JSession', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
$app = $container->get('JApplicationAdministrator');
\Joomla\CMS\Factory::$application = $app;
$app->createExtensionNamespaceMap();
$db = $container->get(\Joomla\Database\DatabaseInterface::class);
$q = $db->getQuery(true)->select('id')->from('#__users')->where($db->quoteName('username') . '=' . $db->quote('admin'));
$adminId = (int) $db->setQuery($q, 0, 1)->loadResult();
$app->loadIdentity($container->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($adminId));

$component = $app->bootComponent('com_xdecaropeople');
$backup = $component->getBackupService();
$storage = $component->getBackupStorageService();
$now = \Joomla\CMS\Factory::getDate()->toSql();

$person = (object) [
    'uuid' => '88888888-8888-4888-8888-888888888888',
    'display_name' => 'Backup Runtime Person',
    'first_name' => 'Backup',
    'last_name' => 'Runtime',
    'person_status' => 'active',
    'state' => 1,
    'access' => 1,
    'created' => $now,
    'created_by' => $adminId,
];
$db->insertObject('#__xdecaropeople_people', $person, 'id');
$db->insertObject('#__xdecaropeople_history', (object) [
    'person_id' => (int) $person->id,
    'action' => 'create',
    'changed_fields' => '["created"]',
    'actor_user_id' => $adminId,
    'created' => $now,
]);
$db->insertObject('#__xdecaropeople_duplicate_ignores', (object) [
    'signature' => hash('sha256', 'backup-runtime-ignore'),
    'match_type' => 'email',
    'record_ids' => json_encode([(int) $person->id]),
    'created_by' => $adminId,
    'created' => $now,
]);
$db->insertObject('#__xdecaropeople_merges', (object) [
    'source_person_id' => 900001,
    'source_uuid' => '99999999-9999-4999-8999-999999999999',
    'target_person_id' => (int) $person->id,
    'target_uuid' => (string) $person->uuid,
    'copied_fields' => '[]',
    'created_by' => $adminId,
    'created' => $now,
]);

$first = $backup->create($adminId, 'runtime_test');
$second = $backup->create($adminId, 'runtime_test_repeat');
if (($first['payload_sha256'] ?? '') === '' || ($first['payload_sha256'] ?? '') !== ($second['payload_sha256'] ?? '')) {
    fwrite(STDERR, "Canonical payload checksum is not stable.\n"); exit(1);
}

$path = (string) ($first['path'] ?? '');
if ($path === '' || !is_file($path)) { fwrite(STDERR, "Backup ZIP was not created.\n"); exit(1); }
$rootReal = realpath(JPATH_ROOT) ?: JPATH_ROOT;
$backupReal = realpath($path) ?: $path;
if (str_starts_with($backupReal, rtrim($rootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
    fwrite(STDERR, "Backup was stored inside the public Joomla root.\n"); exit(1);
}

$zip = new \ZipArchive();
if ($zip->open($path) !== true) { fwrite(STDERR, "Backup ZIP cannot be opened.\n"); exit(1); }
$names = [];
for ($i = 0; $i < $zip->numFiles; $i++) $names[] = $zip->getNameIndex($i);
sort($names);
$expectedNames = ['SHA256SUMS.txt', 'data.json', 'manifest.json'];
sort($expectedNames);
if ($names !== $expectedNames) { fwrite(STDERR, "Unexpected ZIP entries: " . implode(',', $names) . "\n"); exit(1); }
$dataJson = $zip->getFromName('data.json');
$manifestJson = $zip->getFromName('manifest.json');
$sums = $zip->getFromName('SHA256SUMS.txt');
$zip->close();
if (!is_string($dataJson) || !is_string($manifestJson) || !is_string($sums)) { fwrite(STDERR, "Canonical ZIP files missing.\n"); exit(1); }
if (hash('sha256', $dataJson) !== ($first['payload_sha256'] ?? '')) { fwrite(STDERR, "Payload checksum mismatch.\n"); exit(1); }
if (!str_contains($sums, hash('sha256', $dataJson) . '  data.json')) { fwrite(STDERR, "SHA256SUMS does not cover data.json.\n"); exit(1); }

$data = json_decode($dataJson, true, 512, JSON_THROW_ON_ERROR);
$expectedTables = [
    '#__xdecaropeople_people',
    '#__xdecaropeople_history',
    '#__xdecaropeople_duplicate_ignores',
    '#__xdecaropeople_merges',
];
if (array_keys($data['tables'] ?? []) !== $expectedTables) { fwrite(STDERR, "Backup payload table whitelist is wrong.\n"); exit(1); }
foreach (['#__xdecaropeople_backups', '#__xdecaropeople_maintenance_log'] as $forbidden) {
    if (isset(($data['tables'] ?? [])[$forbidden])) { fwrite(STDERR, "Operational table leaked into payload.\n"); exit(1); }
}
$manifest = json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR);
foreach (['backup_uuid','format','format_version','component_version','schema_version','joomla_version','created_utc','created_by','table_counts','people_count','payload_sha256'] as $key) {
    if (!array_key_exists($key, $manifest)) { fwrite(STDERR, "Manifest missing {$key}.\n"); exit(1); }
}
if (($manifest['payload_sha256'] ?? '') !== hash('sha256', $dataJson)) { fwrite(STDERR, "Manifest payload hash mismatch.\n"); exit(1); }

$list = $backup->list();
if (count($list) < 2) { fwrite(STDERR, "Backup metadata list is incomplete.\n"); exit(1); }
$resolved = $backup->resolveDownload((string) $first['uuid']);
if (($resolved['sha256'] ?? '') !== hash_file('sha256', $path)) { fwrite(STDERR, "Download verification failed.\n"); exit(1); }

$publicStorage = new \xdecaro\Component\People\Administrator\Service\BackupStorageService(JPATH_ROOT . '/unsafe-people-backups');
$health = $publicStorage->isHealthy();
if (($health['ok'] ?? true) !== false) { fwrite(STDERR, "Public backup storage was not rejected.\n"); exit(1); }

$backup->delete((string) $first['uuid'], $adminId);
if (is_file($path)) { fwrite(STDERR, "Deleted backup file still exists.\n"); exit(1); }

$q = $db->getQuery(true)->select('action')->from('#__xdecaropeople_maintenance_log')->where($db->quoteName('action') . ' IN (' . implode(',', array_map([$db, 'quote'], ['backup_create','backup_download','backup_delete'])) . ')');
$actions = $db->setQuery($q)->loadColumn();
foreach (['backup_create','backup_download','backup_delete'] as $required) {
    if (!in_array($required, $actions, true)) { fwrite(STDERR, "Missing maintenance action {$required}.\n"); exit(1); }
}

if (($storage->isHealthy()['ok'] ?? false) !== true) { fwrite(STDERR, "Default private storage is not healthy.\n"); exit(1); }

echo "People 1.7.28 backup runtime OK\n";
