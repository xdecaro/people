<?php
namespace xdecaro\Component\People\Administrator\View\Person;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use xdecaro\Component\People\Administrator\Extension\PeopleComponent;

final class HtmlView extends BaseHtmlView
{
    public $form;
    public $item;

    public function display($tpl = null): void
    {
        $this->form = $this->get('Form');
        $this->item = $this->get('Item');
        $user = Factory::getApplication()->getIdentity();
        $new = empty($this->item->id);

        if (!$user->authorise($new ? 'core.create' : 'core.edit', 'com_xdecaropeople')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $component = Factory::getApplication()->bootComponent('com_xdecaropeople');

        if ($component instanceof PeopleComponent) {
            $component->getCoreIntegrationService()->enableUi($this->document->getWebAssetManager());
        }

        $wa = $this->document->getWebAssetManager();
        $wa->useStyle('com_xdecaropeople.admin');
        $wa->useScript('com_xdecaropeople.person');

        ToolbarHelper::title(
            $new ? Text::_('COM_XDECAROPEOPLE_PERSON_NEW') : Text::_('COM_XDECAROPEOPLE_PERSON_EDIT'),
            'user'
        );
        ToolbarHelper::apply('person.apply');
        ToolbarHelper::save('person.save');
        ToolbarHelper::save2new('person.save2new');
        ToolbarHelper::cancel('person.cancel');

        parent::display($tpl);
    }
}
