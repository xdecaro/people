-- Compatibility repair boundary for upgrades from the 0.2.x prerelease.
-- If the old charset declaration prevented the baseline table from being created,
-- create the complete stable table. If it already exists, leave legacy rows untouched;
-- component/script.php adds only missing columns/indexes without tightening nullable data.
CREATE TABLE IF NOT EXISTS `#__xdecaropeople_people` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `display_name` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(150) NOT NULL,
  `last_name` VARCHAR(150) NOT NULL,
  `birth_date` DATE DEFAULT NULL,
  `birth_place` VARCHAR(190) DEFAULT NULL,
  `nationality_code` CHAR(3) DEFAULT NULL,
  `tax_identifier` VARCHAR(64) DEFAULT NULL,
  `email` VARCHAR(254) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `address_line` VARCHAR(255) DEFAULT NULL,
  `postal_code` VARCHAR(32) DEFAULT NULL,
  `city` VARCHAR(190) DEFAULT NULL,
  `region` VARCHAR(190) DEFAULT NULL,
  `country_code` CHAR(2) DEFAULT NULL,
  `language` VARCHAR(16) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `state` TINYINT NOT NULL DEFAULT 1,
  `access` INT UNSIGNED NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `modified` DATETIME DEFAULT NULL,
  `modified_by` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`), UNIQUE KEY `idx_people_uuid` (`uuid`), UNIQUE KEY `idx_people_user_id` (`user_id`), KEY `idx_people_name` (`last_name`,`first_name`), KEY `idx_people_email` (`email`), KEY `idx_people_tax_identifier` (`tax_identifier`), KEY `idx_people_birth` (`birth_date`), KEY `idx_people_state_access` (`state`,`access`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
