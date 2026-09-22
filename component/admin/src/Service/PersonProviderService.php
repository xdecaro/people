<?php

namespace xdecaro\Component\People\Administrator\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;
use Throwable;

final class PersonProviderService
{
    public function __construct(private DatabaseInterface $db, private CoreIntegrationService $core) {}

    public function getPerson(int|string $id, bool $sensitive = false): ?array
    {
        $this->authorise($sensitive);
        $query = $this->db->getQuery(true)->select($this->columns($sensitive))->from($this->db->quoteName('#__xdecaropeople_people', 'p'))->where($this->db->quoteName('p.state') . ' >= 0');
        $requestedUuid = null;

        if (is_int($id) || ctype_digit((string) $id)) {
            $numericId = $this->resolveCanonicalId((int) $id);
            $query->where($this->db->quoteName('p.id') . ' = :id')->bind(':id', $numericId, ParameterType::INTEGER);
        } else {
            $requestedUuid = strtolower(trim((string) $id));
            $uuid = $this->resolveCanonicalUuid($requestedUuid);
            $query->where($this->db->quoteName('p.uuid') . ' = :uuid')->bind(':uuid', $uuid);
        }

        $row = $this->db->setQuery($query, 0, 1)->loadAssoc();
        if (!$row) return null;

        $row = $this->normalizeStructuredFields($row, $sensitive);
        $row['entity_reference'] = $this->core->createEntityReference((int) $row['id'])->toArray();

        if ($requestedUuid !== null && $requestedUuid !== strtolower((string) ($row['uuid'] ?? ''))) {
            $row['merged_from_uuid'] = $requestedUuid;
        }

        return $row;
    }

