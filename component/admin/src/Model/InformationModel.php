<?php

namespace xdecaro\Component\People\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Registry\Registry;
use RuntimeException;
use xdecaro\Component\People\Administrator\Extension\PeopleComponent;
use xdecaro\Component\People\Administrator\Service\CoreIntegrationService;

final class InformationModel extends BaseDatabaseModel
{
    public function getDiagnostics(): array
    {
        $component = $this->component();
        $core = $component->getCoreIntegrationService();
        $record = ExtensionHelper::getExtensionRecord('com_xdecaropeople', 'component', 1);
        $manifest = new Registry($record->manifest_cache ?? '{}');
        $integrity = $component->getIntegrityService()->check(0, false);

        return [
            'component_version' => (string) $manifest->get('version', ''),
            'core_version' => $core->getVersion(),
            'core_ok' => version_compare($core->getVersion(), CoreIntegrationService::MINIMUM_CORE, '>='),
            'table_ok' => !in_array(false, (array) ($integrity['tables'] ?? []), true),
            'php_version' => PHP_VERSION,
            'joomla_version' => JVERSION,
            'integrity' => $integrity,
        ];
    }

    public function getDatabaseSummary(): array
    {
        $db = $this->getDatabase();
        $count = static function (string $where = '') use ($db): int {
            $query = $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__xdecaropeople_people'));
            if ($where !== '') $query->where($where);
            return (int) $db->setQuery($query)->loadResult();
        };
        $missing = static function (string $column) use ($db): int {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__xdecaropeople_people'))
                ->where($db->quoteName('state') . ' <> -2')
                ->where('(' . $db->quoteName($column) . ' IS NULL OR TRIM(' . $db->quoteName($column) . ") = '')");
            return (int) $db->setQuery($query)->loadResult();
        };

        return [
            'total' => $count(),
            'active' => $count($db->quoteName('state') . ' = 1'),
            'unpublished' => $count($db->quoteName('state') . ' = 0'),
            'trashed' => $count($db->quoteName('state') . ' = -2'),
            'missing' => [
                'birth_date' => $missing('birth_date'),
                'sex' => $missing('sex'),
                'tax_identifier' => $missing('tax_identifier'),
                'birth_place' => $missing('birth_place'),
                'address_line' => $missing('address_line'),
                'email' => $missing('email'),
                'phone' => $missing('phone'),
                'nationality_code' => $missing('nationality_code'),
            ],
        ];
    }

    public function getConnectedComponents(): array
    {
        $items = [
            'Core' => ['element' => 'com_xdecarocore', 'url' => 'index.php?option=com_xdecarocore'],
            'Organizations' => ['element' => 'com_xdecaroorganizations', 'url' => 'index.php?option=com_xdecaroorganizations'],
            'Membership' => ['element' => 'com_decaromembership', 'url' => 'index.php?option=com_decaromembership'],
            'Competitions' => ['element' => 'com_competitions', 'url' => 'index.php?option=com_competitions'],
            'Photos' => ['element' => 'com_xdecarophotos', 'url' => 'index.php?option=com_xdecarophotos'],
            'Documents' => ['element' => 'com_xdecarodocuments', 'url' => 'index.php?option=com_xdecarodocuments'],
            'Notifications' => ['element' => 'com_xdecaronotifications', 'url' => 'index.php?option=com_xdecaronotifications'],
        ];

        foreach ($items as $name => &$item) {
            $record = ExtensionHelper::getExtensionRecord($item['element'], 'component', 1);
            $item['name'] = $name;
            $item['installed'] = is_object($record) && !empty($record->extension_id);
            $item['enabled'] = $item['installed'] && (int) ($record->enabled ?? 0) === 1;
            $item['version'] = '';
            if ($item['installed']) {
                $manifest = new Registry($record->manifest_cache ?? '{}');
                $item['version'] = (string) $manifest->get('version', '');
            }
        }
        unset($item);

        return array_values($items);
    }

    public function getBackups(): array
    {
        return $this->component()->getBackupService()->list();
    }

    public function getRecentTrashed(): array
    {
        return $this->component()->getPersonTrashService()->getRecentTrashed(20);
    }

    public function getMaintenanceActivity(): array
    {
        return $this->component()->getMaintenanceLogService()->recent(20);
    }

    private function component(): PeopleComponent
    {
        $component = Factory::getApplication()->bootComponent('com_xdecaropeople');
        if (!$component instanceof PeopleComponent) {
            throw new RuntimeException('People component service is unavailable.');
        }
        return $component;
    }
}
