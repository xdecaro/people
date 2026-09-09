<?php
namespace xdecaro\Component\People\Administrator\Model;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;use Joomla\CMS\MVC\Model\BaseDatabaseModel;use xdecaro\Component\People\Administrator\Extension\PeopleComponent;use xdecaro\Component\People\Administrator\Service\CoreIntegrationService;
final class InformationModel extends BaseDatabaseModel{public function getDiagnostics():array{$c=Factory::getApplication()->bootComponent('com_xdecaropeople');$core=$c instanceof PeopleComponent?$c->getCoreIntegrationService():null;$db=$this->getDatabase();$table=$db->replacePrefix('#__xdecaropeople_people');return ['component_version'=>'1.0.1','core_version'=>$core?$core->getVersion():'','core_ok'=>$core?version_compare($core->getVersion(),CoreIntegrationService::MINIMUM_CORE,'>='):false,'table_ok'=>in_array($table,$db->getTableList(),true),'php_version'=>PHP_VERSION,'joomla_version'=>JVERSION];}}
