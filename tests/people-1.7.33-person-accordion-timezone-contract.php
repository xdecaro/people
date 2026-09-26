<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $path) use ($root, &$failures): string {
    $file = $root . '/' . $path;
    if (!is_file($file)) {
        $failures[] = "Missing {$path}";
        return '';
    }

    return (string) file_get_contents($file);
};

$contains = static function (string $haystack, string $needle, string $message) use (&$failures): void {
    if (!str_contains($haystack, $needle)) {
        $failures[] = $message . " (missing: {$needle})";
    }
};

$notContains = static function (string $haystack, string $needle, string $message) use (&$failures): void {
    if (str_contains($haystack, $needle)) {
        $failures[] = $message . " (unexpected: {$needle})";
    }
};

$template = $read('component/admin/tmpl/person/edit.php');
$view = $read('component/admin/src/View/Person/HtmlView.php');
$js = $read('component/media/js/person-form.js');
$css = $read('component/media/css/person-accordion.css');
$assets = $read('component/media/joomla.asset.json');
$backup = $read('component/admin/src/Service/BackupService.php');

$contains($template, 'id="personAccordion"', 'Person edit must render one accordion container');
$contains($template, 'xdecaro-person-section', 'Person edit accordion section marker is missing');
$contains($template, 'data-bs-parent="#personAccordion"', 'Person edit accordion must keep one section open at a time');
$contains($template, 'person-section-identity', 'Identity accordion section is missing');
$contains($template, 'accordion-collapse collapse show', 'Identity section must be open initially');
$notContains($template, "HTMLHelper::_('uitab.startTabSet'", 'Person edit must no longer render Joomla tabs');

$contains($js, "closest('.accordion-collapse')", 'Validation must locate the accordion section containing an invalid field');
$contains($js, 'shown.bs.collapse', 'Validation must wait for a closed accordion section to open before focusing');
$contains($js, 'sectionLabelFor', 'Validation summary must use accordion section labels');
$contains($js, 'show.bs.collapse', 'Location popup must react to accordion opening');
$contains($js, 'hide.bs.collapse', 'Location popup must react to accordion closing');
$notContains($js, "closest('.tab-pane')", 'Person form validation must no longer depend on tab panes');

$contains($css, '.xdecaro-person-accordion', 'Person accordion styling is missing');
$contains($css, '.xdecaro-person-accordion .accordion-button', 'Person accordion button styling is missing');
$contains($assets, 'com_xdecaropeople.person-accordion', 'Person accordion stylesheet must be registered as a Joomla web asset');
$contains($view, "useStyle('com_xdecaropeople.person-accordion')", 'Person edit view must load the accordion stylesheet');

$contains($backup, "getParam('timezone'", 'Readable backup timestamp must prefer the current Joomla user timezone');
$contains($backup, "get('offset', 'UTC')", 'Readable backup timestamp must retain Joomla global timezone fallback');

if ($failures !== []) {
    fwrite(STDERR, "People 1.7.33 person accordion/timezone contract FAILED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "People 1.7.33 person accordion/timezone contract PASS\n";
