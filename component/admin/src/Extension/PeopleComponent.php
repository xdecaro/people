<?php

namespace xdecaro\Component\People\Administrator\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Extension\MVCComponent;
use RuntimeException;
use xdecaro\Component\People\Administrator\Service\CoreIntegrationService;
use xdecaro\Component\People\Administrator\Service\DuplicateService;
use xdecaro\Component\People\Administrator\Service\NotificationIntegrationService;
use xdecaro\Component\People\Administrator\Service\PersonProviderService;

final class PeopleComponent extends MVCComponent
{
    private ?CoreIntegrationService $core = null;
    private ?PersonProviderService $provider = null;
    private ?DuplicateService $duplicates = null;
    private ?NotificationIntegrationService $notifications = null;

    public function setCoreIntegrationService(CoreIntegrationService $service): void
    {
        $this->core = $service;
    }

    public function getCoreIntegrationService(): CoreIntegrationService
    {
        return $this->core ??= new CoreIntegrationService();
    }

    public function setPersonProviderService(PersonProviderService $service): void
    {
        $this->provider = $service;
    }

    public function getPersonProviderService(): PersonProviderService
    {
        if (!$this->provider) {
            throw new RuntimeException('People provider not initialized.');
        }

        return $this->provider;
    }

    public function setDuplicateService(DuplicateService $service): void
    {
        $this->duplicates = $service;
    }

    public function getDuplicateService(): DuplicateService
    {
        if (!$this->duplicates) {
            throw new RuntimeException('Duplicate service not initialized.');
        }

        return $this->duplicates;
    }

    public function setNotificationIntegrationService(NotificationIntegrationService $service): void
    {
        $this->notifications = $service;
    }

    public function getNotificationIntegrationService(): NotificationIntegrationService
    {
        if (!$this->notifications) {
            throw new RuntimeException('People notification integration service not initialized.');
        }

        return $this->notifications;
    }
}
