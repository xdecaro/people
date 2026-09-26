<?php

namespace xdecaro\Component\People\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use RuntimeException;
use xdecaro\Component\People\Administrator\Extension\PeopleComponent;

final class PeopleController extends AdminController
{
    protected $option = 'com_xdecaropeople';
    protected $view_list = 'people';

    public function getModel($name = 'Person', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function publish()
    {
        $this->checkToken();
        $ids = array_values(array_filter((array) $this->input->get('cid', [], 'int')));
        $notifications = $this->getPeopleComponent()->getNotificationIntegrationService();
        $before = [];

        foreach ($ids as $id) {
            $snapshot = $notifications->snapshot((int) $id);
            if ($snapshot) $before[(int) $id] = $snapshot;
        }

        parent::publish();

        foreach ($ids as $id) {
            $id = (int) $id;
            $previous = $before[$id] ?? null;
            $after = $notifications->snapshot($id);
            if (!$previous || !$after) continue;
            $previousState = (int) ($previous['state'] ?? 0);
            $newState = (int) ($after['state'] ?? 0);
            if ($previousState !== $newState) $notifications->notifyPersonStateChanged($after, $previousState, $newState);
        }
    }

    public function delete(): void
    {
        $this->checkToken();
        $user = $this->app->getIdentity();
        if (!$user->authorise('core.edit.state', 'com_xdecaropeople')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $ids = array_values(array_filter((array) $this->input->get('cid', [], 'int')));
        $count = $this->getPeopleComponent()->getPersonTrashService()->trash($ids, (int) $user->id);
        $this->setRedirect('index.php?option=com_xdecaropeople&view=people');
        $this->app->enqueueMessage($count . ' record spostati nel cestino.', 'success');
    }

    public function restoreTrash(): void
    {
        $this->checkToken();
        $user = $this->app->getIdentity();
        if (!$user->authorise('core.edit.state', 'com_xdecaropeople')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $ids = array_values(array_filter((array) $this->input->get('cid', [], 'int')));
        $count = $this->getPeopleComponent()->getPersonTrashService()->restore($ids, (int) $user->id);
        $this->setRedirect('index.php?option=com_xdecaropeople&view=people&filter_state=-2');
        $this->app->enqueueMessage($count . ' record ripristinati.', 'success');
    }

    public function purge(): void
    {
        $this->checkToken();
        $user = $this->app->getIdentity();
        if (!$user->authorise('core.delete', 'com_xdecaropeople')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        if (!$this->input->getBool('confirm_purge')) {
            throw new RuntimeException('Permanent deletion requires explicit confirmation.', 400);
        }

        $ids = array_values(array_filter((array) $this->input->get('cid', [], 'int')));
        $count = $this->getPeopleComponent()->getPersonTrashService()->purge($ids, (int) $user->id);
        $this->setRedirect('index.php?option=com_xdecaropeople&view=people&filter_state=-2');
        $this->app->enqueueMessage($count . ' record eliminati definitivamente.', 'success');
    }

    private function getPeopleComponent(): PeopleComponent
    {
        $component = $this->app->bootComponent('com_xdecaropeople');
        if (!$component instanceof PeopleComponent) throw new RuntimeException('People component service is unavailable.');
        return $component;
    }
}
