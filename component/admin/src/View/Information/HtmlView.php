<?php

namespace xdecaro\Component\People\Administrator\View\Information;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

final class HtmlView extends BaseHtmlView
{
    public array $diagnostics = [];
    public array $databaseSummary = [];
    public array $connectedComponents = [];
    public array $backups = [];
    public array $recentTrashed = [];
    public array $maintenanceActivity = [];
    public bool $canBackup = false;
    public bool $canRestore = false;
    public bool $canEditState = false;
    public bool $canDelete = false;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_xdecaropeople')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $app->getDocument()->getWebAssetManager()->useStyle('com_xdecaropeople.information');
        $this->diagnostics = (array) $this->get('Diagnostics');
        $this->databaseSummary = (array) $this->get('DatabaseSummary');
        $this->connectedComponents = (array) $this->get('ConnectedComponents');
        $this->backups = (array) $this->get('Backups');
        $this->recentTrashed = (array) $this->get('RecentTrashed');
        $this->maintenanceActivity = (array) $this->get('MaintenanceActivity');
        $this->canBackup = $user->authorise('people.backup', 'com_xdecaropeople');
        $this->canRestore = $user->authorise('people.restore', 'com_xdecaropeople');
        $this->canEditState = $user->authorise('core.edit.state', 'com_xdecaropeople');
        $this->canDelete = $user->authorise('core.delete', 'com_xdecaropeople');

        ToolbarHelper::title(Text::_('COM_XDECAROPEOPLE_INFORMATION'), 'info-circle');
        parent::display($tpl);
    }
}
