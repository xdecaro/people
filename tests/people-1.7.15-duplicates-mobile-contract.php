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
        $fail('Missing People 1.7.15 mobile duplicate contract in ' . $path . ': ' . $needle);
    }
};

if ($version !== '1.7.15') {
    $fail('People 1.7.15 version expected.');
}

$template = $root . '/component/admin/tmpl/duplicates/default.php';
$view = $root . '/component/admin/src/View/Duplicates/HtmlView.php';
$css = $root . '/component/media/css/admin.css';
$js = $root . '/component/media/js/duplicates.js';

foreach ([
    $template,
    $view,
    $css,
    $js,
    $root . '/component/admin/sql/updates/mysql/1.7.15.sql',
] as $path) {
    if (!is_file($path)) {
        $fail('Missing People 1.7.15 file: ' . $path);
    }
}

foreach ([
    'data-duplicate-group=',
    'xdecaro-duplicate-mobile-actions',
    'xdecaro-duplicate-mobile-tabs',
    'data-duplicate-tab=',
    'data-duplicate-panel=',
    'COM_XDECAROPEOPLE_DUPLICATE_MOBILE_RECORDS',
] as $needle) {
    $contains($template, $needle);
}

foreach ([
    "useScript('com_xdecaropeople.duplicates')",
] as $needle) {
    $contains($view, $needle);
}

foreach ([
    '.xdecaro-duplicate-mobile-tabs',
    '.xdecaro-duplicate-mobile-tab.is-active',
    '.xdecaro-duplicate-person[hidden]',
    '.xdecaro-duplicate-field--meta',
    '.xdecaro-duplicate-actions',
] as $needle) {
    $contains($css, $needle);
}

foreach ([
    "window.matchMedia('(max-width: 767.98px)')",
    'setActiveRecord',
    "event.key === 'ArrowRight'",
    "event.key === 'ArrowLeft'",
    "panel.hidden = !active",
] as $needle) {
    $contains($js, $needle);
}

$assets = json_decode((string) file_get_contents($root . '/component/media/joomla.asset.json'), true, 512, JSON_THROW_ON_ERROR);
$found = false;
foreach (($assets['assets'] ?? []) as $asset) {
    if (($asset['name'] ?? '') === 'com_xdecaropeople.duplicates') {
        $found = (($asset['uri'] ?? '') === 'com_xdecaropeople/duplicates.js');
    }
}
if (!$found) {
    $fail('People 1.7.15 must register the duplicates script asset.');
}

foreach ([
    'COM_XDECAROPEOPLE_DUPLICATE_MOBILE_RECORDS=',
] as $key) {
    $contains($root . '/component/admin/language/it-IT/com_xdecaropeople.ini', $key);
    $contains($root . '/component/admin/language/en-GB/com_xdecaropeople.ini', $key);
}

echo "People 1.7.15 mobile duplicate review contract OK\n";
