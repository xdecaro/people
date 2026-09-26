<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$template = (string) file_get_contents($root . '/component/admin/tmpl/duplicates/default.php');
$css = (string) file_get_contents($root . '/component/media/css/admin.css');
$js = (string) file_get_contents($root . '/component/media/js/duplicates.js');

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

foreach ([
    'xdecaro-duplicate-row-control',
    'data-duplicate-select',
    'xdecaro-duplicate-accordion-summary',
] as $needle) {
    if (!str_contains($template, $needle)) {
        $fail('Missing compact duplicate row markup: ' . $needle);
    }
}

$summaryStart = strpos($template, '<summary class="card-header xdecaro-duplicate-accordion-summary">');
$summaryEnd = $summaryStart === false ? false : strpos($template, '</summary>', $summaryStart);
$controlPos = strpos($template, 'xdecaro-duplicate-row-control');

if ($summaryStart === false || $summaryEnd === false || $controlPos === false || $controlPos < $summaryStart || $controlPos > $summaryEnd) {
    $fail('The duplicate selection control must live inside the collapsed row summary.');
}

foreach ([
    '.xdecaro-duplicate-groups {',
    'gap: .2rem;',
    '.xdecaro-duplicate-accordion-summary {',
    'padding: .4rem .55rem;',
    '.xdecaro-duplicate-row-control',
] as $needle) {
    if (!str_contains($css, $needle)) {
        $fail('Missing compact duplicate row CSS: ' . $needle);
    }
}

foreach ([
    "closest('[data-duplicate-select]')",
    'event.stopPropagation()',
] as $needle) {
    if (!str_contains($js, $needle)) {
        $fail('Missing checkbox-in-row interaction protection: ' . $needle);
    }
}

echo "People 1.7.21 compact duplicate rows contract OK\n";
