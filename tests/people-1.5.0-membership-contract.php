<?php
$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));
if (version_compare($version, '1.5.0', '<')) {
    fwrite(STDERR, "People 1.5.0 or newer expected.\n");
    exit(1);
}

$bridge = (string) file_get_contents($root . '/component/admin/src/Service/MembershipIntegrationService.php');
$component = (string) file_get_contents($root . '/component/admin/src/Extension/PeopleComponent.php');
$provider = (string) file_get_contents($root . '/component/admin/services/provider.php');
$view = (string) file_get_contents($root . '/component/admin/src/View/Person/HtmlView.php');
$template = (string) file_get_contents($root . '/component/admin/tmpl/person/edit.php');
$duplicates = (string) file_get_contents($root . '/component/admin/src/Service/DuplicateService.php');

foreach ([
    'membership.person_memberships',
    'getPersonMembershipService',
    'getMembershipsByPersonUuid',
] as $needle) {
    if (!str_contains($bridge, $needle)) {
        fwrite(STDERR, "Membership bridge missing {$needle}.\n");
        exit(1);
    }
}
if (str_contains($bridge, '#__decaromembership_')) {
    fwrite(STDERR, "People must not query Membership private tables.\n");
    exit(1);
}
foreach (['setMembershipIntegrationService','getMembershipIntegrationService'] as $needle) {
    if (!str_contains($component, $needle) || !str_contains($provider, 'MembershipIntegrationService')) {
        fwrite(STDERR, "Membership DI surface incomplete: {$needle}.\n");
        exit(1);
    }
}
if (!str_contains($view, 'membershipAvailable') || !str_contains($template, 'COM_XDECAROPEOPLE_MEMBERSHIP_TAB')) {
    fwrite(STDERR, "Read-only Membership tab contract missing.\n");
    exit(1);
}
foreach (["'email', 'possible'","'phone', 'possible'","'whatsapp', 'possible'"] as $needle) {
    if (!str_contains($duplicates, $needle)) {
        fwrite(STDERR, "Contact duplicate must remain a secondary signal: {$needle}.\n");
        exit(1);
    }
}

echo "People 1.5.0+ Membership integration compatibility contract OK\n";
