<?php

namespace xdecaro\Component\People\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use RuntimeException;
use Throwable;
use xdecaro\Component\People\Administrator\Extension\PeopleComponent;

final class DuplicateController extends BaseController
{
    public function dismiss(): void
    {
        $this->checkRequestToken();

        try {
            $service = $this->getPeopleComponent()->getDuplicateService();
            $service->ignoreGroup(
                $this->input->post->getCmd('match_type'),
                (string) $this->input->post->get('match_key', '', 'raw'),
                (array) $this->input->post->get('record_ids', [], 'array'),
                (int) Factory::getApplication()->getIdentity()->id
            );

            Factory::getApplication()->enqueueMessage(
                Text::_('COM_XDECAROPEOPLE_DUPLICATE_DISMISSED'),
                'success'
            );
        } catch (Throwable $exception) {
            Factory::getApplication()->enqueueMessage(
                $this->safeErrorMessage($exception),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_xdecaropeople&view=duplicates', false));
    }

    public function dismissBatch(): void
    {
        $this->checkRequestToken();

        $filter = $this->input->post->getCmd('duplicate_filter', 'all');
        if (!in_array($filter, ['all', 'conflict', 'strong', 'possible'], true)) {
            $filter = 'all';
        }

        try {
            $raw = trim((string) $this->input->post->get('selected_signatures', '', 'raw'));
            $signatures = $raw === ''
                ? []
                : preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);

            $count = $this->getPeopleComponent()->getDuplicateService()->ignoreGroupsBySignatures(
                is_array($signatures) ? $signatures : [],
                (int) Factory::getApplication()->getIdentity()->id
            );

            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_XDECAROPEOPLE_DUPLICATE_BATCH_DISMISSED', $count),
                'success'
            );
        } catch (Throwable $exception) {
            Factory::getApplication()->enqueueMessage(
                $this->safeErrorMessage($exception),
                'error'
            );
        }

        $url = 'index.php?option=com_xdecaropeople&view=duplicates';
        if ($filter !== 'all') {
            $url .= '&duplicate_filter=' . rawurlencode($filter);
        }

        $this->setRedirect(Route::_($url, false));
    }

    public function merge(): void
    {
        $this->checkRequestToken();

        try {
            $service = $this->getPeopleComponent()->getDuplicateService();
            $result = $service->mergeGroup(
                $this->input->post->getInt('target_id'),
                $this->input->post->getCmd('match_type'),
                (string) $this->input->post->get('match_key', '', 'raw'),
                (array) $this->input->post->get('record_ids', [], 'array'),
                (int) Factory::getApplication()->getIdentity()->id
            );

            $copied = count((array) ($result['copied_fields'] ?? []));
            Factory::getApplication()->enqueueMessage(
                Text::sprintf(
                    'COM_XDECAROPEOPLE_DUPLICATE_MERGED',
                    (int) ($result['target_id'] ?? 0),
                    count((array) ($result['source_ids'] ?? [])),
                    $copied
                ),
                'success'
            );
        } catch (Throwable $exception) {
            Factory::getApplication()->enqueueMessage(
                $this->safeErrorMessage($exception),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_xdecaropeople&view=duplicates', false));
    }

    private function checkRequestToken(): void
    {
        if (!Session::checkToken('post')) {
            throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
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

    private function safeErrorMessage(Throwable $exception): string
    {
        $code = (int) $exception->getCode();
        if (in_array($code, [400, 403, 404, 409], true) && trim($exception->getMessage()) !== '') {
            return $exception->getMessage();
        }

        return Text::_('COM_XDECAROPEOPLE_DUPLICATE_ERROR_GENERIC');
    }
}
