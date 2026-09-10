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
foreach (['p.gender', 'p.has_disability', 'p.street_number'] as $sensitiveMarker) {
    if (!str_contains($provider, $sensitiveMarker)) {
        fwrite(STDERR, "Sensitive provider projection is missing $sensitiveMarker.\n");
        exit(1);
    }
}

$serviceProvider = file_get_contents(__DIR__ . '/../component/admin/services/provider.php');
if (!str_contains($serviceProvider, '$component->setMVCFactory($container->get(MVCFactoryInterface::class));')) {
    fwrite(STDERR, "People Joomla 6 component must initialize its MVCFactory explicitly.\n");
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

$personModel = file_get_contents(__DIR__ . '/../component/admin/src/Model/PersonModel.php');
foreach (['has_disability', 'birth_date', 'gender', 'nationality_code', 'street_number'] as $marker) {
    if (!str_contains($personModel, $marker)) {
        fwrite(STDERR, "PersonModel is missing profile/privacy marker: $marker\n");
        exit(1);
    }
}
if (!str_contains($personModel, "\$data['birth_date'] = \$birthDate !== '' ? \$birthDate : null;")) {
    fwrite(STDERR, "PersonModel must normalize an empty optional birth date to SQL NULL.\n");
    exit(1);
}

$formXml = file_get_contents(__DIR__ . '/../component/admin/forms/person.xml');
foreach ([
    'type="Country" code="alpha3"',
    'type="Country" code="alpha2"',
    'layout="joomla.form.field.list-fancy-select"',
    'name="gender"',
    'name="has_disability"',
    'name="whatsapp"',
    'name="street_number"',
    'name="social"',
] as $marker) {
    if (!str_contains($formXml, $marker)) {
        fwrite(STDERR, "Person form is missing 1.1.0 marker: $marker\n");
        exit(1);
    }
}
if (str_contains($formXml, 'name="display_name"')) {
    fwrite(STDERR, "Display name must remain internal and not be user-editable.\n");
    exit(1);
}

$editTemplate = file_get_contents(__DIR__ . '/../component/admin/tmpl/person/edit.php');
foreach ([
    "HTMLHelper::_('behavior.formvalidator')",
    'name="adminForm"',
    'id="adminForm"',
    'class="form-validate"',
    'option=com_xdecaropeople&view=person&layout=edit',
    "renderFieldset('residence')",
    "renderFieldset('social')",
] as $marker) {
    if (!str_contains($editTemplate, $marker)) {
        fwrite(STDERR, "People person form is missing Joomla 6/profile requirement: $marker\n");
        exit(1);
    }
}
if (str_contains($editTemplate, 'id="person-form"')) {
    fwrite(STDERR, "People person form must use Joomla's standard adminForm id.\n");
    exit(1);
}

$assets = json_decode(file_get_contents(__DIR__ . '/../component/media/joomla.asset.json'), true, 512, JSON_THROW_ON_ERROR);
$assetNames = array_column($assets['assets'] ?? [], 'name');
if (!in_array('com_xdecaropeople.person', $assetNames, true)) {
    fwrite(STDERR, "People person JavaScript asset is not registered.\n");
    exit(1);
}

$tinScript = file_get_contents(__DIR__ . '/../component/media/js/person.js');
foreach (['ITA', 'Codice fiscale', 'FRA', 'Numéro fiscal', "label.textContent"] as $marker) {
    if (!str_contains($tinScript, $marker)) {
        fwrite(STDERR, "TIN nationality helper is missing marker: $marker\n");
        exit(1);
    }
}

$manifest = simplexml_load_file(__DIR__ . '/../component/xdecaropeople.xml');
$package = simplexml_load_file(__DIR__ . '/../package/pkg_xdecaropeople.xml');
$feed = simplexml_load_file(__DIR__ . '/../updates/pkg_xdecaropeople.xml');
if ($manifest === false || $package === false || $feed === false) {
    fwrite(STDERR, "People XML metadata is invalid.\n");
    exit(1);
}

$version = trim(file_get_contents(__DIR__ . '/../VERSION'));
$feedVersion = (string) $feed->update->version;
if ((string) $manifest->version !== $version || (string) $package->version !== $version) {
    fwrite(STDERR, "People source/package version metadata is inconsistent.\n");
    exit(1);
}
if ($feedVersion === '' || version_compare($feedVersion, $version, '>')) {
    fwrite(STDERR, "People public update feed cannot be newer than source.\n");
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
if ((string) $package->name !== 'People' || (string) $feed->update->name !== 'People') {
    fwrite(STDERR, "People visible package/update branding must be People.\n");
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
