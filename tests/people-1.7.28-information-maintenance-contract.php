<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $path) use ($root, &$failures): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        $failures[] = "Missing file: {$path}";
        return '';
    }
    $content = file_get_contents($full);
    if ($content === false) {
        $failures[] = "Unreadable file: {$path}";
        return '';
    }
    return $content;
};
$assertContains = static function (string $needle, string $haystack, string $message) use (&$failures): void {
    if (!str_contains($haystack, $needle)) $failures[] = $message . " (missing: {$needle})";
};
$assertNotContains = static function (string $needle, string $haystack, string $message) use (&$failures): void {
    if (str_contains($haystack, $needle)) $failures[] = $message . " (unexpected: {$needle})";
};

$version = trim($read('VERSION'));
if ($version !== '1.7.28') $failures[] = "VERSION must be 1.7.28, got {$version}";

$componentManifest = $read('component/xdecaropeople.xml');
$packageManifest = $read('package/pkg_people.xml');
$assets = $read('component/media/joomla.asset.json');
$installSql = $read('component/admin/sql/install.mysql.utf8mb4.sql');
$updateSql = $read('component/admin/sql/updates/mysql/1.7.28.sql');
$access = $read('component/admin/access.xml');
$config = $read('component/admin/config.xml');

foreach (['component/xdecaropeople.xml' => $componentManifest, 'package/pkg_people.xml' => $packageManifest] as $path => $xml) {
    $assertContains('<version>1.7.28</version>', $xml, "{$path} must declare 1.7.28");
    $assertContains('<targetplatform name="joomla" version="6.1.3"/>', $xml, "{$path} must remain Joomla 6.1.3 only");
}
$assertContains('"version": "1.7.28"', $assets, 'Web asset manifest must be version 1.7.28');
$assertNotContains('"version": "1.7.27"', $assets, 'No web asset may remain on 1.7.27');

foreach (['#__xdecaropeople_backups', '#__xdecaropeople_maintenance_log'] as $table) {
    $assertContains($table, $installSql, "Install SQL must define {$table}");
    $assertContains($table, $updateSql, "Update SQL must define {$table}");
}
foreach (['#__xdecaropeople_people', '#__xdecaropeople_history', '#__xdecaropeople_duplicate_ignores', '#__xdecaropeople_merges'] as $table) {
    $assertContains($table, $installSql, "Install SQL must keep functional People table {$table}");
}
$assertContains('name="people.backup"', $access, 'ACL must define people.backup');
$assertContains('name="people.restore"', $access, 'ACL must define people.restore');
$assertContains('name="backup_storage_path"', $config, 'Config must define backup_storage_path');
$assertContains('name="backup_max_upload_mb"', $config, 'Config must define backup_max_upload_mb');
$assertContains('default="64"', $config, 'Backup upload limit must default to 64 MB');

$maintenanceSql = $installSql . "\n" . $updateSql;
foreach (['#__xdecaroorganizations_', '#__xdecaromembership_', '#__xdecarocompetitions_', '#__xdecarophotos_', '#__xdecarodocuments_', '#__xdecaronotifications_'] as $foreignPrefix) {
    $assertNotContains($foreignPrefix, $maintenanceSql, 'Maintenance schema must not touch foreign component tables');
}

$maintenanceService = $read('component/admin/src/Service/MaintenanceLogService.php');
$trashService = $read('component/admin/src/Service/PersonTrashService.php');
$backupStorage = $read('component/admin/src/Service/BackupStorageService.php');
$backupService = $read('component/admin/src/Service/BackupService.php');
$provider = $read('component/admin/services/provider.php');
$component = $read('component/admin/src/Extension/PeopleComponent.php');
$controller = $read('component/admin/src/Controller/PeopleController.php');

$assertContains('final class MaintenanceLogService', $maintenanceService, 'MaintenanceLogService must exist');
foreach (['function log(', 'function recent(', 'function latestForSubject('] as $method) $assertContains($method, $maintenanceService, "MaintenanceLogService must expose {$method}");
$assertContains('final class PersonTrashService', $trashService, 'PersonTrashService must exist');
foreach (['function trash(', 'function restore(', 'function purge(', 'function getRecentTrashed('] as $method) $assertContains($method, $trashService, "PersonTrashService must expose {$method}");
$assertContains('final class BackupStorageService', $backupStorage, 'BackupStorageService must exist');
foreach (['function resolvePrivateDirectory(', 'function pathFor(', 'function isHealthy('] as $method) $assertContains($method, $backupStorage, "BackupStorageService must expose {$method}");
$assertContains('final class BackupService', $backupService, 'BackupService must exist');
foreach (['function create(', 'function list(', 'function resolveDownload(', 'function delete('] as $method) $assertContains($method, $backupService, "BackupService must expose {$method}");
foreach (['manifest.json', 'data.json', 'SHA256SUMS.txt'] as $entry) $assertContains($entry, $backupService, "BackupService must use canonical ZIP entry {$entry}");
foreach (['#__xdecaropeople_people', '#__xdecaropeople_history', '#__xdecaropeople_duplicate_ignores', '#__xdecaropeople_merges'] as $table) $assertContains($table, $backupService, "Backup whitelist must contain {$table}");
$assertNotContains('#__xdecaropeople_backups', $backupService, 'Backup payload must not recursively include backup metadata');
$assertNotContains('#__xdecaropeople_maintenance_log', $backupService, 'Backup payload must not include maintenance log');

foreach (['MaintenanceLogService::class', 'PersonTrashService::class', 'BackupStorageService::class', 'BackupService::class'] as $service) $assertContains($service, $provider, "DI must register {$service}");
foreach (['getMaintenanceLogService', 'getPersonTrashService', 'getBackupStorageService', 'getBackupService'] as $getter) $assertContains($getter, $component, "PeopleComponent must expose {$getter}");
$assertContains('function restoreTrash(', $controller, 'PeopleController must expose restoreTrash task');
$assertContains('function purge(', $controller, 'PeopleController must expose explicit purge task');

if ($failures) {
    fwrite(STDERR, "People 1.7.28 maintenance contract FAILED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "People 1.7.28 maintenance contract PASS\n");
