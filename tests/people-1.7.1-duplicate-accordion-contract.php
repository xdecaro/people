<?php

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert(version_compare($version, '1.7.1', '>='), 'People 1.7.1 or newer expected.');

$service = (string) file_get_contents($root . '/component/admin/src/Service/DuplicateService.php');
$template = (string) file_get_contents($root . '/component/admin/tmpl/duplicates/default.php');
$css = (string) file_get_contents($root . '/component/media/css/admin.css');
$it = (string) file_get_contents($root . '/component/admin/language/it-IT/com_xdecaropeople.ini');
$en = (string) file_get_contents($root . '/component/admin/language/en-GB/com_xdecaropeople.ini');

foreach ([
    'applyGroupRisk',
    "'strength'] = 'conflict'",
    "'merge_allowed'] = false",
    "'conflict_fields'] = ['tax_identifier']",
    'assertMergeIdentityCompatible',
    'COM_XDECAROPEOPLE_DUPLICATE_ERROR_TAX_CONFLICT',
    'COM_XDECAROPEOPLE_DUPLICATE_ERROR_MERGE_NOT_ALLOWED',
] as $needle) {
    $assert(str_contains($service, $needle), 'Conflict-safe duplicate service missing: ' . $needle);
}

foreach ([
    '<details class="card xdecaro-duplicate-group" name="xdecaro-duplicate-review">',
    'xdecaro-duplicate-accordion-summary',
    'xdecaro-duplicate-summary-counts',
    'xdecaro-duplicate-difference-summary',
    'xdecaro-duplicate-field--',
    "merge_allowed",
    'COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_CONFLICT',
    'COM_XDECAROPEOPLE_DUPLICATE_FIELD_SAME',
    'COM_XDECAROPEOPLE_DUPLICATE_FIELD_DIFFERENT',
    'COM_XDECAROPEOPLE_DUPLICATE_FIELD_MISSING',
] as $needle) {
    $assert(str_contains($template, $needle), 'Duplicate accordion comparison missing: ' . $needle);
}

foreach ([
    '.xdecaro-duplicate-accordion-summary',
    '.xdecaro-duplicate-field--same',
    '.xdecaro-duplicate-field--different',
    '.xdecaro-duplicate-field--missing',
    '.xdecaro-duplicate-group[open] .xdecaro-duplicate-chevron',
] as $needle) {
    $assert(str_contains($css, $needle), 'Duplicate accordion styling missing: ' . $needle);
}

foreach ([$it, $en] as $language) {
    foreach ([
        'COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_CONFLICT',
        'COM_XDECAROPEOPLE_DUPLICATE_COUNT_SAME',
        'COM_XDECAROPEOPLE_DUPLICATE_COUNT_DIFFERENT',
        'COM_XDECAROPEOPLE_DUPLICATE_COUNT_MISSING',
        'COM_XDECAROPEOPLE_DUPLICATE_CONFLICT_BLOCKED',
        'COM_XDECAROPEOPLE_DUPLICATE_ERROR_TAX_CONFLICT',
    ] as $key) {
        $assert(substr_count($language, $key . '=') === 1, 'Duplicate or missing translation key: ' . $key);
    }

    $assert(substr_count($language, 'COM_XDECAROPEOPLE_DUPLICATE_STRONG_HELP=') === 1, 'Strong help translation must be unique.');
    $assert(substr_count($language, 'COM_XDECAROPEOPLE_DUPLICATE_POSSIBLE_HELP=') === 1, 'Possible help translation must be unique.');
}

echo "People 1.7.1+ duplicate accordion and conflict safety compatibility contract OK\n";
