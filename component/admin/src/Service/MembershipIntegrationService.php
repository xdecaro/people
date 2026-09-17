<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use RuntimeException;
use Throwable;
use xdecaro\Core\Integration\CapabilityRegistry;

/** Optional read-only bridge to the public Membership person-memberships API. */
final class MembershipIntegrationService
{
    private const COMPONENT = 'com_decaromembership';
    private const CAPABILITY = 'membership.person_memberships';
    private const CAPABILITY_VERSION = '1';

    public function isAvailable(): bool
    {
        try {
            $component = $this->component();

            return $this->supports($component)
                && method_exists($component, 'getPersonMembershipService');
        } catch (Throwable) {
            return false;
        }
    }

    public function getPersonMemberships(string $personUuid): array
    {
        $personUuid = strtolower(trim($personUuid));
        if ($personUuid === '') {
            return [];
        }

        try {
            $component = $this->component();
            if (!$this->supports($component) || !method_exists($component, 'getPersonMembershipService')) {
                throw new RuntimeException('Membership person-memberships capability is unavailable.');
            }

            $service = $component->getPersonMembershipService();
            if (!is_object($service) || !method_exists($service, 'getMembershipsByPersonUuid')) {
                throw new RuntimeException('Membership person-memberships service is incompatible.');
            }

            return array_values((array) $service->getMembershipsByPersonUuid($personUuid));
        } catch (Throwable $e) {
            throw new RuntimeException('Membership person-memberships lookup is unavailable.', (int) $e->getCode(), $e);
        }
    }

    private function component(): object
    {
        try {
            $component = Factory::getApplication()->bootComponent(self::COMPONENT);
        } catch (Throwable $e) {
            throw new RuntimeException('Membership component could not be booted.', 0, $e);
        }

        if (!is_object($component) || !method_exists($component, 'getCoreIntegrationService')) {
            throw new RuntimeException('Membership public integration surface is unavailable.');
        }

        return $component;
    }

    private function supports(object $component): bool
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
