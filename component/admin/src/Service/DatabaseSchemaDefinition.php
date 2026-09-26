<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use RuntimeException;

final class DatabaseSchemaDefinition
{
    private const TABLES = [
        '#__xdecaropeople_people' => [
            'role' => 'functional',
            'columns' => [
                'id' => '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT',
                'uuid' => '`uuid` CHAR(36) NOT NULL',
                'user_id' => '`user_id` INT UNSIGNED DEFAULT NULL',
                'display_name' => '`display_name` VARCHAR(255) NOT NULL',
                'first_name' => '`first_name` VARCHAR(150) NOT NULL',
                'last_name' => '`last_name` VARCHAR(150) NOT NULL',
                'preferred_name' => '`preferred_name` VARCHAR(150) DEFAULT NULL',
                'birth_date' => '`birth_date` DATE DEFAULT NULL',
                'sex' => '`sex` CHAR(1) DEFAULT NULL',
                'disability_status' => '`disability_status` TINYINT DEFAULT NULL',
                'disability_types' => '`disability_types` LONGTEXT DEFAULT NULL',
                'disability_other' => '`disability_other` VARCHAR(190) DEFAULT NULL',
                'accessibility_needs' => '`accessibility_needs` LONGTEXT DEFAULT NULL',
                'accessibility_other' => '`accessibility_other` VARCHAR(190) DEFAULT NULL',
                'nationality_code' => '`nationality_code` CHAR(3) DEFAULT NULL',
                'nationality_codes' => '`nationality_codes` LONGTEXT DEFAULT NULL',
                'birth_country_code' => '`birth_country_code` CHAR(2) DEFAULT NULL',
                'birth_place' => '`birth_place` VARCHAR(190) DEFAULT NULL',
                'birth_place_id' => '`birth_place_id` VARCHAR(64) DEFAULT NULL',
                'birth_region' => '`birth_region` VARCHAR(190) DEFAULT NULL',
                'tax_identifier' => '`tax_identifier` VARCHAR(64) DEFAULT NULL',
                'email' => '`email` VARCHAR(254) DEFAULT NULL',
                'phone' => '`phone` VARCHAR(50) DEFAULT NULL',
                'whatsapp' => '`whatsapp` VARCHAR(50) DEFAULT NULL',
                'preferred_contact' => '`preferred_contact` VARCHAR(32) DEFAULT NULL',
                'address_line' => '`address_line` VARCHAR(255) DEFAULT NULL',
                'address_number' => '`address_number` VARCHAR(32) DEFAULT NULL',
                'postal_code' => '`postal_code` VARCHAR(32) DEFAULT NULL',
                'city' => '`city` VARCHAR(190) DEFAULT NULL',
                'region' => '`region` VARCHAR(190) DEFAULT NULL',
                'country_code' => '`country_code` CHAR(2) DEFAULT NULL',
                'residence_place_id' => '`residence_place_id` VARCHAR(64) DEFAULT NULL',
                'additional_addresses' => '`additional_addresses` LONGTEXT DEFAULT NULL',
                'relations_data' => '`relations_data` LONGTEXT DEFAULT NULL',
                'language' => '`language` VARCHAR(16) DEFAULT NULL',
                'social_instagram' => '`social_instagram` VARCHAR(255) DEFAULT NULL',
                'social_facebook' => '`social_facebook` VARCHAR(255) DEFAULT NULL',
                'social_linkedin' => '`social_linkedin` VARCHAR(255) DEFAULT NULL',
                'social_tiktok' => '`social_tiktok` VARCHAR(255) DEFAULT NULL',
                'social_telegram' => '`social_telegram` VARCHAR(255) DEFAULT NULL',
                'social_x' => '`social_x` VARCHAR(255) DEFAULT NULL',
                'social_youtube' => '`social_youtube` VARCHAR(255) DEFAULT NULL',
                'website_url' => '`website_url` VARCHAR(255) DEFAULT NULL',
                'profile_document_uuid' => '`profile_document_uuid` CHAR(36) DEFAULT NULL',
                'person_status' => "`person_status` VARCHAR(16) NOT NULL DEFAULT 'active'",
                'source_component' => '`source_component` VARCHAR(64) DEFAULT NULL',
                'notes' => '`notes` TEXT DEFAULT NULL',
                'state' => '`state` TINYINT NOT NULL DEFAULT 1',
                'access' => '`access` INT UNSIGNED NOT NULL DEFAULT 1',
                'created' => '`created` DATETIME NOT NULL',
                'created_by' => '`created_by` INT UNSIGNED NOT NULL DEFAULT 0',
                'modified' => '`modified` DATETIME DEFAULT NULL',
                'modified_by' => '`modified_by` INT UNSIGNED NOT NULL DEFAULT 0',
            ],
            'primary' => 'PRIMARY KEY (`id`)',
            'unique_indexes' => [
                'idx_people_uuid' => 'UNIQUE KEY `idx_people_uuid` (`uuid`)',
                'idx_people_user_id' => 'UNIQUE KEY `idx_people_user_id` (`user_id`)',
            ],
            'indexes' => [
                'idx_people_name' => 'KEY `idx_people_name` (`last_name`,`first_name`)',
                'idx_people_email' => 'KEY `idx_people_email` (`email`)',
                'idx_people_tax_identifier' => 'KEY `idx_people_tax_identifier` (`tax_identifier`)',
                'idx_people_birth' => 'KEY `idx_people_birth` (`birth_date`)',
                'idx_people_person_status' => 'KEY `idx_people_person_status` (`person_status`)',
                'idx_people_state_access' => 'KEY `idx_people_state_access` (`state`,`access`)',
            ],
        ],
        '#__xdecaropeople_history' => [
            'role' => 'functional',
            'columns' => [
                'id' => '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                'person_id' => '`person_id` INT UNSIGNED NOT NULL',
                'action' => '`action` VARCHAR(32) NOT NULL',
                'changed_fields' => '`changed_fields` TEXT DEFAULT NULL',
                'actor_user_id' => '`actor_user_id` INT UNSIGNED NOT NULL DEFAULT 0',
                'created' => '`created` DATETIME NOT NULL',
            ],
            'primary' => 'PRIMARY KEY (`id`)',
            'unique_indexes' => [],
            'indexes' => [
                'idx_people_history_person' => 'KEY `idx_people_history_person` (`person_id`,`created`)',
                'idx_people_history_actor' => 'KEY `idx_people_history_actor` (`actor_user_id`)',
            ],
        ],
        '#__xdecaropeople_duplicate_ignores' => [
            'role' => 'functional',
            'columns' => [
                'id' => '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                'signature' => '`signature` CHAR(64) NOT NULL',
                'match_type' => '`match_type` VARCHAR(32) NOT NULL',
                'record_ids' => '`record_ids` TEXT NOT NULL',
                'created_by' => '`created_by` INT UNSIGNED NOT NULL DEFAULT 0',
                'created' => '`created` DATETIME NOT NULL',
            ],
            'primary' => 'PRIMARY KEY (`id`)',
            'unique_indexes' => [
                'idx_people_duplicate_ignore_signature' => 'UNIQUE KEY `idx_people_duplicate_ignore_signature` (`signature`)',
            ],
            'indexes' => [
                'idx_people_duplicate_ignore_created' => 'KEY `idx_people_duplicate_ignore_created` (`created`)',
            ],
        ],
        '#__xdecaropeople_merges' => [
            'role' => 'functional',
            'columns' => [
                'id' => '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                'source_person_id' => '`source_person_id` INT UNSIGNED NOT NULL',
                'source_uuid' => '`source_uuid` CHAR(36) NOT NULL',
                'target_person_id' => '`target_person_id` INT UNSIGNED NOT NULL',
                'target_uuid' => '`target_uuid` CHAR(36) NOT NULL',
                'copied_fields' => '`copied_fields` TEXT DEFAULT NULL',
                'created_by' => '`created_by` INT UNSIGNED NOT NULL DEFAULT 0',
                'created' => '`created` DATETIME NOT NULL',
            ],
            'primary' => 'PRIMARY KEY (`id`)',
            'unique_indexes' => [
                'idx_people_merge_source_person' => 'UNIQUE KEY `idx_people_merge_source_person` (`source_person_id`)',
                'idx_people_merge_source_uuid' => 'UNIQUE KEY `idx_people_merge_source_uuid` (`source_uuid`)',
            ],
            'indexes' => [
                'idx_people_merge_target_person' => 'KEY `idx_people_merge_target_person` (`target_person_id`)',
                'idx_people_merge_target_uuid' => 'KEY `idx_people_merge_target_uuid` (`target_uuid`)',
            ],
        ],
        '#__xdecaropeople_backups' => [
            'role' => 'maintenance',
            'columns' => [
                'id' => '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                'uuid' => '`uuid` CHAR(36) NOT NULL',
                'filename' => '`filename` VARCHAR(255) NOT NULL',
                'storage_path' => '`storage_path` VARCHAR(1024) NOT NULL',
                'sha256' => '`sha256` CHAR(64) NOT NULL',
                'size_bytes' => '`size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0',
                'people_count' => '`people_count` INT UNSIGNED NOT NULL DEFAULT 0',
                'component_version' => '`component_version` VARCHAR(32) NOT NULL',
                'schema_version' => '`schema_version` VARCHAR(32) NOT NULL',
                'created' => '`created` DATETIME NOT NULL',
                'created_by' => '`created_by` INT UNSIGNED NOT NULL DEFAULT 0',
                'status' => "`status` VARCHAR(32) NOT NULL DEFAULT 'ready'",
            ],
            'primary' => 'PRIMARY KEY (`id`)',
            'unique_indexes' => [
                'idx_people_backups_uuid' => 'UNIQUE KEY `idx_people_backups_uuid` (`uuid`)',
            ],
            'indexes' => [
                'idx_people_backups_created' => 'KEY `idx_people_backups_created` (`created`)',
                'idx_people_backups_status' => 'KEY `idx_people_backups_status` (`status`)',
            ],
        ],
        '#__xdecaropeople_maintenance_log' => [
            'role' => 'maintenance',
            'columns' => [
                'id' => '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                'action' => '`action` VARCHAR(64) NOT NULL',
                'subject_uuid' => '`subject_uuid` CHAR(36) DEFAULT NULL',
                'actor_user_id' => '`actor_user_id` INT UNSIGNED NOT NULL DEFAULT 0',
                'created' => '`created` DATETIME NOT NULL',
                'metadata' => '`metadata` LONGTEXT DEFAULT NULL',
            ],
            'primary' => 'PRIMARY KEY (`id`)',
            'unique_indexes' => [],
            'indexes' => [
                'idx_people_maintenance_action_created' => 'KEY `idx_people_maintenance_action_created` (`action`,`created`)',
                'idx_people_maintenance_subject_created' => 'KEY `idx_people_maintenance_subject_created` (`subject_uuid`,`created`)',
                'idx_people_maintenance_actor' => 'KEY `idx_people_maintenance_actor` (`actor_user_id`)',
            ],
        ],
    ];

    public function tables(): array
    {
        return self::TABLES;
    }

    public function functionalTables(): array
    {
        return $this->tablesByRole('functional');
    }

    public function maintenanceTables(): array
    {
        return $this->tablesByRole('maintenance');
    }

    public function table(string $table): array
    {
        if (!isset(self::TABLES[$table])) {
            throw new RuntimeException('Unknown People canonical table: ' . $table);
        }

        return self::TABLES[$table];
    }

    public function createTableSql(string $table): string
    {
        $spec = $this->table($table);
        $parts = array_values($spec['columns']);
        $parts[] = $spec['primary'];
        $parts = array_merge($parts, array_values($spec['unique_indexes']), array_values($spec['indexes']));

        return "CREATE TABLE IF NOT EXISTS `{$table}` (\n  "
            . implode(",\n  ", $parts)
            . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    }

    public function legacyRemovals(): array
    {
        return [];
    }

    private function tablesByRole(string $role): array
    {
        return array_keys(array_filter(
            self::TABLES,
            static fn(array $spec): bool => ($spec['role'] ?? '') === $role
        ));
    }
}
