<?php
namespace xdecaro\Component\People\Administrator\Model;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;use Joomla\CMS\MVC\Model\BaseDatabaseModel;use xdecaro\Component\People\Administrator\Extension\PeopleComponent;
final class DuplicatesModel extends BaseDatabaseModel{public function getGroups():array{$c=Factory::getApplication()->bootComponent('com_xdecaropeople');return $c instanceof PeopleComponent?$c->getDuplicateService()->find():[];}}
