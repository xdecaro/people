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
        $this->groups = (array) $this->get('Groups');

        ToolbarHelper::title(Text::_('COM_XDECAROPEOPLE_DUPLICATES'), 'search');

        parent::display($tpl);
    }
}
