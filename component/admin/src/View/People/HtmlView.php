<?php

namespace xdecaro\Component\People\Administrator\View\People;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Throwable;
use xdecaro\Component\People\Administrator\Extension\PeopleComponent;

final class HtmlView extends BaseHtmlView
{
    private const AVAILABLE_COLUMNS = [
        'display_name',
        'birth_date',
        'birth_place',
        'email',
        'phone',
        'person_status',
        'state',
    ];

    public array $items = [];
    public $pagination;
    public $state;
    public bool $canIdentityDetails = false;
    public bool $canSensitive = false;
    public array $visibleColumns = self::AVAILABLE_COLUMNS;
    public array $statusSummary = [
        'total' => 0,
        'published' => 0,
        'suspended' => 0,
        'trashed' => 0,
    ];
    public int $duplicateGroups = 0;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $app->getLanguage()->load('com_xdecaropeople.list', JPATH_ADMINISTRATOR . '/components/com_xdecaropeople');

        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_xdecaropeople')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->items = (array) $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $this->statusSummary = (array) $this->get('StatusSummary');
        $this->canSensitive = $user->authorise('people.view_sensitive', 'com_xdecaropeople')
            || $user->authorise('core.admin', 'com_xdecaropeople');
        $this->canIdentityDetails = $user->authorise('people.view_identity_details', 'com_xdecaropeople')
            || $this->canSensitive
            || $user->authorise('core.admin', 'com_xdecaropeople');
        $this->visibleColumns = $this->resolveVisibleColumns();

        $component = $app->bootComponent('com_xdecaropeople');
        if ($component instanceof PeopleComponent) {
            $component->getCoreIntegrationService()->enableUi($this->document->getWebAssetManager());

            try {
                $this->duplicateGroups = count((array) $component->getDuplicateService()->find(500));
            } catch (Throwable) {
                $this->duplicateGroups = 0;
            }
        }

        $this->document->getWebAssetManager()
            ->useStyle('com_xdecaropeople.admin')
            ->useStyle('com_xdecaropeople.people-list')
            ->useScript('com_xdecaropeople.export');

        ToolbarHelper::title(Text::_('COM_XDECAROPEOPLE_PEOPLE'), 'users');

        $isTrashed = (string) $this->state->get('filter.state') === '-2';

        if ($user->authorise('core.create', 'com_xdecaropeople')) {
            ToolbarHelper::addNew('person.add');

            if ($user->authorise('people.view_sensitive', 'com_xdecaropeople') || $user->authorise('core.admin', 'com_xdecaropeople')) {
                ToolbarHelper::custom('import.open', 'upload', '', Text::_('COM_XDECAROPEOPLE_IMPORT'), false);
            }
        }

        ToolbarHelper::custom('export.open', 'download', '', Text::_('COM_XDECAROPEOPLE_EXPORT'), false);

        if ($user->authorise('core.edit', 'com_xdecaropeople')) {
            ToolbarHelper::editList('person.edit');
        }

        if ($user->authorise('core.edit.state', 'com_xdecaropeople')) {
            if ($isTrashed) {
                ToolbarHelper::custom('people.publish', 'refresh', '', Text::_('COM_XDECAROPEOPLE_TOOLBAR_RESTORE'), true);
            } else {
                ToolbarHelper::publish('people.publish', 'JTOOLBAR_PUBLISH', true);
                ToolbarHelper::unpublish('people.unpublish', 'JTOOLBAR_UNPUBLISH', true);
                ToolbarHelper::trash('people.trash');
            }
        }

        if ($isTrashed && $user->authorise('core.delete', 'com_xdecaropeople')) {
            ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'people.delete');
        }

        if ($user->authorise('core.admin', 'com_xdecaropeople')) {
            ToolbarHelper::preferences('com_xdecaropeople');
        }

        parent::display($tpl);
    }

    public function isColumnVisible(string $column): bool
    {
        if (!in_array($column, self::AVAILABLE_COLUMNS, true)) {
            return false;
        }

        if (in_array($column, ['birth_date', 'birth_place'], true) && !$this->canIdentityDetails) {
            return false;
        }

        return in_array($column, $this->visibleColumns, true);
    }

    private function resolveVisibleColumns(): array
    {
        $params = ComponentHelper::getParams('com_xdecaropeople');
        $configured = $params->get('list_visible_columns', self::AVAILABLE_COLUMNS);

        if (is_string($configured)) {
            $configured = array_filter(array_map('trim', explode(',', $configured)));
        } elseif (is_object($configured)) {
            $configured = (array) $configured;
        }

        if (!is_array($configured)) {
            $configured = self::AVAILABLE_COLUMNS;
        }

        $visible = array_values(array_intersect(self::AVAILABLE_COLUMNS, array_map('strval', $configured)));

        return $visible !== [] ? $visible : ['display_name'];
    }
}
