<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$integrationPath = $root . '/component/admin/src/Service/OrganizationsIntegrationService.php';
$componentPath = $root . '/component/admin/src/Extension/PeopleComponent.php';
$providerPath = $root . '/component/admin/services/provider.php';
$viewPath = $root . '/component/admin/src/View/Person/HtmlView.php';
$templatePath = $root . '/component/admin/tmpl/person/edit.php';

if (!is_file($integrationPath)) {
    fwrite(STDERR, "Missing OrganizationsIntegrationService.php.\n");
    exit(1);
}

$integration = (string) file_get_contents($integrationPath);
$component = (string) file_get_contents($componentPath);
$provider = (string) file_get_contents($providerPath);
$view = (string) file_get_contents($viewPath);
$template = (string) file_get_contents($templatePath);

$checks = [
    [$integration, 'organizations.people_appointments', 'Missing Organizations capability check.'],
    [$integration, 'getPersonAppointmentsService', 'People must use Organizations public service.'],
    [$integration, 'getAppointmentsByPersonUuid', 'People must query Organizations by person UUID.'],
    [$component, 'getOrganizationsIntegrationService', 'PeopleComponent must expose the Organizations integration bridge.'],
    [$provider, 'OrganizationsIntegrationService::class', 'People DI must register the Organizations integration bridge.'],
    [$view, 'organizationsHistoryAvailable', 'Person view must expose Organizations integration availability.'],
    [$view, 'organizationsCurrent', 'Person view must expose current organization appointments.'],
    [$view, 'organizationsHistory', 'Person view must expose organization appointment history.'],
    [$template, 'COM_XDECAROPEOPLE_ORGANIZATIONS_TAB', 'Missing Organizzazioni tab.'],
    [$template, 'organizationsCurrent', 'Organizzazioni tab must render current appointments.'],
    [$template, 'organizationsHistory', 'Organizzazioni tab must render appointment history.'],
];

foreach ($checks as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/component', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $source = (string) file_get_contents($file->getPathname());
    if (str_contains($source, '#__xdecaroorganizations_')) {
        fwrite(STDERR, 'People production code must not query Organizations private tables: ' . $file->getPathname() . "\n");
        exit(1);
    }
}

if (!str_contains($view, "['is_current']") && !str_contains($view, "[\"is_current\"]")) {
    fwrite(STDERR, "People must split current/history using Organizations-owned is_current.\n");
    exit(1);
}

if (!str_contains($template, 'option=com_xdecaroorganizations')) {
    fwrite(STDERR, "Organization names must link to the Organizations edit screen.\n");
    exit(1);
}

echo "People Organizations history tab contract OK\n";
