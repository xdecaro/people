<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use RuntimeException;

final class DatabaseMaintenanceService
{
    public function __construct(
        private DatabaseInterface $db,
        private DatabaseSchemaDefinition $definition,
        private DatabaseSchemaInspector $inspector,
        private MaintenanceLogService $log,
        private BackupService $backup,
        private IntegrityService $integrity
    ) {}

    public function check(int $actorUserId, bool $writeLog = true): array
    {
        $result = $this->inspector->inspect();

        if ($writeLog) {
            $this->log->log('database_check', null, $actorUserId, [
                'status' => (string) ($result['status'] ?? 'Errore'),
                'ok' => (bool) ($result['ok'] ?? false),
            ]);
        }

        return $result;
    }

    public function repair(int $actorUserId): array
    {
        $before = $this->inspector->inspect();
        $operations = $this->buildRepairPlan($before);

        foreach ($operations as $operation) {
            $sql = (string) ($operation['sql'] ?? '');
            if ($sql === '') {
                continue;
            }
            $this->db->setQuery($sql)->execute();
        }

        $after = $this->inspector->inspect();
        $this->log->log('database_repair', null, $actorUserId, [
            'before_status' => (string) ($before['status'] ?? 'Errore'),
            'after_status' => (string) ($after['status'] ?? 'Errore'),
            'operations' => array_values(array_map(
                static fn(array $operation): string => (string) ($operation['label'] ?? ''),
                $operations
            )),
        ]);

        return [
            'before' => $before,
            'after' => $after,
            'operations' => $operations,
        ];
    }

    public function emptyFunctionalData(int $actorUserId, string $confirmation): array
    {
        if ($confirmation !== 'SVUOTA') {
            throw new RuntimeException('Per svuotare i dati People devi digitare esattamente SVUOTA.', 400);
        }

        $safetyBackup = $this->backup->create($actorUserId, 'before_empty_database');
        $safetyUuid = (string) ($safetyBackup['uuid'] ?? '');
        $safetyFilename = (string) ($safetyBackup['filename'] ?? '');
        if ($safetyUuid === '') {
            throw new RuntimeException('Backup di sicurezza non creato. Svuotamento annullato.');
        }
        $this->backup->verify($safetyUuid);

        $deleteOrder = [
            '#__xdecaropeople_history',
            '#__xdecaropeople_duplicate_ignores',
            '#__xdecaropeople_merges',
            '#__xdecaropeople_people',
        ];
        $removedCounts = [];
        foreach ($deleteOrder as $table) {
            $this->assertFunctionalTable($table);
            $removedCounts[$table] = $this->countRows($table);
        }

        $this->db->transactionStart();
        try {
            foreach ($deleteOrder as $table) {
                $query = $this->db->getQuery(true)->delete($this->db->quoteName($table));
                $this->db->setQuery($query)->execute();
            }
            $this->db->transactionCommit();
        } catch (\Throwable $e) {
            $this->db->transactionRollback();
            throw new RuntimeException(
                'Svuotamento People non completato. Il backup di sicurezza è stato creato: ' . ($safetyFilename !== '' ? $safetyFilename : $safetyUuid) . '.',
                0,
                $e
            );
        }

        foreach ($deleteOrder as $table) {
            try {
                $this->db->setQuery('ALTER TABLE ' . $this->db->quoteName($table) . ' AUTO_INCREMENT = 1')->execute();
            } catch (\Throwable) {
                // Reset counter is best-effort; emptied data and safety backup remain valid.
            }
        }

        $schema = $this->inspector->inspect();
        $integrity = $this->integrity->check($actorUserId, false);
        $this->log->log('database_empty', null, $actorUserId, [
            'safety_backup_uuid' => $safetyUuid,
            'safety_backup_filename' => $safetyFilename,
            'removed_counts' => $removedCounts,
            'schema_status' => (string) ($schema['status'] ?? 'Errore'),
            'integrity_ok' => (bool) ($integrity['ok'] ?? false),
        ]);

        return [
            'safety_backup_uuid' => $safetyUuid,
            'safety_backup_filename' => $safetyFilename,
            'removed_counts' => $removedCounts,
            'schema_status' => (string) ($schema['status'] ?? 'Errore'),
            'integrity' => $integrity,
        ];
    }

