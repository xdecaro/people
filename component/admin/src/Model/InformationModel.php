<?php

namespace xdecaro\Component\People\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Registry\Registry;
use xdecaro\Component\People\Administrator\Extension\PeopleComponent;
use xdecaro\Component\People\Administrator\Service\CoreIntegrationService;

final class InformationModel extends BaseDatabaseModel
{
    public function getDiagnostics(): array
    {
        $component = Factory::getApplication()->bootComponent('com_xdecaropeople');
        $core = $component instanceof PeopleComponent ? $component->getCoreIntegrationService() : null;
        $db = $this->getDatabase();
        $tableList = $db->getTableList();
        $requiredTables = [
            $db->replacePrefix('#__xdecaropeople_people'),
            $db->replacePrefix('#__xdecaropeople_history'),
            $db->replacePrefix('#__xdecaropeople_duplicate_ignores'),
            $db->replacePrefix('#__xdecaropeople_merges'),
        ];
        $tablesOk = array_reduce(
            $requiredTables,
            static fn(bool $ok, string $table): bool => $ok && in_array($table, $tableList, true),
            true
        );

        $record = ExtensionHelper::getExtensionRecord('com_xdecaropeople', 'component', 1);
        $manifest = new Registry($record->manifest_cache ?? '{}');

        return [
            'component_version' => (string) $manifest->get('version', ''),
            'core_version' => $core ? $core->getVersion() : '',
            'core_ok' => $core ? version_compare($core->getVersion(), CoreIntegrationService::MINIMUM_CORE, '>=') : false,
            'table_ok' => $tablesOk,
            'php_version' => PHP_VERSION,
            'joomla_version' => JVERSION,
        ];
    }
}
