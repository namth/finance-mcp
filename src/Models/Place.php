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
                        map_url TEXT DEFAULT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                ");

                // Kiểm tra xem bảng places đã có cột map_url chưa
                $stmtPl = $this->db->query("PRAGMA table_info(places)");
                if ($stmtPl) {
                    $cols = $stmtPl->fetchAll();
                    $hasMapUrl = false;
                    foreach ($cols as $c) {
                        if (($c['name'] ?? '') === 'map_url') {
                            $hasMapUrl = true;
                            break;
                        }
                    }
                    if (!$hasMapUrl) {
                        $this->db->exec("ALTER TABLE places ADD COLUMN map_url TEXT DEFAULT NULL");
                    }
                }

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

                // Kiểm tra xem bảng transactions đã có cột place_id chưa
                $stmtTx = $this->db->query("PRAGMA table_info(transactions)");
                if ($stmtTx) {
                    $cols = $stmtTx->fetchAll();
                    $hasPlaceId = false;
                    foreach ($cols as $c) {
                        if (($c['name'] ?? '') === 'place_id') {
                            $hasPlaceId = true;
                            break;
                        }
                    }
                    if (!$hasPlaceId) {
                        $this->db->exec("ALTER TABLE transactions ADD COLUMN place_id INTEGER DEFAULT NULL");
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
                        `map_url` TEXT DEFAULT NULL,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        INDEX (`group_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");

                // Thêm cột map_url vào bảng places nếu chưa có
                $stmtMap = $this->db->query("SHOW COLUMNS FROM `places` LIKE 'map_url'");
                if (!$stmtMap->fetch()) {
                    $this->db->exec("ALTER TABLE `places` ADD COLUMN `map_url` TEXT DEFAULT NULL AFTER `address`");
                }

                // 2. Thêm cột place_id vào bảng products nếu chưa có
                $stmt = $this->db->query("SHOW COLUMNS FROM `products` LIKE 'place_id'");
                if (!$stmt->fetch()) {
                    $this->db->exec("ALTER TABLE `products` ADD COLUMN `place_id` INT UNSIGNED DEFAULT NULL AFTER `group_id`");
                    $this->db->exec("ALTER TABLE `products` ADD INDEX (`place_id`)");
                }

                // 3. Thêm cột place_id vào bảng transactions nếu chưa có
                $stmtTx = $this->db->query("SHOW COLUMNS FROM `transactions` LIKE 'place_id'");
                if (!$stmtTx->fetch()) {
                    $this->db->exec("ALTER TABLE `transactions` ADD COLUMN `place_id` INT UNSIGNED DEFAULT NULL AFTER `group_id`");
                    $this->db->exec("ALTER TABLE `transactions` ADD INDEX (`place_id`)");
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
    public function create(string $name, ?string $address = null, int $groupId = 1, ?string $mapUrl = null): array
    {
        $name = trim($name);
        if (empty($name)) {
            throw new InvalidArgumentException("Tên quán / địa điểm không được để trống.");
        }

        $address = $address ? trim($address) : null;
        $mapUrl = $mapUrl ? trim($mapUrl) : null;

        $stmt = $this->db->prepare("
            INSERT INTO `places` (`group_id`, `name`, `address`, `map_url`)
            VALUES (:group_id, :name, :address, :map_url)
        ");
        $stmt->execute([
            ':group_id' => $groupId,
            ':name'     => $name,
            ':address'  => $address,
            ':map_url'  => $mapUrl,
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

        if (array_key_exists('map_url', $data)) {
            $mapUrl = $data['map_url'] ? trim((string)$data['map_url']) : null;
            $fields[] = "`map_url` = :map_url";
            $params[':map_url'] = $mapUrl;
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

    /**
     * Tìm quán phù hợp nhất trong nhóm dựa trên ID, tên, địa chỉ hoặc từ khóa tìm kiếm
     * Thứ tự ưu tiên:
     * 1. Tìm chính xác theo place_id (nếu có)
     * 2. Khớp cả tên quán và địa chỉ quán
     * 3. Khớp địa chỉ quán ("chỉ khớp địa điểm thì cũng được")
     * 4. Khớp tên quán
     * 5. Khớp từ khóa tìm kiếm chung (trong tên hoặc địa chỉ)
     *
     * @param int|null $placeId ID quán cụ thể
     * @param string|null $name Tên quán cần tìm
     * @param string|null $address Địa chỉ / vị trí quán cần tìm
     * @param string|null $query Từ khóa tìm kiếm quán (tên hoặc địa chỉ)
     * @param int $groupId ID nhóm
     * @return array|null Trả về thông tin quán tìm được, hoặc null nếu không xác định được quán
     */
    public function resolvePlace(
        ?int $placeId = null,
        ?string $name = null,
        ?string $address = null,
        ?string $query = null,
        int $groupId = 1
    ): ?array {
        if ($placeId !== null && $placeId > 0) {
            $p = $this->find($placeId);
            if ($p && (int)$p['group_id'] === $groupId) {
                return $p;
            }
        }

        $allPlaces = $this->all($groupId);
        if (empty($allPlaces)) {
            return null;
        }

        $cleanName = ($name !== null && trim($name) !== '') ? trim($name) : null;
        $cleanAddr = ($address !== null && trim($address) !== '') ? trim($address) : null;
        $cleanQuery = ($query !== null && trim($query) !== '') ? trim($query) : null;

        // Nếu người dùng truyền query nhưng không truyền name/address cụ thể
        if ($cleanQuery !== null && $cleanName === null && $cleanAddr === null) {
            $cleanAddr = $cleanQuery;
            $cleanName = $cleanQuery;
        }

        // 1. Khớp cả tên quán và địa chỉ quán
        if ($cleanName !== null && $cleanAddr !== null && $cleanName !== $cleanAddr) {
            foreach ($allPlaces as $pl) {
                $pName = (string)($pl['name'] ?? '');
                $pAddr = (string)($pl['address'] ?? '');
                if (
                    ($pName !== '' && (mb_stripos($pName, $cleanName) !== false || mb_stripos($cleanName, $pName) !== false)) &&
                    ($pAddr !== '' && (mb_stripos($pAddr, $cleanAddr) !== false || mb_stripos($cleanAddr, $pAddr) !== false))
                ) {
                    return $pl;
                }
            }
        }

        // 2. Ưu tiên khớp theo địa chỉ quán ("ưu tiên lấy theo địa điểm của quán... nếu như là chỉ khớp địa điểm thì cũng được")
        if ($cleanAddr !== null) {
            foreach ($allPlaces as $pl) {
                $pAddr = (string)($pl['address'] ?? '');
                if ($pAddr !== '' && (mb_stripos($pAddr, $cleanAddr) !== false || mb_stripos($cleanAddr, $pAddr) !== false)) {
                    return $pl;
                }
            }
        }

        // 3. Khớp theo tên quán
        if ($cleanName !== null) {
            foreach ($allPlaces as $pl) {
                $pName = (string)($pl['name'] ?? '');
                if ($pName !== '' && (mb_stripos($pName, $cleanName) !== false || mb_stripos($cleanName, $pName) !== false)) {
                    return $pl;
                }
            }
        }

        // 4. Khớp từ khóa chung trong cả name hoặc address của quán
        if ($cleanQuery !== null) {
            foreach ($allPlaces as $pl) {
                $pName = (string)($pl['name'] ?? '');
                $pAddr = (string)($pl['address'] ?? '');
                if (
                    ($pName !== '' && (mb_stripos($pName, $cleanQuery) !== false || mb_stripos($cleanQuery, $pName) !== false)) ||
                    ($pAddr !== '' && (mb_stripos($pAddr, $cleanQuery) !== false || mb_stripos($cleanQuery, $pAddr) !== false))
                ) {
                    return $pl;
                }
            }
        }

        return null;
    }
}
