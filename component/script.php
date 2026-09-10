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
            'birth_date' => 'DATE DEFAULT NULL AFTER `last_name`',
            'sex' => 'CHAR(1) DEFAULT NULL AFTER `birth_date`',
            'disability_status' => 'TINYINT DEFAULT NULL AFTER `sex`',
            'birth_place' => 'VARCHAR(190) DEFAULT NULL AFTER `disability_status`',
            'nationality_code' => 'CHAR(3) DEFAULT NULL AFTER `birth_place`',
            'tax_identifier' => 'VARCHAR(64) DEFAULT NULL AFTER `nationality_code`',
            'whatsapp' => 'VARCHAR(50) DEFAULT NULL AFTER `phone`',
            'address_line' => 'VARCHAR(255) DEFAULT NULL AFTER `whatsapp`',
            'address_number' => 'VARCHAR(32) DEFAULT NULL AFTER `address_line`',
            'postal_code' => 'VARCHAR(32) DEFAULT NULL AFTER `address_number`',
            'city' => 'VARCHAR(190) DEFAULT NULL AFTER `postal_code`',
            'region' => 'VARCHAR(190) DEFAULT NULL AFTER `city`',
            'country_code' => 'CHAR(2) DEFAULT NULL AFTER `region`',
            'language' => 'VARCHAR(16) DEFAULT NULL AFTER `country_code`',
            'social_instagram' => 'VARCHAR(255) DEFAULT NULL AFTER `language`',
            'social_facebook' => 'VARCHAR(255) DEFAULT NULL AFTER `social_instagram`',
            'social_linkedin' => 'VARCHAR(255) DEFAULT NULL AFTER `social_facebook`',
            'social_tiktok' => 'VARCHAR(255) DEFAULT NULL AFTER `social_linkedin`',
            'social_telegram' => 'VARCHAR(255) DEFAULT NULL AFTER `social_tiktok`',
            'social_x' => 'VARCHAR(255) DEFAULT NULL AFTER `social_telegram`',
            'social_youtube' => 'VARCHAR(255) DEFAULT NULL AFTER `social_x`',
            'website_url' => 'VARCHAR(255) DEFAULT NULL AFTER `social_youtube`',
            'notes' => 'TEXT DEFAULT NULL AFTER `website_url`',
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
        ];
        foreach ($indexes as $name => $definition) {
            if (isset($keys[$name])) {
                continue;
            }
            $db->setQuery('ALTER TABLE ' . $db->quoteName($table) . ' ADD ' . $definition)->execute();
        }
    }
}
