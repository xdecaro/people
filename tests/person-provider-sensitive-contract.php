<?php

$root = dirname(__DIR__);
$provider = (string) file_get_contents($root . '/component/admin/src/Service/PersonProviderService.php');

$checks = [
    ['getTableColumns', 'Sensitive provider must inspect the installed People schema before selecting optional fields.'],
    ['birth_date', 'Sensitive provider must retain birth_date support.'],
    ['nationality_code', 'Sensitive provider must retain nationality_code support.'],
    ['nationality_codes', 'Sensitive provider must retain nationality_codes support.'],
    ['createDocumentReference', 'Sensitive provider may expose an optional profile document reference.'],
    ['catch (', 'Optional profile document reference failures must not abort the entire sensitive profile.'],
];

foreach ($checks as [$needle, $message]) {
    if (!str_contains($provider, $needle)) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

if (!preg_match('/private function columns\(bool \$sensitive\): array.*?getTableColumns/s', $provider)) {
    fwrite(STDERR, "columns() must filter its select list against the installed table schema.\n");
    exit(1);
}

if (!preg_match('/profile_document_uuid.*?try\s*\{.*?createDocumentReference.*?catch\s*\(/s', $provider)) {
    fwrite(STDERR, "Profile document reference generation must be best-effort and non-blocking.\n");
    exit(1);
}

echo "People sensitive provider contract OK\n";
