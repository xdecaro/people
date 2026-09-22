CREATE TABLE IF NOT EXISTS `#__xdecaropeople_duplicate_ignores` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `signature` CHAR(64) NOT NULL,
  `match_type` VARCHAR(32) NOT NULL,
  `record_ids` TEXT NOT NULL,
  `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_people_duplicate_ignore_signature` (`signature`),
  KEY `idx_people_duplicate_ignore_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__xdecaropeople_merges` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_person_id` INT UNSIGNED NOT NULL,
  `source_uuid` CHAR(36) NOT NULL,
  `target_person_id` INT UNSIGNED NOT NULL,
  `target_uuid` CHAR(36) NOT NULL,
  `copied_fields` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_people_merge_source_person` (`source_person_id`),
  UNIQUE KEY `idx_people_merge_source_uuid` (`source_uuid`),
  KEY `idx_people_merge_target_person` (`target_person_id`),
  KEY `idx_people_merge_target_uuid` (`target_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
