<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

final class PersonProviderService
{
    public function __construct(private DatabaseInterface $db, private CoreIntegrationService $core)
    {
    }

    public function getPerson(int|string $id, bool $sensitive = false): ?array
    {
        $this->authorise($sensitive);
        $query = $this->db->getQuery(true)
            ->select($this->columns($sensitive))
            ->from($this->db->quoteName('#__xdecaropeople_people', 'p'))
            ->where($this->db->quoteName('p.state') . ' >= 0');

        if (is_int($id) || ctype_digit((string) $id)) {
            $numericId = (int) $id;
            $query->where($this->db->quoteName('p.id') . ' = :id')->bind(':id', $numericId, ParameterType::INTEGER);
        } else {
            $uuid = trim((string) $id);
            $query->where($this->db->quoteName('p.uuid') . ' = :uuid')->bind(':uuid', $uuid);
        }

        $row = $this->db->setQuery($query, 0, 1)->loadAssoc();
        if (!$row) {
            return null;
        }

        $row = $this->normalizeStructuredFields($row, $sensitive);
        $row['entity_reference'] = $this->core->createEntityReference((int) $row['id'])->toArray();

        if ($sensitive) {
            $row['profile_completeness'] = $this->profileCompleteness($row);
        }

        return $row;
    }

    public function searchPeople(array $filters = [], int $limit = 50, bool $sensitive = false): array
    {
        $this->authorise($sensitive);
        $limit = max(1, min(200, $limit));
        $query = $this->db->getQuery(true)
            ->select($this->columns($sensitive))
            ->from($this->db->quoteName('#__xdecaropeople_people', 'p'))
            ->where($this->db->quoteName('p.state') . ' = 1')
            ->where($this->db->quoteName('p.person_status') . " <> 'archived'")
            ->order($this->db->quoteName('p.last_name') . ' ASC, ' . $this->db->quoteName('p.first_name') . ' ASC');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . str_replace(' ', '%', $search) . '%';
            $query->where('(' . $this->db->quoteName('p.display_name') . ' LIKE :s1 OR ' . $this->db->quoteName('p.preferred_name') . ' LIKE :s2 OR ' . $this->db->quoteName('p.email') . ' LIKE :s3)')
                ->bind(':s1', $like)
                ->bind(':s2', $like)
                ->bind(':s3', $like);
        }

        if (!empty($filters['user_id'])) {
            $userId = (int) $filters['user_id'];
            $query->where($this->db->quoteName('p.user_id') . ' = :uid')->bind(':uid', $userId, ParameterType::INTEGER);
        }

        if (!empty($filters['person_status'])) {
            $status = strtolower(trim((string) $filters['person_status']));
            if (in_array($status, ['active', 'archived', 'deceased'], true)) {
                $query->where($this->db->quoteName('p.person_status') . ' = :personStatus')->bind(':personStatus', $status);
            }
        }

        $rows = array_values((array) $this->db->setQuery($query, 0, $limit)->loadAssocList());
        foreach ($rows as &$row) {
            $row = $this->normalizeStructuredFields($row, $sensitive);
            if ($sensitive) {
                $row['profile_completeness'] = $this->profileCompleteness($row);
            }
        }
        unset($row);

        return $rows;
    }

    public function resolveByUserId(int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }

        $rows = $this->searchPeople(['user_id' => $userId], 1, false);
        return $rows[0] ?? null;
    }

    public function profileCompleteness(array $person): int
    {
        $checks = [
            'first_name',
            'last_name',
            'birth_date',
            'nationality_codes',
            'birth_country_code',
            'birth_place',
            'email',
            'country_code',
            'city',
            'language',
        ];

        $complete = 0;
        foreach ($checks as $field) {
            $value = $person[$field] ?? null;
            if (is_array($value) ? !empty($value) : trim((string) $value) !== '') {
                $complete++;
            }
        }

        return (int) round(($complete / count($checks)) * 100);
    }

    private function columns(bool $sensitive): array
    {
        $columns = [
            'p.id', 'p.uuid', 'p.user_id', 'p.display_name', 'p.first_name', 'p.last_name', 'p.preferred_name',
            'p.email', 'p.phone', 'p.whatsapp', 'p.preferred_contact', 'p.state', 'p.access', 'p.language',
            'p.person_status', 'p.source_component',
        ];

        if ($sensitive) {
            $columns = array_merge($columns, [
                'p.birth_date', 'p.sex', 'p.disability_status', 'p.disability_types', 'p.disability_other',
                'p.accessibility_needs', 'p.accessibility_other', 'p.birth_place', 'p.birth_place_id', 'p.birth_region',
                'p.birth_country_code', 'p.nationality_code', 'p.nationality_codes', 'p.tax_identifier',
                'p.address_line', 'p.address_number', 'p.postal_code', 'p.city', 'p.region', 'p.country_code',
                'p.residence_place_id', 'p.additional_addresses', 'p.relations_data', 'p.social_instagram',
                'p.social_facebook', 'p.social_linkedin', 'p.social_tiktok', 'p.social_telegram', 'p.social_x',
                'p.social_youtube', 'p.website_url', 'p.profile_document_uuid', 'p.notes', 'p.created', 'p.modified',
            ]);
        }

        return $columns;
    }

    private function normalizeStructuredFields(array $row, bool $sensitive): array
    {
        if (!$sensitive) {
            return $row;
        }

        foreach (['disability_types', 'accessibility_needs', 'nationality_codes', 'additional_addresses', 'relations_data'] as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            $decoded = json_decode((string) ($row[$field] ?? ''), true);
            $row[$field] = is_array($decoded) ? $decoded : [];
        }

        if (empty($row['nationality_codes']) && !empty($row['nationality_code'])) {
            $row['nationality_codes'] = [(string) $row['nationality_code']];
        }

        return $row;
    }

    private function authorise(bool $sensitive): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', CoreIntegrationService::COMPONENT) && !$user->authorise('core.admin', CoreIntegrationService::COMPONENT)) {
            throw new RuntimeException('Not authorised to query People.', 403);
        }
        if ($sensitive && !$user->authorise('people.view_sensitive', CoreIntegrationService::COMPONENT) && !$user->authorise('core.admin', CoreIntegrationService::COMPONENT)) {
            throw new RuntimeException('Not authorised to query sensitive People fields.', 403);
        }
    }
}
