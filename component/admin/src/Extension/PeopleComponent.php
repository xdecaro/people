<?php

namespace xdecaro\Component\People\Administrator\Extension;
defined('_JEXEC') or die;

use Joomla\CMS\Extension\MVCComponent;
use RuntimeException;
use xdecaro\Component\People\Administrator\Service\CompetitionsIntegrationService;
use xdecaro\Component\People\Administrator\Service\CoreIntegrationService;
use xdecaro\Component\People\Administrator\Service\DuplicateService;
use xdecaro\Component\People\Administrator\Service\ImportService;
use xdecaro\Component\People\Administrator\Service\NotificationIntegrationService;
use xdecaro\Component\People\Administrator\Service\OrganizationsIntegrationService;
use xdecaro\Component\People\Administrator\Service\MembershipIntegrationService;
use xdecaro\Component\People\Administrator\Service\PersonProviderService;

final class PeopleComponent extends MVCComponent
{
    private ?CoreIntegrationService $core = null;
    private ?PersonProviderService $provider = null;
    private ?DuplicateService $duplicates = null;
    private ?ImportService $importer = null;
    private ?NotificationIntegrationService $notifications = null;
    private ?CompetitionsIntegrationService $competitions = null;
    private ?OrganizationsIntegrationService $organizations = null;
    private ?MembershipIntegrationService $membership = null;

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

    public function setImportService(ImportService $service): void
    {
        $this->importer = $service;
    }

    public function getImportService(): ImportService
    {
        if (!$this->importer) {
            throw new RuntimeException('People import service not initialized.');
        }

        return $this->importer;
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

    public function setCompetitionsIntegrationService(CompetitionsIntegrationService $service): void
    {
        $this->competitions = $service;
    }

    public function getCompetitionsIntegrationService(): CompetitionsIntegrationService
    {
        if (!$this->competitions) {
            throw new RuntimeException('People Competitions integration service not initialized.');
        }

        return $this->competitions;
    }

    public function setOrganizationsIntegrationService(OrganizationsIntegrationService $service): void
    {
        $this->organizations = $service;
    }

    public function getOrganizationsIntegrationService(): OrganizationsIntegrationService
    {
        if (!$this->organizations) {
            throw new RuntimeException('People Organizations integration service not initialized.');
        }

        return $this->organizations;
    }

    public function setMembershipIntegrationService(MembershipIntegrationService $service): void
    {
        $this->membership = $service;
    }

    public function getMembershipIntegrationService(): MembershipIntegrationService
    {
        if (!$this->membership) {
            throw new RuntimeException('People Membership integration service not initialized.');
        }

        return $this->membership;
    }
}
