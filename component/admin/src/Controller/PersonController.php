<?php

namespace xdecaro\Component\People\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\FormController;
use RuntimeException;
use Throwable;
use xdecaro\Component\People\Administrator\Extension\PeopleComponent;

final class PersonController extends FormController
{
    protected $option = 'com_xdecaropeople';
    protected $view_item = 'person';
    protected $view_list = 'people';

    private $personModel = null;

    public function getModel($name = '', $prefix = '', $config = ['ignore_request' => true])
    {
        if ($name === '' && $prefix === '') {
            if ($this->personModel === null) {
                $this->personModel = parent::getModel($name, $prefix, $config);
            }

            return $this->personModel;
        }

        return parent::getModel($name, $prefix, $config);
    }

    public function save($key = null, $urlVar = null)
    {
        $component = $this->getPeopleComponent();
        $notifications = $component->getNotificationIntegrationService();
        $posted = (array) $this->input->post->get('jform', [], 'array');
        $recordId = (int) ($posted['id'] ?? $this->input->getInt('id'));
        $before = $recordId > 0 ? $notifications->snapshot($recordId) : null;
        $duplicateBefore = false;

        if ($before !== null) {
            try {
                $duplicateBefore = $component->getDuplicateService()->hasMatch($before, $recordId);
            } catch (Throwable $exception) {
                Log::add(
                    'People pre-save duplicate notification check failed: ' . $exception->getMessage(),
                    Log::WARNING,
                    'com_xdecaropeople'
                );
            }
        }

        $model = $this->getModel();
        $saved = parent::save($key, $urlVar);
        if (!$saved) {
            return false;
        }

        $savedId = (int) $model->getState($model->getName() . '.id');
        if ($savedId < 1) {
            $savedId = $recordId;
        }

        $after = $notifications->snapshot($savedId);
        if (!$after) {
            return true;
        }

        if ($before === null) {
            $notifications->notifyPersonCreated($after);
        } else {
            $changedFields = $notifications->importantChangedFields($before, $after);
            if ($changedFields !== []) {
                $notifications->notifyPersonUpdated($after, $changedFields);
            }

            $previousState = (int) ($before['state'] ?? 0);
            $newState = (int) ($after['state'] ?? 0);
            if ($previousState !== $newState) {
                $notifications->notifyPersonStateChanged($after, $previousState, $newState);
            }
        }

        try {
            $duplicateAfter = $component->getDuplicateService()->hasMatch($after, $savedId);
            if ($duplicateAfter && !$duplicateBefore) {
                $notifications->notifyPossibleDuplicate($after);
            }
        } catch (Throwable $exception) {
            Log::add(
                'People post-save duplicate notification check failed: ' . $exception->getMessage(),
                Log::WARNING,
                'com_xdecaropeople'
            );
        }

        return true;
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
