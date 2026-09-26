<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$css = $root . '/component/media/css/duplicates-compact.css';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$contains = static function (string $path, string $needle) use ($fail): void {
    $content = file_get_contents($path);
    if ($content === false || !str_contains($content, $needle)) {
        $fail('Missing People 1.7.24 compact left-aligned duplicate layout contract in ' . $path . ': ' . $needle);
    }
};

foreach ([
    '.xdecaro-duplicate-select-row {',
    'display: block;',
    '.xdecaro-duplicate-group {',
    'padding: 0 !important;',
    '.xdecaro-duplicate-accordion-summary {',
    'display: grid !important;',
    'grid-template-columns: auto minmax(0, 1fr);',
    '.xdecaro-duplicate-summary-main {',
    'width: 100%;',
    '.xdecaro-duplicate-summary-line1 {',
    'justify-content: space-between;',
    '.xdecaro-duplicate-summary-line2 {',
    '.xdecaro-duplicate-name-comparison {',
    '.xdecaro-duplicate-match-summary {',
    'text-overflow: ellipsis;',
] as $needle) {
    $contains($css, $needle);
}

echo "People 1.7.24 duplicate left-aligned summary layout contract OK\n";
