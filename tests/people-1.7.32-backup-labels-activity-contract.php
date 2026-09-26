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

$backup = $read('component/admin/src/Service/BackupService.php');
$controller = $read('component/admin/src/Controller/MaintenanceController.php');
$model = $read('component/admin/src/Model/InformationModel.php');
$tmpl = $read('component/admin/tmpl/information/default.php');
$js = $read('component/media/js/database-maintenance.js');

$has('buildReadableFilename', $backup, 'BackupService must expose readable backup filename builder');
$has('people-backup-', $backup, 'Readable backup filename prefix missing');
$has('pre-restore', $controller, 'Restore safety backup label must be user-readable');
$has('pre-svuota', $controller, 'Empty safety backup label must be user-readable');
$has('pre-ricrea', $controller, 'Recreate safety backup label must be user-readable');
$has('LIMIT 5', strtoupper($model), 'Maintenance activity default list must be limited to 5 rows');
$has('Mostra tutte', $tmpl . $js, 'Maintenance activity must offer Mostra tutte');
$has('Mostra meno', $tmpl . $js, 'Maintenance activity must offer Mostra meno');
$has('data-maintenance-activity', $tmpl, 'Maintenance activity needs a compact toggle target');

if ($fail) {
    fwrite(STDERR, "People 1.7.32 backup labels/activity contract FAILED\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

echo "People 1.7.32 backup labels/activity contract PASS\n";
