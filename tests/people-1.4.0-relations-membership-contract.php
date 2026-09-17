<?php
$root = dirname(__DIR__);

$version = trim((string) file_get_contents($root . '/VERSION'));
if ($version !== '1.4.0') {
    fwrite(STDERR, "People 1.4.0 version expected.\n");
    exit(1);
}

$form = (string) file_get_contents($root . '/component/admin/forms/person_relation.xml');
$model = (string) file_get_contents($root . '/component/admin/src/Model/PersonModel.php');
$field = (string) file_get_contents($root . '/component/admin/src/Field/PersonField.php');
$duplicates = (string) file_get_contents($root . '/component/admin/src/Service/DuplicateService.php');
$provider = (string) file_get_contents($root . '/component/admin/src/Service/PersonProviderService.php');
$membership = (string) file_get_contents($root . '/component/admin/src/Service/MembershipIntegrationService.php');
$view = (string) file_get_contents($root . '/component/admin/tmpl/person/edit.php');

foreach (['curator','support_administrator','legal_representative','valid_from','valid_to','status'] as $needle) {
    if (!str_contains($form, $needle)) {
        fwrite(STDERR, "Missing relation field/type: {$needle}\n");
        exit(1);
    }
}
foreach (['normalizeRelationDate','COM_XDECAROPEOPLE_ERROR_RELATION_DATES'] as $needle) {
    if (!str_contains($model, $needle)) {
        fwrite(STDERR, "Missing relation validation: {$needle}\n");
        exit(1);
    }
}
if (!str_contains($field, 'birth_date') || !str_contains($field, 'birth_place')) {
    fwrite(STDERR, "People relation selector must display birth identity details.\n");
    exit(1);
}
foreach (["'email', 'possible'","'phone', 'possible'","'whatsapp', 'possible'"] as $needle) {
    if (!str_contains($duplicates, $needle)) {
        fwrite(STDERR, "Secondary duplicate signal must remain non-blocking: {$needle}\n");
        exit(1);
    }
}
foreach (['function personExists','function getRelations','function getCurrentAddress'] as $needle) {
    if (!str_contains($provider, $needle)) {
        fwrite(STDERR, "Missing public People provider method: {$needle}\n");
        exit(1);
    }
}
if (!str_contains($membership, 'membership.person_memberships') || !str_contains($view, 'COM_XDECAROPEOPLE_MEMBERSHIP_TAB')) {
    fwrite(STDERR, "Membership optional integration contract missing.\n");
    exit(1);
}
if (str_contains($membership, '#__decaromembership_')) {
    fwrite(STDERR, "People must not read Membership private tables.\n");
    exit(1);
}

echo "People 1.4.0 relations and Membership integration contract OK\n";
