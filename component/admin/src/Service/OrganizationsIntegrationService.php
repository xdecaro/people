<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use RuntimeException;
use Throwable;
use xdecaro\Core\Integration\CapabilityRegistry;

/** Optional read-only bridge to the public Organizations person-appointments API. */
final class OrganizationsIntegrationService
{
    private const COMPONENT = 'com_xdecaroorganizations';
    private const CAPABILITY = 'organizations.people_appointments';
    private const CAPABILITY_VERSION = '1';

    public function isHistoryAvailable(): bool
    {
        try {
            $component = $this->component();

            return $this->supportsHistory($component)
                && method_exists($component, 'getPersonAppointmentsService');
        } catch (Throwable) {
            return false;
        }
    }

    public function getPersonAppointments(string $personUuid): array
    {
        $personUuid = strtolower(trim($personUuid));
        if ($personUuid === '') {
            return [];
        }

        try {
            $component = $this->component();
            if (!$this->supportsHistory($component) || !method_exists($component, 'getPersonAppointmentsService')) {
                throw new RuntimeException('Organizations person appointment capability is unavailable.');
            }

            $service = $component->getPersonAppointmentsService();
            if (!is_object($service) || !method_exists($service, 'getAppointmentsByPersonUuid')) {
                throw new RuntimeException('Organizations person appointments service is incompatible.');
            }

            return array_values((array) $service->getAppointmentsByPersonUuid($personUuid));
        } catch (Throwable $e) {
            throw new RuntimeException('Organizations person appointment lookup is unavailable.', (int) $e->getCode(), $e);
        }
    }

    private function component(): object
    {
        try {
            $component = Factory::getApplication()->bootComponent(self::COMPONENT);
        } catch (Throwable $e) {
            throw new RuntimeException('Organizations component could not be booted.', 0, $e);
        }

        if (!is_object($component) || !method_exists($component, 'getCoreIntegrationService')) {
            throw new RuntimeException('Organizations public integration surface is unavailable.');
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
