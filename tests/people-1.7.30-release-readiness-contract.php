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
if ($version !== '1.7.30') {
    $fail[] = "VERSION must be 1.7.30, got {$version}";
}

$component = $read('component/xdecaropeople.xml');
$package = $read('package/pkg_people.xml');
$assets = $read('component/media/joomla.asset.json');
$update = $read('component/admin/sql/updates/mysql/1.7.30.sql');
$backup = $read('component/admin/src/Service/BackupService.php');
$view = $read('component/admin/src/View/Information/HtmlView.php');
$js = $read('component/media/js/database-maintenance.js');

foreach (['component/xdecaropeople.xml' => $component, 'package/pkg_people.xml' => $package] as $path => $xml) {
    $has('<version>1.7.30</version>', $xml, "{$path} must declare 1.7.30");
    $has('<targetplatform name="joomla" version="6.1.3"/>', $xml, "{$path} must remain Joomla 6.1.3 only");
}
$has('"version": "1.7.30"', $assets, 'Web asset manifest must use 1.7.30');
$has("private const SCHEMA_VERSION = '1.7.29';", $backup, 'UI-only release must not change backup schema version');
$has('People 1.7.30 schema differences UI marker', $update, '1.7.30 SQL marker missing');
$has("addScriptOptions('com_xdecaropeople.schema-differences'", $view, 'View must expose schema differences');
$has('Differenze rilevate', $js, 'Maintenance asset must render schema differences');

foreach ([
    'tests/people-1.7.30-schema-differences-ui-contract.php',
    '.github/workflows/people-1.7.30-schema-differences-ui.yml',
] as $path) {
    if (!is_file($root . '/' . $path)) {
        $fail[] = "Required 1.7.30 file missing: {$path}";
    }
}

if ($fail) {
    fwrite(STDERR, "People 1.7.30 release readiness contract FAILED\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

echo "People 1.7.30 release readiness contract PASS\n";
