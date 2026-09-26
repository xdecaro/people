<?php

namespace xdecaro\Component\People\Administrator\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use RuntimeException;
use Throwable;

final class BackupStorageService
{
    public function __construct(private ?string $configuredPath = null) {}

    public function resolvePrivateDirectory(): string
    {
        $configured = $this->configuredPath;
        if ($configured === null) {
            $configured = trim((string) ComponentHelper::getParams('com_xdecaropeople')->get('backup_storage_path', ''));
        }

        $path = trim((string) $configured);
        if ($path === '') {
            $path = dirname(JPATH_ROOT) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'com_xdecaropeople' . DIRECTORY_SEPARATOR . 'backups';
        }

        $path = $this->normalizeAbsolutePath($path);
        if ($this->isInsidePublicRoot($path)) {
            throw new RuntimeException('People backup storage must be outside the public Joomla root.');
        }

        if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('People backup storage directory cannot be created.');
        }
        if (!is_writable($path)) {
            throw new RuntimeException('People backup storage directory is not writable.');
        }

        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    public function pathFor(string $backupUuid): string
    {
        $backupUuid = strtolower(trim($backupUuid));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $backupUuid)) {
            throw new RuntimeException('Invalid People backup UUID.');
        }
        return $this->resolvePrivateDirectory() . DIRECTORY_SEPARATOR . 'people-backup-' . $backupUuid . '.zip';
    }

    public function isHealthy(): array
    {
        try {
            $directory = $this->resolvePrivateDirectory();
            return ['ok' => true, 'message' => 'ready', 'directory' => $directory];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function isInsidePublicRoot(string $path): bool
    {
        $root = realpath(JPATH_ROOT) ?: $this->normalizeAbsolutePath(JPATH_ROOT);
        $candidate = realpath($path) ?: $path;
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $candidate = rtrim(str_replace('\\', '/', $candidate), '/');
        return $candidate === $root || str_starts_with($candidate . '/', $root . '/');
    }

    private function normalizeAbsolutePath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path));
        if ($path === '') {
            throw new RuntimeException('People backup storage path is empty.');
        }

        $isAbsolute = str_starts_with($path, DIRECTORY_SEPARATOR)
            || (bool) preg_match('/^[A-Za-z]:\\\\/', $path);
        if (!$isAbsolute) {
            throw new RuntimeException('People backup storage path must be absolute.');
        }

        $parts = [];
        foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        $prefix = str_starts_with($path, DIRECTORY_SEPARATOR) ? DIRECTORY_SEPARATOR : '';
        if (preg_match('/^[A-Za-z]:/', $path, $m)) {
            $prefix = $m[0] . DIRECTORY_SEPARATOR;
            if ($parts !== [] && strcasecmp($parts[0], rtrim($m[0], ':')) === 0) array_shift($parts);
        }
        return $prefix . implode(DIRECTORY_SEPARATOR, $parts);
    }
}
