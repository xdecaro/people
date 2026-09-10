<?php
namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

final class PersonProviderService
{
    public function __construct(
        private DatabaseInterface $db,
        private CoreIntegrationService $core
    ) {
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
            $query->where($this->db->quoteName('p.id') . ' = :id')
                ->bind(':id', $numericId, ParameterType::INTEGER);
        } else {
            $uuid = trim((string) $id);
            $query->where($this->db->quoteName('p.uuid') . ' = :uuid')
                ->bind(':uuid', $uuid);
        }

        $row = $this->db->setQuery($query, 0, 1)->loadAssoc();

        if (!$row) {
            return null;
        }

        $row['entity_reference'] = $this->core->createEntityReference((int) $row['id'])->toArray();

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
            ->order($this->db->quoteName('p.last_name') . ' ASC, ' . $this->db->quoteName('p.first_name') . ' ASC');

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $like = '%' . str_replace(' ', '%', $search) . '%';
            $query->where(
                '(' . $this->db->quoteName('p.display_name') . ' LIKE :s1 OR '
                . $this->db->quoteName('p.email') . ' LIKE :s2)'
            )
                ->bind(':s1', $like)
                ->bind(':s2', $like);
        }

        if (!empty($filters['user_id'])) {
            $userId = (int) $filters['user_id'];
            $query->where($this->db->quoteName('p.user_id') . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER);
        }

        return array_values((array) $this->db->setQuery($query, 0, $limit)->loadAssocList());
    }

    public function resolveByUserId(int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }

        $result = $this->searchPeople(['user_id' => $userId], 1, false);

        return $result[0] ?? null;
    }

    private function columns(bool $sensitive): array
    {
        $columns = [
            'p.id',
            'p.uuid',
            'p.user_id',
            'p.display_name',
            'p.first_name',
            'p.last_name',
            'p.email',
            'p.phone',
            'p.whatsapp',
            'p.state',
            'p.access',
            'p.language',
        ];

        if ($sensitive) {
            $columns = array_merge($columns, [
                'p.birth_date',
                'p.gender',
                'p.has_disability',
                'p.birth_place',
                'p.nationality_code',
                'p.tax_identifier',
                'p.address_line',
                'p.street_number',
                'p.postal_code',
                'p.city',
                'p.region',
                'p.country_code',
                'p.notes',
            ]);
        }

        return $columns;
    }

    private function authorise(bool $sensitive): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (
            !$user->authorise('core.manage', CoreIntegrationService::COMPONENT)
            && !$user->authorise('core.admin', CoreIntegrationService::COMPONENT)
        ) {
            throw new RuntimeException('Not authorised to query People.', 403);
        }

        if (
            $sensitive
            && !$user->authorise('people.view_sensitive', CoreIntegrationService::COMPONENT)
            && !$user->authorise('core.admin', CoreIntegrationService::COMPONENT)
        ) {
            throw new RuntimeException('Not authorised to query sensitive People fields.', 403);
        }
    }
}
