<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$fail = static function (string $message): never { fwrite(STDERR, $message . PHP_EOL); exit(1); };
$read = static function (string $path) use ($fail): string {
    $value = file_get_contents($path);
    if ($value === false) $fail('Unable to read: ' . $path);
    return $value;
};

$servicePath = $root . '/component/admin/src/Service/RestoreService.php';
if (!is_file($servicePath)) $fail('Missing RestoreService.php');
$service = $read($servicePath);
$provider = $read($root . '/component/admin/services/provider.php');
$component = $read($root . '/component/admin/src/Extension/PeopleComponent.php');

foreach ([
    'function preview(',
    'manifest.json',
    'data.json',
    'SHA256SUMS.txt',
    'hash_equals',
    'backup_max_upload_mb',
    'restore_preview',
] as $needle) {
    if (!str_contains($service, $needle)) $fail('Restore preview contract missing: ' . $needle);
}
foreach (['RestoreService::class'] as $needle) {
    if (!str_contains($provider, $needle)) $fail('RestoreService is not registered in DI.');
}
if (!str_contains($component, 'getRestoreService')) $fail('PeopleComponent missing getRestoreService().');

foreach (['extractTo(', 'unzip ', 'shell_exec(', 'exec('] as $dangerous) {
    if (str_contains($service, $dangerous)) $fail('Restore preview must inspect ZIP in memory, not extract/execute: ' . $dangerous);
}

echo "People 1.7.28 restore contract OK\n";
