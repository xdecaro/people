<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$templatePath = $root . '/component/admin/tmpl/dashboard/default.php';
$viewPath = $root . '/component/admin/src/View/Dashboard/HtmlView.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$template = file_get_contents($templatePath);
$view = file_get_contents($viewPath);

if ($template === false || $view === false) {
    $fail('Unable to read People dashboard sources.');
}

foreach ([
    'xdecaro-dashboard-kpis',
    'xdecaro-dashboard-review',
    'xdecaro-dashboard-integrations',
    'COM_XDECAROPEOPLE_DASHBOARD_DUPLICATE_GROUPS',
    'COM_XDECAROPEOPLE_DASHBOARD_DATA_QUALITY',
    'COM_XDECAROPEOPLE_DASHBOARD_ORGANIZATIONS',
    'COM_XDECAROPEOPLE_DASHBOARD_MEMBERSHIP',
    'COM_XDECAROPEOPLE_DASHBOARD_NEW_PERSON',
] as $needle) {
    if (!str_contains($template, $needle)) {
        $fail('Missing People 1.7.27 dashboard template contract: ' . $needle);
    }
}

foreach ([
    'public array $duplicateStats',
    'public array $dataQuality',
    'public bool $organizationsAvailable',
    'public bool $membershipAvailable',
    'getDuplicateService()->find(500)',
    'getOrganizationsIntegrationService()->isHistoryAvailable()',
    'getMembershipIntegrationService()->isAvailable()',
] as $needle) {
    if (!str_contains($view, $needle)) {
        $fail('Missing People 1.7.27 dashboard view contract: ' . $needle);
    }
}

if (str_contains($view, '#__xdecaroorganizations_') || str_contains($view, '#__decaromembership_')) {
    $fail('People dashboard must not query Organizations or Membership private tables directly.');
}

echo "People 1.7.27 dashboard contract OK\n";
