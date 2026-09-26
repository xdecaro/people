<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$fail = [];
$read = static function (string $path) use ($root, &$fail): string {
    $file = $root . '/' . $path;
    if (!is_file($file)) { $fail[] = "Missing {$path}"; return ''; }
    return (string) file_get_contents($file);
};
$has = static function (string $needle, string $haystack, string $message) use (&$fail): void {
    if (!str_contains($haystack, $needle)) $fail[] = $message . " (missing: {$needle})";
};

$version = trim($read('VERSION'));
if ($version !== '1.7.29') $fail[] = "VERSION must be 1.7.29, got {$version}";
$component = $read('component/xdecaropeople.xml');
$package = $read('package/pkg_people.xml');
$assets = $read('component/media/joomla.asset.json');
$access = $read('component/admin/access.xml');
$update = $read('component/admin/sql/updates/mysql/1.7.29.sql');
$backup = $read('component/admin/src/Service/BackupService.php');

foreach (['component/xdecaropeople.xml' => $component, 'package/pkg_people.xml' => $package] as $path => $xml) {
    $has('<version>1.7.29</version>', $xml, "{$path} must declare 1.7.29");
    $has('<targetplatform name="joomla" version="6.1.3"/>', $xml, "{$path} must remain Joomla 6.1.3 only");
}
$has('"version": "1.7.29"', $assets, 'Web asset manifest must use 1.7.29');
if (str_contains($assets, '"version": "1.7.28"')) $fail[] = 'No web asset may remain version 1.7.28';
$has("private const SCHEMA_VERSION = '1.7.29';", $backup, 'Backup schema version must be 1.7.29');
$has('people.database_repair', $access, 'Repair ACL missing');
$has('people.database_destructive', $access, 'Destructive ACL missing');
$has('People 1.7.29 database maintenance schema marker', $update, '1.7.29 SQL marker missing');

foreach ([
    'component/admin/src/Service/DatabaseSchemaDefinition.php',
    'component/admin/src/Service/DatabaseSchemaInspector.php',
    'component/admin/src/Service/DatabaseMaintenanceService.php',
    'component/media/js/database-maintenance.js',
    'tests/people-1.7.29-canonical-schema-contract.php',
    'tests/people-1.7.29-database-schema-runtime.php',
    'tests/people-1.7.29-database-repair-runtime.php',
    'tests/people-1.7.29-database-empty-runtime.php',
    'tests/people-1.7.29-database-recreate-runtime.php',
    'tests/people-1.7.29-database-maintenance-ui-contract.php',
] as $path) {
    if (!is_file($root . '/' . $path)) $fail[] = "Required 1.7.29 file missing: {$path}";
}

if ($fail) {
    fwrite(STDERR, "People 1.7.29 release readiness contract FAILED\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

echo "People 1.7.29 release readiness contract PASS\n";
