<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

final class RelationReciprocity
{
    private const INVERSES = [
        'spouse' => 'spouse',
        'partner' => 'partner',
        'parent' => 'child',
        'child' => 'parent',
        'guardian' => 'ward',
        'ward' => 'guardian',
        'curator' => 'curated_person',
        'curated_person' => 'curator',
        'support_administrator' => 'supported_person',
        'supported_person' => 'support_administrator',
        'legal_representative' => 'represented_person',
        'represented_person' => 'legal_representative',
    ];

    public static function inverseType(string $type): ?string
    {
        $type = strtolower(trim($type));
        return self::INVERSES[$type] ?? null;
    }

    public static function managedEdges(array $relations): array
    {
        $edges = [];

        foreach ($relations as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = strtolower(trim((string) ($row['type'] ?? '')));
            $uuid = strtolower(trim((string) ($row['person_uuid'] ?? '')));
            if ($uuid === '' || self::inverseType($type) === null) {
                continue;
            }

            $edges[$type . '|' . $uuid] = [
                'relation_uuid' => strtolower(trim((string) ($row['relation_uuid'] ?? ''))),
                'type' => $type,
                'person_uuid' => $uuid,
                'valid_from' => trim((string) ($row['valid_from'] ?? '')),
                'valid_to' => trim((string) ($row['valid_to'] ?? '')),
                'status' => strtolower(trim((string) ($row['status'] ?? 'active'))) ?: 'active',
                'note' => trim((string) ($row['note'] ?? '')),
            ];
        }

        return $edges;
    }

    public static function upsert(array $relations, string $type, string $personUuid, array $metadata = []): array
    {
        $type = strtolower(trim($type));
        $personUuid = strtolower(trim($personUuid));

        if ($personUuid === '' || self::inverseType($type) === null) {
            return $relations;
        }

        $relationUuid = strtolower(trim((string) ($metadata['relation_uuid'] ?? '')));
        $validFrom = trim((string) ($metadata['valid_from'] ?? ''));
        $validTo = trim((string) ($metadata['valid_to'] ?? ''));
        $status = strtolower(trim((string) ($metadata['status'] ?? 'active'))) ?: 'active';
        $note = trim((string) ($metadata['note'] ?? ''));

        $found = false;
        $result = [];

        foreach ($relations as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowType = strtolower(trim((string) ($row['type'] ?? '')));
            $rowUuid = strtolower(trim((string) ($row['person_uuid'] ?? '')));

            if ($rowType === $type && $rowUuid === $personUuid) {
                if ($found) {
                    continue;
                }

                $row['relation_uuid'] = $relationUuid !== '' ? $relationUuid : (string) ($row['relation_uuid'] ?? '');
                $row['type'] = $type;
                $row['person_uuid'] = $personUuid;
                $row['valid_from'] = $validFrom;
                $row['valid_to'] = $validTo;
                $row['status'] = in_array($status, ['active', 'inactive'], true) ? $status : 'active';
                $row['note'] = $note;
                $found = true;
            }

            $result[] = $row;
        }

        if (!$found) {
            $result[] = [
                'relation_uuid' => $relationUuid,
                'type' => $type,
                'person_uuid' => $personUuid,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
                'note' => $note,
            ];
        }

        return array_values($result);
    }

    public static function remove(array $relations, string $type, string $personUuid): array
    {
        $type = strtolower(trim($type));
        $personUuid = strtolower(trim($personUuid));

        if ($personUuid === '' || self::inverseType($type) === null) {
            return $relations;
        }

        return array_values(array_filter(
            $relations,
            static function ($row) use ($type, $personUuid): bool {
                if (!is_array($row)) {
                    return false;
                }

                $rowType = strtolower(trim((string) ($row['type'] ?? '')));
                $rowUuid = strtolower(trim((string) ($row['person_uuid'] ?? '')));

                return !($rowType === $type && $rowUuid === $personUuid);
            }
        ));
    }
}
