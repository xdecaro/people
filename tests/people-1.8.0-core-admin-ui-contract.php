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

foreach ([
    'component/admin/tmpl/dashboard/default.php',
    'component/admin/tmpl/people/default.php',
] as $template) {
    $assertContains('xdecaro-scope', $template, basename(dirname($template)) . ' must remain scoped.');
    $assertContains('xdecaro-suite', $template, basename(dirname($template)) . ' must opt into the shared Core administrator suite.');
}

$assertContains('xdecaro-suite__metrics', 'component/admin/tmpl/dashboard/default.php', 'Dashboard KPIs must use the shared Core metric grid.');
$assertContains('xdecaro-suite__metric', 'component/admin/tmpl/dashboard/default.php', 'Dashboard KPI cards must use the shared Core metric primitive.');
$assertContains('xdecaro-suite__actions', 'component/admin/tmpl/dashboard/default.php', 'Dashboard quick actions must use the shared Core action group.');

$people = 'component/admin/tmpl/people/default.php';
$assertContains('xdecaro-filterbar', $people, 'People list filters must use the shared Core filter bar.');
$assertContains('xdecaro-filterbar__search', $people, 'People search must use the shared Core filter search slot.');
$assertContains('xdecaro-filterbar__filter', $people, 'People state filter must use the shared Core filter slot.');
$assertContains('xdecaro-filterbar__actions', $people, 'People search action must use the shared Core filter action slot.');
$assertContains('xdecaro-suite__responsive-wrap', $people, 'People list must use the shared responsive table wrapper.');
$assertContains('xdecaro-suite__responsive-table', $people, 'People list must use the shared responsive table primitive.');
$assertContains('data-label=', $people, 'People table cells must retain labels for mobile card presentation.');

fwrite(STDOUT, "People 1.8.0 Core admin UI phase 1 contract: OK\n");
