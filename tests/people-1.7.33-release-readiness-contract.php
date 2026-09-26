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
if ($version !== '1.7.33') {
    $fail[] = "VERSION must be 1.7.33, got {$version}";
}

$component = $read('component/xdecaropeople.xml');
$package = $read('package/pkg_people.xml');
$assets = $read('component/media/joomla.asset.json');
$update = $read('component/admin/sql/updates/mysql/1.7.33.sql');
$template = $read('component/admin/tmpl/person/edit.php');
$backup = $read('component/admin/src/Service/BackupService.php');
$release = $read('.github/workflows/release.yml');

foreach (['component/xdecaropeople.xml' => $component, 'package/pkg_people.xml' => $package] as $path => $xml) {
    $has('<version>1.7.33</version>', $xml, "{$path} must declare 1.7.33");
    $has('<targetplatform name="joomla" version="6.1.3"/>', $xml, "{$path} must remain Joomla 6.1.3 only");
}

$has('"version": "1.7.33"', $assets, 'Web asset manifest must use 1.7.33');
$has('com_xdecaropeople.person-accordion', $assets, 'Person accordion asset must be packaged');
$has("private const SCHEMA_VERSION = '1.7.29';", $backup, 'Person accordion/timezone release must not change backup payload schema');
$has("getParam('timezone'", $backup, 'Readable backup names must prefer the Joomla user timezone');
$has('id="personAccordion"', $template, 'Person edit accordion implementation missing');
$has('People 1.7.33 person accordion and timezone marker', $update, '1.7.33 SQL marker missing');
$has('php tests/people-1.7.33-person-accordion-timezone-contract.php', $release, 'Release workflow must run 1.7.33 feature contract');
$has('php tests/people-1.7.33-release-readiness-contract.php', $release, 'Release workflow must run 1.7.33 readiness contract');
$has('admin/sql/updates/mysql/1.7.33.sql', $release, 'Release package verification must require 1.7.33 migration marker');
$has('person-accordion.css', $release, 'Release package verification must require the person accordion stylesheet');

foreach ([
    'component/media/css/person-accordion.css',
    'tests/people-1.7.33-person-accordion-timezone-contract.php',
    '.github/workflows/people-1.7.33-person-accordion-timezone.yml',
] as $path) {
    if (!is_file($root . '/' . $path)) {
        $fail[] = "Required 1.7.33 file missing: {$path}";
    }
}

if ($fail) {
    fwrite(STDERR, "People 1.7.33 release readiness contract FAILED\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

echo "People 1.7.33 release readiness contract PASS\n";
