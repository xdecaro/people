<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class pkg_peopleInstallerScript
{
    private const MINIMUM_CORE = '2.0.1';

    public function preflight($type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        $version = $this->getInstalledCoreVersion();
        if ($version !== '' && version_compare($version, self::MINIMUM_CORE, '>=')) {
            return true;
        }

        Factory::getApplication()->enqueueMessage('People requires Core ' . self::MINIMUM_CORE . ' or later.', 'error');
        return false;
    }

    public function postflight($type, $parent): void
    {
        if ($type === 'uninstall') {
            return;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $canonicalId = $this->packageId($db, 'pkg_people');
            $legacyId = $this->packageId($db, 'pkg_xdecaropeople');
            if ($canonicalId <= 0 || $legacyId <= 0 || $canonicalId === $legacyId) {
                return;
            }

            $componentType = 'component';
            $componentElement = 'com_xdecaropeople';
            $query = $db->getQuery(true)
                ->select($db->quoteName('package_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = :type')
                ->where($db->quoteName('element') . ' = :element')
                ->bind(':type', $componentType)
                ->bind(':element', $componentElement);
            $package_id = (int) $db->setQuery($query, 0, 1)->loadResult();
            if ($package_id !== $canonicalId) {
                Factory::getApplication()->enqueueMessage('People package identity migration was not completed because child ownership could not be verified.', 'warning');
                return;
            }

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__update_sites_extensions'))
                ->where($db->quoteName('extension_id') . ' = :legacyId')
                ->bind(':legacyId', $legacyId, ParameterType::INTEGER);
            $db->setQuery($query)->execute();

            $packageType = 'package';
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__extensions'))
                ->where($db->quoteName('extension_id') . ' = :legacyId')
                ->where($db->quoteName('type') . ' = :packageType')
                ->bind(':legacyId', $legacyId, ParameterType::INTEGER)
                ->bind(':packageType', $packageType);
            $db->setQuery($query)->execute();

            if (defined('JPATH_MANIFESTS')) {
                $legacyManifest = JPATH_MANIFESTS . '/packages/pkg_xdecaropeople.xml';
                if (is_file($legacyManifest)) {
                    @unlink($legacyManifest);
                }
            }
        } catch (\Throwable $exception) {
            Factory::getApplication()->enqueueMessage('People package identity migration needs attention: ' . $exception->getMessage(), 'warning');
        }
    }

    private function getInstalledCoreVersion(): string
    {
        if (class_exists(\xdecaro\Core\Version::class)) {
            return trim((string) \xdecaro\Core\Version::VERSION);
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            foreach (['pkg_core', 'pkg_xdecarocore'] as $element) {
                $packageType = 'package';
                $query = $db->getQuery(true)
                    ->select($db->quoteName('manifest_cache'))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = :type')
                    ->where($db->quoteName('element') . ' = :element')
                    ->bind(':type', $packageType)
                    ->bind(':element', $element);
                $manifest = json_decode((string) $db->setQuery($query, 0, 1)->loadResult(), true);
                $version = is_array($manifest) ? trim((string) ($manifest['version'] ?? '')) : '';
                if ($version !== '') {
                    return $version;
                }
            }
        } catch (\Throwable) {
        }

        return '';
    }

    private function packageId(DatabaseInterface $db, string $element): int
    {
        $packageType = 'package';
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':type', $packageType)
            ->bind(':element', $element);
        return (int) $db->setQuery($query, 0, 1)->loadResult();
    }
}
