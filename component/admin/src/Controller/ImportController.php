<?php

namespace xdecaro\Component\People\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use RuntimeException;
use Throwable;
use xdecaro\Component\People\Administrator\Extension\PeopleComponent;

final class ImportController extends BaseController
{
    public function open(): void
    {
        $this->checkRequestToken();
        $this->assertImportPermission();
        $this->setRedirect(Route::_('index.php?option=com_xdecaropeople&view=import', false));
    }

    public function analyze(): void
    {
        $app = Factory::getApplication();

        try {
            $this->checkRequestToken();
            $this->assertImportPermission();

            $payload = $this->payload();
            $rows = array_values(array_filter((array) ($payload['rows'] ?? []), 'is_array'));

            if (count($rows) > 5000) {
                throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_IMPORT_ERROR_TOO_MANY_ROWS'), 400);
            }

            $existingRows = $this->getPeopleComponent()->getImportService()->analyzeCandidates($rows);
            echo new JsonResponse(['existing_rows' => $existingRows]);
        } catch (Throwable $exception) {
            $this->jsonError($exception);
        }

        $app->close();
    }

    public function batch(): void
    {
        $app = Factory::getApplication();

        try {
            $this->checkRequestToken();
            $this->assertImportPermission();

            $payload = $this->payload();
            $rows = array_values(array_filter((array) ($payload['rows'] ?? []), 'is_array'));
            $profile = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($payload['profile'] ?? 'generic')) ?: 'generic';

            if ($rows === []) {
                throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_IMPORT_ERROR_EMPTY_BATCH'), 400);
            }
            if (count($rows) > 150) {
                throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_IMPORT_ERROR_BATCH_LIMIT'), 400);
            }

            $result = $this->getPeopleComponent()
                ->getImportService()
                ->importBatch($rows, (int) $app->getIdentity()->id, $profile);

            echo new JsonResponse($result);
        } catch (Throwable $exception) {
            $this->jsonError($exception);
        }

        $app->close();
    }

    private function payload(): array
    {
        $raw = (string) Factory::getApplication()->input->post->get('payload', '', 'raw');
        if ($raw === '') {
            return [];
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_IMPORT_ERROR_INVALID_PAYLOAD'), 400);
        }

        return $payload;
    }

    private function checkRequestToken(): void
    {
        if (!Session::checkToken('post')) {
            throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }
    }

    private function assertImportPermission(): void
    {
        $user = Factory::getApplication()->getIdentity();
        $isAdmin = $user->authorise('core.admin', 'com_xdecaropeople');
        $canCreate = $user->authorise('core.create', 'com_xdecaropeople');
        $canSensitive = $user->authorise('people.view_sensitive', 'com_xdecaropeople');

        if (!$isAdmin && (!$canCreate || !$canSensitive)) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    private function getPeopleComponent(): PeopleComponent
    {
        $component = Factory::getApplication()->bootComponent('com_xdecaropeople');
        if (!$component instanceof PeopleComponent) {
            throw new RuntimeException('People component service is unavailable.', 503);
        }

        return $component;
    }

    private function jsonError(Throwable $exception): void
    {
        $code = (int) $exception->getCode();
        if (!in_array($code, [400, 403], true)) {
            Log::add('People import request failed: ' . $exception->getMessage(), Log::ERROR, 'com_xdecaropeople');
        }

        $message = in_array($code, [400, 403], true)
            ? $exception->getMessage()
            : Text::_('COM_XDECAROPEOPLE_IMPORT_ERROR_GENERIC');

        echo new JsonResponse(null, $message, true);
    }
}
