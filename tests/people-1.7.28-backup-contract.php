<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$fail = static function (string $message): never { fwrite(STDERR, $message . PHP_EOL); exit(1); };
$read = static function (string $path) use ($fail): string {
    $value = file_get_contents($path);
    if ($value === false) $fail('Unable to read: ' . $path);
    return $value;
};

$storagePath = $root . '/component/admin/src/Service/BackupStorageService.php';
$backupPath = $root . '/component/admin/src/Service/BackupService.php';
foreach ([$storagePath, $backupPath] as $path) {
    if (!is_file($path)) $fail('Missing backup service: ' . basename($path));
}

$storage = $read($storagePath);
$backup = $read($backupPath);
$provider = $read($root . '/component/admin/services/provider.php');
$component = $read($root . '/component/admin/src/Extension/PeopleComponent.php');

foreach ([
    'function resolvePrivateDirectory(',
    'function pathFor(',
    'function isHealthy(',
] as $needle) {
    if (!str_contains($storage, $needle)) $fail('BackupStorageService missing: ' . $needle);
}

foreach ([
    'function create(',
    'function list(',
    'function resolveDownload(',
    'function delete(',
    'manifest.json',
    'data.json',
    'SHA256SUMS.txt',
    '#__xdecaropeople_people',
    '#__xdecaropeople_history',
    '#__xdecaropeople_duplicate_ignores',
    '#__xdecaropeople_merges',
] as $needle) {
    if (!str_contains($backup, $needle)) $fail('BackupService missing contract: ' . $needle);
}

foreach (['#__xdecaropeople_backups', '#__xdecaropeople_maintenance_log'] as $forbiddenPayload) {
    $payloadSection = strstr($backup, 'BACKUP_TABLES');
    if ($payloadSection !== false && preg_match('/BACKUP_TABLES\s*=\s*\[[^;]*' . preg_quote($forbiddenPayload, '/') . '/s', $payloadSection)) {
        $fail('Operational table must not be in backup payload whitelist: ' . $forbiddenPayload);
    }
}

foreach (['BackupStorageService::class', 'BackupService::class'] as $needle) {
    if (!str_contains($provider, $needle)) $fail('Backup service not registered: ' . $needle);
}
foreach (['getBackupStorageService', 'getBackupService'] as $needle) {
    if (!str_contains($component, $needle)) $fail('PeopleComponent missing backup getter: ' . $needle);
}

if (str_contains($storage, 'JPATH_CACHE')) $fail('Backup storage must never fall back to Joomla cache.');

echo "People 1.7.28 backup contract OK\n";
