<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$contains = static function (string $path, string $needle) use ($fail): void {
    $content = file_get_contents($path);
    if ($content === false || !str_contains($content, $needle)) {
        $fail('Missing People 1.7.19 manual merge contract in ' . $path . ': ' . $needle);
    }
};

if ($version !== '1.7.19') {
    $fail('People 1.7.19 version expected.');
}

$service = $root . '/component/admin/src/Service/DuplicateService.php';
$template = $root . '/component/admin/tmpl/duplicates/default.php';

foreach ([
    "if ($type === 'name')",
    'assessManualNameMerge',
    "$bucket['manual_merge'] = $bucket['merge_allowed'];",
    "$assessment['evidence'] && $assessment['conflicts'] === []",
    "in_array('tax_identifier', $assessment['conflicts'], true)",
    'COM_XDECAROPEOPLE_DUPLICATE_ERROR_IDENTITY_CONFLICT',
    "'birth_date' =>",
    "'sex' =>",
    "'tax_identifier' =>",
    "'birth_place' =>",
    "'birth_region' =>",
] as $needle) {
    $contains($service, $needle);
}

foreach ([
    "($group['manual_merge'] ?? false) === true",
    'COM_XDECAROPEOPLE_DUPLICATE_MANUAL_MERGE_HINT',
    'COM_XDECAROPEOPLE_DUPLICATE_KEEP_AND_MERGE',
    'task=duplicate.merge',
] as $needle) {
    $contains($template, $needle);
}

foreach ([
    'COM_XDECAROPEOPLE_DUPLICATE_MANUAL_MERGE_HINT=',
    'COM_XDECAROPEOPLE_DUPLICATE_ERROR_IDENTITY_CONFLICT=',
] as $key) {
    $contains($root . '/component/admin/language/it-IT/com_xdecaropeople.ini', $key);
    $contains($root . '/component/admin/language/en-GB/com_xdecaropeople.ini', $key);
}

echo "People 1.7.19 safe manual possible-duplicate merge contract OK\n";
