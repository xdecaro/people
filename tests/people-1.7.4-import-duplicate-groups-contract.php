<?php

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($version === '1.7.4', 'People 1.7.4 version expected.');

$js = (string) file_get_contents($root . '/component/media/js/import.js');
$template = (string) file_get_contents($root . '/component/admin/tmpl/import/default.php');
$view = (string) file_get_contents($root . '/component/admin/src/View/Import/HtmlView.php');
$css = (string) file_get_contents($root . '/component/media/css/admin.css');
$it = (string) file_get_contents($root . '/component/admin/language/it-IT/com_xdecaropeople.ini');
$en = (string) file_get_contents($root . '/component/admin/language/en-GB/com_xdecaropeople.ini');

foreach ([
    'const groups = new Map()',
    "details.className = 'xdecaro-import-duplicate-group'",
    "details.name = 'xdecaro-import-duplicate-review'",
    'duplicateGroupRows',
    'duplicateGroupRecords',
    'duplicateGroupConflict',
    'duplicateGroupConsolidated',
    'group.items.push(item)',
    'group.rows.join',
] as $needle) {
    $assert(str_contains($js, $needle), 'Grouped duplicate review JavaScript missing: ' . $needle);
}

$assert(str_contains($template, 'xdecaro-import-duplicate-groups'), 'Grouped duplicate review container missing.');
$assert(!str_contains($template, '<tbody id="xdecaro-people-import-duplicate-body"></tbody>'), 'Old flat duplicate table must be removed.');

foreach ([
    'duplicateGroupRows',
    'duplicateGroupRecords',
    'duplicateGroupConflict',
    'duplicateGroupConsolidated',
    'duplicateOutcome',
] as $needle) {
    $assert(str_contains($view, $needle), 'Grouped duplicate review view option missing: ' . $needle);
}

foreach ([
    '.xdecaro-import-duplicate-groups',
    '.xdecaro-import-duplicate-group-summary',
    '.xdecaro-import-duplicate-group[open] .xdecaro-import-duplicate-chevron',
] as $needle) {
    $assert(str_contains($css, $needle), 'Grouped duplicate review styling missing: ' . $needle);
}

foreach ([$it, $en] as $language) {
    foreach ([
        'COM_XDECAROPEOPLE_IMPORT_DUPLICATE_GROUP_ROWS',
        'COM_XDECAROPEOPLE_IMPORT_DUPLICATE_GROUP_RECORDS',
        'COM_XDECAROPEOPLE_IMPORT_DUPLICATE_GROUP_CONFLICT',
        'COM_XDECAROPEOPLE_IMPORT_DUPLICATE_GROUP_CONSOLIDATED',
    ] as $key) {
        $assert(substr_count($language, $key . '=') === 1, 'Duplicate or missing grouped duplicate translation key: ' . $key);
    }
}

echo "People 1.7.4 grouped CSV duplicate review contract OK\n";
