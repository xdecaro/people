<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$fail = [];
$viewPath = $root . '/component/admin/src/View/Information/HtmlView.php';
$jsPath = $root . '/component/media/js/database-maintenance.js';
$cssPath = $root . '/component/media/css/information.css';

$view = is_file($viewPath) ? (string) file_get_contents($viewPath) : '';
$js = is_file($jsPath) ? (string) file_get_contents($jsPath) : '';
$css = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';

$has = static function (string $needle, string $haystack, string $message) use (&$fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail[] = $message . ' (missing: ' . $needle . ')';
    }
};

$has("addScriptOptions('com_xdecaropeople.schema-differences'", $view, 'Information view must expose schema inspection details to the existing maintenance asset');
$has('Differenze rilevate', $js, 'Information page must expose schema differences heading');
$has('xdecaro-schema-differences', $js, 'Information page must render dedicated schema differences container');
$has('Schema conforme: nessuna differenza rilevata.', $js, 'Clean schema state must be explicit');

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
    $has("'{$key}'", $js, "Schema differences renderer must read {$key}");
    $has($label, $js, "Schema differences renderer must label {$key}");
}

$has('Joomla.getOptions', $js, 'Schema differences renderer must read Joomla script options');
$has('.xdecaro-database-maintenance', $js, 'Schema differences renderer must attach inside database maintenance');
$has('.xdecaro-schema-differences', $css, 'Schema differences UI must have dedicated responsive styling');
$has('.xdecaro-schema-difference-group', $css, 'Schema difference groups must have dedicated styling');

if ($fail) {
    fwrite(STDERR, "People 1.7.30 schema differences UI contract FAILED\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

echo "People 1.7.30 schema differences UI contract PASS\n";
