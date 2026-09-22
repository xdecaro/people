<?php

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($version === '1.6.0', 'People 1.6.0 version expected.');

$service = (string) file_get_contents($root . '/component/admin/src/Service/ImportService.php');
$controller = (string) file_get_contents($root . '/component/admin/src/Controller/ImportController.php');
$view = (string) file_get_contents($root . '/component/admin/src/View/Import/HtmlView.php');
$template = (string) file_get_contents($root . '/component/admin/tmpl/import/default.php');
$peopleView = (string) file_get_contents($root . '/component/admin/src/View/People/HtmlView.php');
$table = (string) file_get_contents($root . '/component/admin/src/Table/PersonTable.php');
$provider = (string) file_get_contents($root . '/component/admin/services/provider.php');
$component = (string) file_get_contents($root . '/component/admin/src/Extension/PeopleComponent.php');
$asset = json_decode((string) file_get_contents($root . '/component/media/joomla.asset.json'), true);
$javascript = (string) file_get_contents($root . '/component/media/js/import.js');

foreach ([
    'MAX_BATCH_SIZE = 150',
    'analyzeCandidates(',
    'importBatch(',
    "source_component' => 'com_xdecaropeople.import'",
    "'action' => 'import'",
    '#__xdecaropeople_people',
] as $needle) {
    $assert(str_contains($service, $needle), 'Import service contract missing: ' . $needle);
}

foreach ([
    "Session::checkToken('post')",
    "authorise('core.create', 'com_xdecaropeople')",
    "authorise('people.view_sensitive', 'com_xdecaropeople')",
    'count($rows) > 150',
] as $needle) {
    $assert(str_contains($controller, $needle), 'Import controller security contract missing: ' . $needle);
}

$assert(str_contains($peopleView, "ToolbarHelper::custom('import.open'"), 'People toolbar import action missing.');
$assert(str_contains($view, "useScript('com_xdecaropeople.import')"), 'Import view asset missing.');
$assert(str_contains($template, 'xdecaro-people-import-file'), 'Import template file picker missing.');
$assert(str_contains($provider, 'ImportService::class'), 'Import service DI registration missing.');
$assert(str_contains($component, 'getImportService()'), 'Import service component surface missing.');

foreach ([
    'allowsLegacyImportedLocation',
    "'com_xdecaropeople.import'",
    "'birth_place_id'",
    "'residence_place_id'",
] as $needle) {
    $assert(str_contains($table, $needle), 'Legacy imported location guard missing: ' . $needle);
}

$assets = is_array($asset['assets'] ?? null) ? $asset['assets'] : [];
$importAssets = array_values(array_filter($assets, static fn(array $item): bool => ($item['name'] ?? '') === 'com_xdecaropeople.import'));
$assert(count($importAssets) === 1, 'Import JavaScript asset must be registered exactly once.');
$assert(($importAssets[0]['version'] ?? '') === '1.6.0', 'Import asset version must match People 1.6.0.');

foreach ([
    "new TextDecoder('windows-1252')",
    'consolidateDuplicates',
    'batchSize',
    'tax_identifier',
    "formData.append(options.token, '1')",
] as $needle) {
    $assert(str_contains($javascript, $needle), 'Import JavaScript contract missing: ' . $needle);
}

foreach ([
    'categoria socio',
    'codice socio',
    'tipologia sordità',
    'impianto cocleare',
    'numero_carta',
    'cvv_cvc2',
] as $forbidden) {
    $assert(!str_contains($service, $forbidden), 'Import service must not own source-domain field: ' . $forbidden);
}

echo "People 1.6.0 CSV import contract OK\n";
