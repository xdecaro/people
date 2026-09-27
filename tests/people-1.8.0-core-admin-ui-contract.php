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
$assertContains("public const MINIMUM_CORE = '2.2.2';", $service, 'People 1.8.2 must require Core 2.2.2 for the finalized shared administrator UI.');
$assertContains('useAdminUi($wam)', $service, 'People must load the public Core administrator UI through AssetService.');
$assertNotContains('useComponents($wam)', $service, 'People 1.8.2 must not stop at the old components-only Core UI layer.');

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

$dashboard = 'component/admin/tmpl/dashboard/default.php';
$assertContains('xdecaro-suite__metrics', $dashboard, 'Dashboard KPIs must use the shared Core metric grid.');
$assertContains('xdecaro-suite__metric', $dashboard, 'Dashboard KPI cards must use the shared Core metric primitive.');
$assertContains('xdecaro-suite__section', $dashboard, 'Dashboard sections must use the shared Core section primitive.');
$assertContains('xdecaro-suite__actions', $dashboard, 'Dashboard quick actions must use the shared Core action group.');

$people = 'component/admin/tmpl/people/default.php';
$assertContains('xdecaro-suite__page-header', $people, 'People list must use the shared Core page header.');
$assertContains('xdecaro-suite__eyebrow', $people, 'People list must use the shared Core eyebrow.');
$assertContains('xdecaro-suite__title', $people, 'People list must use the shared Core title.');
$assertContains('xdecaro-suite__description', $people, 'People list must use the shared Core description.');
$assertContains('xdecaro-suite__metrics', $people, 'People list must show shared KPI cards above the filters.');
$assertContains('xdecaro-suite__metric is-primary', $people, 'Total people KPI must use primary accent.');
$assertContains('xdecaro-suite__metric is-success', $people, 'Published KPI must use success accent.');
$assertContains('xdecaro-suite__metric is-neutral', $people, 'Suspended KPI must use neutral accent.');
$assertContains('xdecaro-suite__metric is-danger', $people, 'Trashed KPI must use danger accent.');
$assertContains('xdecaro-suite__metric is-warning', $people, 'Duplicate review KPI must use warning accent.');
$assertContains('is-selected', $people, 'People status KPIs must expose the active filter using the shared selected state.');
$assertContains('COM_XDECAROPEOPLE_KPI_TOTAL', $people, 'People list must use the total KPI language key.');
$assertContains('COM_XDECAROPEOPLE_KPI_DUPLICATES', $people, 'People list must use the duplicate-review KPI language key.');
$assertContains('xdecaro-card xdecaro-suite__section', $people, 'People filters and table must live in the shared Core card, not a Bootstrap-specific panel.');
$assertContains('xdecaro-card__body', $people, 'People list panel must use shared Core card padding.');
$assertContains('xdecaro-filterbar', $people, 'People list filters must use the shared Core filter bar.');
$assertContains('xdecaro-filterbar__search', $people, 'People search must use the shared Core filter search slot.');
$assertContains('xdecaro-filterbar__filter', $people, 'People state filter must use the shared Core filter slot.');
$assertContains('xdecaro-filterbar__actions', $people, 'People search action must use the shared Core filter action slot.');
$assertContains('xdecaro-button xdecaro-button--primary', $people, 'People search must use the shared Core primary button.');
$assertContains('xdecaro-button', $people, 'People reset action must use the shared Core button.');
$assertContains('xdecaro-suite__responsive-wrap', $people, 'People list must use the shared responsive table wrapper.');
$assertContains('xdecaro-suite__responsive-table', $people, 'People list must use the shared responsive table primitive.');
$assertContains('data-label=', $people, 'People table cells must retain labels for mobile card presentation.');
$assertContains('xdecaro-badge xdecaro-badge--', $people, 'People state must be rendered through shared Core semantic badges.');
$assertNotContains('table-striped', $people, 'People list must not reintroduce Bootstrap zebra striping over the shared Core table surface.');
$assertNotContains('badge rounded-pill', $people, 'People status must not use Bootstrap-specific badge styling.');

$peopleCss = 'component/media/css/admin.css';
$assertNotContains('flex: 0 1 16rem', $peopleCss, 'People must not duplicate the shared Core state-filter width.');

$peopleView = 'component/admin/src/View/People/HtmlView.php';
$assertContains('public array $statusSummary', $peopleView, 'People view must expose status KPI values.');
$assertContains("get('StatusSummary')", $peopleView, 'People view must load the status summary from the model.');
$assertContains('duplicateGroups', $peopleView, 'People view must expose duplicate groups requiring review.');

$peopleModel = 'component/admin/src/Model/PeopleModel.php';
$assertContains('public function getStatusSummary(): array', $peopleModel, 'People model must provide status counts without changing list filters.');

$person = 'component/admin/tmpl/person/edit.php';
$assertContains('xdecaro-form', $person, 'Person editor must use the shared Core form contract.');
$assertContains('xdecaro-accordion', $person, 'Person editor must use the shared Core accordion contract.');
$assertContains('xdecaro-accordion__item', $person, 'Person accordion items must use the shared Core item primitive.');
$assertContains('xdecaro-accordion__button', $person, 'Person accordion toggles must use the shared Core button primitive.');
$assertContains('xdecaro-accordion__body', $person, 'Person accordion bodies must use the shared Core body primitive.');

$information = 'component/admin/tmpl/information/default.php';
$assertContains('xdecaro-suite__info-grid', $information, 'Information page must use the shared Core information grid.');
$assertContains('xdecaro-suite__info-card', $information, 'Information page must use the shared Core information card.');
$assertContains('xdecaro-suite__card-heading', $information, 'Information card headings must use the shared Core heading primitive.');
$assertNotContains('xdecaro-suite__info-card xdecaro-info-card__head', $information, 'Information headings must not masquerade as cards.');

fwrite(STDOUT, "People 1.8.2 Core admin UI contract: OK\n");
