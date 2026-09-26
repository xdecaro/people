<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $path) use ($root, &$failures): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        $failures[] = "Missing file: {$path}";
        return '';
    }

    return (string) file_get_contents($full);
};

$controller = $read('component/admin/src/Controller/MaintenanceController.php');
$italian = $read('component/admin/language/it-IT/com_xdecaropeople.ini');
$english = $read('component/admin/language/en-GB/com_xdecaropeople.ini');

if (substr_count($controller, 'catch (RuntimeException $e)') < 3) {
    $failures[] = 'Restore controller actions must catch expected validation/runtime failures instead of exposing the Joomla error page.';
}

if (!str_contains($controller, 'private function restoreFailureMessage(')) {
    $failures[] = 'MaintenanceController must centralize a safe restore failure message.';
}

if (!str_contains($controller, 'COM_XDECAROPEOPLE_RESTORE_INVALID_BACKUP')) {
    $failures[] = 'MaintenanceController must use the translated invalid-backup message.';
}

if (str_contains($controller, 'redirectInformation($e->getMessage()')) {
    $failures[] = 'Raw exception messages must not be displayed to administrators.';
}

foreach ([$italian, $english] as $language) {
    if (!str_contains($language, 'COM_XDECAROPEOPLE_RESTORE_INVALID_BACKUP=')) {
        $failures[] = 'Both administrator languages must define COM_XDECAROPEOPLE_RESTORE_INVALID_BACKUP.';
        break;
    }
}

if ($failures) {
    fwrite(STDERR, "People 1.7.29 restore error handling contract FAILED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "People 1.7.29 restore error handling contract PASS\n";
