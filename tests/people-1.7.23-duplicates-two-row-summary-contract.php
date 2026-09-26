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
$css = $root . '/component/media/css/admin.css';

foreach ([
    'xdecaro-duplicate-summary-line1',
    'xdecaro-duplicate-summary-line2',
    'xdecaro-duplicate-name-comparison',
    'xdecaro-duplicate-match-summary',
    'xdecaro-duplicate-summary-right',
] as $needle) {
    $contains($template, $needle);
}

foreach ([
    '.xdecaro-duplicate-summary-line1',
    '.xdecaro-duplicate-summary-line2',
    '.xdecaro-duplicate-name-comparison',
    '.xdecaro-duplicate-match-summary',
    'grid-template-columns: minmax(0, 1fr) auto;',
] as $needle) {
    $contains($css, $needle);
}

$templateContent = (string) file_get_contents($template);
if (!str_contains($templateContent, "implode(' ↔ ', $recordNames)")) {
    $fail('People 1.7.23 must render compared person names with the ↔ separator.');
}

if (str_contains($templateContent, 'd-flex flex-wrap align-items-center gap-2')) {
    $fail('People 1.7.23 must not keep the old one-line wrapped duplicate header layout.');
}

echo "People 1.7.23 two-row duplicate summary contract OK\n";
