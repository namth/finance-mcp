-- SimpleFinance: Schema Cho Tính Năng Mua Chung (Group Buy Events)
-- Hỗ trợ sự kiện mua chung, danh mục món & phân loại/size, lượt đăng ký công khai và lưu vết thanh toán

CREATE TABLE IF NOT EXISTS `group_buy_events` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT UNSIGNED NOT NULL,
    `creator_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `image_url` TEXT DEFAULT NULL COMMENT 'Đường dẫn ảnh sản phẩm / mẫu áo / bảng size',
    `public_token` VARCHAR(64) NOT NULL UNIQUE,
    `bank_bin` VARCHAR(20) DEFAULT NULL,
    `bank_account_no` VARCHAR(50) DEFAULT NULL,
    `bank_account_name` VARCHAR(100) DEFAULT NULL,
    `deadline` DATETIME DEFAULT NULL,
    `status` ENUM('open', 'closed', 'converted') NOT NULL DEFAULT 'open',
    `transaction_id` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_gb_group` (`group_id`),
    INDEX `idx_gb_creator` (`creator_id`),
    INDEX `idx_gb_token` (`public_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_buy_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `option_name` VARCHAR(100) NOT NULL COMMENT 'Ví dụ: Size S, Size M, Màu Đen, Phở Bò Tái',
    `price` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_gbi_event` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_buy_registrations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT UNSIGNED NOT NULL,
    `participant_name` VARCHAR(100) NOT NULL,
    `participant_phone` VARCHAR(20) DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `total_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `is_notified_paid` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1: Người dùng đã ấn nút Tôi đã chuyển khoản',
    `is_paid` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1: Admin đã tick xác nhận nhận được tiền',
    `paid_at` DATETIME DEFAULT NULL,
    `reg_token` VARCHAR(64) NOT NULL UNIQUE,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_gbr_event` (`event_id`),
    INDEX `idx_gbr_token` (`reg_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_buy_registration_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `registration_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `subtotal` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    INDEX `idx_gbri_reg` (`registration_id`),
    INDEX `idx_gbri_item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
