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
$restore = $read('component/admin/src/Service/RestoreService.php');
$databaseMaintenance = $read('component/admin/src/Service/DatabaseMaintenanceService.php');
$tmpl = $read('component/admin/tmpl/information/default.php');
$js = $read('component/media/js/database-maintenance.js');

$has('buildReadableFilename', $backup, 'BackupService must expose readable backup filename builder');
$has('people-backup-', $backup, 'Readable backup filename prefix missing');
$has("'pre-restore'", $restore, 'Restore safety backup reason must remain explicit');
$has("'before_empty_database'", $databaseMaintenance, 'Empty safety backup reason must remain explicit');
$has("'before_recreate_database'", $databaseMaintenance, 'Recreate safety backup reason must remain explicit');
$has("safety_backup_filename", $restore . $databaseMaintenance, 'Safety backup results must expose readable filenames');
$has("safety_backup_filename", $controller, 'User-facing maintenance messages must use readable safety backup filenames');
$has('data-maintenance-activity', $tmpl, 'Maintenance activity needs a compact toggle target');
$has('data-maintenance-extra', $tmpl, 'Maintenance activity must mark rows after the first five as extra');
$has('Mostra tutte', $tmpl . $js, 'Maintenance activity must offer Mostra tutte');
$has('Mostra meno', $tmpl . $js, 'Maintenance activity must offer Mostra meno');
$has('slice(0, 5)', $js, 'Maintenance activity must keep the first five visible by default');

if ($fail) {
    fwrite(STDERR, "People 1.7.32 backup labels/activity contract FAILED\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

echo "People 1.7.32 backup labels/activity contract PASS\n";
