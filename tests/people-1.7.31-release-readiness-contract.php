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
if (version_compare($version, '1.7.31', '<')) {
    $fail[] = "VERSION must be at least 1.7.31, got {$version}";
}

$component = $read('component/xdecaropeople.xml');
$package = $read('package/pkg_people.xml');
$assets = $read('component/media/joomla.asset.json');
$update = $read('component/admin/sql/updates/mysql/1.7.31.sql');
$backup = $read('component/admin/src/Service/BackupService.php');
$inspector = $read('component/admin/src/Service/DatabaseSchemaInspector.php');
$release = $read('.github/workflows/release.yml');

foreach (['component/xdecaropeople.xml' => $component, 'package/pkg_people.xml' => $package] as $path => $xml) {
    $has('<version>' . $version . '</version>', $xml, "{$path} must declare current VERSION {$version}");
    $has('<targetplatform name="joomla" version="6.1.3"/>', $xml, "{$path} must remain Joomla 6.1.3 only");
}
$has('"version": "' . $version . '"', $assets, 'Web asset manifest must use current VERSION');
$has("private const SCHEMA_VERSION = '1.7.29';", $backup, 'Schema-comparison fix must not change backup payload schema');
$has('People 1.7.31 MariaDB integer-width normalization marker', $update, '1.7.31 SQL marker missing');
$has('function normalizeColumnType(', $inspector, 'Inspector must normalize database column types');
$has('tinyint|smallint|mediumint|int|integer|bigint', $inspector, 'Inspector must limit display-width normalization to integer families');
$has('php tests/people-1.7.31-mariadb-integer-width-contract.php', $release, 'Release workflow must keep the 1.7.31 regression contract');
$has("admin/sql/updates/mysql/1.7.31.sql", $release, 'Release package verification must keep the 1.7.31 migration marker');

foreach ([
    'tests/people-1.7.31-mariadb-integer-width-contract.php',
    '.github/workflows/people-1.7.31-mariadb-integer-width.yml',
] as $path) {
    if (!is_file($root . '/' . $path)) {
        $fail[] = "Required 1.7.31 file missing: {$path}";
    }
}

if ($fail) {
    fwrite(STDERR, "People 1.7.31 release readiness contract FAILED\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

echo "People 1.7.31 release readiness contract PASS\n";
