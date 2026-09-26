<?php

declare(strict_types=1);

define('_JEXEC', 1);

$root = dirname(__DIR__);
$failures = [];

$path = $root . '/component/admin/src/Service/DatabaseSchemaDefinition.php';
if (!is_file($path)) {
    fwrite(STDERR, "People 1.7.29 canonical schema contract FAILED\n- Missing DatabaseSchemaDefinition.php\n");
    exit(1);
}

require_once $path;

use xdecaro\Component\People\Administrator\Service\DatabaseSchemaDefinition;

$definition = new DatabaseSchemaDefinition();
$tables = $definition->tables();
$expectedFunctional = [
    '#__xdecaropeople_people',
    '#__xdecaropeople_history',
    '#__xdecaropeople_duplicate_ignores',
    '#__xdecaropeople_merges',
];
$expectedMaintenance = [
    '#__xdecaropeople_backups',
    '#__xdecaropeople_maintenance_log',
];
$expectedAll = array_merge($expectedFunctional, $expectedMaintenance);

if (array_keys($tables) !== $expectedAll) {
    $failures[] = 'Canonical table list/order must contain exactly the six intended People tables.';
}
if ($definition->functionalTables() !== $expectedFunctional) {
    $failures[] = 'Functional table classification is incorrect.';
}
if ($definition->maintenanceTables() !== $expectedMaintenance) {
    $failures[] = 'Maintenance table classification is incorrect.';
}

foreach ($expectedAll as $table) {
    $spec = $definition->table($table);
    if (($spec['role'] ?? '') !== (in_array($table, $expectedFunctional, true) ? 'functional' : 'maintenance')) {
        $failures[] = "Wrong role for {$table}.";
    }
    $sql = $definition->createTableSql($table);
    if (!str_contains($sql, 'CREATE TABLE') || !str_contains($sql, $table)) {
        $failures[] = "Missing deterministic CREATE TABLE SQL for {$table}.";
    }
    foreach (['#__xdecaroorganizations_', '#__xdecaromembership_', '#__xdecarocompetitions_', '#__xdecarophotos_', '#__xdecarodocuments_', '#__xdecaronotifications_'] as $foreignPrefix) {
        if (str_contains($sql, $foreignPrefix)) {
            $failures[] = "Canonical schema for {$table} references foreign prefix {$foreignPrefix}.";
        }
    }
}

$threw = false;
try {
    $definition->table('#__xdecaropeople_not_real');
} catch (RuntimeException) {
    $threw = true;
}
if (!$threw) {
    $failures[] = 'Unknown table names must be rejected.';
}

$installSql = (string) file_get_contents($root . '/component/admin/sql/install.mysql.utf8mb4.sql');
$extractIdentity = static function (string $sql, string $table): array {
    $pattern = '/CREATE TABLE IF NOT EXISTS `?' . preg_quote($table, '/') . '`?\s*\((.*?)\)\s*ENGINE=/si';
    if (!preg_match($pattern, $sql, $m)) {
        return [];
    }
    preg_match_all('/^\s*`([^`]+)`\s+/m', $m[1], $columns);
    preg_match_all('/(?:UNIQUE\s+KEY|KEY)\s+`([^`]+)`/i', $m[1], $indexes);
    return [
        'columns' => array_values(array_unique($columns[1] ?? [])),
        'indexes' => array_values(array_unique($indexes[1] ?? [])),
    ];
};

foreach ($expectedAll as $table) {
    $canonical = $extractIdentity($definition->createTableSql($table), $table);
    $installer = $extractIdentity($installSql, $table);
    if ($canonical !== $installer) {
        $failures[] = "Installer SQL drift detected for {$table}.";
    }
}

if ($failures) {
    fwrite(STDERR, "People 1.7.29 canonical schema contract FAILED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "People 1.7.29 canonical schema contract PASS\n");
