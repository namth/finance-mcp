ALTER TABLE `group_buy_registrations` ADD COLUMN IF NOT EXISTS `is_delivered` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `group_buy_registrations` ADD COLUMN IF NOT EXISTS `delivered_at` DATETIME DEFAULT NULL;
