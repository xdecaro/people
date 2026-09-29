<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$contains = static function (string $path, string $needle) use ($fail): void {
    $content = file_get_contents($path);
    if ($content === false || !str_contains($content, $needle)) {
        $fail('Missing People 1.7.23 duplicate summary contract in ' . $path . ': ' . $needle);
    }
};

$template = $root . '/component/admin/tmpl/duplicates/default.php';
$css = $root . '/component/media/css/duplicates-compact.css';
$js = $root . '/component/media/js/duplicates.js';

foreach ([
    'xdecaro-duplicate-summary-line1',
    'xdecaro-duplicate-summary-line2',
    'xdecaro-duplicate-name-comparison',
    'xdecaro-duplicate-match-summary',
    'xdecaro-duplicate-summary-right',
    "implode(' ↔ ', \$recordNames)",
] as $needle) {
    $contains($template, $needle);
}

foreach ([
    '.xdecaro-duplicate-summary-line1',
    '.xdecaro-duplicate-summary-line2',
    '.xdecaro-duplicate-name-comparison',
    '.xdecaro-duplicate-match-summary',
    '.xdecaro-duplicate-summary-right',
    'grid-template-columns: auto minmax(0, 1fr);',
] as $needle) {
    $contains($css, $needle);
}

$cssContent = (string) file_get_contents($css);
if (str_contains($cssContent, 'grid-template-columns: auto minmax(11rem, .9fr) minmax(10rem, 1.1fr);')) {
    $fail('People 1.7.23 must not keep the old compressed one-line summary grid.');
}

$jsContent = (string) file_get_contents($js);
if (str_contains($jsContent, 'replaceChildren(line1, line2')) {
    $fail('D+ summary rows must be rendered server-side rather than rebuilt after paint.');
}

echo "People 1.7.23 two-row duplicate summary contract OK\n";
