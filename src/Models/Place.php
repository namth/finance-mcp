<?php

namespace SimpleFinance\Models;

use PDO;
use SimpleFinance\Database;
use InvalidArgumentException;

class Place
{
    private PDO $db;
    private static bool $tableChecked = false;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->ensureSchema();
    }

    /**
     * Tự động kiểm tra và khởi tạo bảng places cùng cột place_id nếu chưa có
     */
    public function ensureSchema(): void
    {
        if (self::$tableChecked) {
            return;
        }

        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $this->db->exec("
                    CREATE TABLE IF NOT EXISTS places (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        group_id INTEGER NOT NULL DEFAULT 1,
                        name TEXT NOT NULL,
                        address TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                ");

                // Kiểm tra xem bảng products đã có cột place_id chưa
                $stmt = $this->db->query("PRAGMA table_info(products)");
                if ($stmt) {
                    $cols = $stmt->fetchAll();
                    $hasPlaceId = false;
                    foreach ($cols as $c) {
                        if (($c['name'] ?? '') === 'place_id') {
                            $hasPlaceId = true;
                            break;
                        }
                    }
                    if (!$hasPlaceId) {
                        $this->db->exec("ALTER TABLE products ADD COLUMN place_id INTEGER DEFAULT NULL");
                    }
                }
            } else {
                // 1. Tạo bảng places nếu chưa có (MySQL)
                $this->db->exec("
                    CREATE TABLE IF NOT EXISTS `places` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `group_id` INT UNSIGNED NOT NULL DEFAULT 1,
                        `name` VARCHAR(150) NOT NULL,
                        `address` VARCHAR(255) DEFAULT NULL,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        INDEX (`group_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");

                // 2. Thêm cột place_id vào bảng products nếu chưa có
                $stmt = $this->db->query("SHOW COLUMNS FROM `products` LIKE 'place_id'");
                if (!$stmt->fetch()) {
                    $this->db->exec("ALTER TABLE `products` ADD COLUMN `place_id` INT UNSIGNED DEFAULT NULL AFTER `group_id`");
                    $this->db->exec("ALTER TABLE `products` ADD INDEX (`place_id`)");
                }
            }

            self::$tableChecked = true;
        } catch (\Throwable $e) {
            // Bỏ qua lỗi nếu không có quyền DDL hoặc đã có
        }
    }

    /**
     * Thêm mới một quán / địa điểm
     */
    public function create(string $name, ?string $address = null, int $groupId = 1): array
    {
        $name = trim($name);
        if (empty($name)) {
            throw new InvalidArgumentException("Tên quán / địa điểm không được để trống.");
        }

        $address = $address ? trim($address) : null;

        $stmt = $this->db->prepare("
            INSERT INTO `places` (`group_id`, `name`, `address`)
            VALUES (:group_id, :name, :address)
        ");
        $stmt->execute([
            ':group_id' => $groupId,
            ':name'     => $name,
            ':address'  => $address,
        ]);

        $id = (int)$this->db->lastInsertId();
        return $this->find($id);
    }

    /**
     * Lấy toàn bộ quán / địa điểm theo nhóm
     */
    public function all(?int $groupId = null): array
    {
        if ($groupId !== null) {
            $stmt = $this->db->prepare("SELECT * FROM `places` WHERE `group_id` = :gid ORDER BY `name` ASC");
            $stmt->execute([':gid' => $groupId]);
            return $stmt->fetchAll();
        }
        $stmt = $this->db->query("SELECT * FROM `places` ORDER BY `name` ASC");
        return $stmt->fetchAll();
    }

    /**
     * Tìm quán theo ID
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM `places` WHERE `id` = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Cập nhật thông tin quán
     */
    public function update(int $id, array $data): ?array
    {
        $place = $this->find($id);
        if (!$place) {
            return null;
        }

        $fields = [];
        $params = [':id' => $id];

        if (array_key_exists('name', $data)) {
            $name = trim((string)$data['name']);
            if (empty($name)) {
                throw new InvalidArgumentException("Tên quán không được để trống.");
            }
            $fields[] = "`name` = :name";
            $params[':name'] = $name;
        }

        if (array_key_exists('address', $data)) {
            $address = $data['address'] ? trim((string)$data['address']) : null;
            $fields[] = "`address` = :address";
            $params[':address'] = $address;
        }

        if (!empty($fields)) {
            $sql = "UPDATE `places` SET " . implode(', ', $fields) . " WHERE `id` = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }

        return $this->find($id);
    }

    /**
     * Xóa quán và gỡ liên kết quán khỏi các sản phẩm
     */
    public function delete(int $id): bool
    {
        // Gỡ place_id ở các products liên quan trước
        $stmtClear = $this->db->prepare("UPDATE `products` SET `place_id` = NULL WHERE `place_id` = :id");
        $stmtClear->execute([':id' => $id]);

        $stmt = $this->db->prepare("DELETE FROM `places` WHERE `id` = :id");
        return $stmt->execute([':id' => $id]);
    }
}
