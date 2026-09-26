<?php

namespace xdecaro\Component\People\Administrator\View\Dashboard;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;
use Throwable;
use xdecaro\Component\People\Administrator\Extension\PeopleComponent;

final class HtmlView extends BaseHtmlView
{
    public int $total = 0;

    public array $duplicateStats = [
        'total' => 0,
        'conflict' => 0,
        'strong' => 0,
        'possible' => 0,
        'records' => 0,
    ];

    public array $dataQuality = [
        'missing_birth_date' => 0,
        'missing_tax_identifier' => 0,
        'missing_email' => 0,
        'missing_phone' => 0,
        'missing_any' => 0,
    ];

    public bool $organizationsAvailable = false;
    public bool $membershipAvailable = false;
    public bool $canCreate = false;
    public bool $canImport = false;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_xdecaropeople')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $app->getLanguage()->load('com_xdecaropeople.dashboard', JPATH_ADMINISTRATOR, null, true);

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $this->loadPeopleSummary($db);

        $component = $app->bootComponent('com_xdecaropeople');
        if ($component instanceof PeopleComponent) {
            $component->getCoreIntegrationService()->enableUi($this->document->getWebAssetManager());
            $this->loadDuplicateSummary($component);

            try {
                $this->organizationsAvailable = $component
                    ->getOrganizationsIntegrationService()->isHistoryAvailable();
            } catch (Throwable) {
                $this->organizationsAvailable = false;
            }

            try {
                $this->membershipAvailable = $component
                    ->getMembershipIntegrationService()->isAvailable();
            } catch (Throwable) {
                $this->membershipAvailable = false;
            }
        }

        $this->canCreate = $user->authorise('core.create', 'com_xdecaropeople')
            || $user->authorise('core.admin', 'com_xdecaropeople');
        $this->canImport = $this->canCreate
            && (
                $user->authorise('people.view_sensitive', 'com_xdecaropeople')
                || $user->authorise('core.admin', 'com_xdecaropeople')
            );

        $this->document->getWebAssetManager()
            ->useStyle('com_xdecaropeople.admin')
            ->useStyle('com_xdecaropeople.dashboard');

        ToolbarHelper::title(Text::_('COM_XDECAROPEOPLE_DASHBOARD'), 'users');

        parent::display($tpl);
    }

    private function loadPeopleSummary(DatabaseInterface $db): void
    {
        $table = $db->quoteName('#__xdecaropeople_people');
        $state = $db->quoteName('state');

        $countQuery = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($table)
            ->where($state . ' >= 0');
        $this->total = (int) $db->setQuery($countQuery)->loadResult();

        $birthDate = $db->quoteName('birth_date');
        $taxIdentifier = $db->quoteName('tax_identifier');
        $email = $db->quoteName('email');
        $phone = $db->quoteName('phone');
        $empty = $db->quote('');

        $missingBirth = '(' . $birthDate . ' IS NULL OR ' . $birthDate . ' = ' . $empty . ')';
        $missingTax = '(' . $taxIdentifier . ' IS NULL OR TRIM(' . $taxIdentifier . ') = ' . $empty . ')';
        $missingEmail = '(' . $email . ' IS NULL OR TRIM(' . $email . ') = ' . $empty . ')';
        $missingPhone = '(' . $phone . ' IS NULL OR TRIM(' . $phone . ') = ' . $empty . ')';

        $qualityQuery = $db->getQuery(true)
            ->select([
                'SUM(CASE WHEN ' . $missingBirth . ' THEN 1 ELSE 0 END) AS missing_birth_date',
                'SUM(CASE WHEN ' . $missingTax . ' THEN 1 ELSE 0 END) AS missing_tax_identifier',
                'SUM(CASE WHEN ' . $missingEmail . ' THEN 1 ELSE 0 END) AS missing_email',
                'SUM(CASE WHEN ' . $missingPhone . ' THEN 1 ELSE 0 END) AS missing_phone',
                'SUM(CASE WHEN (' . $missingBirth . ' OR ' . $missingTax . ' OR ' . $missingEmail . ' OR ' . $missingPhone . ') THEN 1 ELSE 0 END) AS missing_any',
            ])
            ->from($table)
            ->where($state . ' >= 0');

        $row = (array) $db->setQuery($qualityQuery)->loadAssoc();

        foreach (array_keys($this->dataQuality) as $key) {
            $this->dataQuality[$key] = (int) ($row[$key] ?? 0);
        }
    }

    private function loadDuplicateSummary(PeopleComponent $component): void
    {
        $groups = array_values((array) $component->getDuplicateService()->find(500));
        $recordIds = [];

        foreach ($groups as $group) {
            $strength = (string) ($group['strength'] ?? 'possible');
            if (isset($this->duplicateStats[$strength])) {
                $this->duplicateStats[$strength]++;
            }

            foreach ((array) ($group['records'] ?? []) as $record) {
                $recordId = (int) ($record['id'] ?? 0);
                if ($recordId > 0) {
                    $recordIds[$recordId] = true;
                }
            }
        }

        $this->duplicateStats['total'] = count($groups);
        $this->duplicateStats['records'] = count($recordIds);
    }
}
