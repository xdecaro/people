<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$fail = [];
$read = static function (string $path) use ($root, &$fail): string {
    $file = $root . '/' . $path;
    if (!is_file($file)) {
        $fail[] = "Missing {$path}";
        return '';
    }
    return (string) file_get_contents($file);
};
$has = static function (string $needle, string $haystack, string $message) use (&$fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail[] = $message . " (missing: {$needle})";
    }
};

$version = trim($read('VERSION'));
if (!version_compare($version, '1.7.32', '>=')) {
    $fail[] = "VERSION must be at least 1.7.32, got {$version}";
}

$component = $read('component/xdecaropeople.xml');
$package = $read('package/pkg_people.xml');
$assets = $read('component/media/joomla.asset.json');
$update = $read('component/admin/sql/updates/mysql/1.7.32.sql');
$backup = $read('component/admin/src/Service/BackupService.php');
$js = $read('component/media/js/database-maintenance.js');
$release = $read('.github/workflows/release.yml');

foreach (['component/xdecaropeople.xml' => $component, 'package/pkg_people.xml' => $package] as $path => $xml) {
    $has('<version>' . $version . '</version>', $xml, "{$path} must declare current VERSION {$version}");
    $has('<targetplatform name="joomla" version="6.1.3"/>', $xml, "{$path} must remain Joomla 6.1.3 only");
}
$has('"version": "' . $version . '"', $assets, 'Web asset manifest must use current VERSION');
$has("private const SCHEMA_VERSION = '1.7.29';", $backup, 'UX-only backup naming must not change backup payload schema');
$has('People 1.7.32 readable backup names and compact activity marker', $update, '1.7.32 SQL marker missing');
$has('people-backup-', $backup, 'Readable backup filename implementation missing');
$has('slice(0, 5)', $js, 'Compact maintenance activity implementation missing');
$has('php tests/people-1.7.32-backup-labels-activity-contract.php', $release, 'Release workflow must keep running 1.7.32 contract');
$has("admin/sql/updates/mysql/1.7.32.sql", $release, 'Release package verification must keep 1.7.32 migration marker');

foreach ([
    'tests/people-1.7.32-backup-labels-activity-contract.php',
    '.github/workflows/people-1.7.32-readable-backups-compact-activity.yml',
] as $path) {
    if (!is_file($root . '/' . $path)) {
        $fail[] = "Required 1.7.32 file missing: {$path}";
    }
}

if ($fail) {
    fwrite(STDERR, "People 1.7.32 release readiness contract FAILED\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

echo "People 1.7.32 release readiness contract PASS\n";
