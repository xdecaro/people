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
        $fail('Missing People 1.7.20 bulk duplicate contract in ' . $path . ': ' . $needle);
    }
};

if ($version !== '1.7.20') {
    $fail('People 1.7.20 version expected.');
}

$service = $root . '/component/admin/src/Service/DuplicateService.php';
$controller = $root . '/component/admin/src/Controller/DuplicateController.php';
$template = $root . '/component/admin/tmpl/duplicates/default.php';
$view = $root . '/component/admin/src/View/Duplicates/HtmlView.php';
$css = $root . '/component/media/css/admin.css';
$js = $root . '/component/media/js/duplicates.js';

foreach ([
    $root . '/component/admin/sql/updates/mysql/1.7.20.sql',
    $service,
    $controller,
    $template,
    $view,
    $css,
    $js,
] as $path) {
    if (!is_file($path)) {
        $fail('Missing People 1.7.20 file: ' . $path);
    }
}

foreach ([
    'function ignoreGroupsBySignatures(',
    '$this->find(500)',
    'transactionStart()',
    'transactionCommit()',
    'transactionRollback()',
] as $needle) {
    $contains($service, $needle);
}

foreach ([
    'function dismissBatch()',
    'selected_signatures',
    'ignoreGroupsBySignatures',
    'COM_XDECAROPEOPLE_DUPLICATE_BATCH_DISMISSED',
] as $needle) {
    $contains($controller, $needle);
}

foreach ([
    'data-duplicate-bulk-form',
    'data-duplicate-select-all',
    'data-duplicate-selected-count',
    'data-duplicate-selected-signatures',
    'data-duplicate-bulk-dismiss',
    'data-duplicate-select',
    'task=duplicate.dismissBatch',
] as $needle) {
    $contains($template, $needle);
}

foreach ([
    "useScript('com_xdecaropeople.duplicates')",
] as $needle) {
    $contains($view, $needle);
}

foreach ([
    '.xdecaro-duplicate-bulkbar',
    '.xdecaro-duplicate-select-row',
    'gap: .55rem;',
    'padding: .6rem .75rem;',
] as $needle) {
    $contains($css, $needle);
}

foreach ([
    '[data-duplicate-bulk-form]',
    '[data-duplicate-select-all]',
    '[data-duplicate-select]',
    "signatures.join(',')",
    'window.confirm',
] as $needle) {
    $contains($js, $needle);
}

foreach ([
    'COM_XDECAROPEOPLE_DUPLICATE_SELECT_ALL=',
    'COM_XDECAROPEOPLE_DUPLICATE_SELECTED_COUNT=',
    'COM_XDECAROPEOPLE_DUPLICATE_BATCH_NOT_DUPLICATE=',
    'COM_XDECAROPEOPLE_DUPLICATE_BATCH_CONFIRM=',
    'COM_XDECAROPEOPLE_DUPLICATE_BATCH_DISMISSED=',
] as $key) {
    $contains($root . '/component/admin/language/it-IT/com_xdecaropeople.ini', $key);
    $contains($root . '/component/admin/language/en-GB/com_xdecaropeople.ini', $key);
}

echo "People 1.7.20 bulk duplicate dismissal contract OK\n";
