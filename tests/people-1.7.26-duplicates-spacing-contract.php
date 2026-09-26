<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$css = $root . '/component/media/css/duplicates-compact.css';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$content = file_get_contents($css);
if ($content === false) {
    $fail('Unable to read duplicate compact CSS.');
}

foreach ([
    'gap: 0;',
    'margin-top: .20rem;',
    'margin-top: .16rem;',
    'line-height: 1.3;',
] as $needle) {
    if (!str_contains($content, $needle)) {
        $fail('Missing People 1.7.26 duplicate spacing contract: ' . $needle);
    }
}

if (!preg_match('/\.xdecaro-duplicates \.xdecaro-duplicate-summary-line2 \{[^}]*margin-top: \.20rem;[^}]*line-height: 1\.3;/s', $content)) {
    $fail('Row 2 must have 0.20rem top spacing and 1.3 line-height.');
}

if (!preg_match('/\.xdecaro-duplicates \.xdecaro-duplicate-summary-line3 \{[^}]*margin-top: \.16rem;[^}]*line-height: 1\.3;/s', $content)) {
    $fail('Row 3 must have 0.16rem top spacing and 1.3 line-height.');
}

echo "People 1.7.26 duplicate spacing contract OK\n";
