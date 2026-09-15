<?php

declare(strict_types=1);

defined('_JEXEC') || define('_JEXEC', 1);

$root = dirname(__DIR__);

$core = file_get_contents($root . '/component/admin/src/Service/CoreIntegrationService.php') ?: '';
foreach ([
    "MINIMUM_CORE = '2.0.1'",
    'people.provider',
    'people.query',
    'people.user_link',
    'people.duplicates',
    'people.profile',
    'people.relations',
    'people.accessibility',
    'people.world_locations',
    'people.document_reference',
    'CapabilityRegistry',
    'EntityReference',
] as $marker) {
    if (!str_contains($core, $marker)) {
        fwrite(STDERR, "Missing Core integration marker: {$marker}\n");
        exit(1);
    }
}

$packageScript = file_get_contents($root . '/package/script.php') ?: '';
if (!str_contains($packageScript, "MINIMUM_CORE='2.0.1'")) {
    fwrite(STDERR, "People package must require Core 2.0.1 or later.\n");
    exit(1);
}

$provider = file_get_contents($root . '/component/admin/src/Service/PersonProviderService.php') ?: '';
if (str_contains($provider, '#__decaro') && !str_contains($provider, '#__xdecaropeople_')) {
    fwrite(STDERR, "People provider crossed a private legacy-table boundary.\n");
    exit(1);
}
foreach (['p.whatsapp', 'p.sex', 'p.disability_status', 'p.address_number', 'p.social_instagram'] as $marker) {
    if (!str_contains($provider, $marker)) {
        fwrite(STDERR, "People provider missing profile field: {$marker}\n");
        exit(1);
    }
}

$serviceProvider = file_get_contents($root . '/component/admin/services/provider.php') ?: '';
foreach ([
    '$component->setMVCFactory($container->get(MVCFactoryInterface::class));',
    '$component->setCoreIntegrationService($container->get(CoreIntegrationService::class));',
    '$component->setPersonProviderService($container->get(PersonProviderService::class));',
    '$component->setDuplicateService($container->get(DuplicateService::class));',
] as $marker) {
    if (!str_contains($serviceProvider, $marker)) {
        fwrite(STDERR, "People Joomla 6 service provider missing marker: {$marker}\n");
        exit(1);
    }
}

$listModel = file_get_contents($root . '/component/admin/src/Model/PeopleModel.php') ?: '';
if (str_contains($listModel, '->getApplication()')) {
    fwrite(STDERR, "PeopleModel must not call removed model getApplication().\n");
    exit(1);
}
if (!str_contains($listModel, '$this->getUserStateFromRequest(')) {
    fwrite(STDERR, "PeopleModel must use Joomla ListModel state handling.\n");
    exit(1);
}

$personModel = file_get_contents($root . '/component/admin/src/Model/PersonModel.php') ?: '';
foreach ([
    "array_key_exists('birth_date', \$data)",
    "\$data['birth_date'] = \$birthDate !== '' ? \$birthDate : null;",
    "foreach (['phone', 'whatsapp'] as \$field)",
    'validateProfileCompleteness',
    'DuplicateService',
] as $marker) {
    if (!str_contains($personModel, $marker)) {
        fwrite(STDERR, "PersonModel missing current normalization/validation marker: {$marker}\n");
        exit(1);
    }
}

$form = file_get_contents($root . '/component/admin/forms/person.xml') ?: '';
foreach ([
    'addfieldprefix="xdecaro\\Component\\People\\Administrator\\Field"',
    'name="sex"',
    'name="disability_status"',
    'name="disability_types"',
    'name="accessibility_needs"',
    'name="nationality_codes" type="Country" code="alpha3"',
    'name="nationality_code" type="hidden"',
    'name="birth_country_code" type="Country" code="alpha2"',
    'name="birth_place_id"',
    'name="tax_identifier" type="text" label="COM_XDECAROPEOPLE_FIELD_TIN"',
    'name="country_code" type="Country" code="alpha2"',
    'name="additional_addresses" type="subform"',
    'name="relations_data" type="subform"',
] as $marker) {
    if (!str_contains($form, $marker)) {
        fwrite(STDERR, "Person form missing current marker: {$marker}\n");
        exit(1);
    }
}
if (str_contains($form, 'name="display_name"')) {
    fwrite(STDERR, "Display name must remain generated internally.\n");
    exit(1);
}

$editTemplate = file_get_contents($root . '/component/admin/tmpl/person/edit.php') ?: '';
foreach ([
    "HTMLHelper::_('behavior.formvalidator')",
    'name="adminForm"',
    'id="adminForm"',
    'class="form-validate"',
    'xdecaro-person-heading',
    "'documents_tax'",
    "'system'",
] as $marker) {
    if (!str_contains($editTemplate, $marker)) {
        fwrite(STDERR, "People person form missing Joomla 6/current UI marker: {$marker}\n");
        exit(1);
    }
}

$personScript = file_get_contents($root . '/component/media/js/person-form.js') ?: '';
foreach ([
    'formatBirthDateInput',
    'initPhoneNormalization',
    'setConditionalRequired',
    'validateAllLocations',
] as $marker) {
    if (!str_contains($personScript, $marker)) {
        fwrite(STDERR, "Person form script missing current marker: {$marker}\n");
        exit(1);
    }
}

require_once $root . '/component/admin/src/Service/CountryMetadata.php';
use xdecaro\Component\People\Administrator\Service\CountryMetadata;
if (!CountryMetadata::isAlpha2('IT') || !CountryMetadata::isAlpha3('ITA')) {
    fwrite(STDERR, "Country metadata does not validate ISO country codes.\n");
    exit(1);
}

$manifest = simplexml_load_file($root . '/component/xdecaropeople.xml');
$package = simplexml_load_file($root . '/package/pkg_xdecaropeople.xml');
$feed = simplexml_load_file($root . '/updates/pkg_xdecaropeople.xml');
if ($manifest === false || $package === false || $feed === false) {
    fwrite(STDERR, "People XML metadata is invalid.\n");
    exit(1);
}

$version = trim((string) file_get_contents($root . '/VERSION'));
$feedVersion = trim((string) $feed->update->version);
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
    fwrite(STDERR, "People visible package/update branding must remain People.\n");
    exit(1);
}

$assets = json_decode(file_get_contents($root . '/component/media/joomla.asset.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
if (($assets['version'] ?? '') !== $version) {
    fwrite(STDERR, "People Web Asset version must match VERSION.\n");
    exit(1);
}
$assetNames = array_column($assets['assets'] ?? [], 'name');
foreach (['com_xdecaropeople.admin', 'com_xdecaropeople.person-form', 'com_xdecaropeople.person-cancel-fix'] as $asset) {
    if (!in_array($asset, $assetNames, true)) {
        fwrite(STDERR, "Missing People Web Asset: {$asset}\n");
        exit(1);
    }
}

foreach (['en-GB', 'it-IT'] as $tag) {
    $sys = file_get_contents($root . '/component/admin/language/' . $tag . '/com_xdecaropeople.sys.ini') ?: '';
    $ini = file_get_contents($root . '/component/admin/language/' . $tag . '/com_xdecaropeople.ini') ?: '';
    if (!str_contains($sys, 'COM_XDECAROPEOPLE="People"') || !str_contains($ini, 'COM_XDECAROPEOPLE="People"')) {
        fwrite(STDERR, "Administrator product label must be People for {$tag}.\n");
        exit(1);
    }
}

echo "People integration and Joomla 6 profile regression smoke OK\n";
