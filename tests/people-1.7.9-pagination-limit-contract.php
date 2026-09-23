<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$contains = static function (string $path, string $needle) use ($fail): void {
    $content = file_get_contents($path);
    if ($content === false || !str_contains($content, $needle)) {
        $fail('Missing People 1.7.9 pagination contract in ' . $path . ': ' . $needle);
    }
};

if (version_compare($version, '1.7.9', '<')) {
    $fail('People 1.7.9+ version expected.');
}

$model = $root . '/component/admin/src/Model/PeopleModel.php';
$template = $root . '/component/admin/tmpl/people/default.php';
$css = $root . '/component/media/css/admin.css';

foreach ([
    $root . '/component/admin/sql/updates/mysql/1.7.9.sql',
    $model,
    $template,
    $css,
] as $path) {
    if (!is_file($path)) {
        $fail('Missing People 1.7.9 file: ' . $path);
    }
}

if ($version === '1.7.9') {
    foreach ([
        '$allowedLimits = [0, 20, 50, 100, 200, 500];',
        '$limitChoices = [20, 50, 100, 200, 500, 0];',
        "Text::sprintf('COM_XDECAROPEOPLE_PAGINATION_ALL'",
    ] as $needle) {
        $contains(str_starts_with($needle, '$allowedLimits') ? $model : $template, $needle);
    }
} elseif (version_compare($version, '1.7.12', '<')) {
    foreach ([
        '$allowedLimits = [20, 50, 100, 200, 500];',
        "\$this->setState('list.limit', \$limit);",
        "\$this->setState('list.start', (int) (floor(\$start / \$limit) * \$limit));",
    ] as $needle) {
        $contains($model, $needle);
    }

    foreach ([
        '$limitChoices = [20, 50, 100, 200, 500];',
        'name="list[limit]"',
        'xdecaro-people-page-size',
        'getListFooter()',
    ] as $needle) {
        $contains($template, $needle);
    }

    $templateContent = (string) file_get_contents($template);
    if (str_contains($templateContent, '$limitChoices = [20, 50, 100, 200, 500, 0];')
        || str_contains($templateContent, 'COM_XDECAROPEOPLE_PAGINATION_ALL')) {
        $fail('People 1.7.10-1.7.11 must not expose the All page-size option.');
    }
} else {
    foreach ([
        '$allowedLimits = [0, 20, 50, 100, 200, 500, 1000];',
        "\$this->setState('list.limit', \$limit);",
        "\$this->setState('list.start', \$limit === 0 ? 0",
    ] as $needle) {
        $contains($model, $needle);
    }

    foreach ([
        '$limitChoices = [20, 50, 100, 200, 500, 1000, 0];',
        'name="list[limit]"',
        'xdecaro-people-page-size',
        "Text::sprintf('COM_XDECAROPEOPLE_PAGINATION_ALL'",
        'getListFooter()',
    ] as $needle) {
        $contains($template, $needle);
    }
}

foreach ([
    '.xdecaro-people-pagination-footer',
    '.xdecaro-people-page-size .form-select',
    '@media (max-width: 767.98px)',
] as $needle) {
    $contains($css, $needle);
}

foreach ([
    'COM_XDECAROPEOPLE_PAGINATION_SHOW=',
    'COM_XDECAROPEOPLE_PAGINATION_PER_PAGE=',
    'COM_XDECAROPEOPLE_PAGINATION_ALL=',
] as $key) {
    $contains($root . '/component/admin/language/it-IT/com_xdecaropeople.ini', $key);
    $contains($root . '/component/admin/language/en-GB/com_xdecaropeople.ini', $key);
}

$componentXml = simplexml_load_file($root . '/component/xdecaropeople.xml');
$packageXml = simplexml_load_file($root . '/package/pkg_people.xml');
$assets = json_decode((string) file_get_contents($root . '/component/media/joomla.asset.json'), true, 512, JSON_THROW_ON_ERROR);

if ((string) $componentXml->version !== $version || (string) $packageXml->version !== $version) {
    $fail('People manifests must match the current version.');
}

if (($assets['version'] ?? '') !== $version) {
    $fail('People web assets must match the current version.');
}

foreach (($assets['assets'] ?? []) as $item) {
    if (($item['version'] ?? '') !== $version) {
        $fail('Every People web asset must match the current version.');
    }
}


if (version_compare($version, '1.7.11', '>=')) {
    $modelContent = (string) file_get_contents($model);

    foreach ([
        '$limit = (int) $this->state->get(\'list.limit\', 20);',
        '$start = max(0, (int) $this->state->get(\'list.start\', 0));',
    ] as $needle) {
        if (!str_contains($modelContent, $needle)) {
            $fail('People 1.7.11 must read list state directly from the Registry inside populateState.');
        }
    }

    if (str_contains($modelContent, '$this->getState(\'list.limit\', 20)')
        || str_contains($modelContent, '$this->getState(\'list.start\', 0)')) {
        $fail('People 1.7.11 must not call getState() from populateState because that recurses.');
    }
}

echo "People 1.7.9+ page-size pagination contract OK\n";
