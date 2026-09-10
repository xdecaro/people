<?php

namespace xdecaro\Component\People\Administrator\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;

final class PersonField extends ListField
{
    protected $type = 'Person';

    protected function getOptions()
    {
        $options = parent::getOptions();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('uuid'),
                $db->quoteName('display_name'),
                $db->quoteName('id'),
            ])
            ->from($db->quoteName('#__xdecaropeople_people'))
            ->where($db->quoteName('state') . ' >= 0')
            ->order($db->quoteName('last_name') . ' ASC, ' . $db->quoteName('first_name') . ' ASC');

        foreach ((array) $db->setQuery($query)->loadAssocList() as $row) {
            $uuid = trim((string) ($row['uuid'] ?? ''));
            $name = trim((string) ($row['display_name'] ?? ''));
            if ($uuid === '' || $name === '') {
                continue;
            }

            $options[] = HTMLHelper::_('select.option', $uuid, $name . ' (#' . (int) $row['id'] . ')');
        }

        return $options;
    }
}
