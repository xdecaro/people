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

        $this->document->getWebAssetManager()->useStyle('com_xdecaropeople.admin');

        $allGroups = array_values((array) $this->get('Groups'));
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

        $requestedFilter = $app->input->getCmd('duplicate_filter', 'all');
        $this->filter = in_array($requestedFilter, ['all', 'conflict', 'strong', 'possible'], true)
            ? $requestedFilter
            : 'all';

        $this->groups = $this->filter === 'all'
            ? $allGroups
            : array_values(array_filter(
                $allGroups,
                fn(array $group): bool => (string) ($group['strength'] ?? 'possible') === $this->filter
            ));

        ToolbarHelper::title(Text::_('COM_XDECAROPEOPLE_DUPLICATES'), 'search');

        parent::display($tpl);
    }
}
