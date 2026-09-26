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

$js = $root . '/component/media/js/duplicates.js';
$css = $root . '/component/media/css/duplicates-compact.css';

foreach ([
    "const line3 = document.createElement('div');",
    "line3.className = 'xdecaro-duplicate-summary-line3';",
    'line2.append(nameComparison);',
    'line3.append(matchSummary);',
    'summaryMain.replaceChildren(line1, line2, line3);',
] as $needle) {
    $contains($js, $needle);
}

foreach ([
    '.xdecaro-duplicate-summary-line2',
    '.xdecaro-duplicate-summary-line3',
    '.xdecaro-duplicate-name-comparison',
    '.xdecaro-duplicate-match-summary',
] as $needle) {
    $contains($css, $needle);
}

$jsContent = (string) file_get_contents($js);
if (str_contains($jsContent, 'line2.append(matchSummary);')) {
    $fail('People 1.7.25 must keep the comparison names on row 2 and move match/difference details to row 3.');
}

if (!str_contains($jsContent, "line1.append(identity, counts);")) {
    $fail('People 1.7.25 must keep type/record metadata and comparison counters together on row 1.');
}

echo "People 1.7.25 three-row duplicate summary contract OK\n";
