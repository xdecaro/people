<?php
namespace xdecaro\Component\People\Administrator\Service;
defined('_JEXEC') or die;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface; use Joomla\CMS\Extension\ComponentInterface; use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory; use Joomla\CMS\Extension\Service\Provider\MVCFactory; use Joomla\CMS\MVC\Factory\MVCFactoryInterface; use Joomla\Database\DatabaseInterface; use Joomla\DI\Container; use Joomla\DI\ServiceProviderInterface; use xdecaro\Component\People\Administrator\Extension\PeopleComponent;
return new class implements ServiceProviderInterface {
 public function register(Container $c):void{
  $c->registerServiceProvider(new MVCFactory('xdecaro\\Component\\People')); $c->registerServiceProvider(new ComponentDispatcherFactory('xdecaro\\Component\\People'));
  $c->share(CoreIntegrationService::class,static fn()=>new CoreIntegrationService());
  $c->share(PersonProviderService::class,static fn(Container $c)=>new PersonProviderService($c->get(DatabaseInterface::class),$c->get(CoreIntegrationService::class)));
  $c->share(DuplicateService::class,static fn(Container $c)=>new DuplicateService($c->get(DatabaseInterface::class)));
  $c->share(ImportService::class,static fn(Container $c)=>new ImportService($c->get(DatabaseInterface::class)));
  $c->share(NotificationIntegrationService::class,static fn(Container $c)=>new NotificationIntegrationService($c->get(DatabaseInterface::class)));
  $c->share(CompetitionsIntegrationService::class,static fn(Container $c)=>new CompetitionsIntegrationService($c->get(PersonProviderService::class)));
  $c->share(OrganizationsIntegrationService::class,static fn(Container $c)=>new OrganizationsIntegrationService($c->get(PersonProviderService::class)));
  $c->share(MembershipIntegrationService::class,static fn(Container $c)=>new MembershipIntegrationService($c->get(PersonProviderService::class)));
  $c->share(MaintenanceLogService::class,static fn(Container $c)=>new MaintenanceLogService($c->get(DatabaseInterface::class)));
  $c->share(PersonTrashService::class,static fn(Container $c)=>new PersonTrashService($c->get(DatabaseInterface::class),$c->get(MaintenanceLogService::class)));
  $c->share(BackupStorageService::class,static fn()=>new BackupStorageService());
  $c->share(BackupService::class,static fn(Container $c)=>new BackupService($c->get(DatabaseInterface::class),$c->get(BackupStorageService::class),$c->get(MaintenanceLogService::class)));
  $c->share(RestoreService::class,static fn(Container $c)=>new RestoreService($c->get(DatabaseInterface::class),$c->get(BackupService::class),$c->get(MaintenanceLogService::class),$c->get(PersonTrashService::class)));
  $c->share(IntegrityService::class,static fn(Container $c)=>new IntegrityService($c->get(DatabaseInterface::class),$c->get(BackupStorageService::class),$c->get(MaintenanceLogService::class)));
  $c->set(ComponentInterface::class,static function(Container $c):ComponentInterface{$x=new PeopleComponent($c->get(ComponentDispatcherFactoryInterface::class));$x->setMVCFactory($c->get(MVCFactoryInterface::class));$x->setCoreIntegrationService($c->get(CoreIntegrationService::class));$x->setPersonProviderService($c->get(PersonProviderService::class));$x->setDuplicateService($c->get(DuplicateService::class));$x->setImportService($c->get(ImportService::class));$x->setNotificationIntegrationService($c->get(NotificationIntegrationService::class));$x->setCompetitionsIntegrationService($c->get(CompetitionsIntegrationService::class));$x->setOrganizationsIntegrationService($c->get(OrganizationsIntegrationService::class));$x->setMembershipIntegrationService($c->get(MembershipIntegrationService::class));$x->setMaintenanceLogService($c->get(MaintenanceLogService::class));$x->setPersonTrashService($c->get(PersonTrashService::class));$x->setBackupStorageService($c->get(BackupStorageService::class));$x->setBackupService($c->get(BackupService::class));$x->setRestoreService($c->get(RestoreService::class));$x->setIntegrityService($c->get(IntegrityService::class));return $x;});
 }
};
