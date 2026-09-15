<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$personForm = file_get_contents($root . '/component/admin/forms/person.xml') ?: '';
$addressForm = file_get_contents($root . '/component/admin/forms/additional_address.xml') ?: '';
$personJs = file_get_contents($root . '/component/media/js/person-form.js') ?: '';
$css = file_get_contents($root . '/component/media/css/admin.css') ?: '';
$listTemplate = file_get_contents($root . '/component/admin/tmpl/people/default.php') ?: '';
$duplicatesService = file_get_contents($root . '/component/admin/src/Service/DuplicateService.php') ?: '';
$duplicatesTemplate = file_get_contents($root . '/component/admin/tmpl/duplicates/default.php') ?: '';
$model = file_get_contents($root . '/component/admin/src/Model/PersonModel.php') ?: '';
$it = file_get_contents($root . '/component/admin/language/it-IT/com_xdecaropeople.ini') ?: '';
$en = file_get_contents($root . '/component/admin/language/en-GB/com_xdecaropeople.ini') ?: '';

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$expect(str_contains($personJs, 'setConditionalRequired'), 'Conditional required-field helper is missing.');
$expect(str_contains($personJs, 'setConditionalRequired(disabilityOther'), 'Other disability must become required when selected.');
$expect(str_contains($personJs, 'setConditionalRequired(accessibilityOther'), 'Other accessibility need must become required when selected.');
$expect(str_contains($model, 'COM_XDECAROPEOPLE_ERROR_DISABILITY_OTHER_REQUIRED'), 'Server-side disability-other validation is missing.');
$expect(str_contains($model, 'COM_XDECAROPEOPLE_ERROR_ACCESSIBILITY_OTHER_REQUIRED'), 'Server-side accessibility-other validation is missing.');

foreach (['address_line', 'city', 'country_code'] as $field) {
    $expect(
        (bool) preg_match('/<field\s+name="' . preg_quote($field, '/') . '"[^>]*required="true"/i', $addressForm),
        "Additional address field {$field} must be required."
    );
}
$expect(str_contains($model, 'COM_XDECAROPEOPLE_ERROR_ADDITIONAL_ADDRESS_REQUIRED'), 'Server-side additional-address completeness validation is missing.');
$expect(str_contains($css, 'select[name$="[country_code]"]'), 'Additional-address country dropdown overflow selector is missing.');
$expect(str_contains($css, '.choices.is-open'), 'Fancy Select open-state overflow handling is missing.');

$expect(str_contains($listTemplate, 'xdecaro-people-filters'), 'People list filter bar wrapper is missing.');
$expect(str_contains($css, '.xdecaro-people-filters'), 'People list filter bar styles are missing.');
$expect(str_contains($css, 'width: 100%'), 'People list filter bar must span the available width.');

foreach (['email', 'tax_identifier', 'name_birth', 'name', 'phone', 'whatsapp'] as $type) {
    $expect(str_contains($duplicatesService, "'{$type}'"), "Duplicate criterion {$type} is missing.");
}
$expect(str_contains($duplicatesService, "'records'"), 'Duplicate groups must include the actual matching people.');
$expect(str_contains($duplicatesService, "'strength'"), 'Duplicate groups must expose strong/possible confidence.');
$expect(str_contains($duplicatesService, 'hasMatch'), 'Duplicate warning and duplicate page must share one matching service.');
$expect(str_contains($model, 'getDuplicateService()->hasMatch'), 'Person save warning must use DuplicateService matching.');
$expect(str_contains($duplicatesTemplate, 'person.edit&id='), 'Duplicate page must link to each matching person.');
$expect(str_contains($duplicatesTemplate, 'COM_XDECAROPEOPLE_DUPLICATE_STRENGTH'), 'Duplicate page must show confidence.');
$expect(str_contains($duplicatesTemplate, 'COM_XDECAROPEOPLE_DUPLICATE_PERSONS'), 'Duplicate page must show involved people.');

foreach ([$it, $en] as $language) {
    foreach ([
        'COM_XDECAROPEOPLE_ERROR_DISABILITY_OTHER_REQUIRED',
        'COM_XDECAROPEOPLE_ERROR_ACCESSIBILITY_OTHER_REQUIRED',
        'COM_XDECAROPEOPLE_ERROR_ADDITIONAL_ADDRESS_REQUIRED',
        'COM_XDECAROPEOPLE_DUPLICATE_STRENGTH',
        'COM_XDECAROPEOPLE_DUPLICATE_PERSONS',
        'COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_STRONG',
        'COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_POSSIBLE',
        'COM_XDECAROPEOPLE_DUPLICATE_TYPE_NAME',
        'COM_XDECAROPEOPLE_DUPLICATE_TYPE_NAME_BIRTH',
    ] as $key) {
        $expect(str_contains($language, $key . '='), "Missing translation {$key}.");
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "People 1.2.14 contract OK\n";
