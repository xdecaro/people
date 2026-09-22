<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;
use Throwable;

final class DuplicateService
{
    private const SUPPORTED_TYPES = ['email', 'tax_identifier', 'name_birth', 'name', 'phone', 'whatsapp'];

    public function __construct(private DatabaseInterface $db)
    {
    }

    public function find(int $limit = 100): array
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', 'com_xdecaropeople') && !$user->authorise('core.admin', 'com_xdecaropeople')) {
            throw new RuntimeException('Not authorised.', 403);
        }

        $limit = max(1, min(500, $limit));
        $canSensitive = $this->canViewSensitive();
        $ignored = $this->ignoredSignatures();
        $buckets = [];

        foreach ($this->loadCandidates($canSensitive) as $record) {
            foreach ($this->criteriaForRecord($record, $canSensitive) as $criterion) {
                $bucketKey = $criterion['type'] . '|' . $criterion['key'];
                if (!isset($buckets[$bucketKey])) {
                    $buckets[$bucketKey] = [
                        'type' => $criterion['type'],
                        'strength' => $criterion['strength'],
                        'key' => $criterion['key'],
                        'value' => $criterion['value'],
                        'records' => [],
                    ];
                }

                $id = (int) ($record['id'] ?? 0);
                if ($id > 0) {
                    $buckets[$bucketKey]['records'][$id] = $this->recordSummary($record, $canSensitive);
                }
            }
        }

        $groups = [];
        foreach ($buckets as $bucket) {
            if (count($bucket['records']) < 2) {
                continue;
            }

            ksort($bucket['records'], SORT_NUMERIC);
            $bucket['records'] = array_values($bucket['records']);
            $bucket['count'] = count($bucket['records']);
            $ids = array_map(static fn(array $record): int => (int) ($record['id'] ?? 0), $bucket['records']);
            $bucket['signature'] = $this->groupSignature(
                (string) $bucket['type'],
                (string) $bucket['key'],
                $ids
            );

            if (isset($ignored[$bucket['signature']])) {
                continue;
            }

            $groups[] = $bucket;
        }

        usort($groups, static function (array $a, array $b): int {
            $strengthA = ($a['strength'] ?? '') === 'strong' ? 0 : 1;
            $strengthB = ($b['strength'] ?? '') === 'strong' ? 0 : 1;

            return [$strengthA, (string) ($a['type'] ?? ''), (string) ($a['value'] ?? '')]
                <=> [$strengthB, (string) ($b['type'] ?? ''), (string) ($b['value'] ?? '')];
        });

        return array_slice($groups, 0, $limit);
    }

    public function hasMatch(array $data, int $excludeId = 0): bool
    {
        $canSensitive = $this->canViewSensitive();
        $wanted = [];

        foreach ($this->criteriaForRecord($data, $canSensitive) as $criterion) {
            $wanted[$criterion['type'] . '|' . $criterion['key']] = true;
        }

        if ($wanted === []) {
            return false;
        }

        foreach ($this->loadCandidates($canSensitive, $excludeId) as $record) {
            foreach ($this->criteriaForRecord($record, $canSensitive) as $criterion) {
                if (isset($wanted[$criterion['type'] . '|' . $criterion['key']])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ignoreGroup(string $type, string $key, array $recordIds, int $userId): void
    {
        $this->assertManagePermission();
        $recordIds = $this->normalizeIds($recordIds);

        if (count($recordIds) < 2) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_DUPLICATE_ERROR_INVALID_GROUP'), 400);
        }

        $this->assertCurrentGroup($type, $key, $recordIds);
        $signature = $this->groupSignature($type, $key, $recordIds);

        $existing = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__xdecaropeople_duplicate_ignores'))
            ->where($this->db->quoteName('signature') . ' = :signature')
            ->bind(':signature', $signature);

        if ((int) $this->db->setQuery($existing)->loadResult() > 0) {
            return;
        }

        $row = (object) [
            'signature' => $signature,
            'match_type' => $type,
            'record_ids' => json_encode($recordIds, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => $userId,
            'created' => Factory::getDate()->toSql(),
        ];

        $this->db->insertObject('#__xdecaropeople_duplicate_ignores', $row);
    }

    public function mergeGroup(int $targetId, string $type, string $key, array $recordIds, int $userId): array
    {
        $this->assertMergePermission();

        $recordIds = $this->normalizeIds($recordIds);
        if ($targetId < 1 || count($recordIds) < 2 || !in_array($targetId, $recordIds, true)) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_DUPLICATE_ERROR_INVALID_GROUP'), 400);
        }

        $this->assertCurrentGroup($type, $key, $recordIds);

        $rows = $this->loadFullRecords($recordIds);
        if (count($rows) !== count($recordIds) || !isset($rows[$targetId])) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_DUPLICATE_ERROR_RECORD_MISSING'), 404);
        }

        $target = $rows[$targetId];
        if ((string) ($target['person_status'] ?? '') === 'archived') {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_DUPLICATE_ERROR_TARGET_ARCHIVED'), 400);
        }

        $userIds = [];
        foreach ($rows as $row) {
            $linkedUserId = (int) ($row['user_id'] ?? 0);
            if ($linkedUserId > 0) {
                $userIds[$linkedUserId] = $linkedUserId;
            }
        }

        if (count($userIds) > 1) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_DUPLICATE_ERROR_MULTIPLE_USERS'), 400);
        }

        $copyFields = [
            'preferred_name', 'birth_date', 'sex', 'disability_status', 'disability_types',
            'disability_other', 'accessibility_needs', 'accessibility_other', 'nationality_code',
            'nationality_codes', 'birth_country_code', 'birth_place', 'birth_place_id',
            'birth_region', 'tax_identifier', 'email', 'phone', 'whatsapp', 'preferred_contact',
            'address_line', 'address_number', 'postal_code', 'city', 'region', 'country_code',
            'residence_place_id', 'additional_addresses', 'relations_data', 'language',
            'social_instagram', 'social_facebook', 'social_linkedin', 'social_tiktok',
            'social_telegram', 'social_x', 'social_youtube', 'website_url',
            'profile_document_uuid', 'notes',
        ];

        $sources = [];
        foreach ($recordIds as $id) {
            if ($id !== $targetId) {
                $sources[$id] = $rows[$id];
            }
        }

        $updatedTarget = $target;
        $copiedFields = [];

        foreach ($sources as $source) {
            foreach ($copyFields as $field) {
                if ($this->isMissingValue($updatedTarget[$field] ?? null) && !$this->isMissingValue($source[$field] ?? null)) {
                    $updatedTarget[$field] = $source[$field];
                    $copiedFields[$field] = $field;
                }
            }
        }

        $singleUserId = $userIds !== [] ? (int) reset($userIds) : 0;
        if ($singleUserId > 0 && (int) ($target['user_id'] ?? 0) !== $singleUserId) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_DUPLICATE_ERROR_KEEP_LINKED_USER'), 400);
        }

        $now = Factory::getDate()->toSql();
        $this->db->transactionStart();

        try {
            foreach ($sources as $sourceId => $source) {
                $sourceUuid = strtolower(trim((string) ($source['uuid'] ?? '')));
                $targetUuid = strtolower(trim((string) ($target['uuid'] ?? '')));

                $sourceUpdate = (object) [
                    'id' => $sourceId,
                    'person_status' => 'archived',
                    'state' => 0,
                    'modified' => $now,
                    'modified_by' => $userId,
                ];

                $this->db->updateObject('#__xdecaropeople_people', $sourceUpdate, 'id');

                $mergeRow = (object) [
                    'source_person_id' => $sourceId,
                    'source_uuid' => $sourceUuid,
                    'target_person_id' => $targetId,
                    'target_uuid' => $targetUuid,
                    'copied_fields' => json_encode(array_values($copiedFields), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'created_by' => $userId,
                    'created' => $now,
                ];
                $this->db->insertObject('#__xdecaropeople_merges', $mergeRow);

                $this->writeHistory(
                    $sourceId,
                    'merge_source',
                    ['merged_into' => $targetUuid, 'target_person_id' => $targetId],
                    $userId,
                    $now
                );
            }

            $targetUpdate = (object) ['id' => $targetId, 'modified' => $now, 'modified_by' => $userId];
            foreach (array_keys($copiedFields) as $field) {
                $targetUpdate->{$field} = $updatedTarget[$field] ?? null;
            }
            $this->db->updateObject('#__xdecaropeople_people', $targetUpdate, 'id');

            $this->writeHistory(
                $targetId,
                'merge_target',
                [
                    'merged_sources' => array_keys($sources),
                    'copied_fields' => array_values($copiedFields),
                ],
                $userId,
                $now
            );

            $this->db->transactionCommit();
        } catch (Throwable $exception) {
            try {
                $this->db->transactionRollback();
            } catch (Throwable) {
            }
            throw $exception;
        }

        return [
            'target_id' => $targetId,
            'source_ids' => array_keys($sources),
            'copied_fields' => array_values($copiedFields),
        ];
    }

    private function loadCandidates(bool $canSensitive, int $excludeId = 0): array
    {
        $columns = [
            $this->db->quoteName('id'),
            $this->db->quoteName('uuid'),
            $this->db->quoteName('user_id'),
            $this->db->quoteName('display_name'),
            $this->db->quoteName('first_name'),
            $this->db->quoteName('last_name'),
            $this->db->quoteName('email'),
            $this->db->quoteName('phone'),
            $this->db->quoteName('whatsapp'),
            $this->db->quoteName('person_status'),
            $this->db->quoteName('source_component'),
            $this->db->quoteName('created'),
        ];

        if ($canSensitive) {
            foreach ([
                'birth_date', 'sex', 'tax_identifier', 'birth_place', 'birth_region',
                'address_line', 'address_number', 'postal_code', 'city', 'region', 'country_code',
            ] as $column) {
                $columns[] = $this->db->quoteName($column);
            }
        }

        $query = $this->db->getQuery(true)
            ->select($columns)
            ->from($this->db->quoteName('#__xdecaropeople_people'))
            ->where($this->db->quoteName('state') . ' >= 0')
            ->where($this->db->quoteName('person_status') . " <> 'archived'")
            ->order($this->db->quoteName('id') . ' ASC');

        if ($excludeId > 0) {
            $query->where($this->db->quoteName('id') . ' <> :excludeId')
                ->bind(':excludeId', $excludeId, ParameterType::INTEGER);
        }

        return (array) $this->db->setQuery($query)->loadAssocList();
    }

    private function loadFullRecords(array $ids): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__xdecaropeople_people'));

        $placeholders = [];
        foreach (array_values($ids) as $index => $id) {
            $placeholder = ':id' . $index;
            $placeholders[] = $placeholder;
            $query->bind($placeholder, $id, ParameterType::INTEGER);
        }

        $query->where($this->db->quoteName('id') . ' IN (' . implode(',', $placeholders) . ')');

        $rows = [];
        foreach ((array) $this->db->setQuery($query)->loadAssocList() as $row) {
            $rows[(int) $row['id']] = $row;
        }

        return $rows;
    }

    private function criteriaForRecord(array $record, bool $canSensitive): array
    {
        $criteria = [];

        $email = self::normalizeText($record['email'] ?? null);
        if ($email !== '') {
            $criteria[] = $this->criterion('email', 'possible', $email, $email);
        }

        if ($canSensitive) {
            $tin = self::normalizeTin($record['tax_identifier'] ?? null);
            if ($tin !== '') {
                $criteria[] = $this->criterion('tax_identifier', 'strong', $tin, $tin);
            }
        }

        $firstName = self::normalizeText($record['first_name'] ?? null);
        $lastName = self::normalizeText($record['last_name'] ?? null);
        $displayName = trim((string) ($record['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) ($record['first_name'] ?? '') . ' ' . (string) ($record['last_name'] ?? ''));
        }

        $nameKey = $firstName !== '' && $lastName !== '' ? $firstName . '|' . $lastName : '';
        if ($canSensitive && $nameKey !== '') {
            $birthDate = self::normalizeBirthDate($record['birth_date'] ?? null);
            if ($birthDate !== '') {
                $criteria[] = $this->criterion(
                    'name_birth',
                    'strong',
                    $nameKey . '|' . $birthDate,
                    trim($displayName . ' · ' . $birthDate)
                );
            }
        }

        $phone = self::normalizePhone($record['phone'] ?? null);
        if ($phone !== '') {
            $criteria[] = $this->criterion('phone', 'possible', $phone, $phone);
        }

        $whatsapp = self::normalizePhone($record['whatsapp'] ?? null);
        if ($whatsapp !== '') {
            $criteria[] = $this->criterion('whatsapp', 'possible', $whatsapp, $whatsapp);
        }

        if ($nameKey !== '') {
            $criteria[] = $this->criterion('name', 'possible', $nameKey, $displayName);
        }

        return $criteria;
    }

    private function criterion(string $type, string $strength, string $key, string $value): array
    {
        return [
            'type' => $type,
            'strength' => $strength,
            'key' => $key,
            'value' => $value,
        ];
    }

    private function recordSummary(array $record, bool $canSensitive): array
    {
        $displayName = trim((string) ($record['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) ($record['first_name'] ?? '') . ' ' . (string) ($record['last_name'] ?? ''));
        }

        $summary = [
            'id' => (int) ($record['id'] ?? 0),
            'uuid' => trim((string) ($record['uuid'] ?? '')),
            'user_id' => (int) ($record['user_id'] ?? 0),
            'display_name' => $displayName,
            'email' => trim((string) ($record['email'] ?? '')),
            'phone' => self::normalizePhone($record['phone'] ?? null),
            'whatsapp' => self::normalizePhone($record['whatsapp'] ?? null),
            'source_component' => trim((string) ($record['source_component'] ?? '')),
            'created' => trim((string) ($record['created'] ?? '')),
        ];

        if ($canSensitive) {
            foreach ([
                'birth_date', 'sex', 'tax_identifier', 'birth_place', 'birth_region',
                'address_line', 'address_number', 'postal_code', 'city', 'region', 'country_code',
            ] as $field) {
                $summary[$field] = trim((string) ($record[$field] ?? ''));
            }
        }

        return $summary;
    }

    private function assertCurrentGroup(string $type, string $key, array $recordIds): void
    {
        $type = trim($type);
        $key = trim($key);

        if (!in_array($type, self::SUPPORTED_TYPES, true) || $key === '') {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_DUPLICATE_ERROR_INVALID_GROUP'), 400);
        }

        $canSensitive = $this->canViewSensitive();
        if (in_array($type, ['tax_identifier', 'name_birth'], true) && !$canSensitive) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $rows = $this->loadFullRecords($recordIds);
        if (count($rows) !== count($recordIds)) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_DUPLICATE_ERROR_RECORD_MISSING'), 404);
        }

        foreach ($recordIds as $id) {
            $row = $rows[$id] ?? null;
            if (!$row || (string) ($row['person_status'] ?? '') === 'archived') {
                throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_DUPLICATE_ERROR_RECORD_MISSING'), 404);
            }

            $matched = false;
            foreach ($this->criteriaForRecord($row, $canSensitive) as $criterion) {
                if ($criterion['type'] === $type && hash_equals((string) $criterion['key'], $key)) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_DUPLICATE_ERROR_GROUP_CHANGED'), 409);
            }
        }
    }

    private function ignoredSignatures(): array
    {
        try {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('signature'))
                ->from($this->db->quoteName('#__xdecaropeople_duplicate_ignores'));

            $result = [];
            foreach ((array) $this->db->setQuery($query)->loadColumn() as $signature) {
                $signature = trim((string) $signature);
                if ($signature !== '') {
                    $result[$signature] = true;
                }
            }

            return $result;
        } catch (Throwable) {
            return [];
        }
    }

    private function groupSignature(string $type, string $key, array $recordIds): string
    {
        $recordIds = $this->normalizeIds($recordIds);
        return hash('sha256', trim($type) . '|' . trim($key) . '|' . implode(',', $recordIds));
    }

    private function normalizeIds(array $recordIds): array
    {
        $ids = [];
        foreach ($recordIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        ksort($ids, SORT_NUMERIC);
        return array_values($ids);
    }

    private function isMissingValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' || $trimmed === '[]' || $trimmed === '{}';
        }

        return false;
    }

    private function writeHistory(int $personId, string $action, array $details, int $userId, string $created): void
    {
        $row = (object) [
            'person_id' => $personId,
            'action' => $action,
            'changed_fields' => json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'actor_user_id' => $userId,
            'created' => $created,
        ];

        $this->db->insertObject('#__xdecaropeople_history', $row);
    }

    private function canViewSensitive(): bool
    {
        $user = Factory::getApplication()->getIdentity();
        return $user->authorise('people.view_sensitive', 'com_xdecaropeople')
            || $user->authorise('core.admin', 'com_xdecaropeople');
    }

    private function assertManagePermission(): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', 'com_xdecaropeople') && !$user->authorise('core.admin', 'com_xdecaropeople')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    private function assertMergePermission(): void
    {
        $this->assertManagePermission();
        $user = Factory::getApplication()->getIdentity();

        $canEdit = $user->authorise('core.edit', 'com_xdecaropeople') || $user->authorise('core.admin', 'com_xdecaropeople');
        $canEditState = $user->authorise('core.edit.state', 'com_xdecaropeople') || $user->authorise('core.admin', 'com_xdecaropeople');

        if (!$canEdit || !$canEditState || !$this->canViewSensitive()) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    private static function normalizeText(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private static function normalizeTin(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }

    private static function normalizePhone(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return preg_replace('/\s+/u', '', $value) ?? $value;
    }

    private static function normalizeBirthDate(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
            return $matches[1];
        }

        return $value;
    }
}
