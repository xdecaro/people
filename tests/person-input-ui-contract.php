<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$js = file_get_contents($root . '/component/media/js/person-form.js') ?: '';
$css = file_get_contents($root . '/component/media/css/admin.css') ?: '';
$model = file_get_contents($root . '/component/admin/src/Model/PersonModel.php') ?: '';
$field = file_get_contents($root . '/component/admin/src/Field/PersonField.php') ?: '';
$form = file_get_contents($root . '/component/admin/forms/person.xml') ?: '';
$template = file_get_contents($root . '/component/admin/tmpl/person/edit.php') ?: '';

$failures = [];

$expectContains = static function (string $haystack, string $needle, string $message) use (&$failures): void {
    if (!str_contains($haystack, $needle)) {
        $failures[] = $message;
    }
};

$expectContains($js, 'const formatBirthDateInput =', 'Birth date numeric input formatter is missing.');
$expectContains($js, 'initBirthDateInput();', 'Birth date formatter is not initialized.');
$expectContains($form, 'name="birth_date" type="calendar"', 'Birth date calendar field is missing.');
$expectContains($form, 'inputmode="numeric"', 'Birth date field must request a numeric keyboard.');

$expectContains($model, "foreach (['phone', 'whatsapp'] as \$field)", 'Phone and WhatsApp normalization loop is missing.');
$expectContains($model, 'normalizePhoneNumber', 'Server-side phone normalization helper is missing.');
$expectContains($js, 'initPhoneNormalization();', 'Client-side phone normalization is not initialized.');

$expectContains($css, 'flex-flow:row nowrap', 'Person edit tabs must stay on one row.');
$expectContains($css, 'overflow-x:auto', 'Person edit tabs must scroll horizontally when needed.');
$expectContains($css, ':has(select[name*="[person_uuid]"])', 'Relations subform overflow fix is not scoped to person selectors.');
$expectContains($css, '.choices.is-open', 'Relations subform overflow fix must activate only while Fancy Select is open.');

$expectContains($field, "getInt('id')", 'Person selector does not detect the person currently being edited.');
$expectContains($field, "ParameterType::INTEGER", 'Person selector does not bind the current person id safely.');
$expectContains($field, "<> :currentId", 'Person selector does not exclude the current person.');

$expectContains($template, 'xdecaro-person-heading', 'Person edit heading above tabs is missing.');
$expectContains($template, '$this->item->first_name', 'Person edit heading must use the current first name.');
$expectContains($template, '$this->item->last_name', 'Person edit heading must use the current last name.');
$expectContains($template, "Text::_('COM_XDECAROPEOPLE_PERSON_NEW')", 'Person edit heading must fall back to the new-person label.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "People input/UI contract OK\n";
