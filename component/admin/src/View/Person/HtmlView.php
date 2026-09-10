<?php

namespace xdecaro\Component\People\Administrator\View\Person;
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Throwable;
use xdecaro\Component\People\Administrator\Extension\PeopleComponent;

final class HtmlView extends BaseHtmlView
{
    public $form;
    public $item;
    public ?int $profileCompleteness = null;

    public function display($tpl = null): void
    {
        $this->form = $this->get('Form');
        $this->item = $this->get('Item');

        $app = Factory::getApplication();
        $user = $app->getIdentity();
        $isNew = empty($this->item->id);
        if (!$user->authorise($isNew ? 'core.create' : 'core.edit', 'com_xdecaropeople')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $component = $app->bootComponent('com_xdecaropeople');
        if ($component instanceof PeopleComponent) {
            $component->getCoreIntegrationService()->enableUi($this->document->getWebAssetManager());

            if (!$isNew && ($user->authorise('people.view_sensitive', 'com_xdecaropeople') || $user->authorise('core.admin', 'com_xdecaropeople'))) {
                try {
                    $person = $component->getPersonProviderService()->getPerson((int) $this->item->id, true);
                    $this->profileCompleteness = isset($person['profile_completeness']) ? (int) $person['profile_completeness'] : null;
                } catch (Throwable) {
                    $this->profileCompleteness = null;
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
        ]);

        $assets = $this->document->getWebAssetManager();
        $assets->useStyle('com_xdecaropeople.admin');

        // Use a patch-specific asset name so an already registered 1.2.0/1.2.1
        // definition cannot shadow the hotfix script after an in-place update.
        $assets->registerAndUseScript(
            'com_xdecaropeople.person-form-1.2.2',
            'com_xdecaropeople/js/person-form.js',
            ['version' => '1.2.2'],
            ['defer' => true],
            ['core']
        );

        ToolbarHelper::title($isNew ? Text::_('COM_XDECAROPEOPLE_PERSON_NEW') : Text::_('COM_XDECAROPEOPLE_PERSON_EDIT'), 'user');
        ToolbarHelper::apply('person.apply');
        ToolbarHelper::save('person.save');
        ToolbarHelper::save2new('person.save2new');
        ToolbarHelper::cancel('person.cancel');

        parent::display($tpl);
    }
}
