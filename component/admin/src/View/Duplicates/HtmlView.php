<?php

namespace xdecaro\Component\People\Administrator\View\Duplicates;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use xdecaro\Component\People\Administrator\Extension\PeopleComponent;

final class HtmlView extends BaseHtmlView
{
    public array $groups = [];
    public array $summary = [
        'total' => 0,
        'conflict' => 0,
        'strong' => 0,
        'possible' => 0,
        'records' => 0,
    ];
    public string $filter = 'all';
    public string $search = '';
    public string $typeFilter = 'all';
    public bool $canMerge = false;
    public bool $canSensitive = false;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_xdecaropeople')
            && !$user->authorise('core.admin', 'com_xdecaropeople')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->canSensitive = $user->authorise('people.view_sensitive', 'com_xdecaropeople')
            || $user->authorise('core.admin', 'com_xdecaropeople');

        $this->canMerge = $this->canSensitive
            && (
                $user->authorise('core.edit', 'com_xdecaropeople')
                || $user->authorise('core.admin', 'com_xdecaropeople')
            )
            && (
                $user->authorise('core.edit.state', 'com_xdecaropeople')
                || $user->authorise('core.admin', 'com_xdecaropeople')
            );

        $component = $app->bootComponent('com_xdecaropeople');
        if ($component instanceof PeopleComponent) {
            $component->getCoreIntegrationService()->enableUi($this->document->getWebAssetManager());
        }

        $assets = $this->document->getWebAssetManager();
        $assets->useStyle('com_xdecaropeople.admin');
        $assets->useStyle('com_xdecaropeople.duplicates-compact');
        $assets->useScript('com_xdecaropeople.duplicates');

        $allGroups = array_values((array) $this->get('Groups'));
        $sensitiveGroupTypes = ['tax_identifier', 'name_birth'];

        if (!$this->canSensitive) {
            $allGroups = array_values(array_filter(
                $allGroups,
                static fn(array $group): bool => !in_array(
                    (string) ($group['type'] ?? ''),
                    $sensitiveGroupTypes,
                    true
                )
            ));
        }

        $recordIds = [];

        foreach ($allGroups as $group) {
            $strength = (string) ($group['strength'] ?? 'possible');
            if (isset($this->summary[$strength])) {
                $this->summary[$strength]++;
            }

            foreach ((array) ($group['records'] ?? []) as $record) {
                $recordId = (int) ($record['id'] ?? 0);
                if ($recordId > 0) {
                    $recordIds[$recordId] = true;
                }
            }
        }

        $this->summary['total'] = count($allGroups);
        $this->summary['records'] = count($recordIds);

        $allowedStrengths = ['all', 'conflict', 'strong', 'possible'];
        $allowedTypes = ['all', 'email', 'tax_identifier', 'name_birth', 'name', 'phone', 'whatsapp'];
        $searchFields = ['display_name', 'email', 'phone', 'whatsapp'];

        if ($this->canSensitive) {
            $searchFields[] = 'tax_identifier';
            $searchFields[] = 'birth_place';
        } else {
            $allowedTypes = array_values(array_diff($allowedTypes, $sensitiveGroupTypes));
        }

        $requestedFilter = $app->input->getCmd('duplicate_filter', 'all');
        $this->filter = in_array($requestedFilter, $allowedStrengths, true)
            ? $requestedFilter
            : 'all';

        $requestedType = $app->input->getCmd('duplicate_type', 'all');
        $this->typeFilter = in_array($requestedType, $allowedTypes, true)
            ? $requestedType
            : 'all';

        $this->search = trim($app->input->getString('duplicate_search', ''));
        $searchNeedle = mb_strtolower($this->search, 'UTF-8');

        $this->groups = array_values(array_filter(
            $allGroups,
            function (array $group) use ($searchNeedle, $searchFields, $sensitiveGroupTypes): bool {
                $groupType = (string) ($group['type'] ?? '');

                if ($this->filter !== 'all'
                    && (string) ($group['strength'] ?? 'possible') !== $this->filter) {
                    return false;
                }

                if ($this->typeFilter !== 'all' && $groupType !== $this->typeFilter) {
                    return false;
                }

                if ($searchNeedle === '') {
                    return true;
                }

                $parts = [
                    $groupType,
                    (string) ($group['value'] ?? ''),
                ];

                if ($this->canSensitive || !in_array($groupType, $sensitiveGroupTypes, true)) {
                    $parts[] = (string) ($group['key'] ?? '');
                }

                foreach ((array) ($group['records'] ?? []) as $record) {
                    foreach ($searchFields as $field) {
                        $parts[] = (string) ($record[$field] ?? '');
                    }
                }

                $haystack = mb_strtolower(implode(' ', $parts), 'UTF-8');

                return str_contains($haystack, $searchNeedle);
            }
        ));

        ToolbarHelper::title(Text::_('COM_XDECAROPEOPLE_DUPLICATES'), 'search');

        parent::display($tpl);
    }
}
