<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$currentVersion = trim((string) file_get_contents($root . '/VERSION'));

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$contains = static function (string $path, string $needle) use ($fail): void {
    $content = file_get_contents($path);
    if ($content === false || !str_contains($content, $needle)) {
        $fail('Missing expected export contract in ' . $path . ': ' . $needle);
    }
};

$view = $root . '/component/admin/src/View/People/HtmlView.php';
$controller = $root . '/component/admin/src/Controller/ExportController.php';
$service = $root . '/component/admin/src/Service/ExportService.php';
$template = $root . '/component/admin/tmpl/people/default.php';
$script = $root . '/component/media/js/export.js';
$asset = $root . '/component/media/joomla.asset.json';
$componentManifest = $root . '/component/xdecaropeople.xml';
$packageManifest = $root . '/package/pkg_people.xml';

foreach ([$controller, $service, $script, $root . '/component/admin/sql/updates/mysql/1.7.7.sql'] as $path) {
    if (!is_file($path)) {
        $fail('Missing People 1.7.7 export file: ' . $path);
    }
}

$contains($view, "ToolbarHelper::custom('export.open'");
$contains($view, "useScript('com_xdecaropeople.export')");
$contains($controller, '$this->checkToken();');
$contains($controller, "authorise('core.manage', 'com_xdecaropeople')");
$contains($controller, "['xlsx', 'csv', 'pdf']");
$contains($controller, "'people.view_sensitive'");
$contains($service, 'public function toCsv(');
$contains($service, 'public function toXlsx(');
$contains($service, 'public function toPdf(');
$contains($service, "ZipArchive::CREATE | ZipArchive::OVERWRITE");
$contains($service, '$scope === \'selected\'');
$contains($service, '$scope === \'filtered\'');
$contains($service, "a.state') . ' >= 0'");
$contains($template, 'xdecaro-people-export-dialog');
$contains($template, 'value="xlsx"');
$contains($template, 'value="csv"');
$contains($template, 'value="pdf"');
$contains($template, 'value="filtered"');
$contains($template, 'value="all"');
$contains($template, 'value="selected"');
$contains($script, "task === 'export.open'");
$contains($script, "exportForm.submit()");
$contains($root . '/component/admin/language/it-IT/com_xdecaropeople.ini', 'COM_XDECAROPEOPLE_EXPORT_TITLE=');
$contains($root . '/component/admin/language/en-GB/com_xdecaropeople.ini', 'COM_XDECAROPEOPLE_EXPORT_TITLE=');

$assets = json_decode((string) file_get_contents($asset), true, 512, JSON_THROW_ON_ERROR);
if (version_compare($currentVersion, '1.7.7', '<') || ($assets['version'] ?? null) !== $currentVersion) {
    $fail('Web asset registry must match People 1.7.7+ current version.');
}

$exportAsset = null;
foreach (($assets['assets'] ?? []) as $item) {
    if (($item['name'] ?? '') === 'com_xdecaropeople.export') {
        $exportAsset = $item;
        break;
    }
}
if (($exportAsset['uri'] ?? '') !== 'com_xdecaropeople/export.js') {
    $fail('Export web asset is not registered correctly.');
}

$componentXml = simplexml_load_file($componentManifest);
$packageXml = simplexml_load_file($packageManifest);
if ((string) $componentXml->version !== $currentVersion || (string) $packageXml->version !== $currentVersion) {
    $fail('People manifests must match the current People version.');
}

echo "People 1.7.7+ export contract OK\n";
