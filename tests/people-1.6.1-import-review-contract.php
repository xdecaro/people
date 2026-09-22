<?php

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($version === '1.6.1', 'People 1.6.1 version expected.');

$template = (string) file_get_contents($root . '/component/admin/tmpl/import/default.php');
$view = (string) file_get_contents($root . '/component/admin/src/View/Import/HtmlView.php');
$javascript = (string) file_get_contents($root . '/component/media/js/import.js');
$css = (string) file_get_contents($root . '/component/media/css/admin.css');
$italian = (string) file_get_contents($root . '/component/admin/language/it-IT/com_xdecaropeople.ini');
$english = (string) file_get_contents($root . '/component/admin/language/en-GB/com_xdecaropeople.ini');

foreach ([
    'xdecaro-people-import-invalid-panel',
    'xdecaro-people-import-invalid-title',
    'xdecaro-people-import-invalid-body',
    'COM_XDECAROPEOPLE_IMPORT_PERSON',
    'COM_XDECAROPEOPLE_IMPORT_PROBLEM',
] as $needle) {
    $assert(str_contains($template, $needle), 'Correction-detail template missing: ' . $needle);
}

foreach ([
    'invalidDetailsTitle',
    'COM_XDECAROPEOPLE_IMPORT_INVALID_DETAILS_JS',
] as $needle) {
    $assert(str_contains($view, $needle), 'Correction-detail view option missing: ' . $needle);
}

foreach ([
    'renderInvalidDetails',
    'first_name: clean(row.first_name)',
    'last_name: clean(row.last_name)',
    'tax_identifier: clean(row.tax_identifier)',
    'message: errors[0]',
    'warnings: errors.slice(1)',
    'invalidPanel.open = true',
] as $needle) {
    $assert(str_contains($javascript, $needle), 'Correction-detail JavaScript missing: ' . $needle);
}

$assert(str_contains($css, '.xdecaro-import-review'), 'Correction-detail responsive styling missing.');

foreach ([$italian, $english] as $language) {
    foreach ([
        'COM_XDECAROPEOPLE_IMPORT_INVALID_DETAILS_TITLE',
        'COM_XDECAROPEOPLE_IMPORT_INVALID_DETAILS_HELP',
        'COM_XDECAROPEOPLE_IMPORT_PERSON',
        'COM_XDECAROPEOPLE_IMPORT_PROBLEM',
    ] as $needle) {
        $assert(str_contains($language, $needle), 'Correction-detail language key missing: ' . $needle);
    }
}

echo "People 1.6.1 pre-import correction review contract OK\n";
