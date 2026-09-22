<?php

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($version === '1.7.6', 'People 1.7.6 version expected.');

$view = (string) file_get_contents($root . '/component/admin/src/View/Duplicates/HtmlView.php');
$template = (string) file_get_contents($root . '/component/admin/tmpl/duplicates/default.php');
$css = (string) file_get_contents($root . '/component/media/css/admin.css');
$it = (string) file_get_contents($root . '/component/admin/language/it-IT/com_xdecaropeople.ini');
$en = (string) file_get_contents($root . '/component/admin/language/en-GB/com_xdecaropeople.ini');

foreach ([
    "public array \$summary",
    "'total' => 0",
    "'conflict' => 0",
    "'strong' => 0",
    "'possible' => 0",
    "'records' => 0",
    "public string \$filter = 'all'",
    "getCmd('duplicate_filter', 'all')",
    "['all', 'conflict', 'strong', 'possible']",
    "count(\$recordIds)",
] as $needle) {
    $assert(str_contains($view, $needle), 'Duplicate dashboard view contract missing: ' . $needle);
}

foreach ([
    'xdecaro-duplicate-dashboard',
    'xdecaro-duplicate-kpi',
    'duplicate_filter=',
    'COM_XDECAROPEOPLE_DUPLICATE_DASHBOARD_TOTAL',
    'COM_XDECAROPEOPLE_DUPLICATE_DASHBOARD_CONFLICT',
    'COM_XDECAROPEOPLE_DUPLICATE_DASHBOARD_STRONG',
    'COM_XDECAROPEOPLE_DUPLICATE_DASHBOARD_POSSIBLE',
    'COM_XDECAROPEOPLE_DUPLICATE_DASHBOARD_RECORDS',
    'COM_XDECAROPEOPLE_DUPLICATE_FILTER_EMPTY',
] as $needle) {
    $assert(str_contains($template, $needle), 'Duplicate dashboard template contract missing: ' . $needle);
}

foreach ([
    '.xdecaro-duplicate-dashboard',
    '.xdecaro-duplicate-kpi',
    '.xdecaro-duplicate-kpi.is-active',
    '@media (max-width: 991.98px)',
    '@media (max-width: 575.98px)',
] as $needle) {
    $assert(str_contains($css, $needle), 'Duplicate dashboard responsive styling missing: ' . $needle);
}

foreach ([$it, $en] as $language) {
    foreach ([
        'COM_XDECAROPEOPLE_DUPLICATE_DASHBOARD_TITLE',
        'COM_XDECAROPEOPLE_DUPLICATE_DASHBOARD_TOTAL',
        'COM_XDECAROPEOPLE_DUPLICATE_DASHBOARD_CONFLICT',
        'COM_XDECAROPEOPLE_DUPLICATE_DASHBOARD_STRONG',
        'COM_XDECAROPEOPLE_DUPLICATE_DASHBOARD_POSSIBLE',
        'COM_XDECAROPEOPLE_DUPLICATE_DASHBOARD_RECORDS',
        'COM_XDECAROPEOPLE_DUPLICATE_FILTER_EMPTY',
        'COM_XDECAROPEOPLE_DUPLICATE_FILTER_SHOW_ALL',
    ] as $key) {
        $assert(substr_count($language, $key . '=') === 1, 'Duplicate or missing dashboard translation key: ' . $key);
    }
}

echo "People 1.7.6 duplicate dashboard contract OK\n";
