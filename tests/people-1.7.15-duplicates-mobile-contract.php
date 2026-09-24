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
$css = $root . '/component/media/css/admin.css';

foreach ([
    $root . '/component/admin/sql/updates/mysql/1.7.15.sql',
    $template,
    $css,
] as $path) {
    if (!is_file($path)) {
        $fail('Missing People 1.7.15 file: ' . $path);
    }
}

foreach ([
    'xdecaro-duplicate-mobile-overview',
    'xdecaro-duplicate-mobile-record-strip',
    'xdecaro-duplicate-mobile-record-chip',
    'xdecaro-duplicate-mobile-swipe-help',
    'xdecaro-duplicate-mobile-quick-action',
    'COM_XDECAROPEOPLE_DUPLICATE_MOBILE_RECORDS',
    'COM_XDECAROPEOPLE_DUPLICATE_MOBILE_SWIPE_HELP',
    'COM_XDECAROPEOPLE_DUPLICATE_NOT_DUPLICATE',
] as $needle) {
    $contains($template, $needle);
}

foreach ([
    '.xdecaro-duplicate-mobile-overview',
    '.xdecaro-duplicate-mobile-record-strip',
    '.xdecaro-duplicate-mobile-record-chip',
    '.xdecaro-duplicate-mobile-quick-action',
    'scroll-snap-type: x mandatory',
    'scroll-snap-align: start',
    '.xdecaro-duplicate-actions {',
    'padding-bottom: max(8rem',
] as $needle) {
    $contains($css, $needle);
}

foreach ([
    'COM_XDECAROPEOPLE_DUPLICATE_MOBILE_RECORDS=',
    'COM_XDECAROPEOPLE_DUPLICATE_MOBILE_SWIPE_HELP=',
] as $key) {
    $contains($root . '/component/admin/language/it-IT/com_xdecaropeople.ini', $key);
    $contains($root . '/component/admin/language/en-GB/com_xdecaropeople.ini', $key);
}

$componentXml = simplexml_load_file($root . '/component/xdecaropeople.xml');
$packageXml = simplexml_load_file($root . '/package/pkg_people.xml');
$assets = json_decode((string) file_get_contents($root . '/component/media/joomla.asset.json'), true, 512, JSON_THROW_ON_ERROR);

if ((string) $componentXml->version !== '1.7.15' || (string) $packageXml->version !== '1.7.15') {
    $fail('People manifests must be version 1.7.15.');
}

if (($assets['version'] ?? '') !== '1.7.15') {
    $fail('People web assets must be version 1.7.15.');
}

foreach (($assets['assets'] ?? []) as $item) {
    if (($item['version'] ?? '') !== '1.7.15') {
        $fail('Every People web asset must be version 1.7.15.');
    }
}

echo "People 1.7.15 mobile duplicate review contract OK\n";
