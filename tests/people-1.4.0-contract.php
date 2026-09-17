<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$version = trim((string) file_get_contents($root . '/VERSION'));
$assert(version_compare($version, '1.4.0', '>='), 'People VERSION must be 1.4.0 or newer.');

$relationForm = (string) file_get_contents($root . '/component/admin/forms/person_relation.xml');
$model = (string) file_get_contents($root . '/component/admin/src/Model/PersonModel.php');
$provider = (string) file_get_contents($root . '/component/admin/src/Service/PersonProviderService.php');
$peopleModel = (string) file_get_contents($root . '/component/admin/src/Model/PeopleModel.php');
$peopleView = (string) file_get_contents($root . '/component/admin/src/View/People/HtmlView.php');
$peopleTemplate = (string) file_get_contents($root . '/component/admin/tmpl/people/default.php');
$field = (string) file_get_contents($root . '/component/admin/src/Field/PersonField.php');
$reciprocity = (string) file_get_contents($root . '/component/admin/src/Service/RelationReciprocity.php');

foreach ([
    'relation_uuid',
    'valid_from',
    'valid_to',
    'status',
    'support_administrator',
    'legal_representative',
    'curator',
    'partner',
] as $marker) {
    $assert(str_contains($relationForm, $marker), "Relationship form missing {$marker}.");
}

foreach ([
    'RELATION_STATUSES',
    'relationPersonExists',
    'normalizeIsoDate',
    'COM_XDECAROPEOPLE_ERROR_RELATION_DATE_ORDER',
] as $marker) {
    $assert(str_contains($model, $marker), "People relationship validation missing {$marker}.");
}

foreach ([
    'function personExists(string $uuid): bool',
    'function getRelations(string $uuid): array',
    'function getCurrentAddress(string $uuid): ?array',
] as $marker) {
    $assert(str_contains($provider, $marker), "People public provider missing {$marker}.");
}

$assert(str_contains($peopleModel, 'people.view_identity_details'), 'People list query must gate identity details by ACL.');
$assert(str_contains($peopleView, 'public bool $canIdentityDetails = false;'), 'People list view must expose identity-detail permission state.');
$assert(str_contains($peopleTemplate, "COM_XDECAROPEOPLE_FIELD_BIRTH_DATE"), 'People list must render birth date when authorised.');
$assert(str_contains($peopleTemplate, "COM_XDECAROPEOPLE_FIELD_BIRTH_PLACE"), 'People list must render birth place when authorised.');
$assert(str_contains($field, 'people.view_identity_details'), 'Person relation selector must gate identity details by ACL.');
$assert(str_contains($reciprocity, "'guardian' => 'ward'"), 'Guardian relation must be reciprocal with ward.');
$assert(str_contains($reciprocity, "'support_administrator' => 'supported_person'"), 'Support administrator relation reciprocal mapping is missing.');
$assert(str_contains($reciprocity, "'legal_representative' => 'represented_person'"), 'Legal representative reciprocal mapping is missing.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "People 1.4.0+ relationship and identity compatibility contract OK\n";