    public function recreateFunctionalDatabase(int $actorUserId, string $confirmation): array
    {
        if ($confirmation !== 'RICREA') {
            throw new RuntimeException('Per ricreare il database People devi digitare esattamente RICREA.', 400);
        }

        $safetyBackup = $this->backup->create($actorUserId, 'before_recreate_database');
        $safetyUuid = (string) ($safetyBackup['uuid'] ?? '');
        $safetyFilename = (string) ($safetyBackup['filename'] ?? '');
        if ($safetyUuid === '') {
            throw new RuntimeException('Backup di sicurezza non creato. Ricreazione annullata.');
        }
        $this->backup->verify($safetyUuid);

        $functionalTables = $this->definition->functionalTables();
        foreach ($functionalTables as $table) {
            $this->assertFunctionalTable($table);
        }

        try {
            foreach (array_reverse($functionalTables) as $table) {
                $this->db->setQuery('DROP TABLE IF EXISTS ' . $this->db->quoteName($table))->execute();
            }

            foreach ($functionalTables as $table) {
                $this->db->setQuery($this->definition->createTableSql($table))->execute();
            }

            $repair = $this->repair($actorUserId);
            $schema = $this->inspector->inspect();
            $integrity = $this->integrity->check($actorUserId, false);

            if (empty($schema['ok'])) {
                throw new RuntimeException('Lo schema People ricreato non corrisponde allo schema canonico.');
            }

            $this->log->log('database_recreate', null, $actorUserId, [
                'safety_backup_uuid' => $safetyUuid,
                'safety_backup_filename' => $safetyFilename,
                'recreated_tables' => $functionalTables,
                'schema_status' => (string) ($schema['status'] ?? 'Errore'),
                'integrity_ok' => (bool) ($integrity['ok'] ?? false),
                'repair_operations' => count((array) ($repair['operations'] ?? [])),
            ]);

            return [
                'safety_backup_uuid' => $safetyUuid,
                'safety_backup_filename' => $safetyFilename,
                'schema_status' => (string) ($schema['status'] ?? 'Errore'),
                'integrity' => $integrity,
                'recreated_tables' => $functionalTables,
            ];
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Ricreazione database People non completata. Il backup di sicurezza è stato creato: ' . ($safetyFilename !== '' ? $safetyFilename : $safetyUuid) . '.',
                0,
                $e
            );
        }
    }

    private function buildRepairPlan(array $inspection): array
    {
        $plan = [];

        foreach ((array) ($inspection['missing_tables'] ?? []) as $table) {
            $this->assertCanonicalTable((string) $table);
            $plan[] = [
                'label' => 'create_table:' . $table,
                'sql' => $this->definition->createTableSql((string) $table),
            ];
        }

        foreach ((array) ($inspection['missing_columns'] ?? []) as $table => $columns) {
            $spec = $this->definition->table((string) $table);
            foreach ((array) $columns as $column) {
                $fragment = (string) ($spec['columns'][$column] ?? '');
                if ($fragment === '') {
                    throw new RuntimeException('Canonical column definition missing for ' . $table . '.' . $column);
                }
                $plan[] = [
                    'label' => 'add_column:' . $table . '.' . $column,
                    'sql' => 'ALTER TABLE ' . $this->db->quoteName((string) $table) . ' ADD ' . $fragment,
                ];
            }
        }

        foreach ((array) ($inspection['incompatible_columns'] ?? []) as $table => $columns) {
            $spec = $this->definition->table((string) $table);
            foreach ((array) $columns as $column) {
                $fragment = (string) ($spec['columns'][$column] ?? '');
                if ($fragment === '') {
                    throw new RuntimeException('Canonical column definition missing for ' . $table . '.' . $column);
                }
                $plan[] = [
                    'label' => 'modify_column:' . $table . '.' . $column,
                    'sql' => 'ALTER TABLE ' . $this->db->quoteName((string) $table) . ' MODIFY ' . $fragment,
                ];
            }
        }

        foreach ((array) ($inspection['missing_indexes'] ?? []) as $table => $indexes) {
            $spec = $this->definition->table((string) $table);
            foreach ((array) $indexes as $index) {
                $fragment = $this->indexFragment($spec, (string) $index);
                $plan[] = [
                    'label' => 'add_index:' . $table . '.' . $index,
                    'sql' => 'ALTER TABLE ' . $this->db->quoteName((string) $table) . ' ADD ' . $fragment,
                ];
            }
        }

        foreach ((array) ($inspection['incompatible_indexes'] ?? []) as $table => $indexes) {
            $spec = $this->definition->table((string) $table);
            foreach ((array) $indexes as $index) {
                $fragment = $this->indexFragment($spec, (string) $index);
                $quotedIndex = $this->db->quoteName((string) $index);
                $plan[] = [
                    'label' => 'replace_index:' . $table . '.' . $index,
                    'sql' => 'ALTER TABLE ' . $this->db->quoteName((string) $table)
                        . ' DROP INDEX ' . $quotedIndex . ', ADD ' . $fragment,
                ];
            }
        }

        foreach ($this->definition->legacyRemovals() as $removal) {
            if (!is_array($removal)) {
                continue;
            }
            $table = (string) ($removal['table'] ?? '');
            $type = (string) ($removal['type'] ?? '');
            $name = (string) ($removal['name'] ?? '');
            if ($table === '' || $name === '') {
                continue;
            }
            $this->assertCanonicalTable($table);

            if ($type === 'column' && in_array($name, (array) (($inspection['unknown_columns'][$table] ?? [])), true)) {
                $plan[] = [
                    'label' => 'drop_legacy_column:' . $table . '.' . $name,
                    'sql' => 'ALTER TABLE ' . $this->db->quoteName($table) . ' DROP COLUMN ' . $this->db->quoteName($name),
                ];
            }
            if ($type === 'index' && in_array($name, (array) (($inspection['unknown_indexes'][$table] ?? [])), true)) {
                $plan[] = [
                    'label' => 'drop_legacy_index:' . $table . '.' . $name,
                    'sql' => 'ALTER TABLE ' . $this->db->quoteName($table) . ' DROP INDEX ' . $this->db->quoteName($name),
                ];
            }
        }

        return $plan;
    }

    private function countRows(string $table): int
    {
        $this->assertCanonicalTable($table);
        $query = $this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName($table));
        return (int) $this->db->setQuery($query)->loadResult();
    }

    private function indexFragment(array $spec, string $index): string
    {
        $fragment = (string) (($spec['unique_indexes'][$index] ?? $spec['indexes'][$index] ?? ''));
        if ($fragment === '') {
            throw new RuntimeException('Canonical index definition missing: ' . $index);
        }
        return $fragment;
    }

    private function assertCanonicalTable(string $table): void
    {
        if (!array_key_exists($table, $this->definition->tables())) {
            throw new RuntimeException('Database maintenance attempted outside canonical People tables: ' . $table);
        }
    }

    private function assertFunctionalTable(string $table): void
    {
        if (!in_array($table, $this->definition->functionalTables(), true)) {
            throw new RuntimeException('Destructive database maintenance attempted outside functional People tables: ' . $table);
        }
    }
}
