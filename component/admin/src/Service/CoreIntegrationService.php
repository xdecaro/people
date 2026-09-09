<?php
namespace xdecaro\Component\People\Administrator\Service;
defined('_JEXEC') or die;
use Joomla\CMS\WebAsset\WebAssetManager;
use xdecaro\Core\Integration\Capability;
use xdecaro\Core\Integration\CapabilityRegistry;
use xdecaro\Core\Integration\EntityReference;
final class CoreIntegrationService {
 public const COMPONENT='com_xdecaropeople'; public const MINIMUM_CORE='1.4.0';
 public function getVersion():string{return class_exists(\xdecaro\Core\Version::class)?trim((string)\xdecaro\Core\Version::VERSION):'';}
 public function enableUi(WebAssetManager $wam):bool{$v=$this->getVersion();if($v===''||version_compare($v,self::MINIMUM_CORE,'<')||!class_exists(\xdecaro\Core\Asset\AssetService::class))return false;try{return (new \xdecaro\Core\Asset\AssetService())->useComponents($wam);}catch(\Throwable){return false;}}
 public function createEntityReference(int|string $id):EntityReference{if(!class_exists(EntityReference::class))throw new \RuntimeException('Core reference API unavailable.');return new EntityReference(self::COMPONENT,'person',$id);}
 public function registerCapabilities(CapabilityRegistry $registry):void{$registry->registerMany([new Capability(self::COMPONENT,'people.provider','1.0.0'),new Capability(self::COMPONENT,'people.query','1.0.0'),new Capability(self::COMPONENT,'people.user_link','1.0.0'),new Capability(self::COMPONENT,'people.duplicates','1.0.0')]);}
}
