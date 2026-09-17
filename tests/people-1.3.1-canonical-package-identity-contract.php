<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));

if ($version !== '1.3.1') {
    fwrite(STDERR, "People canonical package/identity-details release must be 1.3.1.\n");
    exit(1);
}

$requiredFiles = [
    $root . '/package/pkg_people.xml',
    $root . '/package/script.php',
    $root . '/updates/pkg_people.xml',
    $root . '/build/build.sh',
    $root . '/component/admin/access.xml',
    $root . '/component/admin/src/Service/PersonProviderService.php',
];
foreach ($requiredFiles as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing People 1.3.1 canonical file: {$path}\n");
        exit(1);
    }
}

$manifest = (string) file_get_contents($root . '/package/pkg_people.xml');
$installer = (string) file_get_contents($root . '/package/script.php');
$feed = (string) file_get_contents($root . '/updates/pkg_people.xml');
$build = (string) file_get_contents($root . '/build/build.sh');
$access = (string) file_get_contents($root . '/component/admin/access.xml');
$provider = (string) file_get_contents($root . '/component/admin/src/Service/PersonProviderService.php');

$checks = [
    [$manifest, '<packagename>people</packagename>', 'People package must use packagename people.'],
    [$manifest, 'updates/pkg_people.xml', 'People package must register the canonical update feed.'],
    [$feed, '<element>pkg_people</element>', 'People update feed must identify pkg_people.'],
    [$feed, 'pkg_people_1.3.1.zip', 'People update feed must publish pkg_people_1.3.1.zip.'],
    [$build, 'pkg_people_${VERSION}.zip', 'People build must create the canonical package ZIP.'],
    [$installer, 'pkg_peopleInstallerScript', 'People installer class must follow the canonical package identity.'],
    [$installer, 'pkg_xdecaropeople', 'People installer must recognize the legacy package during migration.'],
    [$installer, 'package_id', 'People package migration must verify child ownership before retiring the legacy package.'],
    [$access, 'people.view_identity_details', 'People ACL must expose the limited identity-details permission.'],
    [$provider, 'searchPeopleForIdentity', 'People provider must expose a dedicated identity-disambiguation search method.'],
    [$provider, 'people.view_identity_details', 'Identity-disambiguation provider access must use the limited ACL action.'],
];

foreach ($checks as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

if (!preg_match('/private function identityColumns\(\): array\s*\{(.*?)\n\s*\}\n\n\s*private function columns/s', $provider, $matches)) {
    fwrite(STDERR, "Unable to isolate People identityColumns().\n");
    exit(1);
}
$identityBody = $matches[1];
if (!str_contains($identityBody, "'birth_date'") || !str_contains($identityBody, "'birth_place'")) {
    fwrite(STDERR, "People identity-details provider must explicitly allow only birth date/place identity fields.\n");
    exit(1);
}
foreach (['disability_status', 'tax_identifier', 'address_line', 'accessibility_needs'] as $forbidden) {
    if (str_contains($identityBody, $forbidden)) {
        fwrite(STDERR, "People identity-details allowlist contains unrelated sensitive field: {$forbidden}\n");
        exit(1);
    }
}

echo "People 1.3.1 canonical package and identity-details contract OK\n";
