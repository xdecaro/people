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
        $fail('Missing People 1.7.25 three-row duplicate summary contract in ' . $path . ': ' . $needle);
    }
};

$template = $root . '/component/admin/tmpl/duplicates/default.php';
$css = $root . '/component/media/css/duplicates-compact.css';
$js = $root . '/component/media/js/duplicates.js';

foreach ([
    'xdecaro-duplicate-summary-line1',
    'xdecaro-duplicate-summary-line2',
    'xdecaro-duplicate-summary-line3',
    'xdecaro-duplicate-name-comparison',
    'xdecaro-duplicate-match-summary',
    'xdecaro-duplicate-summary-counts xdecaro-duplicate-summary-right',
] as $needle) {
    $contains($template, $needle);
}

foreach ([
    '.xdecaro-duplicate-summary-line2',
    '.xdecaro-duplicate-summary-line3',
    '.xdecaro-duplicate-name-comparison',
    '.xdecaro-duplicate-match-summary',
] as $needle) {
    $contains($css, $needle);
}

$templateContent = (string) file_get_contents($template);
$line1 = strpos($templateContent, 'xdecaro-duplicate-summary-line1');
$line2 = strpos($templateContent, 'xdecaro-duplicate-summary-line2');
$line3 = strpos($templateContent, 'xdecaro-duplicate-summary-line3');

if ($line1 === false || $line2 === false || $line3 === false || !($line1 < $line2 && $line2 < $line3)) {
    $fail('People D+ must keep metadata/counters on row 1, names on row 2 and match/difference details on row 3.');
}

$jsContent = (string) file_get_contents($js);
if (str_contains($jsContent, 'summaryMain.replaceChildren(line1, line2, line3)')) {
    $fail('D+ three-row summary must be rendered server-side rather than rebuilt after paint.');
}

echo "People 1.7.25 three-row duplicate summary contract OK\n";
