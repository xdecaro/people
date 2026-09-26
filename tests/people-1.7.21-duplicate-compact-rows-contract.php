<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$cssPath = $root . '/component/media/css/duplicates-compact.css';
$jsPath = $root . '/component/media/js/duplicates.js';
$viewPath = $root . '/component/admin/src/View/Duplicates/HtmlView.php';
$assetsPath = $root . '/component/media/joomla.asset.json';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

foreach ([$cssPath, $jsPath, $viewPath, $assetsPath] as $path) {
    if (!is_file($path)) {
        $fail('Missing People 1.7.21+ compact duplicate row file: ' . $path);
    }
}

$css = (string) file_get_contents($cssPath);
$js = (string) file_get_contents($jsPath);
$view = (string) file_get_contents($viewPath);
$assets = (string) file_get_contents($assetsPath);

foreach ([
    '.xdecaro-duplicate-groups',
    '.xdecaro-duplicate-select-row',
    'display: block;',
    '.xdecaro-duplicate-accordion-summary',
    '.xdecaro-duplicate-row-control',
] as $needle) {
    if (!str_contains($css, $needle)) {
        $fail('Missing compact duplicate row CSS: ' . $needle);
    }
}

foreach ([
    "row.querySelector('.xdecaro-duplicate-select-box')",
    "row.querySelector('.xdecaro-duplicate-accordion-summary')",
    "control.classList.add('xdecaro-duplicate-row-control')",
    'summary.prepend(control)',
    "closest('[data-duplicate-select]')",
    'event.stopPropagation()',
] as $needle) {
    if (!str_contains($js, $needle)) {
        $fail('Missing integrated checkbox row behavior: ' . $needle);
    }
}

if (!str_contains($view, "useStyle('com_xdecaropeople.duplicates-compact')")) {
    $fail('Duplicates view must load the compact duplicate row stylesheet.');
}

foreach ([
    'com_xdecaropeople.duplicates-compact',
    'com_xdecaropeople/duplicates-compact.css',
] as $needle) {
    if (!str_contains($assets, $needle)) {
        $fail('Missing compact duplicate stylesheet asset: ' . $needle);
    }
}

echo "People 1.7.21+ compact duplicate rows contract OK\n";
