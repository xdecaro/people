<?php

namespace xdecaro\Component\People\Administrator\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class PersonField extends ListField
{
    protected $type = 'Person';

    protected function getOptions()
    {
        $options = parent::getOptions();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        $canIdentity = $user->authorise('people.view_identity_details', 'com_xdecaropeople')
            || $user->authorise('people.view_sensitive', 'com_xdecaropeople')
            || $user->authorise('core.admin', 'com_xdecaropeople');
        $currentId = $app->getInput()->getInt('id');
        $columns = [
            $db->quoteName('uuid'),
            $db->quoteName('display_name'),
            $db->quoteName('id'),
        ];
        if ($canIdentity) {
            $columns[] = $db->quoteName('birth_date');
            $columns[] = $db->quoteName('birth_place');
        }
        $query = $db->getQuery(true)
            ->select($columns)
            ->from($db->quoteName('#__xdecaropeople_people'))
            ->where($db->quoteName('state') . ' >= 0')
            ->order($db->quoteName('last_name') . ' ASC, ' . $db->quoteName('first_name') . ' ASC');

        if ($currentId > 0) {
            $query->where($db->quoteName('id') . ' <> :currentId')
                ->bind(':currentId', $currentId, ParameterType::INTEGER);
        }

        foreach ((array) $db->setQuery($query)->loadAssocList() as $row) {
            $uuid = trim((string) ($row['uuid'] ?? ''));
            $name = trim((string) ($row['display_name'] ?? ''));
            if ($uuid === '' || $name === '') {
                continue;
            }

            $meta = [];
            if ($canIdentity && !empty($row['birth_date'])) {
                $meta[] = (string) $row['birth_date'];
            }
            if ($canIdentity && !empty($row['birth_place'])) {
                $meta[] = (string) $row['birth_place'];
            }
            $label = $name . ($meta !== [] ? ' · ' . implode(' · ', $meta) : '');

            $options[] = HTMLHelper::_('select.option', $uuid, $label);
        }

        return $options;
    }
}
