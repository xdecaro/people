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

$controller = $read('component/admin/src/Controller/MaintenanceController.php');
$model = $read('component/admin/src/Model/InformationModel.php');
$view = $read('component/admin/src/View/Information/HtmlView.php');
$template = $read('component/admin/tmpl/information/default.php');
$assets = $read('component/media/joomla.asset.json');
$js = $read('component/media/js/database-maintenance.js');
$access = $read('component/admin/access.xml');

foreach (['checkDatabase','repairDatabase','emptyDatabase','recreateDatabase'] as $method) {
    $has('function ' . $method . '(', $controller, "MaintenanceController missing {$method}");
}
$has("requirePermission('people.database_repair')", $controller, 'Repair must enforce database repair ACL');
$has("requirePermission('people.database_destructive')", $controller, 'Destructive actions must enforce destructive ACL');
$has("getString('database_confirmation')", $controller, 'Controller must read typed database confirmation');
$has('getDatabaseSchemaStatus', $model, 'InformationModel must expose database schema status');
foreach (['databaseSchemaStatus','canDatabaseRepair','canDatabaseDestructive'] as $property) $has('$' . $property, $view, "Information view missing {$property}");
$has("useScript('com_xdecaropeople.database-maintenance')", $view, 'Information view must load maintenance JS');
foreach (['Controlla database','Ripara database','Svuota dati People','Ricrea database People','SVUOTA','RICREA'] as $label) $has($label, $template, "Information template missing {$label}");
$has('Organizations', $template, 'Template must warn about external references');
$has('Membership', $template, 'Template must warn about external references');
$has('Competitions', $template, 'Template must warn about external references');
$has('com_xdecaropeople.database-maintenance', $assets, 'Database maintenance JS asset must be registered');
$has('data-database-confirm', $js, 'Maintenance JS must bind typed confirmations');
$has('people.database_repair', $access, 'ACL repair action missing');
$has('people.database_destructive', $access, 'ACL destructive action missing');

if ($fail) {
    fwrite(STDERR, "People 1.7.29 database maintenance UI contract FAILED\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

echo "People 1.7.29 database maintenance UI contract PASS\n";
