ALTER TABLE `#__xdecaropeople_people`
  ADD COLUMN `preferred_name` VARCHAR(150) DEFAULT NULL AFTER `last_name`,
  ADD COLUMN `disability_types` LONGTEXT DEFAULT NULL AFTER `disability_status`,
  ADD COLUMN `disability_other` VARCHAR(190) DEFAULT NULL AFTER `disability_types`,
  ADD COLUMN `accessibility_needs` LONGTEXT DEFAULT NULL AFTER `disability_other`,
  ADD COLUMN `accessibility_other` VARCHAR(190) DEFAULT NULL AFTER `accessibility_needs`,
  ADD COLUMN `nationality_codes` LONGTEXT DEFAULT NULL AFTER `nationality_code`,
  ADD COLUMN `birth_country_code` CHAR(2) DEFAULT NULL AFTER `nationality_codes`,
  ADD COLUMN `birth_place_id` VARCHAR(64) DEFAULT NULL AFTER `birth_place`,
  ADD COLUMN `birth_region` VARCHAR(190) DEFAULT NULL AFTER `birth_place_id`,
  ADD COLUMN `preferred_contact` VARCHAR(32) DEFAULT NULL AFTER `whatsapp`,
  ADD COLUMN `residence_place_id` VARCHAR(64) DEFAULT NULL AFTER `country_code`,
  ADD COLUMN `additional_addresses` LONGTEXT DEFAULT NULL AFTER `residence_place_id`,
  ADD COLUMN `relations_data` LONGTEXT DEFAULT NULL AFTER `additional_addresses`,
  ADD COLUMN `profile_document_uuid` CHAR(36) DEFAULT NULL AFTER `website_url`,
  ADD COLUMN `person_status` VARCHAR(16) NOT NULL DEFAULT 'active' AFTER `profile_document_uuid`,
  ADD COLUMN `source_component` VARCHAR(64) DEFAULT NULL AFTER `person_status`,
  ADD KEY `idx_people_person_status` (`person_status`);

UPDATE `#__xdecaropeople_people`
SET `nationality_codes` = CONCAT('["', `nationality_code`, '"]')
WHERE `nationality_code` IS NOT NULL
  AND `nationality_code` <> ''
  AND (`nationality_codes` IS NULL OR `nationality_codes` = '');

UPDATE `#__xdecaropeople_people`
SET `source_component` = 'com_xdecaropeople'
WHERE `source_component` IS NULL OR `source_component` = '';

CREATE TABLE IF NOT EXISTS `#__xdecaropeople_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `person_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(32) NOT NULL,
  `changed_fields` TEXT DEFAULT NULL,
  `actor_user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_people_history_person` (`person_id`,`created`),
  KEY `idx_people_history_actor` (`actor_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
