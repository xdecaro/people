<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use RuntimeException;

final class BackupStorageService
{
    public function resolvePrivateDirectory(): string
    {
        $params = ComponentHelper::getParams('com_xdecaropeople');
        $configured = trim((string) $params->get('backup_storage_path', ''));
        $directory = $configured !== ''
            ? $configured
            : dirname(JPATH_ROOT) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'xdecaropeople' . DIRECTORY_SEPARATOR . 'backups';

        if (!$this->isAbsolutePath($directory)) {
            throw new RuntimeException('People backup storage path must be absolute.');
        }

        $webRoot = realpath(JPATH_ROOT) ?: JPATH_ROOT;
        $cleanDirectory = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory), DIRECTORY_SEPARATOR);

        if (!is_dir($cleanDirectory) && !@mkdir($cleanDirectory, 0700, true) && !is_dir($cleanDirectory)) {
            throw new RuntimeException('People backup storage directory cannot be created.');
        }

        $resolved = realpath($cleanDirectory);
        if ($resolved === false) {
            throw new RuntimeException('People backup storage directory cannot be resolved.');
        }

        $resolvedRoot = rtrim(realpath($webRoot) ?: $webRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $resolvedWithSlash = rtrim($resolved, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($resolvedWithSlash, $resolvedRoot)) {
            throw new RuntimeException('People backup storage must be outside the Joomla web root.');
        }

        if (!is_writable($resolved)) {
            throw new RuntimeException('People backup storage directory is not writable.');
        }

        return $resolved;
    }

    public function pathFor(string $backupUuid): string
    {
        $backupUuid = strtolower(trim($backupUuid));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $backupUuid)) {
            throw new RuntimeException('Invalid backup UUID.');
        }

        return $this->resolvePrivateDirectory() . DIRECTORY_SEPARATOR . 'people-' . $backupUuid . '.zip';
    }

    public function isHealthy(): array
    {
        try {
            $directory = $this->resolvePrivateDirectory();
            return ['ok' => true, 'message' => 'OK', 'directory' => $directory];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'directory' => null];
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
