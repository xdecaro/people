<?php

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert(version_compare($version, '1.7.3', '>='), 'People 1.7.3 or newer expected.');

$js = (string) file_get_contents($root . '/component/media/js/import.js');
$template = (string) file_get_contents($root . '/component/admin/tmpl/import/default.php');
$view = (string) file_get_contents($root . '/component/admin/src/View/Import/HtmlView.php');
$it = (string) file_get_contents($root . '/component/admin/language/it-IT/com_xdecaropeople.ini');
$en = (string) file_get_contents($root . '/component/admin/language/en-GB/com_xdecaropeople.ini');

foreach ([
    'normalizeIdentityName',
    'duplicateRows: []',
    'duplicateGroups: 0',
    'duplicateExtraRows: 0',
    'duplicate_status',
    'renderDuplicateDetails',
    'conflicts.push(item)',
    'valid: state.prepared.length',
    'duplicates: state.duplicateGroups',
    'renderDuplicateDetails();',
] as $needle) {
    $assert(str_contains($js, $needle), 'Import duplicate-row review JavaScript missing: ' . $needle);
}

foreach ([
    'xdecaro-people-import-duplicate-panel',
    'xdecaro-people-import-duplicate-title',
    'xdecaro-people-import-duplicate-body',
    'COM_XDECAROPEOPLE_IMPORT_DUPLICATE_GROUP',
    'COM_XDECAROPEOPLE_IMPORT_DUPLICATE_OUTCOME',
] as $needle) {
    $assert(str_contains($template, $needle), 'Import duplicate-row template missing: ' . $needle);
}

foreach ([
    'duplicateRowsTitle',
    'duplicateGroupsTitle',
    'duplicatePrimary',
    'duplicateConsolidated',
    'duplicateConflict',
    'COM_XDECAROPEOPLE_FIELD_BIRTH_REGION',
    'COM_XDECAROPEOPLE_FIELD_RESIDENCE_REGION',
] as $needle) {
    $assert(str_contains($view, $needle), 'Import duplicate-row view option missing: ' . $needle);
}

foreach ([$it, $en] as $language) {
    foreach ([
        'COM_XDECAROPEOPLE_IMPORT_DUPLICATE_DETAILS_TITLE',
        'COM_XDECAROPEOPLE_IMPORT_DUPLICATE_DETAILS_HELP',
        'COM_XDECAROPEOPLE_IMPORT_DUPLICATE_ROWS_JS',
        'COM_XDECAROPEOPLE_IMPORT_DUPLICATE_GROUPS_JS',
        'COM_XDECAROPEOPLE_IMPORT_DUPLICATE_PRIMARY',
        'COM_XDECAROPEOPLE_IMPORT_DUPLICATE_CONSOLIDATED',
        'COM_XDECAROPEOPLE_IMPORT_DUPLICATE_CONFLICT',
    ] as $key) {
        $assert(substr_count($language, $key . '=') === 1, 'Duplicate or missing import translation key: ' . $key);
    }
}

echo "People 1.7.3+ CSV duplicate-row review compatibility contract OK\n";
