<?php

namespace xdecaro\Component\People\Administrator\View\Person;
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Throwable;
use xdecaro\Component\People\Administrator\Extension\PeopleComponent;

final class HtmlView extends BaseHtmlView
{
    public $form;
    public $item;
    public bool $competitionsHistoryAvailable = false;
    public array $competitionsHistory = [];
    public bool $organizationsHistoryAvailable = false;
    public array $organizationsCurrent = [];
    public array $organizationsHistory = [];
    public bool $membershipAvailable = false;
    public array $memberships = [];

    public function display($tpl = null): void
    {
        $this->form = $this->get('Form');
        $this->item = $this->get('Item');

        $app = Factory::getApplication();
        $language = $app->getLanguage();
        if ($language !== null) {
            $language->load('com_xdecaropeople_competitions', JPATH_ADMINISTRATOR);
            $language->load('com_xdecaropeople_organizations', JPATH_ADMINISTRATOR);
        }

        $user = $app->getIdentity();
        $isNew = empty($this->item->id);
        if (!$user->authorise($isNew ? 'core.create' : 'core.edit', 'com_xdecaropeople')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $component = $app->bootComponent('com_xdecaropeople');
        if ($component instanceof PeopleComponent) {
            $component->getCoreIntegrationService()->enableUi($this->document->getWebAssetManager());

            $personUuid = strtolower(trim((string) ($this->item->uuid ?? '')));
            if (!$isNew && $personUuid !== '') {
                try {
                    $organizations = $component->getOrganizationsIntegrationService();
                    if ($organizations->isHistoryAvailable()) {
                        $organizationAppointments = $organizations->getPersonAppointments($personUuid);
                        foreach ($organizationAppointments as $appointment) {
                            if (!empty($appointment['is_current'])) {
                                $this->organizationsCurrent[] = $appointment;
                            } else {
                                $this->organizationsHistory[] = $appointment;
                            }
                        }

                        if ($language !== null) {
                            $language->load('com_xdecaroorganizations', JPATH_ADMINISTRATOR);
                        }

                        $this->organizationsHistoryAvailable = true;
                    }
                } catch (Throwable $e) {
                    Log::add(
                        'People Organizations history integration is unavailable: ' . $e->getMessage(),
                        Log::WARNING,
                        'com_xdecaropeople'
                    );
                    $this->organizationsCurrent = [];
                    $this->organizationsHistory = [];
                    $this->organizationsHistoryAvailable = false;
                }

                try {
                    $membership = $component->getMembershipIntegrationService();
                    if ($membership->isAvailable()) {
                        $this->memberships = $membership->getPersonMemberships($personUuid);
                        $this->membershipAvailable = true;
                    }
                } catch (Throwable $e) {
                    Log::add(
                        'People Membership integration is unavailable: ' . $e->getMessage(),
                        Log::WARNING,
                        'com_xdecaropeople'
                    );
                    $this->memberships = [];
                    $this->membershipAvailable = false;
                }

                try {
                    $competitions = $component->getCompetitionsIntegrationService();
                    if ($competitions->isHistoryAvailable()) {
                        $this->competitionsHistory = $competitions->getPersonHistory($personUuid);
                        $this->competitionsHistoryAvailable = true;
                    }
                } catch (Throwable $e) {
                    Log::add(
                        'People Competitions history integration is unavailable: ' . $e->getMessage(),
                        Log::WARNING,
                        'com_xdecaropeople'
                    );
                    $this->competitionsHistory = [];
                    $this->competitionsHistoryAvailable = false;
                }
            }
        }

        $this->document->addScriptOptions('com_xdecaropeople.person', [
            'locationUrl' => 'index.php?option=com_xdecaropeople&task=location.search&format=json',
            'token' => Session::getFormToken(),
            'locationMinChars' => 2,
            'locationLoading' => Text::_('COM_XDECAROPEOPLE_LOCATION_LOADING'),
            'locationEmpty' => Text::_('COM_XDECAROPEOPLE_LOCATION_EMPTY'),
            'locationError' => Text::_('COM_XDECAROPEOPLE_ERROR_WORLD_LOCATION_UNAVAILABLE'),
            'locationSelectionRequired' => Text::_('COM_XDECAROPEOPLE_ERROR_LOCATION_SELECTION_REQUIRED'),
            'validationSummaryTitle' => Text::_('COM_XDECAROPEOPLE_VALIDATION_SUMMARY_TITLE'),
            'validationSummaryIntro' => Text::_('COM_XDECAROPEOPLE_VALIDATION_SUMMARY_INTRO'),
        ]);

        $assets = $this->document->getWebAssetManager();
        $assets->useStyle('com_xdecaropeople.admin');
        $assets->useScript('com_xdecaropeople.person-form');
        $assets->useScript('com_xdecaropeople.person-cancel-fix');

        ToolbarHelper::title($isNew ? Text::_('COM_XDECAROPEOPLE_PERSON_NEW') : Text::_('COM_XDECAROPEOPLE_PERSON_EDIT'), 'user');
        ToolbarHelper::apply('person.apply');
        ToolbarHelper::save('person.save');
        ToolbarHelper::save2new('person.save2new');
        ToolbarHelper::cancel('person.cancel');

        parent::display($tpl);
    }
}
