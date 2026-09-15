<?php

declare(strict_types=1);

defined('_JEXEC') || define('_JEXEC', 1);

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));
if ($version !== '1.2.15') {
    fwrite(STDERR, "Expected People VERSION 1.2.15, got {$version}.\n");
    exit(1);
}

$component = simplexml_load_file($root . '/component/xdecaropeople.xml');
$package = simplexml_load_file($root . '/package/pkg_xdecaropeople.xml');
if ($component === false || $package === false) {
    fwrite(STDERR, "People manifests are invalid XML.\n");
    exit(1);
}
if ((string) $component->version !== '1.2.15' || (string) $package->version !== '1.2.15') {
    fwrite(STDERR, "People component/package manifests must be 1.2.15.\n");
    exit(1);
}

$assets = json_decode(file_get_contents($root . '/component/media/joomla.asset.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
if (($assets['version'] ?? '') !== '1.2.15') {
    fwrite(STDERR, "People Web Asset root version must be 1.2.15.\n");
    exit(1);
}
foreach ($assets['assets'] ?? [] as $asset) {
    if (($asset['version'] ?? '') !== '1.2.15') {
        fwrite(STDERR, "Every People Web Asset entry must be 1.2.15.\n");
        exit(1);
    }
}

$provider = file_get_contents($root . '/component/admin/src/Service/PersonProviderService.php') ?: '';
if (!str_contains($provider, 'function getPeopleByUuids(')) {
    fwrite(STDERR, "People 1.2.15 must expose getPeopleByUuids().\n");
    exit(1);
}

if (is_file($root . '/component/admin/sql/updates/mysql/1.2.15.sql')) {
    fwrite(STDERR, "People 1.2.15 batch provider must not introduce a schema migration.\n");
    exit(1);
}

echo "People 1.2.15 release contract OK\n";
