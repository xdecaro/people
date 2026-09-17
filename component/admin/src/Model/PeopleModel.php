<?php
namespace xdecaro\Component\People\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;

final class PeopleModel extends ListModel
{
    public function __construct($config = [])
    {
        $config['filter_fields'] ??= [
            'id',
            'display_name',
            'first_name',
            'last_name',
            'email',
            'state',
            'created',
        ];

        parent::__construct($config);
    }

    protected function populateState($ordering = 'a.last_name', $direction = 'asc'): void
    {
        $this->setState(
            'filter.search',
            $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string')
        );
        $this->setState(
            'filter.state',
            $this->getUserStateFromRequest($this->context . '.filter.state', 'filter_state', '', 'string')
        );

        parent::populateState($ordering, $direction);
    }

    protected function getListQuery(): DatabaseQuery
    {
        $db = $this->getDatabase();
        $user = Factory::getApplication()->getIdentity();
        $canIdentity = $user->authorise('people.view_identity_details', 'com_xdecaropeople')
            || $user->authorise('people.view_sensitive', 'com_xdecaropeople')
            || $user->authorise('core.admin', 'com_xdecaropeople');

        $columns = [
            'a.id',
            'a.uuid',
            'a.user_id',
            'a.display_name',
            'a.first_name',
            'a.last_name',
            'a.email',
            'a.phone',
            'a.state',
            'a.access',
            'a.created',
            'a.modified',
        ];
        if ($canIdentity) {
            $columns[] = 'a.birth_date';
            $columns[] = 'a.birth_place';
        }

        $query = $db->getQuery(true)
            ->select($columns)
            ->from($db->quoteName('#__xdecaropeople_people', 'a'));

        $state = $this->getState('filter.state');
        if ($state !== '') {
            $state = (int) $state;
            $query->where($db->quoteName('a.state') . ' = :state')
                ->bind(':state', $state, ParameterType::INTEGER);
        } else {
            $query->where($db->quoteName('a.state') . ' >= 0');
        }

        $search = trim((string) $this->getState('filter.search'));
        if ($search !== '') {
            $like = '%' . str_replace(' ', '%', $search) . '%';
            $conditions = [
                $db->quoteName('a.display_name') . ' LIKE :s1',
                $db->quoteName('a.first_name') . ' LIKE :s2',
                $db->quoteName('a.last_name') . ' LIKE :s3',
                $db->quoteName('a.email') . ' LIKE :s4',
            ];
            if ($canIdentity) {
                $conditions[] = $db->quoteName('a.birth_place') . ' LIKE :s5';
            }
            $query->where('(' . implode(' OR ', $conditions) . ')')
                ->bind(':s1', $like)
                ->bind(':s2', $like)
                ->bind(':s3', $like)
                ->bind(':s4', $like);
            if ($canIdentity) {
                $query->bind(':s5', $like);
            }
        }

        $order = (string) $this->state->get('list.ordering', 'a.last_name');
        $allowed = [
            'a.id',
            'a.display_name',
            'a.first_name',
            'a.last_name',
            'a.email',
            'a.state',
            'a.created',
        ];
        if (!in_array($order, $allowed, true)) {
            $order = 'a.last_name';
        }

        $direction = strtoupper((string) $this->state->get('list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        return $query->order($order . ' ' . $direction);
    }
}
