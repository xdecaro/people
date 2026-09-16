<?php

$root = dirname(__DIR__);
$servicePath = $root . '/component/admin/src/Service/CompetitionsIntegrationService.php';
$component = (string) file_get_contents($root . '/component/admin/src/Extension/PeopleComponent.php');
$provider = (string) file_get_contents($root . '/component/admin/services/provider.php');
$view = (string) file_get_contents($root . '/component/admin/src/View/Person/HtmlView.php');
$template = (string) file_get_contents($root . '/component/admin/tmpl/person/edit.php');

if (!is_file($servicePath)) {
    fwrite(STDERR, "CompetitionsIntegrationService is missing.\n");
    exit(1);
}

$service = (string) file_get_contents($servicePath);

$checks = [
    [$service, "bootComponent('com_competitions')", 'People must discover Competitions through its public Joomla component surface.'],
    [$service, 'CapabilityRegistry', 'People must use the Core capability registry for optional Competitions discovery.'],
    [$service, 'getCoreIntegrationService', 'People must ask Competitions to expose its capabilities.'],
    [$service, 'registerCapabilities', 'People must register Competitions capabilities before using the history API.'],
    [$service, "competitions.people_history", 'People must require the competitions.people_history capability.'],
    [$service, 'getPersonHistoryService', 'People must call the public Competitions person-history service.'],
    [$service, 'getHistoryByPersonUuid', 'People must query history by the stable People UUID.'],
    [$component, 'setCompetitionsIntegrationService', 'PeopleComponent must accept CompetitionsIntegrationService from DI.'],
    [$component, 'getCompetitionsIntegrationService', 'PeopleComponent must expose CompetitionsIntegrationService.'],
    [$provider, 'CompetitionsIntegrationService::class', 'The People service provider must register CompetitionsIntegrationService.'],
    [$view, 'competitionsHistoryAvailable', 'Person view must expose competitionsHistoryAvailable.'],
    [$view, 'competitionsHistory', 'Person view must expose competitionsHistory.'],
    [$view, 'getPersonHistory', 'Person view must obtain competition history outside the template.'],
    [$template, "'competitions'", 'Person template must contain a Competitions tab identifier.'],
    [$template, 'competitionsHistoryAvailable', 'Competitions tab must be conditional on integration availability.'],
    [$template, 'competitionsHistory', 'Competitions tab must render the history collection.'],
    [$template, 'COM_XDECAROPEOPLE_COMPETITIONS_HISTORY', 'Person template must use translated Competition history UI text.'],
];

foreach ($checks as [$source, $needle, $message]) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

if (str_contains($service, '#__xdecarocompetitions_')) {
    fwrite(STDERR, "People must never query Competitions private tables directly.\n");
    exit(1);
}

if (str_contains($service, 'xdecaro\\Component\\Competitions')) {
    fwrite(STDERR, "People must not compile against Competitions implementation namespaces.\n");
    exit(1);
}

echo "Competitions history tab contract OK\n";
