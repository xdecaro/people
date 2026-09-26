<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

final class DatabaseSchemaInspector
{
    public function __construct(
        private DatabaseInterface $db,
        private DatabaseSchemaDefinition $definition
    ) {}

    public function inspect(): array
    {
        $result = [
            'status' => 'OK',
            'ok' => true,
            'tables' => [],
            'missing_tables' => [],
            'unexpected_tables' => [],
            'missing_columns' => [],
            'incompatible_columns' => [],
            'unknown_columns' => [],
            'missing_indexes' => [],
            'incompatible_indexes' => [],
            'unknown_indexes' => [],
            'engine_differences' => [],
            'collation_differences' => [],
        ];

        $actualTables = array_values((array) $this->db->getTableList());
        $prefix = (string) $this->db->getPrefix();
        $canonicalActual = [];
        foreach (array_keys($this->definition->tables()) as $table) {
            $canonicalActual[$this->db->replacePrefix($table)] = $table;
        }

        foreach ($actualTables as $actual) {
            if (str_starts_with($actual, $prefix . 'xdecaropeople_') && !isset($canonicalActual[$actual])) {
                $result['unexpected_tables'][] = '#__' . substr($actual, strlen($prefix));
            }
        }

        foreach ($this->definition->tables() as $table => $spec) {
            $actualName = $this->db->replacePrefix($table);
            $exists = in_array($actualName, $actualTables, true);
            $result['tables'][$table] = $exists;
            if (!$exists) {
                $result['missing_tables'][] = $table;
                continue;
            }

            $columns = $this->loadColumns($table);
            $expectedColumns = array_keys((array) ($spec['columns'] ?? []));
            foreach ($expectedColumns as $column) {
                if (!isset($columns[$column])) {
                    $result['missing_columns'][$table][] = $column;
                    continue;
                }
                if (!$this->columnCompatible((string) $spec['columns'][$column], $columns[$column])) {
                    $result['incompatible_columns'][$table][] = $column;
                }
            }
            foreach (array_keys($columns) as $column) {
                if (!in_array($column, $expectedColumns, true)) {
                    $result['unknown_columns'][$table][] = $column;
                }
            }

            $indexes = $this->loadIndexes($table);
            $expectedIndexes = array_merge(
                (array) ($spec['unique_indexes'] ?? []),
                (array) ($spec['indexes'] ?? [])
            );
            foreach ($expectedIndexes as $name => $fragment) {
                if (!isset($indexes[$name])) {
                    $result['missing_indexes'][$table][] = $name;
                    continue;
                }
                if (!$this->indexCompatible((string) $fragment, $indexes[$name])) {
                    $result['incompatible_indexes'][$table][] = $name;
                }
            }
            foreach (array_keys($indexes) as $name) {
                if ($name !== 'PRIMARY' && !isset($expectedIndexes[$name])) {
                    $result['unknown_indexes'][$table][] = $name;
                }
            }

            $status = $this->loadTableStatus($actualName);
            if ($status) {
                if (strcasecmp((string) ($status['Engine'] ?? ''), 'InnoDB') !== 0) {
                    $result['engine_differences'][$table] = (string) ($status['Engine'] ?? '');
                }
                if (strcasecmp((string) ($status['Collation'] ?? ''), 'utf8mb4_unicode_ci') !== 0) {
                    $result['collation_differences'][$table] = (string) ($status['Collation'] ?? '');
                }
            }
        }

        foreach (['missing_tables','unexpected_tables','missing_columns','incompatible_columns','unknown_columns','missing_indexes','incompatible_indexes','unknown_indexes','engine_differences','collation_differences'] as $key) {
            if (!empty($result[$key])) {
                $result['ok'] = false;
                $result['status'] = 'Da aggiornare';
                break;
            }
        }

        return $result;
    }

    private function loadColumns(string $table): array
    {
        $rows = (array) $this->db->setQuery('SHOW FULL COLUMNS FROM ' . $this->db->quoteName($table))->loadAssocList();
        $out = [];
        foreach ($rows as $row) {
            $name = (string) ($row['Field'] ?? '');
            if ($name !== '') {
                $out[$name] = $row;
            }
        }
        return $out;
    }

    private function loadIndexes(string $table): array
    {
        $rows = (array) $this->db->setQuery('SHOW INDEX FROM ' . $this->db->quoteName($table))->loadAssocList();
        $out = [];
        foreach ($rows as $row) {
            $name = (string) ($row['Key_name'] ?? '');
            if ($name === '') {
                continue;
            }
            $out[$name] ??= ['unique' => ((int) ($row['Non_unique'] ?? 1)) === 0, 'columns' => []];
            $position = max(1, (int) ($row['Seq_in_index'] ?? 1));
            $out[$name]['columns'][$position] = (string) ($row['Column_name'] ?? '');
        }
        foreach ($out as &$index) {
            ksort($index['columns']);
            $index['columns'] = array_values($index['columns']);
        }
        unset($index);
        return $out;
    }

    private function loadTableStatus(string $actualName): ?array
    {
        $row = $this->db->setQuery('SHOW TABLE STATUS LIKE ' . $this->db->quote($actualName))->loadAssoc();
        return $row ?: null;
    }

    private function columnCompatible(string $fragment, array $actual): bool
    {
        $expectedType = '';
        if (preg_match('/^`[^`]+`\s+(.+?)(?=\s+NOT NULL|\s+DEFAULT|\s+AUTO_INCREMENT|$)/i', trim($fragment), $m)) {
            $expectedType = strtolower(preg_replace('/\s+/', ' ', trim($m[1])) ?? '');
        }
        $actualType = strtolower(preg_replace('/\s+/', ' ', trim((string) ($actual['Type'] ?? ''))) ?? '');
        if ($expectedType !== '' && $expectedType !== $actualType) {
            return false;
        }

        $expectedNullable = !str_contains(strtoupper($fragment), ' NOT NULL');
        $actualNullable = strtoupper((string) ($actual['Null'] ?? 'YES')) === 'YES';
        if ($expectedNullable !== $actualNullable) {
            return false;
        }

        $expectedAuto = str_contains(strtoupper($fragment), 'AUTO_INCREMENT');
        $actualAuto = str_contains(strtolower((string) ($actual['Extra'] ?? '')), 'auto_increment');
        if ($expectedAuto !== $actualAuto) {
            return false;
        }

        if (preg_match('/\sDEFAULT\s+(NULL|\'([^\']*)\'|"([^"]*)"|(-?\d+))/i', $fragment, $m)) {
            $expectedDefault = strtoupper($m[1]) === 'NULL' ? null : ($m[2] !== '' ? $m[2] : ($m[3] !== '' ? $m[3] : $m[4]));
            $actualDefault = $actual['Default'] ?? null;
            if ((string) ($expectedDefault ?? '') !== (string) ($actualDefault ?? '')) {
                return false;
            }
        }

        return true;
    }

    private function indexCompatible(string $fragment, array $actual): bool
    {
        $expectedUnique = str_starts_with(strtoupper(trim($fragment)), 'UNIQUE KEY');
        if ($expectedUnique !== (bool) ($actual['unique'] ?? false)) {
            return false;
        }
        $expectedColumns = [];
        if (preg_match('/\((.*)\)/', $fragment, $m)) {
            preg_match_all('/`([^`]+)`/', $m[1], $columns);
            $expectedColumns = array_values($columns[1] ?? []);
        }
        return $expectedColumns === array_values((array) ($actual['columns'] ?? []));
    }
}
