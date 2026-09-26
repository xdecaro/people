<?php

namespace xdecaro\Component\People\Administrator\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use RuntimeException;
use Throwable;
use ZipArchive;

final class BackupService
{
    public const BACKUP_TABLES = [
        '#__xdecaropeople_people',
        '#__xdecaropeople_history',
        '#__xdecaropeople_duplicate_ignores',
        '#__xdecaropeople_merges',
    ];
    public const FORMAT = 'xdecaro.people.backup';
    public const FORMAT_VERSION = 1;
    public const SCHEMA_VERSION = '1.7.28';

    public function __construct(
        private DatabaseInterface $db,
        private BackupStorageService $storage,
        private MaintenanceLogService $maintenanceLog
    ) {}

    public function create(int $actorUserId, string $reason = 'manual'): array
    {
        $uuid = self::uuidV4();
        $path = $this->storage->pathFor($uuid);
        $tables = [];
        $counts = [];

        foreach (self::BACKUP_TABLES as $table) {
            $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName($table))
                ->order($this->db->quoteName('id') . ' ASC');
            $rows = array_values((array) $this->db->setQuery($query)->loadAssocList());
            foreach ($rows as &$row) {
                ksort($row, SORT_STRING);
            }
            unset($row);
            $tables[$table] = $rows;
            $counts[$table] = count($rows);
        }

        $data = ['tables' => $tables];
        $dataJson = self::canonicalJson($data);
        $payloadSha = hash('sha256', $dataJson);
        $version = $this->componentVersion();
        $manifest = [
            'backup_uuid' => $uuid,
            'format' => self::FORMAT,
            'format_version' => self::FORMAT_VERSION,
            'component_version' => $version,
            'schema_version' => self::SCHEMA_VERSION,
            'joomla_version' => JVERSION,
            'created_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'created_by' => max(0, $actorUserId),
            'reason' => trim($reason) !== '' ? trim($reason) : 'manual',
            'tables' => self::BACKUP_TABLES,
            'table_counts' => $counts,
            'people_count' => $counts['#__xdecaropeople_people'] ?? 0,
            'payload_sha256' => $payloadSha,
        ];
        $manifestJson = self::canonicalJson($manifest);
        $sums = hash('sha256', $dataJson) . "  data.json\n"
            . hash('sha256', $manifestJson) . "  manifest.json\n";

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create People backup ZIP.');
        }

        try {
            if (!$zip->addFromString('manifest.json', $manifestJson)
                || !$zip->addFromString('data.json', $dataJson)
                || !$zip->addFromString('SHA256SUMS.txt', $sums)) {
                throw new RuntimeException('Unable to write People backup ZIP.');
            }
        } finally {
            $zip->close();
        }

        if (!is_file($path)) {
            throw new RuntimeException('People backup ZIP was not persisted.');
        }

        $fileSha = hash_file('sha256', $path);
        if (!is_string($fileSha) || $fileSha === '') {
            @unlink($path);
            throw new RuntimeException('Unable to hash People backup ZIP.');
        }

        $record = (object) [
            'uuid' => $uuid,
            'filename' => basename($path),
            'storage_path' => $path,
            'sha256' => $fileSha,
            'size_bytes' => (int) filesize($path),
            'people_count' => (int) ($manifest['people_count'] ?? 0),
            'component_version' => $version,
            'schema_version' => self::SCHEMA_VERSION,
            'created' => Factory::getDate()->toSql(),
            'created_by' => max(0, $actorUserId),
            'status' => 'ready',
        ];

        try {
            $this->db->insertObject('#__xdecaropeople_backups', $record, 'id');
        } catch (Throwable $e) {
            @unlink($path);
            throw $e;
        }

        $this->maintenanceLog->log('backup_create', $uuid, $actorUserId, [
            'backup_id' => (int) ($record->id ?? 0),
            'people_count' => (int) $record->people_count,
            'size_bytes' => (int) $record->size_bytes,
            'reason' => (string) $manifest['reason'],
            'payload_sha256' => $payloadSha,
        ]);

        return [
            'id' => (int) ($record->id ?? 0),
            'uuid' => $uuid,
            'path' => $path,
            'filename' => basename($path),
            'sha256' => $fileSha,
            'payload_sha256' => $payloadSha,
            'people_count' => (int) $record->people_count,
            'size_bytes' => (int) $record->size_bytes,
            'manifest' => $manifest,
        ];
    }

    public function list(): array
    {
        $query = $this->db->getQuery(true)
            ->select(['id', 'uuid', 'filename', 'sha256', 'size_bytes', 'people_count', 'component_version', 'schema_version', 'created', 'created_by', 'status'])
            ->from($this->db->quoteName('#__xdecaropeople_backups'))
            ->order($this->db->quoteName('created') . ' DESC, ' . $this->db->quoteName('id') . ' DESC');
        return array_values((array) $this->db->setQuery($query)->loadAssocList());
    }

    public function resolveDownload(string $backupUuid): array
    {
        $row = $this->loadBackup($backupUuid);
        $path = (string) ($row['storage_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException('People backup file is unavailable.');
        }
        $sha = hash_file('sha256', $path);
        if (!is_string($sha) || !hash_equals((string) $row['sha256'], $sha)) {
            throw new RuntimeException('People backup file checksum is invalid.');
        }

        $actor = 0;
        try {
            $actor = (int) Factory::getApplication()->getIdentity()->id;
        } catch (Throwable) {
            $actor = 0;
        }
        $this->maintenanceLog->log('backup_download', (string) $row['uuid'], $actor, [
            'backup_id' => (int) $row['id'],
            'size_bytes' => (int) $row['size_bytes'],
        ]);

        $row['path'] = $path;
        return $row;
    }

    public function delete(string $backupUuid, int $actorUserId): void
    {
        $row = $this->loadBackup($backupUuid);
        $path = (string) ($row['storage_path'] ?? '');
        if ($path !== '' && is_file($path) && !@unlink($path)) {
            throw new RuntimeException('Unable to delete People backup file.');
        }

        $uuid = (string) $row['uuid'];
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__xdecaropeople_backups'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $row['id']);
        $this->db->setQuery($query)->execute();

        $this->maintenanceLog->log('backup_delete', $uuid, $actorUserId, [
            'backup_id' => (int) $row['id'],
            'people_count' => (int) $row['people_count'],
            'size_bytes' => (int) $row['size_bytes'],
        ]);
    }

    public static function canonicalJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    private function loadBackup(string $uuid): array
    {
        $uuid = strtolower(trim($uuid));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid)) {
            throw new RuntimeException('Invalid People backup UUID.');
        }
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__xdecaropeople_backups'))
            ->where($this->db->quoteName('uuid') . ' = :uuid')
            ->bind(':uuid', $uuid);
        $row = $this->db->setQuery($query, 0, 1)->loadAssoc();
        if (!$row) {
            throw new RuntimeException('People backup not found.');
        }
        return $row;
    }

    private function componentVersion(): string
    {
        $record = ExtensionHelper::getExtensionRecord('com_xdecaropeople', 'component', 1);
        $manifest = new Registry($record->manifest_cache ?? '{}');
        return (string) $manifest->get('version', self::SCHEMA_VERSION);
    }

    private static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
