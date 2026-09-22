<?php

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($version === '1.7.0', 'People 1.7.0 version expected.');

$service = (string) file_get_contents($root . '/component/admin/src/Service/DuplicateService.php');
$controller = (string) file_get_contents($root . '/component/admin/src/Controller/DuplicateController.php');
$provider = (string) file_get_contents($root . '/component/admin/src/Service/PersonProviderService.php');
$view = (string) file_get_contents($root . '/component/admin/src/View/Duplicates/HtmlView.php');
$template = (string) file_get_contents($root . '/component/admin/tmpl/duplicates/default.php');
$install = (string) file_get_contents($root . '/component/admin/sql/install.mysql.utf8mb4.sql');
$update = (string) file_get_contents($root . '/component/admin/sql/updates/mysql/1.7.0.sql');
$css = (string) file_get_contents($root . '/component/media/css/admin.css');
$di = (string) file_get_contents($root . '/component/admin/services/provider.php');
$competitions = (string) file_get_contents($root . '/component/admin/src/Service/CompetitionsIntegrationService.php');
$organizations = (string) file_get_contents($root . '/component/admin/src/Service/OrganizationsIntegrationService.php');
$membership = (string) file_get_contents($root . '/component/admin/src/Service/MembershipIntegrationService.php');

foreach ([
    'function ignoreGroup(',
    'function mergeGroup(',
    '#__xdecaropeople_duplicate_ignores',
    '#__xdecaropeople_merges',
    "'merge_source'",
    "'merge_target'",
    "person_status' => 'archived'",
    "state' => 0",
    'transactionStart()',
    'transactionCommit()',
    'transactionRollback()',
    'COM_XDECAROPEOPLE_DUPLICATE_ERROR_MULTIPLE_USERS',
] as $needle) {
    $assert(str_contains($service, $needle), 'Duplicate resolution service contract missing: ' . $needle);
}

foreach ([
    "Session::checkToken('post')",
    'function dismiss()',
    'function merge()',
] as $needle) {
    $assert(str_contains($controller, $needle), 'Duplicate controller contract missing: ' . $needle);
}

foreach ([
    'resolveCanonicalUuid',
    'resolveCanonicalId',
    '#__xdecaropeople_merges',
    'merged_from_uuid',
    'getEquivalentUuids',
] as $needle) {
    $assert(str_contains($provider, $needle), 'Merged UUID provider compatibility missing: ' . $needle);
}


foreach ([$competitions, $organizations, $membership] as $integration) {
    $assert(str_contains($integration, 'getEquivalentUuids'), 'Merged UUID aliases must be included in optional integration lookups.');
    $assert(str_contains($integration, 'PersonProviderService $people'), 'Optional integrations must receive the People provider for alias expansion.');
}

foreach ([
    'new CompetitionsIntegrationService(',
    'new OrganizationsIntegrationService(',
    'new MembershipIntegrationService(',
    '$container->get(PersonProviderService::class)',
] as $needle) {
    $assert(str_contains($di, $needle), 'Merge-aware integration DI missing: ' . $needle);
}

foreach ([
    'canMerge',
    'people.view_sensitive',
    "useStyle('com_xdecaropeople.admin')",
] as $needle) {
    $assert(str_contains($view, $needle), 'Duplicate review view contract missing: ' . $needle);
}

foreach ([
    'xdecaro-duplicate-compare',
    'COM_XDECAROPEOPLE_DUPLICATE_KEEP_AND_MERGE',
    'COM_XDECAROPEOPLE_DUPLICATE_NOT_DUPLICATE',
    'record_ids[]',
    "task=duplicate.merge",
    "task=duplicate.dismiss",
] as $needle) {
    $assert(str_contains($template, $needle), 'Duplicate review template contract missing: ' . $needle);
}

foreach ([$install, $update] as $schema) {
    foreach ([
        '#__xdecaropeople_duplicate_ignores',
        '#__xdecaropeople_merges',
        'source_uuid',
        'target_uuid',
    ] as $needle) {
        $assert(str_contains($schema, $needle), 'Duplicate resolution schema missing: ' . $needle);
    }
}

$assert(str_contains($css, '.xdecaro-duplicate-compare'), 'Responsive duplicate comparison styling missing.');
$assert(str_contains($css, '@media (max-width: 575.98px)'), 'Mobile duplicate comparison styling missing.');

echo "People 1.7.0 duplicate resolution contract OK\n";
