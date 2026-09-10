<?php
$core = file_get_contents(__DIR__ . '/../component/admin/src/Service/CoreIntegrationService.php');
foreach ([
    "MINIMUM_CORE='1.4.0'",
    'people.provider',
    'people.query',
    'people.user_link',
    'people.duplicates',
    'CapabilityRegistry',
    'EntityReference',
] as $marker) {
    if (!str_contains($core, $marker)) {
        fwrite(STDERR, "Missing $marker\n");
        exit(1);
    }
}

$provider = file_get_contents(__DIR__ . '/../component/admin/src/Service/PersonProviderService.php');
if (str_contains($provider, '#__decaro') && !str_contains($provider, '#__xdecaropeople_')) {
    fwrite(STDERR, "People provider crossed a private legacy-table boundary.\n");
    exit(1);
}

$model = file_get_contents(__DIR__ . '/../component/admin/src/Model/PeopleModel.php');
if (str_contains($model, '->getApplication()')) {
    fwrite(STDERR, "PeopleModel must not call the removed model getApplication() method.\n");
    exit(1);
}
if (!str_contains($model, '$this->getUserStateFromRequest(')) {
    fwrite(STDERR, "PeopleModel must use Joomla ListModel state handling.\n");
    exit(1);
}

$manifest = simplexml_load_file(__DIR__ . '/../component/xdecaropeople.xml');
$package = simplexml_load_file(__DIR__ . '/../package/pkg_xdecaropeople.xml');
$feed = simplexml_load_file(__DIR__ . '/../updates/pkg_xdecaropeople.xml');
if ($manifest === false || $package === false || $feed === false) {
    fwrite(STDERR, "People XML metadata is invalid.\n");
    exit(1);
}

$version = trim(file_get_contents(__DIR__ . '/../VERSION'));
if ((string) $manifest->version !== $version || (string) $package->version !== $version || (string) $feed->update->version !== $version) {
    fwrite(STDERR, "People version metadata is inconsistent.\n");
    exit(1);
}
if ((string) $manifest->targetplatform['version'] !== '6.*' || (string) $package->targetplatform['version'] !== '6.*') {
    fwrite(STDERR, "People manifests must be Joomla 6 only.\n");
    exit(1);
}
if ((string) $feed->update->targetplatform['version'] !== '6\\.[0-9]+') {
    fwrite(STDERR, "People update feed must target Joomla 6 only.\n");
    exit(1);
}
if ((string) $manifest->author !== 'Luca De Caro' || (string) $package->author !== 'Luca De Caro') {
    fwrite(STDERR, "People manifest author metadata changed unexpectedly.\n");
    exit(1);
}

$languages = [];
foreach ($manifest->administration->languages->language as $language) {
    $languages[] = (string) $language;
}
foreach ([
    'en-GB/com_xdecaropeople.ini',
    'en-GB/com_xdecaropeople.sys.ini',
    'it-IT/com_xdecaropeople.ini',
    'it-IT/com_xdecaropeople.sys.ini',
] as $requiredLanguage) {
    if (!in_array($requiredLanguage, $languages, true)) {
        fwrite(STDERR, "Missing manifest language: $requiredLanguage\n");
        exit(1);
    }
}

foreach (['en-GB', 'it-IT'] as $tag) {
    $sys = file_get_contents(__DIR__ . '/../component/admin/language/' . $tag . '/com_xdecaropeople.sys.ini');
    if (!str_contains($sys, 'COM_XDECAROPEOPLE="People"')) {
        fwrite(STDERR, "Administrator menu label must be People for $tag.\n");
        exit(1);
    }
    if (!str_contains($sys, 'COM_XDECAROPEOPLE_XML_DESCRIPTION=')) {
        fwrite(STDERR, "Missing XML description language key for $tag.\n");
        exit(1);
    }
}

echo "People integration and Joomla 6 regression smoke OK\n";
