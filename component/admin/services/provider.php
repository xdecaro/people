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

        $container->share(
            CoreIntegrationService::class,
            static fn(): CoreIntegrationService => new CoreIntegrationService()
        );
        $container->share(
            PersonProviderService::class,
            static fn(Container $container): PersonProviderService => new PersonProviderService(
                $container->get(DatabaseInterface::class),
                $container->get(CoreIntegrationService::class)
            )
        );
        $container->share(
            DuplicateService::class,
            static fn(Container $container): DuplicateService => new DuplicateService(
                $container->get(DatabaseInterface::class)
            )
        );

        $container->set(
            ComponentInterface::class,
            static function (Container $container): ComponentInterface {
                $component = new PeopleComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class)
                );
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
                $component->setCoreIntegrationService($container->get(CoreIntegrationService::class));
                $component->setPersonProviderService($container->get(PersonProviderService::class));
                $component->setDuplicateService($container->get(DuplicateService::class));

                return $component;
            }
        );
    }
};
