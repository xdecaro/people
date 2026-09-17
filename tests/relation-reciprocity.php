<?php

declare(strict_types=1);

defined('_JEXEC') || define('_JEXEC', 1);

$root = dirname(__DIR__);
$servicePath = $root . '/component/admin/src/Service/RelationReciprocity.php';
$modelPath = $root . '/component/admin/src/Model/PersonModel.php';
$tablePath = $root . '/component/admin/src/Table/PersonTable.php';
$installerPath = $root . '/component/script.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

if (!is_file($servicePath)) {
    fwrite(STDERR, "RelationReciprocity service is missing.\n");
    exit(1);
}

require_once $servicePath;

use xdecaro\Component\People\Administrator\Service\RelationReciprocity;

$assert(RelationReciprocity::inverseType('spouse') === 'spouse', 'Spouse must be reciprocal with spouse.');
$assert(RelationReciprocity::inverseType('parent') === 'child', 'Parent must be reciprocal with child.');
$assert(RelationReciprocity::inverseType('child') === 'parent', 'Child must be reciprocal with parent.');
$assert(RelationReciprocity::inverseType('guardian') === 'ward', 'Guardian must be reciprocal with ward.');
$assert(RelationReciprocity::inverseType('ward') === 'guardian', 'Ward must be reciprocal with guardian.');
$assert(RelationReciprocity::inverseType('curator') === 'curated_person', 'Curator must be reciprocal with curated person.');
$assert(RelationReciprocity::inverseType('support_administrator') === 'supported_person', 'Support administrator must be reciprocal with supported person.');
$assert(RelationReciprocity::inverseType('legal_representative') === 'represented_person', 'Legal representative must be reciprocal with represented person.');
$assert(RelationReciprocity::inverseType('partner') === 'partner', 'Partner must be reciprocal with partner.');
$assert(RelationReciprocity::inverseType('responsible') === null, 'Responsible must remain directional.');
$assert(RelationReciprocity::inverseType('other') === null, 'Other relation must remain directional.');

$sourceUuid = '06d8130d-0c32-490c-b689-bfa82503a50d';
$relations = [
    ['type' => 'guardian', 'person_uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'note' => 'keep'],
];

$relations = RelationReciprocity::upsert($relations, 'spouse', $sourceUuid, [
    'relation_uuid' => '11111111-1111-4111-8111-111111111111',
    'valid_from' => '2026-01-01',
    'valid_to' => '',
    'status' => 'active',
    'note' => 'shared',
]);
$spouseRows = array_values(array_filter($relations, static fn (array $row): bool => ($row['type'] ?? '') === 'spouse' && ($row['person_uuid'] ?? '') === $sourceUuid));
$assert(count($spouseRows) === 1, 'Reciprocal spouse relation must be added exactly once.');
$assert(($spouseRows[0]['valid_from'] ?? '') === '2026-01-01', 'Reciprocal metadata must preserve relation start date.');
$assert(($spouseRows[0]['relation_uuid'] ?? '') === '11111111-1111-4111-8111-111111111111', 'Reciprocal metadata must preserve relation UUID.');

$relations = RelationReciprocity::upsert($relations, 'spouse', $sourceUuid);
$assert(count(array_filter($relations, static fn (array $row): bool => ($row['type'] ?? '') === 'spouse' && ($row['person_uuid'] ?? '') === $sourceUuid)) === 1, 'Repeated synchronization must not duplicate reciprocal relations.');

$relations = RelationReciprocity::remove($relations, 'spouse', $sourceUuid);
$assert(count(array_filter($relations, static fn (array $row): bool => ($row['type'] ?? '') === 'spouse' && ($row['person_uuid'] ?? '') === $sourceUuid)) === 0, 'Removing a managed relation must remove its reciprocal edge.');
$assert(count(array_filter($relations, static fn (array $row): bool => ($row['type'] ?? '') === 'guardian')) === 1, 'Directional relations must be preserved while synchronizing reciprocal relations.');

$edges = RelationReciprocity::managedEdges([
    ['type' => 'spouse', 'person_uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'note' => 'x'],
    ['type' => 'parent', 'person_uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'note' => 'y'],
    ['type' => 'other', 'person_uuid' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'note' => 'z'],
]);
$assert(count($edges) === 2, 'Only reciprocal-managed relation types must participate in synchronization.');

$persistenceCode = (file_get_contents($modelPath) ?: '') . "\n" . (file_get_contents($tablePath) ?: '');
$assert(str_contains($persistenceCode, 'RelationReciprocity'), 'People persistence must use RelationReciprocity.');
$assert(str_contains($persistenceCode, 'synchronizeReciprocalRelations'), 'People persistence must synchronize reciprocal relations after save.');
$assert(str_contains($persistenceCode, 'RelationReciprocity::managedEdges'), 'People persistence must compare reciprocal-managed relation edges.');
$assert(str_contains($persistenceCode, 'RelationReciprocity::upsert'), 'People persistence must create/repair reciprocal relations.');
$assert(str_contains($persistenceCode, 'RelationReciprocity::remove'), 'People persistence must remove reciprocal relations when the source relation is removed.');

$installer = file_get_contents($installerPath) ?: '';
$assert(str_contains($installer, 'repairReciprocalRelations'), 'Installer must backfill reciprocal relations for existing People records.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "People relation reciprocity OK\n";
