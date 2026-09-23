<?php

namespace xdecaro\Component\People\Administrator\View\People;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use xdecaro\Component\People\Administrator\Extension\PeopleComponent;

final class HtmlView extends BaseHtmlView
{
    public array $items = [];
    public $pagination;
    public $state;
    public bool $canIdentityDetails = false;

    public function display($tpl = null): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', 'com_xdecaropeople')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->items = (array) $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $this->canIdentityDetails = $user->authorise('people.view_identity_details', 'com_xdecaropeople')
            || $user->authorise('people.view_sensitive', 'com_xdecaropeople')
            || $user->authorise('core.admin', 'com_xdecaropeople');

        $component = Factory::getApplication()->bootComponent('com_xdecaropeople');
        if ($component instanceof PeopleComponent) {
            $component->getCoreIntegrationService()->enableUi($this->document->getWebAssetManager());
        }

        $this->document->getWebAssetManager()\n            ->useStyle('com_xdecaropeople.admin')\n            ->useScript('com_xdecaropeople.export');

        ToolbarHelper::title(Text::_('COM_XDECAROPEOPLE_PEOPLE'), 'users');

        $isTrashed = (string) $this->state->get('filter.state') === '-2';

        if ($user->authorise('core.create', 'com_xdecaropeople')) {
            ToolbarHelper::addNew('person.add');

            if ($user->authorise('people.view_sensitive', 'com_xdecaropeople') || $user->authorise('core.admin', 'com_xdecaropeople')) {
                ToolbarHelper::custom('import.open', 'upload', '', Text::_('COM_XDECAROPEOPLE_IMPORT'), false);
            }
        }

        ToolbarHelper::custom('export.open', 'download', '', Text::_('COM_XDECAROPEOPLE_EXPORT'), false);\n\n        if ($user->authorise('core.edit', 'com_xdecaropeople')) {
            ToolbarHelper::editList('person.edit');
        }

        if ($user->authorise('core.edit.state', 'com_xdecaropeople')) {
            if ($isTrashed) {
                ToolbarHelper::custom(
                    'people.publish',
                    'refresh',
                    '',
                    Text::_('COM_XDECAROPEOPLE_TOOLBAR_RESTORE'),
                    true
                );
            } else {
                ToolbarHelper::publish('people.publish', 'JTOOLBAR_PUBLISH', true);
                ToolbarHelper::unpublish('people.unpublish', 'JTOOLBAR_UNPUBLISH', true);
                ToolbarHelper::trash('people.trash');
            }
        }

        // Permanent deletion is intentionally available only from the Trash
        // view, preventing an active person from being deleted by mistake.
        if ($isTrashed && $user->authorise('core.delete', 'com_xdecaropeople')) {
            ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'people.delete');
        }

        if ($user->authorise('core.admin', 'com_xdecaropeople')) {
            ToolbarHelper::preferences('com_xdecaropeople');
        }

        parent::display($tpl);
    }
}
