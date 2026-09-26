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
    if (!str_contains($haystack, $needle)) {
        $failures[] = $message . " (missing: {$needle})";
    }
};

$assertNotContains = static function (string $needle, string $haystack, string $message) use (&$failures): void {
    if (str_contains($haystack, $needle)) {
        $failures[] = $message . " (unexpected: {$needle})";
    }
};

$version = trim($read('VERSION'));
if ($version !== '1.7.28') {
    $failures[] = "VERSION must be 1.7.28, got {$version}";
}

$componentManifest = $read('component/xdecaropeople.xml');
$packageManifest = $read('package/pkg_people.xml');
$assets = $read('component/media/joomla.asset.json');
$installSql = $read('component/admin/sql/install.mysql.utf8mb4.sql');
$updateSql = $read('component/admin/sql/updates/mysql/1.7.28.sql');
$access = $read('component/admin/access.xml');
$config = $read('component/admin/config.xml');

foreach ([
    'component/xdecaropeople.xml' => $componentManifest,
    'package/pkg_people.xml' => $packageManifest,
] as $path => $xml) {
    $assertContains('<version>1.7.28</version>', $xml, "{$path} must declare 1.7.28");
    $assertContains('<targetplatform name="joomla" version="6.1.3"/>', $xml, "{$path} must remain Joomla 6.1.3 only");
}

$assertContains('"version": "1.7.28"', $assets, 'Web asset manifest must be version 1.7.28');
$assertNotContains('"version": "1.7.27"', $assets, 'No web asset may remain on 1.7.27');

foreach (['#__xdecaropeople_backups', '#__xdecaropeople_maintenance_log'] as $table) {
    $assertContains($table, $installSql, "Install SQL must define {$table}");
    $assertContains($table, $updateSql, "Update SQL must define {$table}");
}

foreach ([
    '#__xdecaropeople_people',
    '#__xdecaropeople_history',
    '#__xdecaropeople_duplicate_ignores',
    '#__xdecaropeople_merges',
] as $table) {
    $assertContains($table, $installSql, "Install SQL must keep functional People table {$table}");
}

$assertContains('name="people.backup"', $access, 'ACL must define people.backup');
$assertContains('name="people.restore"', $access, 'ACL must define people.restore');
$assertContains('name="backup_storage_path"', $config, 'Config must define backup_storage_path');
$assertContains('name="backup_max_upload_mb"', $config, 'Config must define backup_max_upload_mb');
$assertContains('default="64"', $config, 'Backup upload limit must default to 64 MB');

$maintenanceSql = $installSql . "\n" . $updateSql;
foreach ([
    '#__xdecaroorganizations_',
    '#__xdecaromembership_',
    '#__xdecarocompetitions_',
    '#__xdecarophotos_',
    '#__xdecarodocuments_',
    '#__xdecaronotifications_',
] as $foreignPrefix) {
    $assertNotContains($foreignPrefix, $maintenanceSql, 'Maintenance schema must not touch foreign component tables');
}

if ($failures) {
    fwrite(STDERR, "People 1.7.28 maintenance contract FAILED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "People 1.7.28 maintenance contract PASS\n");
