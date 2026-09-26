<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$read = static function (string $path) use ($root, &$failures): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) { $failures[] = "Missing file: {$path}"; return ''; }
    return (string) file_get_contents($full);
};
$has = static function (string $needle, string $haystack, string $message) use (&$failures): void {
    if (!str_contains($haystack, $needle)) $failures[] = $message;
};
$service = $read('component/admin/src/Service/RestoreService.php');
$provider = $read('component/admin/services/provider.php');
$component = $read('component/admin/src/Extension/PeopleComponent.php');
$has('final class RestoreService', $service, 'RestoreService must exist');
foreach (['function preview(', 'function restoreFull(', 'function restorePerson('] as $method) $has($method, $service, "RestoreService must expose {$method}");
foreach (['manifest.json','data.json','SHA256SUMS.txt','hash_equals','ZipArchive'] as $token) $has($token, $service, "RestoreService missing validation token {$token}");
$has('RestoreService::class', $provider, 'DI must register RestoreService');
$has('getRestoreService', $component, 'PeopleComponent must expose RestoreService');
if ($failures) { fwrite(STDERR, "People restore contract FAILED\n- " . implode("\n- ", $failures) . "\n"); exit(1); }
echo "People restore contract PASS\n";
