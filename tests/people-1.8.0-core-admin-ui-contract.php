<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$assertContains = static function (string $needle, string $path, string $message) use ($root): void {
    $content = file_get_contents($root . '/' . $path);
    if ($content === false || !str_contains($content, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\nExpected to find: {$needle}\nFile: {$path}\n");
        exit(1);
    }
};

$assertNotContains = static function (string $needle, string $path, string $message) use ($root): void {
    $content = file_get_contents($root . '/' . $path);
    if ($content !== false && str_contains($content, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\nUnexpected: {$needle}\nFile: {$path}\n");
        exit(1);
    }
};

$service = 'component/admin/src/Service/CoreIntegrationService.php';
$assertContains("public const MINIMUM_CORE = '2.2.0';", $service, 'People 1.8.0 must require Core 2.2.0 for the shared administrator UI.');
$assertContains('useAdminUi($wam)', $service, 'People must load the public Core administrator UI through AssetService.');
$assertNotContains('useComponents($wam)', $service, 'People 1.8.0 must not stop at the old components-only Core UI layer.');

$templates = [
    'component/admin/tmpl/dashboard/default.php',
    'component/admin/tmpl/people/default.php',
    'component/admin/tmpl/duplicates/default.php',
    'component/admin/tmpl/import/default.php',
    'component/admin/tmpl/information/default.php',
    'component/admin/tmpl/person/edit.php',
];

foreach ($templates as $template) {
    $assertContains('xdecaro-scope', $template, basename(dirname($template)) . ' must remain scoped.');
    $assertContains('xdecaro-suite', $template, basename(dirname($template)) . ' must opt into the shared Core administrator suite.');
}

$assertContains('xdecaro-suite__metrics', 'component/admin/tmpl/dashboard/default.php', 'Dashboard KPIs must use the shared Core metric grid.');
$assertContains('xdecaro-filterbar', 'component/admin/tmpl/people/default.php', 'People list filters must use the shared Core filter bar.');
$assertContains('xdecaro-suite__responsive-wrap', 'component/admin/tmpl/people/default.php', 'People list must use the shared responsive table wrapper.');
$assertContains('data-label=', 'component/admin/tmpl/people/default.php', 'People table cells must retain labels for mobile card presentation.');
$assertContains('xdecaro-form', 'component/admin/tmpl/person/edit.php', 'Person editor must use the shared Core form contract.');
$assertContains('xdecaro-accordion', 'component/admin/tmpl/person/edit.php', 'Person editor accordion must use the shared Core accordion contract.');
$assertContains('xdecaro-suite__info-grid', 'component/admin/tmpl/information/default.php', 'Information must use the shared Core information grid.');

fwrite(STDOUT, "People 1.8.0 Core admin UI contract: OK\n");
