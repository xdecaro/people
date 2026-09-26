<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseInterface;
use RuntimeException;
use ZipArchive;

final class RestoreService
{
    private const FORMAT = 'xdecaro.people.backup';
    private const FORMAT_VERSION = 1;
    private const PAYLOAD_TABLES = [
        '#__xdecaropeople_people',
        '#__xdecaropeople_history',
        '#__xdecaropeople_duplicate_ignores',
        '#__xdecaropeople_merges',
    ];
    private const ZIP_ENTRIES = ['manifest.json', 'data.json', 'SHA256SUMS.txt'];

    public function __construct(
        private DatabaseInterface $db,
        private BackupService $backup,
        private MaintenanceLogService $log,
        private PersonTrashService $trash
    ) {}

    public function preview(string $zipPath, int $actorUserId): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZIP extension is required for People restore.');
        }
        if (!is_file($zipPath) || !is_readable($zipPath)) {
            throw new RuntimeException('People restore file is not readable.');
        }

        $maxMb = max(1, (int) ComponentHelper::getParams('com_xdecaropeople')->get('backup_max_upload_mb', 64));
        $size = filesize($zipPath);
        if ($size === false || $size > $maxMb * 1024 * 1024) {
            throw new RuntimeException('People restore file exceeds the configured size limit.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Invalid People backup ZIP.');
        }

        try {
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')) {
                    throw new RuntimeException('Unsafe path found in People backup ZIP.');
                }
                $names[] = $name;
            }
            sort($names);
            $expected = self::ZIP_ENTRIES;
            sort($expected);
            if ($names !== $expected) {
                throw new RuntimeException('People backup ZIP contains missing or unexpected files.');
            }

            $manifestJson = $zip->getFromName('manifest.json');
            $dataJson = $zip->getFromName('data.json');
            $sums = $zip->getFromName('SHA256SUMS.txt');
            if (!is_string($manifestJson) || !is_string($dataJson) || !is_string($sums)) {
                throw new RuntimeException('People backup ZIP is incomplete.');
            }
        } finally {
            $zip->close();
        }

        try {
            $manifest = json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR);
            $data = json_decode($dataJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('People backup JSON is invalid.', 0, $e);
        }
        if (!is_array($manifest) || !is_array($data)) {
            throw new RuntimeException('People backup JSON structure is invalid.');
        }

        $payloadHash = hash('sha256', $dataJson);
        if (!isset($manifest['payload_sha256']) || !is_string($manifest['payload_sha256']) || !hash_equals($manifest['payload_sha256'], $payloadHash)) {
            throw new RuntimeException('People backup payload checksum mismatch.');
        }
        if (!str_contains($sums, $payloadHash . '  data.json')) {
            throw new RuntimeException('People backup checksum file is inconsistent.');
        }

        if (($manifest['format'] ?? null) !== self::FORMAT || ($data['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException('Unsupported People backup format.');
        }
        if ((int) ($manifest['format_version'] ?? 0) !== self::FORMAT_VERSION || (int) ($data['format_version'] ?? 0) !== self::FORMAT_VERSION) {
            throw new RuntimeException('Unsupported People backup format version.');
        }

        $tables = $data['tables'] ?? null;
        if (!is_array($tables)) {
            throw new RuntimeException('People backup tables payload is invalid.');
        }
        $tableNames = array_keys($tables);
        sort($tableNames);
        $expectedTables = self::PAYLOAD_TABLES;
        sort($expectedTables);
        if ($tableNames !== $expectedTables) {
            throw new RuntimeException('People backup table whitelist mismatch.');
        }

        $counts = [];
        foreach (self::PAYLOAD_TABLES as $table) {
            if (!is_array($tables[$table])) {
                throw new RuntimeException('People backup table rows are invalid.');
            }
            $counts[$table] = count($tables[$table]);
        }
        foreach ($tables['#__xdecaropeople_people'] as $row) {
            if (!is_array($row) || !$this->validUuid((string) ($row['uuid'] ?? ''))) {
                throw new RuntimeException('People backup contains an invalid person UUID.');
            }
        }
        foreach ($tables['#__xdecaropeople_merges'] as $row) {
            if (!is_array($row)
                || !$this->validUuid((string) ($row['source_uuid'] ?? ''))
                || !$this->validUuid((string) ($row['target_uuid'] ?? ''))) {
                throw new RuntimeException('People backup contains an invalid merge UUID.');
            }
        }

        $peopleActive = 0;
        $peopleTrashed = 0;
        foreach ($tables['#__xdecaropeople_people'] as $row) {
            $state = (int) ($row['state'] ?? 0);
            if ($state === -2) {
                $peopleTrashed++;
            } elseif ($state >= 0) {
                $peopleActive++;
            }
        }

        $result = [
            'compatible' => true,
            'blocking_errors' => [],
            'warnings' => [],
            'manifest' => $manifest,
            'counts' => $counts,
            'people_active' => $peopleActive,
            'people_trashed' => $peopleTrashed,
            'payload_sha256' => $payloadHash,
            'data' => $data,
        ];

        $this->log->log('restore_preview', null, $actorUserId, [
            'backup_uuid' => (string) ($manifest['backup_uuid'] ?? ''),
            'component_version' => (string) ($manifest['component_version'] ?? ''),
            'people_count' => $counts['#__xdecaropeople_people'],
            'compatible' => true,
        ]);

        return $result;
    }

    public function restoreFull(string $zipPath, int $actorUserId): array
    {
        $preview = $this->preview($zipPath, $actorUserId);
        $tables = $preview['data']['tables'] ?? null;
        if (!is_array($tables)) {
            throw new RuntimeException('People restore payload is unavailable.');
        }

        $safetyBackup = $this->backup->create($actorUserId, 'pre-restore');
        $restoredCounts = [];

        $deleteOrder = [
            '#__xdecaropeople_duplicate_ignores',
            '#__xdecaropeople_merges',
            '#__xdecaropeople_history',
            '#__xdecaropeople_people',
        ];
        $insertOrder = self::PAYLOAD_TABLES;

        $this->db->transactionStart();
        try {
            foreach ($deleteOrder as $table) {
                $this->db->setQuery(
                    $this->db->getQuery(true)->delete($this->db->quoteName($table))
                )->execute();
            }

            foreach ($insertOrder as $table) {
                $rows = $tables[$table] ?? null;
                if (!is_array($rows)) {
                    throw new RuntimeException('People restore table payload is invalid.');
                }

                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        throw new RuntimeException('People restore row payload is invalid.');
                    }
                    $object = (object) $row;
                    $this->db->insertObject($table, $object);
                }

                $restoredCounts[$table] = count($rows);
            }

            foreach ($restoredCounts as $table => $expectedCount) {
                $actualCount = (int) $this->db->setQuery(
                    $this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName($table))
                )->loadResult();
                if ($actualCount !== $expectedCount) {
                    throw new RuntimeException('People restore integrity count mismatch.');
                }
            }

            $this->db->transactionCommit();
        } catch (\Throwable $e) {
            $this->db->transactionRollback();
            throw new RuntimeException('People restore failed and was rolled back.', 0, $e);
        }

        $this->log->log('restore_full', null, $actorUserId, [
            'backup_uuid' => (string) ($preview['manifest']['backup_uuid'] ?? ''),
            'safety_backup_uuid' => (string) ($safetyBackup['uuid'] ?? ''),
            'restored_counts' => $restoredCounts,
        ]);

        return [
            'safety_backup_uuid' => (string) ($safetyBackup['uuid'] ?? ''),
            'restored_counts' => $restoredCounts,
            'integrity_ok' => true,
            'warnings' => (array) ($preview['warnings'] ?? []),
        ];
    }

    public function restorePerson(string $zipPath, string $personUuid, int $actorUserId, bool $overwrite = false): array
    {
        throw new RuntimeException('Single-person People restore is not implemented yet.', 501);
    }

    private function validUuid(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', trim($uuid)) === 1;
    }
}
