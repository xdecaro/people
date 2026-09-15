<?php

declare(strict_types=1);

defined('_JEXEC') || define('_JEXEC', 1);

$root = dirname(__DIR__);
$provider = file_get_contents($root . '/component/admin/src/Service/PersonProviderService.php') ?: '';

$required = [
    'function getPeopleByUuids(array $uuids, bool $sensitive = false): array',
    '$this->authorise($sensitive);',
    "strtolower(trim((string) $uuid))",
    "preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid)",
    "p.state') . ' >= 0'",
    "':uuid' . $index",
    "->bind($placeholder, $uuid)",
    "p.uuid') . ' IN ('",
    '$this->normalizeStructuredFields($row, $sensitive)',
    "$found[$key] = $row",
    "$result[$uuid] = $found[$uuid]",
];

foreach ($required as $marker) {
    if (!str_contains($provider, $marker)) {
        fwrite(STDERR, "People batch provider contract missing marker: {$marker}\n");
        exit(1);
    }
}

if (preg_match('/IN\s*\([^)]*\$uuid[^)]*\)/', $provider)) {
    fwrite(STDERR, "People batch provider must bind UUID values instead of interpolating them into SQL.\n");
    exit(1);
}

if (substr_count($provider, 'function getPeopleByUuids(') !== 1) {
    fwrite(STDERR, "People batch provider must expose exactly one public batch method.\n");
    exit(1);
}

echo "People batch provider contract OK\n";
