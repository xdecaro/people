<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

final class com_xdecaropeopleInstallerScript
{
    public function postflight($type, $parent): void
    {
        if (!in_array((string) $type, ['install', 'update', 'discover_install'], true)) {
            return;
        }

        try {
            /** @var DatabaseInterface $db */
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $this->repairPeopleTable($db);
            $this->repairHistoryTable($db);
        } catch (Throwable $e) {
            Log::add('People schema repair warning: ' . $e->getMessage(), Log::WARNING, 'xdecaropeople');
            throw $e;
        }
    }

    private function repairPeopleTable(DatabaseInterface $db): void
    {
        $table = $db->replacePrefix('#__xdecaropeople_people');
        if (!in_array($table, $db->getTableList(), true)) {
            return;
        }

        $columns = array_change_key_case($db->getTableColumns($table, false), CASE_LOWER);
        $definitions = [
            'user_id' => 'INT UNSIGNED DEFAULT NULL AFTER `uuid`',
            'preferred_name' => 'VARCHAR(150) DEFAULT NULL AFTER `last_name`',
            'birth_date' => 'DATE DEFAULT NULL AFTER `preferred_name`',
            'sex' => 'CHAR(1) DEFAULT NULL AFTER `birth_date`',
            'disability_status' => 'TINYINT DEFAULT NULL AFTER `sex`',
            'disability_types' => 'LONGTEXT DEFAULT NULL AFTER `disability_status`',
            'disability_other' => 'VARCHAR(190) DEFAULT NULL AFTER `disability_types`',
            'accessibility_needs' => 'LONGTEXT DEFAULT NULL AFTER `disability_other`',
            'accessibility_other' => 'VARCHAR(190) DEFAULT NULL AFTER `accessibility_needs`',
            'birth_place' => 'VARCHAR(190) DEFAULT NULL AFTER `accessibility_other`',
            'nationality_code' => 'CHAR(3) DEFAULT NULL AFTER `birth_place`',
            'nationality_codes' => 'LONGTEXT DEFAULT NULL AFTER `nationality_code`',
            'birth_country_code' => 'CHAR(2) DEFAULT NULL AFTER `nationality_codes`',
            'birth_place_id' => 'VARCHAR(64) DEFAULT NULL AFTER `birth_country_code`',
            'birth_region' => 'VARCHAR(190) DEFAULT NULL AFTER `birth_place_id`',
            'tax_identifier' => 'VARCHAR(64) DEFAULT NULL AFTER `birth_region`',
            'whatsapp' => 'VARCHAR(50) DEFAULT NULL AFTER `phone`',
            'preferred_contact' => 'VARCHAR(32) DEFAULT NULL AFTER `whatsapp`',
            'address_line' => 'VARCHAR(255) DEFAULT NULL AFTER `preferred_contact`',
            'address_number' => 'VARCHAR(32) DEFAULT NULL AFTER `address_line`',
            'postal_code' => 'VARCHAR(32) DEFAULT NULL AFTER `address_number`',
            'city' => 'VARCHAR(190) DEFAULT NULL AFTER `postal_code`',
            'region' => 'VARCHAR(190) DEFAULT NULL AFTER `city`',
            'country_code' => 'CHAR(2) DEFAULT NULL AFTER `region`',
            'residence_place_id' => 'VARCHAR(64) DEFAULT NULL AFTER `country_code`',
            'additional_addresses' => 'LONGTEXT DEFAULT NULL AFTER `residence_place_id`',
            'relations_data' => 'LONGTEXT DEFAULT NULL AFTER `additional_addresses`',
            'language' => 'VARCHAR(16) DEFAULT NULL AFTER `relations_data`',
            'social_instagram' => 'VARCHAR(255) DEFAULT NULL AFTER `language`',
            'social_facebook' => 'VARCHAR(255) DEFAULT NULL AFTER `social_instagram`',
            'social_linkedin' => 'VARCHAR(255) DEFAULT NULL AFTER `social_facebook`',
            'social_tiktok' => 'VARCHAR(255) DEFAULT NULL AFTER `social_linkedin`',
            'social_telegram' => 'VARCHAR(255) DEFAULT NULL AFTER `social_tiktok`',
            'social_x' => 'VARCHAR(255) DEFAULT NULL AFTER `social_telegram`',
            'social_youtube' => 'VARCHAR(255) DEFAULT NULL AFTER `social_x`',
            'website_url' => 'VARCHAR(255) DEFAULT NULL AFTER `social_youtube`',
            'profile_document_uuid' => 'CHAR(36) DEFAULT NULL AFTER `website_url`',
            'person_status' => "VARCHAR(16) NOT NULL DEFAULT 'active' AFTER `profile_document_uuid`",
            'source_component' => 'VARCHAR(64) DEFAULT NULL AFTER `person_status`',
            'notes' => 'TEXT DEFAULT NULL AFTER `source_component`',
        ];

        foreach ($definitions as $column => $definition) {
            if (array_key_exists(strtolower($column), $columns)) {
                continue;
            }
            $query = 'ALTER TABLE ' . $db->quoteName($table)
                . ' ADD COLUMN ' . $db->quoteName($column) . ' ' . $definition;
            $db->setQuery($query)->execute();
            $columns[strtolower($column)] = true;
        }

        $db->setQuery(
            'UPDATE ' . $db->quoteName($table)
            . ' SET ' . $db->quoteName('nationality_codes') . ' = CONCAT(\'["\', ' . $db->quoteName('nationality_code') . ', \'"]\')'
            . ' WHERE ' . $db->quoteName('nationality_code') . ' IS NOT NULL'
            . ' AND ' . $db->quoteName('nationality_code') . " <> ''"
            . ' AND (' . $db->quoteName('nationality_codes') . ' IS NULL OR ' . $db->quoteName('nationality_codes') . " = '')"
        )->execute();

        $db->setQuery(
            'UPDATE ' . $db->quoteName($table)
            . ' SET ' . $db->quoteName('source_component') . " = 'com_xdecaropeople'"
            . ' WHERE ' . $db->quoteName('source_component') . ' IS NULL OR ' . $db->quoteName('source_component') . " = ''"
        )->execute();

        $keys = [];
        foreach ((array) $db->setQuery('SHOW INDEX FROM ' . $db->quoteName($table))->loadObjectList() as $row) {
            $name = (string) ($row->Key_name ?? $row->key_name ?? '');
            if ($name !== '') {
                $keys[$name] = true;
            }
        }

        $indexes = [
            'idx_people_user_id' => 'UNIQUE KEY `idx_people_user_id` (`user_id`)',
            'idx_people_email' => 'KEY `idx_people_email` (`email`)',
            'idx_people_tax_identifier' => 'KEY `idx_people_tax_identifier` (`tax_identifier`)',
            'idx_people_birth' => 'KEY `idx_people_birth` (`birth_date`)',
            'idx_people_person_status' => 'KEY `idx_people_person_status` (`person_status`)',
        ];
        foreach ($indexes as $name => $definition) {
            if (isset($keys[$name])) {
                continue;
            }
            $db->setQuery('ALTER TABLE ' . $db->quoteName($table) . ' ADD ' . $definition)->execute();
        }
    }

    private function repairHistoryTable(DatabaseInterface $db): void
    {
        $table = $db->quoteName('#__xdecaropeople_history');
        $db->setQuery(
            'CREATE TABLE IF NOT EXISTS ' . $table . ' ('
            . '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '`person_id` INT UNSIGNED NOT NULL,'
            . '`action` VARCHAR(32) NOT NULL,'
            . '`changed_fields` TEXT DEFAULT NULL,'
            . '`actor_user_id` INT UNSIGNED NOT NULL DEFAULT 0,'
            . '`created` DATETIME NOT NULL,'
            . 'PRIMARY KEY (`id`),'
            . 'KEY `idx_people_history_person` (`person_id`,`created`),'
            . 'KEY `idx_people_history_actor` (`actor_user_id`)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        )->execute();
    }
}
