CREATE TABLE IF NOT EXISTS `#__xdecaropeople_backups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `storage_path` VARCHAR(1024) NOT NULL,
  `sha256` CHAR(64) NOT NULL,
  `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `people_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `component_version` VARCHAR(32) NOT NULL,
  `schema_version` VARCHAR(32) NOT NULL,
  `created` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(32) NOT NULL DEFAULT 'ready',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_people_backup_uuid` (`uuid`),
  KEY `idx_people_backup_created` (`created`),
  KEY `idx_people_backup_status_created` (`status`,`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__xdecaropeople_maintenance_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action` VARCHAR(64) NOT NULL,
  `subject_uuid` CHAR(36) DEFAULT NULL,
  `actor_user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL,
  `metadata` LONGTEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_people_maintenance_action_created` (`action`,`created`),
  KEY `idx_people_maintenance_subject_created` (`subject_uuid`,`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
