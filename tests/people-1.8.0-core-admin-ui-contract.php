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
$assertContains("public const MINIMUM_CORE = '2.2.2';", $service, 'People 1.8.3 must require Core 2.2.2 for the finalized shared administrator UI.');
$assertContains('useAdminUi($wam)', $service, 'People must load the public Core administrator UI through AssetService.');
$assertNotContains('useComponents($wam)', $service, 'People must not stop at the old components-only Core UI layer.');

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
    'xdecaro-suite__title',
    'xdecaro-suite__description',
    'xdecaro-suite__metrics',
    'xdecaro-suite__metric xdecaro-people-kpi-link is-primary',
    'xdecaro-suite__metric xdecaro-people-kpi-link is-success',
    'xdecaro-suite__metric xdecaro-people-kpi-link is-neutral',
    'xdecaro-suite__metric xdecaro-people-kpi-link is-danger',
    'xdecaro-suite__metric xdecaro-people-kpi-link is-warning',
    'filter_state=1',
    'filter_state=0',
    'filter_state=-2',
    'aria-current="page"',
    'xdecaro-filterbar',
    'xdecaro-filterbar__search',
    'xdecaro-filterbar__filter',
    'xdecaro-filterbar__actions',
    'xdecaro-suite__responsive-wrap',
    'xdecaro-suite__responsive-table',
    'xdecaro-people-status-heading',
    'xdecaro-people-status-cell',
    'xdecaro-people-status-link',
    'xdecaro-people-status-badge',
    'data-label=',
] as $marker) {
    $assertContains($marker, $people, 'People list missing shared/filter UI marker: ' . $marker);
}

$peopleView = 'component/admin/src/View/People/HtmlView.php';
$assertContains("useStyle('com_xdecaropeople.people-list')", $peopleView, 'People list must load its narrow migration bridge after the base admin asset.');
$assertContains('public array $statusSummary', $peopleView, 'People view must expose status KPI values.');
$assertContains("get('StatusSummary')", $peopleView, 'People view must load the status summary from the model.');
$assertContains('duplicateGroups', $peopleView, 'People view must expose duplicate groups requiring review.');

$listCss = 'component/media/css/people-list.css';
foreach ([
    '.xdecaro-suite__metric > .card-body',
    'flex: 0 1 13rem',
    '--bs-table-striped-bg: var(--xdecaro-color-surface)',
    '.xdecaro-people-kpi-link',
    '.xdecaro-people-kpi-link.is-primary',
    '.xdecaro-people-kpi-link.is-success',
    '.xdecaro-people-kpi-link.is-neutral',
    '.xdecaro-people-kpi-link.is-danger',
    '.xdecaro-people-kpi-link.is-warning',
    '.xdecaro-people-status-heading',
    '.xdecaro-people-status-cell',
    '.xdecaro-people-status-link',
    '.xdecaro-people-status-badge',
    'justify-content: center',
    '@container xdecaro-suite (max-width: 38rem)',
] as $marker) {
    $assertContains($marker, $listCss, 'People list bridge missing parity/filter rule: ' . $marker);
}
$assertNotContains('.dc-', $listCss, 'Courses-private selectors must never be copied into People.');

$assets = 'component/media/joomla.asset.json';
$assertContains('com_xdecaropeople.people-list', $assets, 'People list bridge must be registered in the Web Asset Manager.');
$assertContains('"version": "1.8.3"', $assets, 'People Web Assets must match version 1.8.3.');

$peopleModel = 'component/admin/src/Model/PeopleModel.php';
$assertContains('public function getStatusSummary(): array', $peopleModel, 'People model must provide status counts without changing list filters.');

$person = 'component/admin/tmpl/person/edit.php';
foreach (['xdecaro-form', 'xdecaro-accordion', 'xdecaro-accordion__item', 'xdecaro-accordion__button', 'xdecaro-accordion__body'] as $marker) {
    $assertContains($marker, $person, 'Person editor missing shared Core form marker: ' . $marker);
}

$information = 'component/admin/tmpl/information/default.php';
$assertContains('xdecaro-suite__info-grid', $information, 'Information page must use the shared Core information grid.');
$assertContains('xdecaro-suite__info-card', $information, 'Information page must use the shared Core information card.');
$assertContains('xdecaro-suite__card-heading', $information, 'Information card headings must use the shared Core heading primitive.');

fwrite(STDOUT, "People 1.8.3 Core admin UI contract: OK\n");
