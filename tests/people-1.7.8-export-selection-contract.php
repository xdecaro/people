<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$contains = static function (string $path, string $needle) use ($fail): void {
    $content = file_get_contents($path);
    if ($content === false || !str_contains($content, $needle)) {
        $fail('Missing People 1.7.8 export contract in ' . $path . ': ' . $needle);
    }
};

if (version_compare($version, '1.7.8', '<')) {
    $fail('People 1.7.8+ version expected.');
}

$view = $root . '/component/admin/src/View/People/HtmlView.php';
$template = $root . '/component/admin/tmpl/people/default.php';
$script = $root . '/component/media/js/export.js';
$controller = $root . '/component/admin/src/Controller/ExportController.php';
$service = $root . '/component/admin/src/Service/ExportService.php';
$asset = $root . '/component/media/joomla.asset.json';

foreach ([
    $root . '/component/admin/sql/updates/mysql/1.7.8.sql',
    $view,
    $template,
    $script,
    $controller,
    $service,
] as $path) {
    if (!is_file($path)) {
        $fail('Missing People 1.7.8 file: ' . $path);
    }
}

$contains($view, 'public bool $canSensitive = false;');
$contains($view, '$this->canSensitive = $user->authorise(\'people.view_sensitive\'');

foreach ([
    'xdecaro-people-export-clear-selection',
    'xdecaro-people-export-columns-visible',
    'xdecaro-people-export-columns-all',
    'xdecaro-people-export-columns-none',
    'data-xdecaro-export-column',
    'value="display_name"',
    'value="first_name"',
    'value="last_name"',
    'value="tax_identifier"',
    'value="birth_date"',
    'value="birth_place"',
    'value="email"',
    'value="phone"',
    'value="state"',
] as $needle) {
    $contains($template, $needle);
}

foreach ([
    'com_xdecaropeople.export.selected_ids.v1',
    'window.sessionStorage',
    'selectedIds',
    'restorePageSelection',
    'clearSelectionButton',
    "Array.from(selectedIds).join(',')",
    "appendHidden('export_columns[]'",
    "export_selected_ids",
    "setColumns('visible')",
    "setColumns('all')",
    "setColumns('none')",
] as $needle) {
    $contains($script, $needle);
}

foreach ([
    "get('export_columns'",
    "getString('export_selected_ids'",
    'resolveColumns($requestedColumns',
    'toXlsx($rows, $columns)',
    'toPdf($rows, $columns)',
    'toCsv($rows, $columns)',
] as $needle) {
    $contains($controller, $needle);
}

foreach ([
    'public function resolveColumns(',
    'private function allowedColumns(',
    'if ($canSensitive)',
    'if ($canIdentityDetails)',
    'PDF_COLUMNS_PER_PAGE = 8',
    'array_chunk($columns, self::PDF_COLUMNS_PER_PAGE)',
    'public function toCsv(array $rows, array $columns)',
    'public function toXlsx(array $rows, array $columns)',
    'public function toPdf(array $rows, array $columns)',
] as $needle) {
    $contains($service, $needle);
}

foreach ([
    'COM_XDECAROPEOPLE_EXPORT_NO_COLUMNS=',
    'COM_XDECAROPEOPLE_EXPORT_SELECTION_PERSIST_HELP=',
    'COM_XDECAROPEOPLE_EXPORT_CLEAR_SELECTION=',
    'COM_XDECAROPEOPLE_EXPORT_COLUMNS=',
    'COM_XDECAROPEOPLE_EXPORT_COLUMNS_VISIBLE=',
    'COM_XDECAROPEOPLE_EXPORT_COLUMNS_ALL=',
    'COM_XDECAROPEOPLE_EXPORT_COLUMNS_NONE=',
] as $key) {
    $contains($root . '/component/admin/language/it-IT/com_xdecaropeople.ini', $key);
    $contains($root . '/component/admin/language/en-GB/com_xdecaropeople.ini', $key);
}

$assets = json_decode((string) file_get_contents($asset), true, 512, JSON_THROW_ON_ERROR);
if (($assets['version'] ?? '') !== $version) {
    $fail('People web assets must match the current version.');
}

foreach (($assets['assets'] ?? []) as $item) {
    if (($item['version'] ?? '') !== $version) {
        $fail('Every People web asset must match the current version.');
    }
}

$componentXml = simplexml_load_file($root . '/component/xdecaropeople.xml');
$packageXml = simplexml_load_file($root . '/package/pkg_people.xml');

if ((string) $componentXml->version !== $version || (string) $packageXml->version !== $version) {
    $fail('People manifests must match the current version.');
}

echo "People 1.7.8+ persistent selection and export-column contract OK\n";
