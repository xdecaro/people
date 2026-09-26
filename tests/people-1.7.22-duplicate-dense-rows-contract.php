<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));

if ($version !== '1.7.22') {
    echo "People 1.7.22 dense duplicate row contract skipped for {$version}\n";
    exit(0);
}

$cssPath = $root . '/component/media/css/duplicates-compact.css';
$jsPath = $root . '/component/media/js/duplicates.js';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

foreach ([$cssPath, $jsPath] as $path) {
    if (!is_file($path)) {
        $fail('Missing People 1.7.22 dense duplicate row file: ' . $path);
    }
}

$css = (string) file_get_contents($cssPath);
$js = (string) file_get_contents($jsPath);

foreach ([
    'gap: .12rem;',
    'padding: .28rem .45rem;',
    'min-height: 2.75rem;',
    '.xdecaro-duplicates .xdecaro-duplicate-summary-main {',
    'grid-template-columns: auto minmax(11rem, .9fr) minmax(10rem, 1.1fr);',
    'align-items: center;',
    'margin-top: 0 !important;',
    'white-space: nowrap;',
] as $needle) {
    if (!str_contains($css, $needle)) {
        $fail('Missing dense duplicate row CSS: ' . $needle);
    }
}

foreach ([
    "control.classList.add('xdecaro-duplicate-row-control')",
    'summary.prepend(control)',
    "closest('[data-duplicate-select]')",
    'event.stopPropagation()',
] as $needle) {
    if (!str_contains($js, $needle)) {
        $fail('Checkbox must remain integrated inside the duplicate row: ' . $needle);
    }
}

echo "People 1.7.22 dense duplicate rows contract OK\n";
