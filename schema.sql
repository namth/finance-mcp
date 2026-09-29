-- Cơ sở dữ liệu Quản lý chi tiêu & Công nợ (SimpleFinance MCP)
-- Tương thích MySQL 8.0+ / MariaDB 10.4+

CREATE TABLE IF NOT EXISTS `members` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `default_price` DECIMAL(15, 2) DEFAULT NULL COMMENT 'Giá mặc định nếu có, để NULL nếu giá thay đổi tùy lần dùng',
    `description` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL COMMENT 'Tiêu đề đợt chi tiêu / hóa đơn (ví dụ: Ăn trưa ngày 20/09)',
    `payer_id` INT UNSIGNED NOT NULL COMMENT 'Người đứng ra thanh toán toàn bộ hóa đơn',
    `total_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('draft', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    `note` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`payer_id`) REFERENCES `members`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transaction_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `price` DECIMAL(15, 2) NOT NULL COMMENT 'Giá tiền thực tế áp dụng cho lần sử dụng này',
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `subtotal` DECIMAL(15, 2) GENERATED ALWAYS AS (`price` * `quantity`) STORED,
    `note` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transaction_item_members` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT UNSIGNED NOT NULL,
    `member_id` INT UNSIGNED NOT NULL,
    `share_amount` DECIMAL(15, 2) NOT NULL COMMENT 'Số tiền thành viên này cần đóng góp cho món đồ này',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`item_id`) REFERENCES `transaction_items`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `debts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `debtor_id` INT UNSIGNED NOT NULL COMMENT 'Người nợ',
    `creditor_id` INT UNSIGNED NOT NULL COMMENT 'Chủ nợ (người được nợ)',
    `amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Số tiền còn nợ',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_debtor_creditor` (`debtor_id`, `creditor_id`),
    FOREIGN KEY (`debtor_id`) REFERENCES `members`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`creditor_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `debt_settlements` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `debtor_id` INT UNSIGNED NOT NULL COMMENT 'Người trả nợ',
    `creditor_id` INT UNSIGNED NOT NULL COMMENT 'Người nhận tiền thanh toán',
    `amount` DECIMAL(15, 2) NOT NULL COMMENT 'Số tiền đã trả/gạch nợ',
    `note` VARCHAR(255) DEFAULT NULL,
    `settled_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`debtor_id`) REFERENCES `members`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creditor_id`) REFERENCES `members`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
