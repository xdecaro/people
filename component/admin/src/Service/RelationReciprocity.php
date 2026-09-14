<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

final class RelationReciprocity
{
    private const INVERSES = [
        'spouse' => 'spouse',
        'parent' => 'child',
        'child' => 'parent',
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
                'type' => $type,
                'person_uuid' => $uuid,
            ];
        }

        return $edges;
    }

    public static function upsert(array $relations, string $type, string $personUuid): array
    {
        $type = strtolower(trim($type));
        $personUuid = strtolower(trim($personUuid));

        if ($personUuid === '' || self::inverseType($type) === null) {
            return $relations;
        }

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

                $row['type'] = $type;
                $row['person_uuid'] = $personUuid;
                $row['note'] = (string) ($row['note'] ?? '');
                $found = true;
            }

            $result[] = $row;
        }

        if (!$found) {
            $result[] = [
                'type' => $type,
                'person_uuid' => $personUuid,
                'note' => '',
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