    public function getPeopleByUuids(array $uuids, bool $sensitive = false): array
    {
        $this->authorise($sensitive);
        $normalized = [];

        foreach ($uuids as $uuid) {
            $uuid = strtolower(trim((string) $uuid));
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid)) {
                $normalized[$uuid] = $uuid;
            }
        }

        if ($normalized === []) return [];

        $resolved = [];
        foreach ($normalized as $requestedUuid) {
            $resolved[$requestedUuid] = $this->resolveCanonicalUuid($requestedUuid);
        }

        $canonicalUuids = array_values(array_unique(array_values($resolved)));
        $query = $this->db->getQuery(true)
            ->select($this->columns($sensitive))
            ->from($this->db->quoteName('#__xdecaropeople_people', 'p'))
            ->where($this->db->quoteName('p.state') . ' >= 0');

        $placeholders = [];
        foreach ($canonicalUuids as $index => $uuid) {
            $placeholder = ':uuid' . $index;
            $placeholders[] = $placeholder;
            $query->bind($placeholder, $uuid);
        }

        $query->where($this->db->quoteName('p.uuid') . ' IN (' . implode(',', $placeholders) . ')');

        $found = [];
        foreach ((array) $this->db->setQuery($query)->loadAssocList() as $row) {
            $row = $this->normalizeStructuredFields($row, $sensitive);
            $row['entity_reference'] = $this->core->createEntityReference((int) $row['id'])->toArray();
            $key = strtolower((string) ($row['uuid'] ?? ''));
            if ($key !== '') {
                $found[$key] = $row;
            }
        }

        $result = [];
        foreach ($resolved as $requestedUuid => $canonicalUuid) {
            if (!isset($found[$canonicalUuid])) {
                continue;
            }

            $row = $found[$canonicalUuid];
            if ($requestedUuid !== $canonicalUuid) {
                $row['merged_from_uuid'] = $requestedUuid;
            }
            $result[$requestedUuid] = $row;
        }

        return $result;
    }

    public function getEquivalentUuids(string $uuid): array
    {
        $this->authorise(false);

        $canonical = $this->resolveCanonicalUuid(strtolower(trim($uuid)));
        if ($canonical === '') {
            return [];
        }

        $result = [$canonical => $canonical];
        $queue = [$canonical];
        $depth = 0;

        while ($queue !== [] && $depth < 10) {
            $current = array_shift($queue);
            $depth++;

            try {
                $query = $this->db->getQuery(true)
                    ->select($this->db->quoteName('source_uuid'))
                    ->from($this->db->quoteName('#__xdecaropeople_merges'))
                    ->where($this->db->quoteName('target_uuid') . ' = :targetUuid')
                    ->bind(':targetUuid', $current);

                $sources = (array) $this->db->setQuery($query)->loadColumn();
            } catch (Throwable) {
                break;
            }

            foreach ($sources as $sourceUuid) {
                $sourceUuid = strtolower(trim((string) $sourceUuid));
                if ($sourceUuid === '' || isset($result[$sourceUuid])) {
                    continue;
                }
                $result[$sourceUuid] = $sourceUuid;
                $queue[] = $sourceUuid;
            }
        }

        return array_values($result);
    }

    public function personExists(string $uuid): bool
    {
        $uuid = strtolower(trim($uuid));
        if ($uuid === '') {
            return false;
        }

        return $this->getPerson($uuid, false) !== null;
    }

    public function getRelations(string $uuid): array
    {
        $this->authorise(true);
        $person = $this->getPerson($uuid, true);
        if ($person === null) {
            return [];
        }

        $relations = is_array($person['relations_data'] ?? null) ? $person['relations_data'] : [];
        $relatedUuids = [];
        foreach ($relations as $relation) {
            $relatedUuid = strtolower(trim((string) ($relation['person_uuid'] ?? '')));
            if ($relatedUuid !== '') {
                $relatedUuids[$relatedUuid] = $relatedUuid;
            }
        }

        $people = $relatedUuids !== [] ? $this->getPeopleByUuids(array_values($relatedUuids), false) : [];
        foreach ($relations as &$relation) {
            $relatedUuid = strtolower(trim((string) ($relation['person_uuid'] ?? '')));
            $relation['related_person'] = $people[$relatedUuid] ?? null;
        }
        unset($relation);

        return array_values($relations);
    }

    public function getCurrentAddress(string $uuid): ?array
    {
        $this->authorise(true);
        $person = $this->getPerson($uuid, true);
        if ($person === null) {
            return null;
        }

        $address = [
            'address_line' => $person['address_line'] ?? null,
            'address_number' => $person['address_number'] ?? null,
            'postal_code' => $person['postal_code'] ?? null,
            'city' => $person['city'] ?? null,
            'region' => $person['region'] ?? null,
            'country_code' => $person['country_code'] ?? null,
            'residence_place_id' => $person['residence_place_id'] ?? null,
        ];

        foreach ($address as $value) {
            if (trim((string) ($value ?? '')) !== '') {
                return $address;
            }
        }

        return null;
    }

    public function searchPeople(array $filters = [], int $limit = 50, bool $sensitive = false): array
    {
        $this->authorise($sensitive);
        return $this->searchRows($filters, $limit, $this->columns($sensitive), $sensitive);
    }

    public function searchPeopleForIdentity(array $filters = [], int $limit = 50): array
    {
        $this->authoriseIdentityDetails();
        return $this->searchRows($filters, $limit, $this->identityColumns(), false);
    }

    public function resolveByUserId(int $userId): ?array
    {
        if ($userId < 1) return null;
        $rows = $this->searchPeople(['user_id' => $userId], 1, false);
        return $rows[0] ?? null;
    }

    private function searchRows(array $filters, int $limit, array $columns, bool $normalizeSensitive): array
    {
        $limit = max(1, min(200, $limit));
        $query = $this->db->getQuery(true)
            ->select($columns)
            ->from($this->db->quoteName('#__xdecaropeople_people', 'p'))
            ->where($this->db->quoteName('p.state') . ' = 1')
            ->where($this->db->quoteName('p.person_status') . " <> 'archived'")
            ->order($this->db->quoteName('p.last_name') . ' ASC, ' . $this->db->quoteName('p.first_name') . ' ASC');
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . str_replace(' ', '%', $search) . '%';
            $query->where('(' . $this->db->quoteName('p.display_name') . ' LIKE :s1 OR ' . $this->db->quoteName('p.preferred_name') . ' LIKE :s2 OR ' . $this->db->quoteName('p.email') . ' LIKE :s3)')->bind(':s1', $like)->bind(':s2', $like)->bind(':s3', $like);
        }
        if (!empty($filters['user_id'])) {
            $userId = (int) $filters['user_id'];
            $query->where($this->db->quoteName('p.user_id') . ' = :uid')->bind(':uid', $userId, ParameterType::INTEGER);
        }
        if (!empty($filters['person_status'])) {
            $status = strtolower(trim((string) $filters['person_status']));
            if (in_array($status, ['active', 'archived', 'deceased'], true)) $query->where($this->db->quoteName('p.person_status') . ' = :personStatus')->bind(':personStatus', $status);
        }
        $rows = array_values((array) $this->db->setQuery($query, 0, $limit)->loadAssocList());
        if ($normalizeSensitive) {
            foreach ($rows as &$row) $row = $this->normalizeStructuredFields($row, true);
            unset($row);
        }
        return $rows;
    }

    private function publicColumns(): array
    {
        return ['p.id','p.uuid','p.user_id','p.display_name','p.first_name','p.last_name','p.preferred_name','p.email','p.phone','p.whatsapp','p.preferred_contact','p.state','p.access','p.language','p.person_status','p.source_component'];
    }

    private function identityColumns(): array
    {
        $columns = $this->publicColumns();
        foreach (['birth_date', 'birth_place'] as $column) {
            if ($this->columnAvailable($column)) $columns[] = 'p.' . $column;
        }
        return $columns;
    }

    private function columns(bool $sensitive): array
    {
        $columns = $this->publicColumns();
        if (!$sensitive) return $columns;
        $sensitiveColumns = ['birth_date','sex','disability_status','disability_types','disability_other','accessibility_needs','accessibility_other','birth_place','birth_place_id','birth_region','birth_country_code','nationality_code','nationality_codes','tax_identifier','address_line','address_number','postal_code','city','region','country_code','residence_place_id','additional_addresses','relations_data','social_instagram','social_facebook','social_linkedin','social_tiktok','social_telegram','social_x','social_youtube','website_url','profile_document_uuid','notes','created','modified'];
        foreach ($sensitiveColumns as $column) if ($this->columnAvailable($column)) $columns[] = 'p.' . $column;
        return $columns;
    }

    private function columnAvailable(string $column): bool
    {
        static $available = null;
        if ($available === null) {
            $table = $this->db->replacePrefix('#__xdecaropeople_people');
            $available = array_change_key_case((array) $this->db->getTableColumns($table, true), CASE_LOWER);
        }
        return array_key_exists(strtolower($column), $available);
    }

    private function normalizeStructuredFields(array $row, bool $sensitive): array
    {
        if (!$sensitive) return $row;
        foreach (['disability_types','accessibility_needs','nationality_codes','additional_addresses','relations_data'] as $field) {
            if (!array_key_exists($field, $row)) continue;
            $decoded = json_decode((string) ($row[$field] ?? ''), true);
            $row[$field] = is_array($decoded) ? $decoded : [];
        }
        if (empty($row['nationality_codes']) && !empty($row['nationality_code'])) $row['nationality_codes'] = [(string) $row['nationality_code']];
        if (!empty($row['profile_document_uuid'])) {
            try { $row['profile_document_reference'] = $this->core->createDocumentReference((string) $row['profile_document_uuid'])->toArray(); } catch (Throwable) {}
        }
        return $row;
    }

    private function resolveCanonicalId(int $id): int
    {
        if ($id < 1) {
            return $id;
        }

        $seen = [];
        $current = $id;

        for ($depth = 0; $depth < 10; $depth++) {
            if (isset($seen[$current])) {
                break;
            }
            $seen[$current] = true;

            try {
                $query = $this->db->getQuery(true)
                    ->select($this->db->quoteName('target_person_id'))
                    ->from($this->db->quoteName('#__xdecaropeople_merges'))
                    ->where($this->db->quoteName('source_person_id') . ' = :sourceId')
                    ->bind(':sourceId', $current, ParameterType::INTEGER);

                $next = (int) $this->db->setQuery($query, 0, 1)->loadResult();
            } catch (Throwable) {
                return $current;
            }

            if ($next < 1 || $next === $current) {
                break;
            }
            $current = $next;
        }

        return $current;
    }

    private function resolveCanonicalUuid(string $uuid): string
    {
        $uuid = strtolower(trim($uuid));
        if ($uuid === '') {
            return $uuid;
        }

        $seen = [];
        $current = $uuid;

        for ($depth = 0; $depth < 10; $depth++) {
            if (isset($seen[$current])) {
                break;
            }
            $seen[$current] = true;

            try {
                $query = $this->db->getQuery(true)
                    ->select($this->db->quoteName('target_uuid'))
                    ->from($this->db->quoteName('#__xdecaropeople_merges'))
                    ->where($this->db->quoteName('source_uuid') . ' = :sourceUuid')
                    ->bind(':sourceUuid', $current);

                $next = strtolower(trim((string) $this->db->setQuery($query, 0, 1)->loadResult()));
            } catch (Throwable) {
                return $current;
            }

            if ($next === '' || $next === $current) {
                break;
            }
            $current = $next;
        }

        return $current;
    }

    private function authorise(bool $sensitive): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', CoreIntegrationService::COMPONENT) && !$user->authorise('core.admin', CoreIntegrationService::COMPONENT)) throw new RuntimeException('Not authorised to query People.', 403);
        if ($sensitive && !$user->authorise('people.view_sensitive', CoreIntegrationService::COMPONENT) && !$user->authorise('core.admin', CoreIntegrationService::COMPONENT)) throw new RuntimeException('Not authorised to query sensitive People fields.', 403);
    }

    private function authoriseIdentityDetails(): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', CoreIntegrationService::COMPONENT) && !$user->authorise('core.admin', CoreIntegrationService::COMPONENT)) throw new RuntimeException('Not authorised to query People.', 403);
        if (!$user->authorise('people.view_identity_details', CoreIntegrationService::COMPONENT)
            && !$user->authorise('people.view_sensitive', CoreIntegrationService::COMPONENT)
            && !$user->authorise('core.admin', CoreIntegrationService::COMPONENT)) {
            throw new RuntimeException('Not authorised to query People identity details.', 403);
        }
    }
}
