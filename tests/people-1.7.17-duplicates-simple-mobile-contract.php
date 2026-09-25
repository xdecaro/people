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
        $fail('Missing People 1.7.17 simple mobile duplicate contract in ' . $path . ': ' . $needle);
    }
};

if ($version !== '1.7.17') {
    $fail('People 1.7.17 version expected.');
}

$template = $root . '/component/admin/tmpl/duplicates/default.php';
$css = $root . '/component/media/css/admin.css';
$js = $root . '/component/media/js/duplicates.js';

foreach ([
    $root . '/component/admin/sql/updates/mysql/1.7.17.sql',
    $template,
    $css,
    $js,
] as $path) {
    if (!is_file($path)) {
        $fail('Missing People 1.7.17 file: ' . $path);
    }
}

foreach ([
    'xdecaro-duplicate-mobile-record-summary',
    'data-duplicate-position',
    'data-duplicate-prev',
    'data-duplicate-next',
    'COM_XDECAROPEOPLE_DUPLICATE_MOBILE_PREVIOUS',
    'COM_XDECAROPEOPLE_DUPLICATE_MOBILE_NEXT',
    'COM_XDECAROPEOPLE_DUPLICATE_MOBILE_NAV_HELP',
] as $needle) {
    $contains($template, $needle);
}

$templateContent = (string) file_get_contents($template);
foreach ([
    'xdecaro-duplicate-mobile-tabs',
    'data-duplicate-tab=',
    'role="tab"',
] as $needle) {
    if (str_contains($templateContent, $needle)) {
        $fail('People 1.7.17 must not expose the old mobile tab UI: ' . $needle);
    }
}

foreach ([
    '.xdecaro-duplicate-mobile-record-summary',
    '.xdecaro-duplicate-mobile-nav',
    'grid-template-columns: 1fr 1fr;',
    '.xdecaro-duplicate-person[hidden]',
] as $needle) {
    $contains($css, $needle);
}

foreach ([
    "window.matchMedia('(max-width: 767.98px)')",
    'data-duplicate-prev',
    'data-duplicate-next',
    'group.dataset.duplicateIndex',
    'position.textContent =',
    'prev.disabled = safeIndex === 0',
    'next.disabled = safeIndex >= panels.length - 1',
] as $needle) {
    $contains($js, $needle);
}

foreach ([
    'COM_XDECAROPEOPLE_DUPLICATE_MOBILE_RECORD=',
    'COM_XDECAROPEOPLE_DUPLICATE_MOBILE_NAV_HELP=',
    'COM_XDECAROPEOPLE_DUPLICATE_MOBILE_PREVIOUS=',
    'COM_XDECAROPEOPLE_DUPLICATE_MOBILE_NEXT=',
] as $key) {
    $contains($root . '/component/admin/language/it-IT/com_xdecaropeople.ini', $key);
    $contains($root . '/component/admin/language/en-GB/com_xdecaropeople.ini', $key);
}

echo "People 1.7.17 simple mobile duplicate navigation contract OK\n";
