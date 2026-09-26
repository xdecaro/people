<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$read = static function (string $path) use ($fail): string {
    $content = file_get_contents($path);
    if ($content === false) {
        $fail('Unable to read: ' . $path);
    }
    return $content;
};

$version = trim($read($root . '/VERSION'));
$componentXml = $read($root . '/component/xdecaropeople.xml');
$packageXml = $read($root . '/package/pkg_people.xml');
$assetsJson = $read($root . '/component/media/joomla.asset.json');
$installSql = $read($root . '/component/admin/sql/install.mysql.utf8mb4.sql');
$updatePath = $root . '/component/admin/sql/updates/mysql/1.7.28.sql';
$accessXml = $read($root . '/component/admin/access.xml');
$configXml = $read($root . '/component/admin/config.xml');

if ($version !== '1.7.28') {
    $fail('VERSION must be 1.7.28, got ' . $version);
}

foreach ([$componentXml, $packageXml] as $xml) {
    if (!str_contains($xml, '<version>1.7.28</version>')) {
        $fail('Manifest version 1.7.28 is missing.');
    }
    if (!str_contains($xml, 'version="6.1.3"')) {
        $fail('Joomla target 6.1.3 must remain unchanged.');
    }
}

$assets = json_decode($assetsJson, true, 512, JSON_THROW_ON_ERROR);
if (($assets['version'] ?? null) !== '1.7.28') {
    $fail('Web asset registry version must be 1.7.28.');
}
foreach (($assets['assets'] ?? []) as $asset) {
    if (($asset['version'] ?? null) !== '1.7.28') {
        $fail('Every People asset version must be 1.7.28.');
    }
}

if (!is_file($updatePath)) {
    $fail('Missing SQL update 1.7.28.sql.');
}
$updateSql = $read($updatePath);

foreach (['#__xdecaropeople_backups', '#__xdecaropeople_maintenance_log'] as $table) {
    if (!str_contains($installSql, $table) || !str_contains($updateSql, $table)) {
        $fail('Missing maintenance table in install/update SQL: ' . $table);
    }
}

foreach (['#__xdecaropeople_people', '#__xdecaropeople_history', '#__xdecaropeople_duplicate_ignores', '#__xdecaropeople_merges'] as $table) {
    if (!str_contains($installSql, $table)) {
        $fail('Existing functional People table missing from install SQL: ' . $table);
    }
}

foreach (['people.backup', 'people.restore'] as $action) {
    if (!str_contains($accessXml, $action)) {
        $fail('Missing ACL action: ' . $action);
    }
}

foreach (['backup_storage_path', 'backup_max_upload_mb'] as $param) {
    if (!str_contains($configXml, $param)) {
        $fail('Missing maintenance configuration field: ' . $param);
    }
}

foreach ([
    '#__xdecaroorganizations_',
    '#__decaromembership_',
    '#__xdecarocompetitions_',
    '#__xdecarophotos_',
    '#__xdecarodocuments_',
    '#__xdecaronotifications_',
] as $foreignTablePrefix) {
    if (str_contains($updateSql, $foreignTablePrefix)) {
        $fail('Maintenance SQL must not reference foreign component tables: ' . $foreignTablePrefix);
    }
}

$serviceFiles = [
    'MaintenanceLogService' => $root . '/component/admin/src/Service/MaintenanceLogService.php',
    'PersonTrashService' => $root . '/component/admin/src/Service/PersonTrashService.php',
];
foreach ($serviceFiles as $name => $path) {
    if (!is_file($path)) {
        $fail('Missing maintenance service: ' . $name);
    }
}

$provider = $read($root . '/component/admin/services/provider.php');
$component = $read($root . '/component/admin/src/Extension/PeopleComponent.php');
$controller = $read($root . '/component/admin/src/Controller/PeopleController.php');

foreach ([
    'MaintenanceLogService::class',
    'PersonTrashService::class',
] as $needle) {
    if (!str_contains($provider, $needle)) {
        $fail('Service not registered in DI: ' . $needle);
    }
}
foreach ([
    'getMaintenanceLogService',
    'getPersonTrashService',
] as $needle) {
    if (!str_contains($component, $needle)) {
        $fail('PeopleComponent missing service getter: ' . $needle);
    }
}
foreach ([
    'function delete(',
    'function restoreTrash(',
    'function purge(',
    'checkToken()',
    "authorise('core.edit.state'",
    "authorise('core.delete'",
] as $needle) {
    if (!str_contains($controller, $needle)) {
        $fail('PeopleController missing trash lifecycle contract: ' . $needle);
    }
}

echo "People 1.7.28 information maintenance contract OK\n";
