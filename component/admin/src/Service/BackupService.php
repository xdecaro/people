<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use JsonException;
use RuntimeException;
use ZipArchive;

final class BackupService
{
    private const FORMAT = 'xdecaro.people.backup';
    private const FORMAT_VERSION = 1;
    private const SCHEMA_VERSION = '1.7.29';

    private const PAYLOAD_TABLES = [
        '#__xdecaropeople_people',
        '#__xdecaropeople_history',
        '#__xdecaropeople_duplicate_ignores',
        '#__xdecaropeople_merges',
    ];

    public function __construct(
        private DatabaseInterface $db,
        private BackupStorageService $storage,
        private MaintenanceLogService $log
    ) {}

    public function create(int $actorUserId, string $reason = 'manual'): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZIP extension is required to create People backups.');
        }

        $backupUuid = self::uuidV4();
        $path = $this->storage->pathFor($backupUuid);
        $tables = [];
        $counts = [];

        foreach (self::PAYLOAD_TABLES as $table) {
            $rows = $this->loadTableRows($table);
            $tables[$table] = $rows;
            $counts[$table] = count($rows);
        }

        $data = [
            'format' => self::FORMAT,
            'format_version' => self::FORMAT_VERSION,
            'tables' => $tables,
        ];
        $dataJson = $this->encodeJson($data);
        $payloadSha256 = hash('sha256', $dataJson);
        $componentVersion = $this->componentVersion();
        $createdUtc = gmdate('c');
        $normalizedReason = trim($reason) !== '' ? trim($reason) : 'manual';
        $peopleCount = (int) ($counts['#__xdecaropeople_people'] ?? 0);
        $readableFilename = $this->buildReadableFilename(
            $normalizedReason,
            $componentVersion,
            $peopleCount,
            $createdUtc
        );

        $manifest = [
            'format' => self::FORMAT,
            'format_version' => self::FORMAT_VERSION,
            'backup_uuid' => $backupUuid,
            'component_version' => $componentVersion,
            'schema_version' => self::SCHEMA_VERSION,
            'joomla_version' => JVERSION,
            'created_utc' => $createdUtc,
            'created_by' => max(0, $actorUserId),
            'reason' => $normalizedReason,
            'tables' => self::PAYLOAD_TABLES,
            'table_counts' => $counts,
            'people_count' => $peopleCount,
            'payload_sha256' => $payloadSha256,
        ];
        $manifestJson = $this->encodeJson($manifest);
        $manifestSha256 = hash('sha256', $manifestJson);
        $sums = $payloadSha256 . "  data.json\n" . $manifestSha256 . "  manifest.json\n";

        $zip = new ZipArchive();
        $opened = $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new RuntimeException('People backup ZIP cannot be created.');
        }

        try {
            if (!$zip->addFromString('manifest.json', $manifestJson)
                || !$zip->addFromString('data.json', $dataJson)
                || !$zip->addFromString('SHA256SUMS.txt', $sums)) {
                throw new RuntimeException('People backup ZIP could not be populated.');
            }
        } finally {
            $zip->close();
        }

        clearstatcache(true, $path);
        $sizeBytes = filesize($path);
        $fileSha256 = hash_file('sha256', $path);
        if ($sizeBytes === false || $fileSha256 === false) {
            @unlink($path);
            throw new RuntimeException('People backup file metadata cannot be calculated.');
        }

        $record = (object) [
            'uuid' => $backupUuid,
            'filename' => $readableFilename,
            'storage_path' => $path,
            'sha256' => $fileSha256,
            'size_bytes' => (int) $sizeBytes,
            'people_count' => $peopleCount,
            'component_version' => $componentVersion,
            'schema_version' => self::SCHEMA_VERSION,
            'created' => Factory::getDate()->toSql(),
            'created_by' => max(0, $actorUserId),
            'status' => 'ready',
        ];

        try {
            $this->db->insertObject('#__xdecaropeople_backups', $record, 'id');
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        }

        $this->log->log('backup_create', null, $actorUserId, [
            'backup_uuid' => $backupUuid,
            'filename' => $readableFilename,
            'people_count' => (int) $record->people_count,
            'size_bytes' => (int) $sizeBytes,
            'reason' => $manifest['reason'],
        ]);

        return [
            'id' => (int) ($record->id ?? 0),
            'uuid' => $backupUuid,
            'filename' => $readableFilename,
            'path' => $path,
            'sha256' => $fileSha256,
            'payload_sha256' => $payloadSha256,
            'size_bytes' => (int) $sizeBytes,
            'people_count' => (int) $record->people_count,
            'component_version' => $componentVersion,
            'schema_version' => self::SCHEMA_VERSION,
            'created_utc' => $createdUtc,
            'status' => 'ready',
        ];
    }

    public function list(): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('uuid'),
                $this->db->quoteName('filename'),
                $this->db->quoteName('sha256'),
                $this->db->quoteName('size_bytes'),
                $this->db->quoteName('people_count'),
                $this->db->quoteName('component_version'),
                $this->db->quoteName('schema_version'),
                $this->db->quoteName('created'),
                $this->db->quoteName('created_by'),
                $this->db->quoteName('status'),
            ])
            ->from($this->db->quoteName('#__xdecaropeople_backups'))
            ->order($this->db->quoteName('id') . ' DESC');

        return array_values((array) $this->db->setQuery($query)->loadAssocList());
    }

    public function verify(string $backupUuid): array
    {
        $row = $this->loadBackup($backupUuid);
        if (!$row || ($row['status'] ?? '') !== 'ready') {
            throw new RuntimeException('People backup is not available.', 404);
        }

        $path = (string) ($row['storage_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException('People backup file is missing.', 404);
        }

        $actualSha256 = hash_file('sha256', $path);
        if ($actualSha256 === false || !hash_equals((string) $row['sha256'], $actualSha256)) {
            throw new RuntimeException('People backup file integrity check failed.', 409);
        }

        $row['path'] = $path;
        unset($row['storage_path']);
        return $row;
    }

    public function resolveDownload(string $backupUuid): array
    {
        $row = $this->verify($backupUuid);
        $actorUserId = (int) (Factory::getApplication()->getIdentity()->id ?? 0);
        $this->log->log('backup_download', null, $actorUserId, [
            'backup_uuid' => (string) $row['uuid'],
            'filename' => (string) ($row['filename'] ?? ''),
            'size_bytes' => (int) $row['size_bytes'],
        ]);
        return $row;
    }

    public function delete(string $backupUuid, int $actorUserId): void
    {
        $row = $this->loadBackup($backupUuid);
        if (!$row) {
            throw new RuntimeException('People backup not found.', 404);
        }

        $path = (string) ($row['storage_path'] ?? '');
        if ($path !== '' && is_file($path) && !@unlink($path)) {
            throw new RuntimeException('People backup file cannot be deleted.');
        }

        $uuid = strtolower(trim($backupUuid));
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__xdecaropeople_backups'))
            ->where($this->db->quoteName('uuid') . ' = :uuid')
            ->bind(':uuid', $uuid);
        $this->db->setQuery($query)->execute();

        $this->log->log('backup_delete', null, $actorUserId, [
            'backup_uuid' => $uuid,
            'filename' => (string) ($row['filename'] ?? ''),
            'size_bytes' => (int) ($row['size_bytes'] ?? 0),
        ]);
    }

    private function loadTableRows(string $table): array
    {
        if (!in_array($table, self::PAYLOAD_TABLES, true)) {
            throw new RuntimeException('Table is not part of the People backup whitelist.');
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName($table))
            ->order($this->db->quoteName('id') . ' ASC');

        return array_values((array) $this->db->setQuery($query)->loadAssocList());
    }

    private function loadBackup(string $backupUuid): ?array
    {
        $uuid = strtolower(trim($backupUuid));
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__xdecaropeople_backups'))
            ->where($this->db->quoteName('uuid') . ' = :uuid')
            ->bind(':uuid', $uuid);
        $row = $this->db->setQuery($query, 0, 1)->loadAssoc();
        return $row ?: null;
    }

    private function componentVersion(): string
    {
        $record = ExtensionHelper::getExtensionRecord('com_xdecaropeople', 'component', 1);
        $manifest = new Registry($record->manifest_cache ?? '{}');
        return (string) $manifest->get('version', self::SCHEMA_VERSION);
    }

    private function buildReadableFilename(string $reason, string $componentVersion, int $peopleCount, string $createdUtc): string
    {
        $reasonSlug = match ($reason) {
            'manual' => 'manuale',
            'pre-restore' => 'pre-restore',
            'before_empty_database' => 'pre-svuota',
            'before_recreate_database' => 'pre-ricrea',
            default => trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtolower($reason)), '-'),
        };
        if ($reasonSlug === '') {
            $reasonSlug = 'automatico';
        }

        $version = trim((string) preg_replace('/[^0-9A-Za-z._-]+/', '-', $componentVersion), '-');
        if ($version === '') {
            $version = self::SCHEMA_VERSION;
        }

        try {
            $date = new \DateTimeImmutable($createdUtc);
            $app = Factory::getApplication();
            $identity = $app->getIdentity();
            $userTimezone = trim((string) $identity->getParam('timezone', ''));
            $timezone = $userTimezone !== '' ? $userTimezone : (string) $app->get('offset', 'UTC');
            $date = $date->setTimezone(new \DateTimeZone($timezone !== '' ? $timezone : 'UTC'));
            $stamp = $date->format('Y-m-d_H-i-s');
        } catch (\Throwable) {
            $stamp = gmdate('Y-m-d_H-i-s');
        }

        return sprintf(
            'people-backup-%s-%s-v%s-%d-persone.zip',
            $reasonSlug,
            $stamp,
            $version,
            max(0, $peopleCount)
        );
    }

    private function encodeJson(array $value): string
    {
        try {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('People backup JSON encoding failed.', 0, $e);
        }
    }

    private static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }
}
