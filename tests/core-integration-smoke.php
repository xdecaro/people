<?php

defined('_JEXEC') || define('_JEXEC', 1);

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
foreach (['p.whatsapp', 'p.sex', 'p.disability_status', 'p.address_number', 'p.social_instagram'] as $marker) {
    if (!str_contains($provider, $marker)) {
        fwrite(STDERR, "People provider missing profile field: $marker\n");
        exit(1);
    }
}

$serviceProvider = file_get_contents(__DIR__ . '/../component/admin/services/provider.php');
if (!str_contains($serviceProvider, '$component->setMVCFactory($container->get(MVCFactoryInterface::class));')) {
    fwrite(STDERR, "People Joomla 6 component must initialize its MVCFactory explicitly.\n");
    exit(1);
}

$listModel = file_get_contents(__DIR__ . '/../component/admin/src/Model/PeopleModel.php');
if (str_contains($listModel, '->getApplication()')) {
    fwrite(STDERR, "PeopleModel must not call the removed model getApplication() method.\n");
    exit(1);
}
if (!str_contains($listModel, '$this->getUserStateFromRequest(')) {
    fwrite(STDERR, "PeopleModel must use Joomla ListModel state handling.\n");
    exit(1);
}

$personModel = file_get_contents(__DIR__ . '/../component/admin/src/Model/PersonModel.php');
foreach ([
    "array_key_exists('birth_date', \$data)",
    "\$data['birth_date'] = \$birthDate !== '' ? \$birthDate : null;",
    "'disability_status'",
    "'address_number'",
] as $marker) {
    if (!str_contains($personModel, $marker)) {
        fwrite(STDERR, "PersonModel missing profile normalization/ACL marker: $marker\n");
        exit(1);
    }
}

$form = file_get_contents(__DIR__ . '/../component/admin/forms/person.xml');
foreach ([
    'addfieldprefix="xdecaro\\Component\\People\\Administrator\\Field"',
    'name="sex"',
    'name="disability_status"',
    'name="nationality_code" type="Country" code="alpha3"',
    'layout="joomla.form.field.list-fancy-select"',
    'name="tax_identifier" type="text" label="COM_XDECAROPEOPLE_FIELD_TIN"',
    'name="whatsapp"',
    'fieldset name="residence"',
    'name="address_number"',
    'name="country_code" type="Country" code="alpha2"',
    'fieldset name="social"',
    'name="social_instagram"',
    'name="social_youtube"',
] as $marker) {
    if (!str_contains($form, $marker)) {
        fwrite(STDERR, "Person form missing 1.1.0 marker: $marker\n");
        exit(1);
    }
}
if (str_contains($form, 'name="display_name"')) {
    fwrite(STDERR, "Display name must be generated internally, not editable in the person form.\n");
    exit(1);
}
if (!str_contains($form, 'name="disability_status" type="list" label="COM_XDECAROPEOPLE_FIELD_DISABILITY" filter="string"')) {
    fwrite(STDERR, "Disability choice must preserve the nullable blank value.\n");
    exit(1);
}

$editTemplate = file_get_contents(__DIR__ . '/../component/admin/tmpl/person/edit.php');
foreach ([
    "HTMLHelper::_('behavior.formvalidator')",
    'name="adminForm"',
    'id="adminForm"',
    'class="form-validate"',
    'option=com_xdecaropeople&view=person&layout=edit',
    "'residence'",
    "'social'",
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

$personScript = file_get_contents(__DIR__ . '/../component/media/js/person-form.js');
foreach (['jform_nationality_code', 'jform_tax_identifier', 'tinLabels', 'updateTinLabel'] as $marker) {
    if (!str_contains($personScript, $marker)) {
        fwrite(STDERR, "Person TIN script missing marker: $marker\n");
        exit(1);
    }
}

require_once __DIR__ . '/../component/admin/src/Service/CountryMetadata.php';
use xdecaro\Component\People\Administrator\Service\CountryMetadata;
if (!CountryMetadata::isAlpha2('IT') || !CountryMetadata::isAlpha3('ITA')) {
    fwrite(STDERR, "Country metadata does not validate ISO country codes.\n");
    exit(1);
}
$tinLabels = CountryMetadata::tinLabels();
if (($tinLabels['ITA'] ?? '') !== 'Codice fiscale' || ($tinLabels['FRA'] ?? '') !== 'Numéro fiscal') {
    fwrite(STDERR, "European TIN labels are missing required Italy/France mappings.\n");
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

$mediaFolders = [];
foreach ($manifest->media->folder as $folder) {
    $mediaFolders[] = (string) $folder;
}
if (!in_array('js', $mediaFolders, true)) {
    fwrite(STDERR, "People manifest must install JavaScript media.\n");
    exit(1);
}

$assets = json_decode(file_get_contents(__DIR__ . '/../component/media/joomla.asset.json'), true, 512, JSON_THROW_ON_ERROR);
if (($assets['version'] ?? '') !== $version) {
    fwrite(STDERR, "People Web Asset version must match VERSION.\n");
    exit(1);
}
$assetNames = array_column($assets['assets'] ?? [], 'name');
if (!in_array('com_xdecaropeople.person-form', $assetNames, true)) {
    fwrite(STDERR, "People person-form Web Asset is not registered.\n");
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
    $ini = file_get_contents(__DIR__ . '/../component/admin/language/' . $tag . '/com_xdecaropeople.ini');
    if (!str_contains($sys, 'COM_XDECAROPEOPLE="People"') || !str_contains($ini, 'COM_XDECAROPEOPLE="People"')) {
        fwrite(STDERR, "Administrator visible product label must be People for $tag.\n");
        exit(1);
    }
    if (!str_contains($sys, 'COM_XDECAROPEOPLE_XML_DESCRIPTION=')) {
        fwrite(STDERR, "Missing XML description language key for $tag.\n");
        exit(1);
    }
}

$installer = file_get_contents(__DIR__ . '/../component/script.php');
foreach (['disability_status', 'whatsapp', 'address_number', 'social_instagram', 'website_url'] as $column) {
    if (!str_contains($installer, "'$column' =>")) {
        fwrite(STDERR, "Installer repair missing 1.1.0 column: $column\n");
        exit(1);
    }
}

echo "People integration and Joomla 6 profile regression smoke OK\n";
