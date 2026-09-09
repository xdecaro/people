<?php
namespace xdecaro\Component\People\Administrator\Controller;
defined('_JEXEC') or die;
use Joomla\CMS\MVC\Controller\AdminController;
final class PeopleController extends AdminController{public function getModel($name='Person',$prefix='Administrator',$config=['ignore_request'=>true]){return parent::getModel($name,$prefix,$config);}}
