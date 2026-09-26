<?php
namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use xdecaro\Component\People\Administrator\Extension\PeopleComponent;

return new class implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('xdecaro\\Component\\People'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('xdecaro\\Component\\People'));

        $container->share(CoreIntegrationService::class, static fn(): CoreIntegrationService => new CoreIntegrationService());
        $container->share(PersonProviderService::class, static fn(Container $container): PersonProviderService => new PersonProviderService(
            $container->get(DatabaseInterface::class),
            $container->get(CoreIntegrationService::class)
        ));
        $container->share(DuplicateService::class, static fn(Container $container): DuplicateService => new DuplicateService(
            $container->get(DatabaseInterface::class)
        ));
        $container->share(ImportService::class, static fn(Container $container): ImportService => new ImportService(
            $container->get(DatabaseInterface::class)
        ));
        $container->share(NotificationIntegrationService::class, static fn(Container $container): NotificationIntegrationService => new NotificationIntegrationService(
            $container->get(DatabaseInterface::class)
        ));
        $container->share(CompetitionsIntegrationService::class, static fn(Container $container): CompetitionsIntegrationService => new CompetitionsIntegrationService(
            $container->get(PersonProviderService::class)
        ));
        $container->share(OrganizationsIntegrationService::class, static fn(Container $container): OrganizationsIntegrationService => new OrganizationsIntegrationService(
            $container->get(PersonProviderService::class)
        ));
        $container->share(MembershipIntegrationService::class, static fn(Container $container): MembershipIntegrationService => new MembershipIntegrationService(
            $container->get(PersonProviderService::class)
        ));
        $container->share(MaintenanceLogService::class, static fn(Container $container): MaintenanceLogService => new MaintenanceLogService(
            $container->get(DatabaseInterface::class)
        ));
        $container->share(PersonTrashService::class, static fn(Container $container): PersonTrashService => new PersonTrashService(
            $container->get(DatabaseInterface::class),
            $container->get(MaintenanceLogService::class)
        ));
        $container->share(BackupStorageService::class, static fn(): BackupStorageService => new BackupStorageService());
        $container->share(BackupService::class, static fn(Container $container): BackupService => new BackupService(
            $container->get(DatabaseInterface::class),
            $container->get(BackupStorageService::class),
            $container->get(MaintenanceLogService::class)
        ));

        $container->set(ComponentInterface::class, static function (Container $container): ComponentInterface {
            $component = new PeopleComponent($container->get(ComponentDispatcherFactoryInterface::class));
            $component->setMVCFactory($container->get(MVCFactoryInterface::class));
            $component->setCoreIntegrationService($container->get(CoreIntegrationService::class));
            $component->setPersonProviderService($container->get(PersonProviderService::class));
            $component->setDuplicateService($container->get(DuplicateService::class));
            $component->setImportService($container->get(ImportService::class));
            $component->setNotificationIntegrationService($container->get(NotificationIntegrationService::class));
            $component->setCompetitionsIntegrationService($container->get(CompetitionsIntegrationService::class));
            $component->setOrganizationsIntegrationService($container->get(OrganizationsIntegrationService::class));
            $component->setMembershipIntegrationService($container->get(MembershipIntegrationService::class));
            $component->setMaintenanceLogService($container->get(MaintenanceLogService::class));
            $component->setPersonTrashService($container->get(PersonTrashService::class));
            $component->setBackupStorageService($container->get(BackupStorageService::class));
            $component->setBackupService($container->get(BackupService::class));
            return $component;
        });
    }
};
