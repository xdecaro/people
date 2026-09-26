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
        $fail('Missing People 1.7.21 compact duplicate row contract in ' . $path . ': ' . $needle);
    }
};

$template = $root . '/component/admin/tmpl/duplicates/default.php';
$css = $root . '/component/media/css/admin.css';

foreach ([
    'xdecaro-duplicate-row',
    'xdecaro-duplicate-row-select',
    'xdecaro-duplicate-row-details',
    'data-duplicate-select',
] as $needle) {
    $contains($template, $needle);
}

$templateContent = (string) file_get_contents($template);
if (str_contains($templateContent, 'class="xdecaro-duplicate-select-row"')) {
    $fail('People 1.7.21 must place the selection checkbox inside the duplicate row, not in an external column.');
}

foreach ([
    '.xdecaro-duplicate-groups {',
    'gap: .2rem;',
    '.xdecaro-duplicate-row {',
    '.xdecaro-duplicate-row-select {',
    '.xdecaro-duplicate-accordion-summary {',
    'padding: .45rem .55rem;',
    'border-radius: .35rem;',
] as $needle) {
    $contains($css, $needle);
}

echo "People 1.7.21 compact duplicate row layout contract OK\n";
