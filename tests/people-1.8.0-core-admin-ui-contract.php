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
$assertContains("public const MINIMUM_CORE = '2.2.10';", $service, 'People 1.8.7 must require Core 2.2.10 for shared list UI.');
$assertContains('useAdminUi($wam)', $service, 'People must load the public Core administrator UI through AssetService.');
$assertNotContains('useComponents($wam)', $service, 'People must not stop at the old components-only Core UI layer.');

$packageScript = 'package/script.php';
$assertContains("MINIMUM_CORE = '2.2.10'", $packageScript, 'People package preflight must require Core 2.2.10.');

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

$people = 'component/admin/tmpl/people/default.php';
foreach ([
    'xdecaro-suite__page-header',
    'xdecaro-suite__eyebrow',
    'xdecaro-suite__metrics',
    'xdecaro-suite__metric xdecaro-people-kpi-link is-primary',
    'filter_state=1',
    'filter_state=0',
    'filter_state=-2',
    'aria-current="page"',
    'xdecaro-filterbar xdecaro-filterbar--panel',
    'xdecaro-filterbar__primary',
    'xdecaro-filterbar__search-shell',
    'data-xdecaro-filterbar-toggle',
    'data-xdecaro-filterbar-open',
    'data-xdecaro-filterbar-close',
    'filter_person_status',
    'filter_state',
    'xdecaro-suite__responsive-wrap',
    'xdecaro-suite__responsive-table xdecaro-suite__responsive-table--striped',
    'xdecaro-people-person-status-heading',
    'xdecaro-people-person-status-cell',
    'COM_XDECAROPEOPLE_FIELD_PERSON_STATUS',
    'xdecaro-people-publication-heading',
    'xdecaro-people-publication-cell',
    "Text::_('JSTATUS')",
    'xdecaro-people-publication-link',
    'icon-check',
    'visually-hidden',
    'xdecaro-badge--success',
    'xdecaro-badge--warning',
    'xdecaro-badge--neutral',
    'data-label=',
    "HTMLHelper::_('searchtools.sort'",
    "isColumnVisible('display_name')",
    "isColumnVisible('state')",
] as $marker) {
    $assertContains($marker, $people, 'People list missing shared status/filter/sort UI marker: ' . $marker);
}
$assertNotContains("Text::_('JGLOBAL_FIELDSET_PUBLISHING')", $people, 'People list must use the compact Joomla Status heading.');
$assertNotContains('xdecaro-people-status-badge', $people, 'People must use Core semantic badges instead of local status badge primitives.');

$peopleView = 'component/admin/src/View/People/HtmlView.php';
$assertContains("useStyle('com_xdecaropeople.people-list')", $peopleView, 'People list must load its narrow component-specific bridge after Core UI.');
$assertContains("load('com_xdecaropeople.list'", $peopleView, 'People list-specific strings must use Joomla Language.');
$assertContains('public array $statusSummary', $peopleView, 'People view must expose status KPI values.');
$assertContains('public array $visibleColumns', $peopleView, 'People view must expose persistent column visibility.');

$listCss = 'component/media/css/people-list.css';
foreach ([
    '.xdecaro-suite .xdecaro-people-kpi-link',
    '.xdecaro-suite .xdecaro-people-kpi-link.is-active',
    '.xdecaro-suite .xdecaro-people-person-status-heading',
    '.xdecaro-suite .xdecaro-people-publication-heading',
    'a.xdecaro-people-publication-link.is-success',
    '@container xdecaro-suite (max-width: 38rem)',
] as $marker) {
    $assertContains($marker, $listCss, 'People list bridge missing component-specific rule: ' . $marker);
}
foreach ([
    '.xdecaro-people-status-badge',
    '.xdecaro-people-filters',
    '.xdecaro-people-filter-search',
    '.xdecaro-people-filter-state',
    '.xdecaro-people-filter-submit',
    '.card.xdecaro-suite__metric.is-primary',
    '--bs-table-striped-bg:',
] as $marker) {
    $assertNotContains($marker, $listCss, 'People list bridge must not duplicate Core generic UI primitive: ' . $marker);
}

$assets = 'component/media/joomla.asset.json';
$assertContains('com_xdecaropeople.people-list', $assets, 'People list bridge must be registered in WAM.');
$assertContains('"version": "1.8.7"', $assets, 'People Web Assets must match version 1.8.7.');

$peopleModel = 'component/admin/src/Model/PeopleModel.php';
$assertContains('public function getStatusSummary(): array', $peopleModel, 'People model must provide status counts.');
$assertContains("'filter.person_status'", $peopleModel, 'People model must persist the person-status filter.');
$assertContains("['active', 'archived', 'deceased']", $peopleModel, 'People person-status filter must be whitelisted.');

fwrite(STDOUT, "People 1.8.7 / Core 2.2.10 admin UI contract: OK\n");
