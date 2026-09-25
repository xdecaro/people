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
        $fail('Missing People 1.7.18 all-record mobile duplicate contract in ' . $path . ': ' . $needle);
    }
};

if (version_compare($version, '1.7.18', '<')) {
    $fail('People 1.7.18+ version expected.');
}

$template = $root . '/component/admin/tmpl/duplicates/default.php';
$view = $root . '/component/admin/src/View/Duplicates/HtmlView.php';
$css = $root . '/component/media/css/admin.css';

foreach ([
    $root . '/component/admin/sql/updates/mysql/1.7.18.sql',
    $template,
    $view,
    $css,
] as $path) {
    if (!is_file($path)) {
        $fail('Missing People 1.7.18 file: ' . $path);
    }
}

foreach ([
    'xdecaro-duplicate-mobile-all',
    'xdecaro-duplicate-mobile-people',
    'xdecaro-duplicate-mobile-person-head',
    'xdecaro-duplicate-mobile-matrix',
    'xdecaro-duplicate-mobile-field',
    'xdecaro-duplicate-mobile-values',
    'xdecaro-duplicate-mobile-value',
    'COM_XDECAROPEOPLE_DUPLICATE_NOT_DUPLICATE',
] as $needle) {
    $contains($template, $needle);
}

$templateContent = (string) file_get_contents($template);
foreach ([
    'data-duplicate-prev',
    'data-duplicate-next',
    'data-duplicate-position',
    'xdecaro-duplicate-mobile-tabs',
] as $needle) {
    if (str_contains($templateContent, $needle)) {
        $fail('People 1.7.18 must not expose previous mobile navigation UI: ' . $needle);
    }
}

foreach ([
    '.xdecaro-duplicate-mobile-all',
    '.xdecaro-duplicate-mobile-person-head',
    '.xdecaro-duplicate-mobile-matrix',
    '.xdecaro-duplicate-mobile-field--different',
    '.xdecaro-duplicate-mobile-value',
    '.xdecaro-duplicate-compare {',
    'display: none;',
] as $needle) {
    $contains($css, $needle);
}

if ($version === '1.7.18' || $version === '1.7.19') {
    $viewContent = (string) file_get_contents($view);
    if (str_contains($viewContent, "useScript('com_xdecaropeople.duplicates')")) {
        $fail('People 1.7.18/1.7.19 must not require duplicate navigation JavaScript.');
    }

    $assets = json_decode((string) file_get_contents($root . '/component/media/joomla.asset.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach (($assets['assets'] ?? []) as $asset) {
        if (($asset['name'] ?? '') === 'com_xdecaropeople.duplicates') {
            $fail('People 1.7.18/1.7.19 must not register a duplicate navigation script asset.');
        }
    }
}

echo "People 1.7.18+ all-record mobile duplicate comparison contract OK\n";
