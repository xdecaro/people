<?php

namespace xdecaro\Component\People\Administrator\Controller;

defined('_JEXEC') or die;

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

    private function getPeopleComponent(): PeopleComponent
    {
        $component = $this->app->bootComponent('com_xdecaropeople');
        if (!$component instanceof PeopleComponent) {
            throw new RuntimeException('People component service is unavailable.');
        }

        return $component;
    }
}
