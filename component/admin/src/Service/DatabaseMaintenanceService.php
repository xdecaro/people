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
}
