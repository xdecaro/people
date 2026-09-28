<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$template = (string) file_get_contents($root . '/component/admin/tmpl/people/default.php');
$model = (string) file_get_contents($root . '/component/admin/src/Model/PeopleModel.php');
$css = (string) file_get_contents($root . '/component/media/css/people-list.css');
$view = (string) file_get_contents($root . '/component/admin/src/View/People/HtmlView.php');
$manifest = simplexml_load_file($root . '/component/xdecaropeople.xml');
$package = simplexml_load_file($root . '/package/pkg_people.xml');
$installer = (string) file_get_contents($root . '/package/script.php');

if (trim((string) file_get_contents($root . '/VERSION')) !== '1.8.6') {
    throw new RuntimeException('People VERSION must be 1.8.6.');
}
if ($manifest === false || trim((string) $manifest->version) !== '1.8.6') {
    throw new RuntimeException('People component manifest must be 1.8.6.');
}
if ($package === false || trim((string) $package->version) !== '1.8.6') {
    throw new RuntimeException('People package manifest must be 1.8.6.');
}
if (!str_contains($installer, "private const MINIMUM_CORE = '2.2.9';")) {
    throw new RuntimeException('People 1.8.6 must require Core 2.2.9+.');
}

foreach ([
    'xdecaro-filterbar--panel',
    'data-xdecaro-filterbar-toggle',
    'data-xdecaro-filterbar-open',
    'data-xdecaro-filterbar-close',
    'filter_person_status',
    'filter_state',
    'xdecaro-suite__responsive-table--striped',
    "Text::_('JSTATUS')",
] as $marker) {
    if (!str_contains($template, $marker)) {
        throw new RuntimeException('People shared list UI marker missing: ' . $marker);
    }
}
if (str_contains($template, "Text::_('JGLOBAL_FIELDSET_PUBLISHING')")) {
    throw new RuntimeException('People list technical status heading must use the compact Joomla Status label.');
}

foreach ([
    "'filter.person_status'",
    "'filter_person_status'",
    "['active', 'archived', 'deceased']",
    "a.phone') . ' LIKE :s5'",
] as $marker) {
    if (!str_contains($model, $marker)) {
        throw new RuntimeException('People list model filter/search marker missing: ' . $marker);
    }
}

foreach ([
    '.xdecaro-people-filters',
    '.xdecaro-people-filter-search',
    '.xdecaro-people-filter-state',
    '.xdecaro-people-filter-submit',
] as $localLayoutSelector) {
    if (str_contains($css, $localLayoutSelector)) {
        throw new RuntimeException('People must not duplicate shared Core filter layout CSS: ' . $localLayoutSelector);
    }
}
if (!str_contains($css, 'a.xdecaro-people-publication-link.is-success') || !str_contains($css, 'color: var(--xdecaro-color-success) !important;')) {
    throw new RuntimeException('Published icon must preserve the semantic green color against Joomla link states.');
}

if (!str_contains($view, "load('com_xdecaropeople.list'")) {
    throw new RuntimeException('People list-specific multilingual strings must be loaded through Joomla Language.');
}
foreach (['en-GB', 'it-IT'] as $tag) {
    $path = $root . '/component/admin/language/' . $tag . '/com_xdecaropeople.list.ini';
    if (!is_file($path)) {
        throw new RuntimeException('Missing list language file: ' . $tag);
    }
    $language = (string) file_get_contents($path);
    foreach (['COM_XDECAROPEOPLE_SEARCH_PLACEHOLDER', 'COM_XDECAROPEOPLE_FILTERS', 'COM_XDECAROPEOPLE_FILTERS_CLOSE', 'COM_XDECAROPEOPLE_FILTER_PERSON_STATUS_ALL', 'COM_XDECAROPEOPLE_FILTER_STATE_ALL'] as $key) {
        if (!str_contains($language, $key . '=')) {
            throw new RuntimeException('Missing ' . $tag . ' list language key: ' . $key);
        }
    }
}

echo "People 1.8.6 shared list UI contract passed.\n";
