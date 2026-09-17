<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$version = trim((string) file_get_contents($root . '/VERSION'));
$expect(version_compare($version, '1.2.16', '>='), "Expected People VERSION 1.2.16 or newer, got {$version}.");

$component = simplexml_load_file($root . '/component/xdecaropeople.xml');
$packagePath = is_file($root . '/package/pkg_people.xml')
    ? $root . '/package/pkg_people.xml'
    : $root . '/package/pkg_xdecaropeople.xml';
$feedPath = is_file($root . '/updates/pkg_people.xml')
    ? $root . '/updates/pkg_people.xml'
    : $root . '/updates/pkg_xdecaropeople.xml';
$package = simplexml_load_file($packagePath);
$feed = simplexml_load_file($feedPath);
$expect($component !== false && $package !== false && $feed !== false, 'People XML metadata must parse.');

if ($component !== false) {
    $expect((string) $component->version === $version, 'Component manifest must match VERSION.');
    $expect((string) $component->targetplatform['version'] === '6.1.3', 'Component manifest must target Joomla 6.1.3 exactly.');
}

if ($package !== false) {
    $expect((string) $package->version === $version, 'Package manifest must match VERSION.');
    $expect((string) $package->targetplatform['version'] === '6.1.3', 'Package manifest must target Joomla 6.1.3 exactly.');
}

if ($feed !== false) {
    $update = $feed->update;
    $expect((string) $update->targetplatform['version'] === '6\\.1\\.3$', 'Update feed must target Joomla 6.1.3 exactly.');
    $expect((string) $update->php_minimum === '8.3.0', 'Update feed must require PHP 8.3.0+.');
    $feedVersion = (string) $update->version;
    $expect(version_compare($feedVersion, $version, '<='), 'Published update feed must not be newer than source VERSION.');
}

$assets = json_decode(file_get_contents($root . '/component/media/joomla.asset.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
$expect(($assets['version'] ?? '') === $version, 'Web Asset root version must match VERSION.');
foreach ($assets['assets'] ?? [] as $asset) {
    $expect(($asset['version'] ?? '') === $version, 'Every People Web Asset entry must match VERSION.');
}

// People 1.2.16 was specifically a metadata/UI-only release. Keep that
// historical guarantee scoped to the 1.2.16 marker instead of forbidding
// legitimate schema-version markers in all later patch releases.
$expect(!is_file($root . '/component/admin/sql/updates/mysql/1.2.16.sql'), 'People 1.2.16 must remain metadata/UI-only with no schema migration.');

$readme = file_get_contents($root . '/README.md') ?: '';
$agents = file_get_contents($root . '/AGENTS.md') ?: '';
$expect(str_contains($readme, 'Joomla: `6.1.3` only'), 'README must declare Joomla 6.1.3 only.');
$expect(str_contains($agents, 'Joomla 6.1.3 only'), 'AGENTS.md must declare Joomla 6.1.3 only.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "People Joomla 6.1.3 release compatibility contract OK\n";
