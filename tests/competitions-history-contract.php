<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$servicePath = $root . '/component/admin/src/Service/CompetitionsIntegrationService.php';
$componentPath = $root . '/component/admin/src/Extension/PeopleComponent.php';
$providerPath = $root . '/component/admin/services/provider.php';
$viewPath = $root . '/component/admin/src/View/Person/HtmlView.php';
$templatePath = $root . '/component/admin/tmpl/person/edit.php';
$itPath = $root . '/component/admin/language/it-IT/com_xdecaropeople.ini';
$enPath = $root . '/component/admin/language/en-GB/com_xdecaropeople.ini';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

if (!is_file($servicePath)) {
    $fail('CompetitionsIntegrationService.php is missing.');
}

$service = file_get_contents($servicePath);
$component = file_get_contents($componentPath);
$provider = file_get_contents($providerPath);
$view = file_get_contents($viewPath);
$template = file_get_contents($templatePath);
$it = file_get_contents($itPath);
$en = file_get_contents($enPath);

foreach ([$service, $component, $provider, $view, $template, $it, $en] as $source) {
    if ($source === false) {
        $fail('Unable to read a required People history source file.');
    }
}

foreach ([
    'final class CompetitionsIntegrationService',
    'function isHistoryAvailable(): bool',
    'function getPersonHistory(string $personUuid): array',
    "competitions.people_history",
    "CapabilityRegistry",
    "bootComponent('com_competitions')",
    'getPersonHistoryService',
] as $fragment) {
    if (!str_contains($service, $fragment)) {
        $fail('Missing CompetitionsIntegrationService contract fragment: ' . $fragment);
    }
}

if (str_contains($service, '#__xdecarocompetitions_')) {
    $fail('People must not read Competitions private tables.');
}
if (str_contains($service, 'xdecaro\\Component\\Competitions')) {
    $fail('People must not import Competitions implementation namespaces.');
}

if (!str_contains($component, 'getCompetitionsIntegrationService(): CompetitionsIntegrationService')) {
    $fail('PeopleComponent competitions integration getter is missing.');
}
if (!str_contains($provider, 'CompetitionsIntegrationService::class')) {
    $fail('CompetitionsIntegrationService is not wired in People provider.php.');
}
foreach (['public bool $competitionsHistoryAvailable = false;', 'public array $competitionsHistory = [];'] as $fragment) {
    if (!str_contains($view, $fragment)) {
        $fail('Missing Person view history property: ' . $fragment);
    }
}
if (!str_contains($view, 'getCompetitionsIntegrationService()')) {
    $fail('Person view does not consume the optional Competitions integration.');
}
if (!str_contains($template, "'competitions'")) {
    $fail('Competitions tab is missing from person template.');
}
if (!str_contains($template, 'competitionsHistoryAvailable')) {
    $fail('Competitions tab is not conditional on integration availability.');
}
foreach (['competition_name', 'season_name', 'team_name', 'role', 'shirt_number', 'status'] as $field) {
    if (!str_contains($template, $field)) {
        $fail('History table does not render field: ' . $field);
    }
}

foreach ([
    'COM_XDECAROPEOPLE_COMPETITIONS_TAB',
    'COM_XDECAROPEOPLE_COMPETITIONS_HISTORY',
    'COM_XDECAROPEOPLE_COMPETITIONS_EMPTY',
    'COM_XDECAROPEOPLE_COMPETITIONS_COMPETITION',
    'COM_XDECAROPEOPLE_COMPETITIONS_SEASON',
    'COM_XDECAROPEOPLE_COMPETITIONS_TEAM',
    'COM_XDECAROPEOPLE_COMPETITIONS_ROLE',
    'COM_XDECAROPEOPLE_COMPETITIONS_SHIRT_NUMBER',
    'COM_XDECAROPEOPLE_COMPETITIONS_STATUS',
] as $key) {
    if (!str_contains($it, $key) || !str_contains($en, $key)) {
        $fail('Missing People history language key in IT/EN: ' . $key);
    }
}

foreach (glob($root . '/component/**/*.php', GLOB_BRACE) ?: [] as $path) {
    $source = file_get_contents($path);
    if ($source !== false && str_contains($source, '#__xdecarocompetitions_')) {
        $fail('Direct Competitions table reference found in People: ' . $path);
    }
}

$workflow = file_get_contents($root . '/.github/workflows/competitions-history-contract.yml');
if ($workflow === false || !str_contains($workflow, 'php tests/competitions-history-contract.php')) {
    $fail('Competitions history contract is not executed by its CI workflow.');
}

echo "People Competitions history contract OK\n";
