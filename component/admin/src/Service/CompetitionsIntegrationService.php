<?php

namespace xdecaro\Component\People\Administrator\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use RuntimeException;
use Throwable;
use xdecaro\Core\Integration\CapabilityRegistry;

/** Optional read-only bridge to the public Competitions person-history API. */
final class CompetitionsIntegrationService
{
    public function __construct(private PersonProviderService $people)
    {
    }

    private const COMPONENT = 'com_xdecarocompetitions';
    private const CAPABILITY = 'competitions.people_history';
    private const CAPABILITY_VERSION = '1';

    public function isHistoryAvailable(): bool
    {
        try {
            $component = $this->component();
            return $this->supportsHistory($component)
                && method_exists($component, 'getPersonHistoryService');
        } catch (Throwable) {
            return false;
        }
    }

    public function getPersonHistory(string $personUuid): array
    {
        $personUuid = strtolower(trim($personUuid));
        if ($personUuid === '') {
            return [];
        }

        try {
            $component = $this->component();
            if (!$this->supportsHistory($component) || !method_exists($component, 'getPersonHistoryService')) {
                throw new RuntimeException('Competitions person history capability is unavailable.');
            }

            $service = $component->getPersonHistoryService();
            if (!is_object($service) || !method_exists($service, 'getHistoryByPersonUuid')) {
                throw new RuntimeException('Competitions person history service is incompatible.');
            }

            return array_values((array) $service->getHistoryByPersonUuid($personUuid));
        } catch (Throwable $e) {
            throw new RuntimeException('Competitions person history lookup is unavailable.', (int) $e->getCode(), $e);
        }
    }

    private function component(): object
    {
        try {
            $component = Factory::getApplication()->bootComponent('com_competitions');
        } catch (Throwable $e) {
            throw new RuntimeException('Competitions component could not be booted.', 0, $e);
        }

        if (!is_object($component) || !method_exists($component, 'getCoreIntegrationService')) {
            throw new RuntimeException('Competitions public integration surface is unavailable.');
        }

        return $component;
    }

    private function supportsHistory(object $component): bool
    {
        if (!class_exists(CapabilityRegistry::class)) {
            return false;
        }

        $core = $component->getCoreIntegrationService();
        if (!is_object($core) || !method_exists($core, 'registerCapabilities')) {
            return false;
        }

        $registry = new CapabilityRegistry();
        $core->registerCapabilities($registry);

        return $registry->supports(self::COMPONENT, self::CAPABILITY, self::CAPABILITY_VERSION);
    }
}
