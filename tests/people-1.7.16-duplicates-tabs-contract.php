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
        $fail('Missing People 1.7.16 duplicate tabs contract in ' . $path . ': ' . $needle);
    }
};

if ($version !== '1.7.16') {
    $fail('People 1.7.16 version expected.');
}

$template = $root . '/component/admin/tmpl/duplicates/default.php';
$view = $root . '/component/admin/src/View/Duplicates/HtmlView.php';
$css = $root . '/component/media/css/admin.css';
$js = $root . '/component/media/js/duplicates.js';

foreach ([
    $root . '/component/admin/sql/updates/mysql/1.7.16.sql',
    $template,
    $view,
    $css,
    $js,
] as $path) {
    if (!is_file($path)) {
        $fail('Missing People 1.7.16 file: ' . $path);
    }
}

foreach ([
    'xdecaro-duplicate-mobile-tabs',
    'xdecaro-duplicate-mobile-tab',
    'data-duplicate-tab=',
    'data-duplicate-panel=',
    'role="tab"',
    'role="tabpanel"',
    'COM_XDECAROPEOPLE_DUPLICATE_MOBILE_TAB_HELP',
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
    'display: block;',
] as $needle) {
    $contains($css, $needle);
}

foreach ([
    "window.matchMedia('(max-width: 767.98px)')",
    'const activate = (group, index',
    "event.key === 'ArrowRight'",
    "event.key === 'ArrowLeft'",
    "event.key === 'Home'",
    "event.key === 'End'",
    'panel.hidden = mobileQuery.matches ? !active : false;',
] as $needle) {
    $contains($js, $needle);
}

$assets = json_decode((string) file_get_contents($root . '/component/media/joomla.asset.json'), true, 512, JSON_THROW_ON_ERROR);
$duplicatesAsset = null;
foreach (($assets['assets'] ?? []) as $asset) {
    if (($asset['name'] ?? '') === 'com_xdecaropeople.duplicates') {
        $duplicatesAsset = $asset;
        break;
    }
}

if (($duplicatesAsset['uri'] ?? '') !== 'com_xdecaropeople/duplicates.js') {
    $fail('People 1.7.16 must register the duplicates.js asset.');
}

foreach ([
    'COM_XDECAROPEOPLE_DUPLICATE_MOBILE_TAB_HELP=',
] as $key) {
    $contains($root . '/component/admin/language/it-IT/com_xdecaropeople.ini', $key);
    $contains($root . '/component/admin/language/en-GB/com_xdecaropeople.ini', $key);
}

echo "People 1.7.16 mobile duplicate tabs contract OK\n";
