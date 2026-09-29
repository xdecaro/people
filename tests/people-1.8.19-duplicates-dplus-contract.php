<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$template = (string) file_get_contents($root . '/component/admin/tmpl/duplicates/default.php');
$view = (string) file_get_contents($root . '/component/admin/src/View/Duplicates/HtmlView.php');
$css = (string) file_get_contents($root . '/component/media/css/duplicates-compact.css');
$js = (string) file_get_contents($root . '/component/media/js/duplicates.js');

foreach ([
    'data-duplicate-filter-form',
    'data-xdecaro-filterbar',
    'name="duplicate_search"',
    'name="duplicate_filter"',
    'name="duplicate_type"',
    'xdecaro-duplicate-summary-line1',
    'xdecaro-duplicate-summary-line2',
    'xdecaro-duplicate-summary-line3',
    'xdecaro-duplicate-name-comparison',
    'xdecaro-duplicate-open-records',
    'data-duplicate-close-group',
    'task=duplicate.merge',
    'task=duplicate.dismiss',
    'task=duplicate.dismissBatch',
] as $marker) {
    if (!str_contains($template, $marker)) {
        throw new RuntimeException('Duplicates D+ template marker missing: ' . $marker);
    }
}

if (str_contains($template, 'name="xdecaro-duplicate-review"')) {
    throw new RuntimeException('Duplicates D+ must allow more than one accordion group to stay open.');
}

foreach ([
    'public string $search',
    'public string $typeFilter',
    'duplicate_search',
    'duplicate_type',
    'mb_strtolower',
    'display_name',
] as $marker) {
    if (!str_contains($view, $marker)) {
        throw new RuntimeException('Duplicates D+ view/filter marker missing: ' . $marker);
    }
}

foreach ([
    '$sensitiveGroupTypes',
    '$searchFields',
    'array_diff($allowedTypes, $sensitiveGroupTypes)',
    "\$group['value'] = '';",
    "!in_array(\$groupType, \$sensitiveGroupTypes, true)",
] as $marker) {
    if (!str_contains($view, $marker)) {
        throw new RuntimeException('Duplicates D+ sensitive-data guard missing: ' . $marker);
    }
}

foreach ([
    '.xdecaro-duplicate-summary-line1',
    '.xdecaro-duplicate-summary-line2',
    '.xdecaro-duplicate-summary-line3',
    '.xdecaro-duplicate-open-records',
    '@media (max-width: 767.98px)',
    'grid-template-columns: 1fr',
] as $marker) {
    if (!str_contains($css, $marker)) {
        throw new RuntimeException('Duplicates D+ responsive CSS marker missing: ' . $marker);
    }
}

if (str_contains($js, 'replaceChildren(line1, line2, line3)')) {
    throw new RuntimeException('Duplicates D+ summary must be rendered server-side, not rebuilt after paint.');
}

foreach ([
    'data-duplicate-close-group',
    "closest('.xdecaro-duplicate-group')",
] as $marker) {
    if (!str_contains($js, $marker)) {
        throw new RuntimeException('Duplicates D+ interaction marker missing: ' . $marker);
    }
}

foreach (['data-duplicate-prev', 'data-duplicate-next', 'COM_XDECAROPEOPLE_DUPLICATE_PREVIOUS', 'COM_XDECAROPEOPLE_DUPLICATE_NEXT'] as $forbidden) {
    if (str_contains($template, $forbidden) || str_contains($js, $forbidden)) {
        throw new RuntimeException('Duplicates D+ must not restore sequential previous/next navigation: ' . $forbidden);
    }
}

echo "People duplicates D+ contract passed.\n";
