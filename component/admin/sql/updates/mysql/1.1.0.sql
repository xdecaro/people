ALTER TABLE `#__xdecaropeople_people`
  ADD COLUMN `gender` CHAR(1) DEFAULT NULL AFTER `birth_date`,
  ADD COLUMN `has_disability` TINYINT DEFAULT NULL AFTER `gender`,
  ADD COLUMN `whatsapp` VARCHAR(50) DEFAULT NULL AFTER `phone`,
  ADD COLUMN `street_number` VARCHAR(32) DEFAULT NULL AFTER `address_line`,
  ADD COLUMN `instagram` VARCHAR(255) DEFAULT NULL AFTER `language`,
  ADD COLUMN `facebook` VARCHAR(255) DEFAULT NULL AFTER `instagram`,
  ADD COLUMN `linkedin` VARCHAR(255) DEFAULT NULL AFTER `facebook`,
  ADD COLUMN `tiktok` VARCHAR(255) DEFAULT NULL AFTER `linkedin`,
  ADD COLUMN `telegram` VARCHAR(255) DEFAULT NULL AFTER `tiktok`,
  ADD COLUMN `x_twitter` VARCHAR(255) DEFAULT NULL AFTER `telegram`,
  ADD COLUMN `youtube` VARCHAR(255) DEFAULT NULL AFTER `x_twitter`,
  ADD COLUMN `website` VARCHAR(255) DEFAULT NULL AFTER `youtube`;
