<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$config = (string) file_get_contents($root . '/component/admin/config.xml');
$template = (string) file_get_contents($root . '/component/admin/tmpl/people/default.php');
$view = (string) file_get_contents($root . '/component/admin/src/View/People/HtmlView.php');
$model = (string) file_get_contents($root . '/component/admin/src/Model/PeopleModel.php');
$installer = (string) file_get_contents($root . '/package/script.php');

if (trim((string) file_get_contents($root . '/VERSION')) !== '1.8.7') {
    throw new RuntimeException('People VERSION must be 1.8.7.');
}

foreach ([
    'name="list_view"',
    'name="list_visible_columns"',
    'value="display_name"',
    'value="birth_date"',
    'value="birth_place"',
    'value="email"',
    'value="phone"',
    'value="person_status"',
    'value="state"',
] as $marker) {
    if (!str_contains($config, $marker)) {
        throw new RuntimeException('Persistent list column option missing: ' . $marker);
    }
}

foreach ([
    'public array $visibleColumns',
    'ComponentHelper::getParams',
    'list_visible_columns',
    'isColumnVisible',
] as $marker) {
    if (!str_contains($view, $marker)) {
        throw new RuntimeException('People view column preference marker missing: ' . $marker);
    }
}

foreach ([
    "HTMLHelper::_('searchtools.sort'",
    "isColumnVisible('display_name')",
    "isColumnVisible('birth_date')",
    "isColumnVisible('birth_place')",
    "isColumnVisible('email')",
    "isColumnVisible('phone')",
    "isColumnVisible('person_status')",
    "isColumnVisible('state')",
] as $marker) {
    if (!str_contains($template, $marker)) {
        throw new RuntimeException('People sortable/visible column marker missing: ' . $marker);
    }
}

foreach (["'a.birth_date'", "'a.birth_place'", "'a.phone'", "'a.person_status'", "'a.state'"] as $marker) {
    if (!str_contains($model, $marker)) {
        throw new RuntimeException('People sortable model whitelist marker missing: ' . $marker);
    }
}

if (!str_contains($installer, "private const MINIMUM_CORE = '2.2.10';")) {
    throw new RuntimeException('People 1.8.7 must require Core 2.2.10+.');
}

echo "People 1.8.7 persistent columns and ordering contract passed.\n";
