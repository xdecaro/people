<?php

namespace xdecaro\Component\People\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use RuntimeException;
use Throwable;
use xdecaro\Core\Location\WorldLocationService;

final class LocationController extends BaseController
{
    public function search(): void
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        try {
            if (!$user->authorise('core.manage', 'com_xdecaropeople') && !$user->authorise('core.admin', 'com_xdecaropeople')) {
                throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            if (!Session::checkToken('get')) {
                throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
            }

            if (!class_exists(WorldLocationService::class)) {
                throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_ERROR_WORLD_LOCATION_CORE_REQUIRED'), 503);
            }

            $query = trim($app->input->getString('q'));
            $country = strtoupper(trim($app->input->getCmd('country')));
            $limit = max(1, min(30, $app->input->getInt('limit', 15)));
            $language = strtolower(substr(str_replace('_', '-', $app->getLanguage()->getTag()), 0, 2));

            if (mb_strlen($query) < 2) {
                echo new JsonResponse(['items' => []]);
                $app->close();
                return;
            }

            $service = WorldLocationService::fromJoomlaConfiguration();
            $items = $service->searchCities($query, $country !== '' ? $country : null, $language, $limit);

            echo new JsonResponse(['items' => $items]);
        } catch (Throwable $exception) {
            Log::add('People world location search failed: ' . $exception->getMessage(), Log::WARNING, 'com_xdecaropeople');
            $message = $exception instanceof RuntimeException && $exception->getCode() === 403
                ? $exception->getMessage()
                : Text::_('COM_XDECAROPEOPLE_ERROR_WORLD_LOCATION_UNAVAILABLE');
            echo new JsonResponse(null, $message, true);
        }

        $app->close();
    }
}
