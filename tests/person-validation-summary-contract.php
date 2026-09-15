<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$js = file_get_contents($root . '/component/media/js/person-form.js');
$view = file_get_contents($root . '/component/admin/src/View/Person/HtmlView.php');
$it = file_get_contents($root . '/component/admin/language/it-IT/com_xdecaropeople.ini');
$en = file_get_contents($root . '/component/admin/language/en-GB/com_xdecaropeople.ini');

$assertContains = static function (string $needle, string $haystack, string $message): void {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assertContains('const collectInvalidFields =', $js, 'Missing invalid-field collector.');
$assertContains('const showValidationSummary =', $js, 'Missing validation summary renderer.');
$assertContains("querySelectorAll(':invalid')", $js, 'Validation summary must include native/Joomla invalid fields.');
$assertContains('validationSummaryTitle', $view, 'View must expose validation summary title to JavaScript.');
$assertContains('validationSummaryIntro', $view, 'View must expose validation summary intro to JavaScript.');
$assertContains('COM_XDECAROPEOPLE_VALIDATION_SUMMARY_TITLE="Impossibile salvare: controlla i campi indicati."', $it, 'Missing Italian validation summary title.');
$assertContains('COM_XDECAROPEOPLE_VALIDATION_SUMMARY_INTRO="Controlla:"', $it, 'Missing Italian validation summary intro.');
$assertContains('COM_XDECAROPEOPLE_VALIDATION_SUMMARY_TITLE="Unable to save: check the indicated fields."', $en, 'Missing English validation summary title.');
$assertContains('COM_XDECAROPEOPLE_VALIDATION_SUMMARY_INTRO="Check:"', $en, 'Missing English validation summary intro.');

echo "person validation summary contract: OK\n";
