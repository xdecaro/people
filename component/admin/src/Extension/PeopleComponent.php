<?php

namespace xdecaro\Component\People\Administrator\Extension;
defined('_JEXEC') or die;

use Joomla\CMS\Extension\MVCComponent;
use RuntimeException;
use xdecaro\Component\People\Administrator\Service\BackupService;
use xdecaro\Component\People\Administrator\Service\BackupStorageService;
use xdecaro\Component\People\Administrator\Service\CompetitionsIntegrationService;
use xdecaro\Component\People\Administrator\Service\CoreIntegrationService;
use xdecaro\Component\People\Administrator\Service\DuplicateService;
use xdecaro\Component\People\Administrator\Service\ImportService;
use xdecaro\Component\People\Administrator\Service\IntegrityService;
use xdecaro\Component\People\Administrator\Service\MaintenanceLogService;
use xdecaro\Component\People\Administrator\Service\NotificationIntegrationService;
use xdecaro\Component\People\Administrator\Service\OrganizationsIntegrationService;
use xdecaro\Component\People\Administrator\Service\MembershipIntegrationService;
use xdecaro\Component\People\Administrator\Service\PersonProviderService;
use xdecaro\Component\People\Administrator\Service\PersonTrashService;
use xdecaro\Component\People\Administrator\Service\RestoreService;

final class PeopleComponent extends MVCComponent
{
    private ?CoreIntegrationService $core=null; private ?PersonProviderService $provider=null; private ?DuplicateService $duplicates=null; private ?ImportService $importer=null; private ?NotificationIntegrationService $notifications=null; private ?CompetitionsIntegrationService $competitions=null; private ?OrganizationsIntegrationService $organizations=null; private ?MembershipIntegrationService $membership=null; private ?MaintenanceLogService $maintenanceLog=null; private ?PersonTrashService $trash=null; private ?BackupStorageService $backupStorage=null; private ?BackupService $backup=null; private ?RestoreService $restore=null; private ?IntegrityService $integrity=null;
    public function setCoreIntegrationService(CoreIntegrationService $s):void{$this->core=$s;} public function getCoreIntegrationService():CoreIntegrationService{return $this->core??=new CoreIntegrationService();}
    public function setPersonProviderService(PersonProviderService $s):void{$this->provider=$s;} public function getPersonProviderService():PersonProviderService{if(!$this->provider)throw new RuntimeException('People provider not initialized.');return $this->provider;}
    public function setDuplicateService(DuplicateService $s):void{$this->duplicates=$s;} public function getDuplicateService():DuplicateService{if(!$this->duplicates)throw new RuntimeException('Duplicate service not initialized.');return $this->duplicates;}
    public function setImportService(ImportService $s):void{$this->importer=$s;} public function getImportService():ImportService{if(!$this->importer)throw new RuntimeException('People import service not initialized.');return $this->importer;}
    public function setNotificationIntegrationService(NotificationIntegrationService $s):void{$this->notifications=$s;} public function getNotificationIntegrationService():NotificationIntegrationService{if(!$this->notifications)throw new RuntimeException('People notification integration service not initialized.');return $this->notifications;}
    public function setCompetitionsIntegrationService(CompetitionsIntegrationService $s):void{$this->competitions=$s;} public function getCompetitionsIntegrationService():CompetitionsIntegrationService{if(!$this->competitions)throw new RuntimeException('People Competitions integration service not initialized.');return $this->competitions;}
    public function setOrganizationsIntegrationService(OrganizationsIntegrationService $s):void{$this->organizations=$s;} public function getOrganizationsIntegrationService():OrganizationsIntegrationService{if(!$this->organizations)throw new RuntimeException('People Organizations integration service not initialized.');return $this->organizations;}
    public function setMembershipIntegrationService(MembershipIntegrationService $s):void{$this->membership=$s;} public function getMembershipIntegrationService():MembershipIntegrationService{if(!$this->membership)throw new RuntimeException('People Membership integration service not initialized.');return $this->membership;}
    public function setMaintenanceLogService(MaintenanceLogService $s):void{$this->maintenanceLog=$s;} public function getMaintenanceLogService():MaintenanceLogService{if(!$this->maintenanceLog)throw new RuntimeException('People maintenance log service not initialized.');return $this->maintenanceLog;}
    public function setPersonTrashService(PersonTrashService $s):void{$this->trash=$s;} public function getPersonTrashService():PersonTrashService{if(!$this->trash)throw new RuntimeException('People trash service not initialized.');return $this->trash;}
    public function setBackupStorageService(BackupStorageService $s):void{$this->backupStorage=$s;} public function getBackupStorageService():BackupStorageService{if(!$this->backupStorage)throw new RuntimeException('People backup storage service not initialized.');return $this->backupStorage;}
    public function setBackupService(BackupService $s):void{$this->backup=$s;} public function getBackupService():BackupService{if(!$this->backup)throw new RuntimeException('People backup service not initialized.');return $this->backup;}
    public function setRestoreService(RestoreService $s):void{$this->restore=$s;} public function getRestoreService():RestoreService{if(!$this->restore)throw new RuntimeException('People restore service not initialized.');return $this->restore;}
    public function setIntegrityService(IntegrityService $s):void{$this->integrity=$s;} public function getIntegrityService():IntegrityService{if(!$this->integrity)throw new RuntimeException('People integrity service not initialized.');return $this->integrity;}
}
