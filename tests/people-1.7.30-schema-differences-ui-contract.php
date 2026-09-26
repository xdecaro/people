<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$fail = [];
$templatePath = $root . '/component/admin/tmpl/information/default.php';
$cssPath = $root . '/component/media/css/information.css';

$template = is_file($templatePath) ? (string) file_get_contents($templatePath) : '';
$css = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';

$has = static function (string $needle, string $haystack, string $message) use (&$fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail[] = $message . ' (missing: ' . $needle . ')';
    }
};

$has('Differenze rilevate', $template, 'Information page must expose schema differences heading');
$has('xdecaro-schema-differences', $template, 'Information page must render dedicated schema differences container');

$groups = [
    'missing_tables' => 'Tabelle mancanti',
    'unexpected_tables' => 'Tabelle non previste',
    'missing_columns' => 'Colonne mancanti',
    'incompatible_columns' => 'Colonne incompatibili',
    'unknown_columns' => 'Colonne non previste',
    'missing_indexes' => 'Indici mancanti',
    'incompatible_indexes' => 'Indici incompatibili',
    'unknown_indexes' => 'Indici non previsti',
    'engine_differences' => 'Engine differente',
    'collation_differences' => 'Collation differente',
];

foreach ($groups as $key => $label) {
    $has("['{$key}']", $template, "Information page must read {$key}");
    $has($label, $template, "Information page must label {$key}");
}

$has("!empty(\$schema['ok'])", $template, 'Schema differences UI must distinguish clean schema state');
$has('Schema conforme: nessuna differenza rilevata.', $template, 'Clean schema state must be explicit');
$has('.xdecaro-schema-differences', $css, 'Schema differences UI must have dedicated responsive styling');
$has('.xdecaro-schema-difference-group', $css, 'Schema difference groups must have dedicated styling');

if ($fail) {
    fwrite(STDERR, "People 1.7.30 schema differences UI contract FAILED\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

echo "People 1.7.30 schema differences UI contract PASS\n";
