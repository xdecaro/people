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
            if ($snapshot) {
                $before[(int) $id] = $snapshot;
            }
        }

        parent::publish();

        foreach ($ids as $id) {
            $id = (int) $id;
            $previous = $before[$id] ?? null;
            $after = $notifications->snapshot($id);
            if (!$previous || !$after) {
                continue;
            }

            $previousState = (int) ($previous['state'] ?? 0);
            $newState = (int) ($after['state'] ?? 0);
            if ($previousState !== $newState) {
                $notifications->notifyPersonStateChanged($after, $previousState, $newState);
            }
        }
    }

    public function delete()
    {
        $this->checkToken();
        $user = $this->app->getIdentity();
        if (!$user->authorise('core.edit.state', 'com_xdecaropeople') && !$user->authorise('core.admin', 'com_xdecaropeople')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $ids = $this->selectedIds();
        $this->getPeopleComponent()->getPersonTrashService()->trash($ids, (int) $user->id);
        $this->setRedirect('index.php?option=com_xdecaropeople&view=people');
    }

    public function restoreTrash()
    {
        $this->checkToken();
        $user = $this->app->getIdentity();
        if (!$user->authorise('core.edit.state', 'com_xdecaropeople') && !$user->authorise('core.admin', 'com_xdecaropeople')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->getPeopleComponent()->getPersonTrashService()->restore($this->selectedIds(), (int) $user->id);
        $this->setRedirect('index.php?option=com_xdecaropeople&view=people&filter_state=-2');
    }

    public function purge()
    {
        $this->checkToken();
        $user = $this->app->getIdentity();
        if (!$user->authorise('core.delete', 'com_xdecaropeople') && !$user->authorise('core.admin', 'com_xdecaropeople')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $confirmed = (int) $this->input->getInt('confirm_purge', 0) === 1;
        if (!$confirmed) {
            throw new RuntimeException('Permanent deletion requires explicit confirmation.');
        }

        $this->getPeopleComponent()->getPersonTrashService()->purge($this->selectedIds(), (int) $user->id);
        $this->setRedirect('index.php?option=com_xdecaropeople&view=people&filter_state=-2');
    }

    private function selectedIds(): array
    {
        return array_values(array_filter(array_map('intval', (array) $this->input->get('cid', [], 'array')), static fn(int $id): bool => $id > 0));
    }

    private function getPeopleComponent(): PeopleComponent
    {
        $component = $this->app->bootComponent('com_xdecaropeople');
        if (!$component instanceof PeopleComponent) {
            throw new RuntimeException('People component service is unavailable.');
        }

        return $component;
    }
}
