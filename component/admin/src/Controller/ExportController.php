<?php

namespace xdecaro\Component\People\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;
use RuntimeException;
use xdecaro\Component\People\Administrator\Service\ExportService;

final class ExportController extends BaseController
{
    protected $option = 'com_xdecaropeople';

    public function download(): void
    {
        $this->checkToken();

        $user = $this->app->getIdentity();

        if (!$user->authorise('core.manage', 'com_xdecaropeople')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $format = strtolower($this->input->getCmd('export_format', 'xlsx'));
        $scope = strtolower($this->input->getCmd('export_scope', 'filtered'));

        if (!in_array($format, ['xlsx', 'csv', 'pdf'], true)) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_EXPORT_ERROR_FORMAT'), 400);
        }

        if (!in_array($scope, ['all', 'filtered', 'selected'], true)) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_EXPORT_ERROR_SCOPE'), 400);
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) $this->input->get('cid', [], 'array')),
            static fn (int $id): bool => $id > 0
        )));

        if ($scope === 'selected' && $ids === []) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_EXPORT_NO_SELECTION'), 400);
        }

        $search = $scope === 'filtered' ? trim($this->input->getString('filter_search', '')) : '';
        $state = $scope === 'filtered' ? $this->input->getString('filter_state', '') : '';

        $canIdentityDetails = $user->authorise('people.view_identity_details', 'com_xdecaropeople')
            || $user->authorise('people.view_sensitive', 'com_xdecaropeople')
            || $user->authorise('core.admin', 'com_xdecaropeople');
        $canSensitive = $user->authorise('people.view_sensitive', 'com_xdecaropeople')
            || $user->authorise('core.admin', 'com_xdecaropeople');

        $service = new ExportService(Factory::getContainer()->get(DatabaseInterface::class));
        $rows = $service->loadRows($scope, $ids, $search, $state, $canIdentityDetails, $canSensitive);

        [$mimeType, $payload] = match ($format) {
            'xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $service->toXlsx($rows, $canIdentityDetails, $canSensitive),
            ],
            'pdf' => ['application/pdf', $service->toPdf($rows, $canIdentityDetails)],
            default => ['text/csv; charset=UTF-8', $service->toCsv($rows, $canIdentityDetails, $canSensitive)],
        };

        $filename = 'people-' . Factory::getDate()->format('Y-m-d-His') . '.' . $format;

        $this->app->setHeader('Content-Type', $mimeType, true);
        $this->app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);
        $this->app->setHeader('Content-Length', (string) strlen($payload), true);
        $this->app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true);
        $this->app->sendHeaders();

        echo $payload;
        $this->app->close();
    }
}
