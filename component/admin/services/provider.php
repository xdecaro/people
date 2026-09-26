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
        $container->share(PersonProviderService::class, static fn(Container $c): PersonProviderService => new PersonProviderService($c->get(DatabaseInterface::class), $c->get(CoreIntegrationService::class)));
        $container->share(DuplicateService::class, static fn(Container $c): DuplicateService => new DuplicateService($c->get(DatabaseInterface::class)));
        $container->share(ImportService::class, static fn(Container $c): ImportService => new ImportService($c->get(DatabaseInterface::class)));
        $container->share(NotificationIntegrationService::class, static fn(Container $c): NotificationIntegrationService => new NotificationIntegrationService($c->get(DatabaseInterface::class)));
        $container->share(CompetitionsIntegrationService::class, static fn(Container $c): CompetitionsIntegrationService => new CompetitionsIntegrationService($c->get(PersonProviderService::class)));
        $container->share(OrganizationsIntegrationService::class, static fn(Container $c): OrganizationsIntegrationService => new OrganizationsIntegrationService($c->get(PersonProviderService::class)));
        $container->share(MembershipIntegrationService::class, static fn(Container $c): MembershipIntegrationService => new MembershipIntegrationService($c->get(PersonProviderService::class)));
        $container->share(MaintenanceLogService::class, static fn(Container $c): MaintenanceLogService => new MaintenanceLogService($c->get(DatabaseInterface::class)));
        $container->share(PersonTrashService::class, static fn(Container $c): PersonTrashService => new PersonTrashService($c->get(DatabaseInterface::class), $c->get(MaintenanceLogService::class)));
        $container->share(BackupStorageService::class, static fn(): BackupStorageService => new BackupStorageService());
        $container->share(BackupService::class, static fn(Container $c): BackupService => new BackupService($c->get(DatabaseInterface::class), $c->get(BackupStorageService::class), $c->get(MaintenanceLogService::class)));
        $container->share(RestoreService::class, static fn(Container $c): RestoreService => new RestoreService(
            $c->get(DatabaseInterface::class),
            $c->get(MaintenanceLogService::class),
            null,
            $c->get(BackupService::class)
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
            $component->setRestoreService($container->get(RestoreService::class));
            return $component;
        });
    }
};
