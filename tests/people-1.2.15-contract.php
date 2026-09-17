<?php

declare(strict_types=1);

defined('_JEXEC') || define('_JEXEC', 1);

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));
if (version_compare($version, '1.2.15', '<')) {
    fwrite(STDERR, "People batch provider contract requires VERSION 1.2.15 or later, got {$version}.\n");
    exit(1);
}

$packagePath = is_file($root . '/package/pkg_people.xml')
    ? $root . '/package/pkg_people.xml'
    : $root . '/package/pkg_xdecaropeople.xml';
$component = simplexml_load_file($root . '/component/xdecaropeople.xml');
$package = simplexml_load_file($packagePath);
if ($component === false || $package === false) {
    fwrite(STDERR, "People manifests are invalid XML.\n");
    exit(1);
}
if ((string) $component->version !== $version || (string) $package->version !== $version) {
    fwrite(STDERR, "People component/package manifests must match VERSION.\n");
    exit(1);
}

$assets = json_decode(file_get_contents($root . '/component/media/joomla.asset.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
if (($assets['version'] ?? '') !== $version) {
    fwrite(STDERR, "People Web Asset root version must match VERSION.\n");
    exit(1);
}
foreach ($assets['assets'] ?? [] as $asset) {
    if (($asset['version'] ?? '') !== $version) {
        fwrite(STDERR, "Every People Web Asset entry must match VERSION.\n");
        exit(1);
    }
}

$provider = file_get_contents($root . '/component/admin/src/Service/PersonProviderService.php') ?: '';
if (!str_contains($provider, 'function getPeopleByUuids(')) {
    fwrite(STDERR, "People 1.2.15+ must preserve getPeopleByUuids().\n");
    exit(1);
}

if (is_file($root . '/component/admin/sql/updates/mysql/1.2.15.sql')) {
    fwrite(STDERR, "People 1.2.15 batch provider must remain schema-neutral.\n");
    exit(1);
}

echo "People 1.2.15+ batch provider compatibility contract OK\n";
