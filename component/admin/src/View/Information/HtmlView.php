<?php
namespace xdecaro\Component\People\Administrator\View\Information;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;use Joomla\CMS\Language\Text;use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;use Joomla\CMS\Toolbar\ToolbarHelper;
final class HtmlView extends BaseHtmlView{public array $diagnostics=[];public function display($tpl=null):void{if(!Factory::getApplication()->getIdentity()->authorise('core.manage','com_xdecaropeople'))throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);$this->diagnostics=(array)$this->get('Diagnostics');ToolbarHelper::title(Text::_('COM_XDECAROPEOPLE_INFORMATION'),'info-circle');parent::display($tpl);}}
