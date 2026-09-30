<?php

namespace SimpleFinance\Models;

use PDO;
use SimpleFinance\Database;
use InvalidArgumentException;

class Product
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
        // Đảm bảo schema của places và cột place_id đã sẵn sàng
        (new Place($this->db))->ensureSchema();
    }

    public function create(
        string $name,
        ?float $defaultPrice = null,
        ?string $description = null,
        int $groupId = 1,
        ?int $placeId = null
    ): array {
        $name = trim($name);
        if (empty($name)) {
            throw new InvalidArgumentException("Tên sản phẩm/dịch vụ không được để trống.");
        }

        if ($defaultPrice !== null && $defaultPrice < 0) {
            throw new InvalidArgumentException("Giá sản phẩm không được là số âm.");
        }

        $placeId = ($placeId !== null && $placeId > 0) ? $placeId : null;

        $stmt = $this->db->prepare("
            INSERT INTO `products` (`group_id`, `place_id`, `name`, `default_price`, `description`)
            VALUES (:group_id, :place_id, :name, :default_price, :description)
        ");
        $stmt->execute([
            ':group_id'      => $groupId,
            ':place_id'      => $placeId,
            ':name'          => $name,
            ':default_price' => $defaultPrice !== null ? round($defaultPrice, 2) : null,
            ':description'   => $description ? trim($description) : null,
        ]);

        $id = (int)$this->db->lastInsertId();
        return $this->find($id);
    }

    public function all(?int $groupId = null, ?int $placeId = null, ?string $search = null): array
    {
        $sql = "
            SELECT p.*, pl.name AS place_name, pl.address AS place_address
            FROM `products` p
            LEFT JOIN `places` pl ON p.place_id = pl.id
        ";
        $conditions = [];
        $params = [];

        if ($groupId !== null) {
            $conditions[] = "p.group_id = :gid";
            $params[':gid'] = $groupId;
        }

        if ($placeId !== null) {
            $conditions[] = "p.place_id = :place_id";
            $params[':place_id'] = $placeId;
        }

        if ($search !== null && trim($search) !== '') {
            $conditions[] = "(LOWER(p.name) LIKE :search OR LOWER(COALESCE(p.description, '')) LIKE :search)";
            $params[':search'] = '%' . strtolower(trim($search)) . '%';
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY p.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, pl.name AS place_name, pl.address AS place_address
            FROM `products` p
            LEFT JOIN `places` pl ON p.place_id = pl.id
            WHERE p.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $id, array $data): ?array
    {
        $product = $this->find($id);
        if (!$product) {
            return null;
        }

        $fields = [];
        $params = [':id' => $id];

        if (array_key_exists('name', $data)) {
            $name = trim((string)$data['name']);
            if (empty($name)) {
                throw new InvalidArgumentException("Tên sản phẩm/dịch vụ không được để trống.");
            }
            $fields[] = "`name` = :name";
            $params[':name'] = $name;
        }

        if (array_key_exists('place_id', $data)) {
            $placeId = $data['place_id'];
            $fields[] = "`place_id` = :place_id";
            $params[':place_id'] = ($placeId !== null && (int)$placeId > 0) ? (int)$placeId : null;
        }

        if (array_key_exists('default_price', $data)) {
            $price = $data['default_price'];
            if ($price !== null) {
                $price = (float)$price;
                if ($price < 0) {
                    throw new InvalidArgumentException("Giá sản phẩm không được là số âm.");
                }
                $params[':default_price'] = round($price, 2);
            } else {
                $params[':default_price'] = null;
            }
            $fields[] = "`default_price` = :default_price";
        }

        if (array_key_exists('description', $data)) {
            $fields[] = "`description` = :description";
            $params[':description'] = $data['description'] ? trim((string)$data['description']) : null;
        }

        if (!empty($fields)) {
            $sql = "UPDATE `products` SET " . implode(', ', $fields) . " WHERE `id` = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM `products` WHERE `id` = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Tìm hoặc tự động tạo sản phẩm dựa trên tên sản phẩm, địa điểm / quán và giá.
     * Thứ tự ưu tiên:
     * 1. Khớp tên sản phẩm + Khớp tên quán + Khớp địa chỉ quán
     * 2. Khớp tên sản phẩm + Khớp địa chỉ quán ("chỉ khớp địa điểm thì cũng được")
     * 3. Khớp tên sản phẩm + Khớp tên quán
     * 4. Khớp tên sản phẩm trong nhóm
     * 5. Nếu chưa có trong hệ thống -> Tự động tạo quán (nếu có thông tin) và tự động tạo sản phẩm mới
     */
    public function resolveProduct(
        string|int $productIdentifier,
        ?string $placeName = null,
        ?string $placeAddress = null,
        ?float $price = null,
        int $groupId = 1
    ): array {
        if (is_numeric($productIdentifier) && (int)$productIdentifier > 0) {
            $existing = $this->find((int)$productIdentifier);
            if ($existing && (int)$existing['group_id'] === $groupId) {
                if ($price !== null && $price >= 0 && $existing['default_price'] === null) {
                    $this->update((int)$existing['id'], ['default_price' => $price]);
                    $existing['default_price'] = round($price, 2);
                }
                return $existing;
            }
        }

        $productName = trim((string)$productIdentifier);
        if (empty($productName)) {
            throw new InvalidArgumentException("Tên sản phẩm/dịch vụ không được để trống.");
        }

        $placeName = $placeName !== null ? trim($placeName) : null;
        $placeAddress = $placeAddress !== null ? trim($placeAddress) : null;

        // Lấy tất cả sản phẩm trong nhóm có tên trùng khớp (không phân biệt hoa thường)
        $stmt = $this->db->prepare("
            SELECT p.*, pl.name AS place_name, pl.address AS place_address
            FROM `products` p
            LEFT JOIN `places` pl ON p.place_id = pl.id
            WHERE p.group_id = :gid AND LOWER(TRIM(p.name)) = LOWER(TRIM(:pname))
        ");
        $stmt->execute([':gid' => $groupId, ':pname' => $productName]);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $matchedProduct = null;

        if (!empty($candidates)) {
            // 1. Khớp cả tên quán và địa chỉ quán
            if (!empty($placeName) && !empty($placeAddress)) {
                foreach ($candidates as $cand) {
                    $candPlName = (string)($cand['place_name'] ?? '');
                    $candPlAddr = (string)($cand['place_address'] ?? '');
                    $matchName = ($candPlName !== '' && (mb_stripos($candPlName, $placeName) !== false || mb_stripos($placeName, $candPlName) !== false));
                    $matchAddr = ($candPlAddr !== '' && (mb_stripos($candPlAddr, $placeAddress) !== false || mb_stripos($placeAddress, $candPlAddr) !== false));
                    if ($matchName && $matchAddr) {
                        $matchedProduct = $cand;
                        break;
                    }
                }
            }

            // 2. Khớp địa chỉ quán ("còn nếu như là chỉ khớp địa điểm thì cũng được")
            if (!$matchedProduct && !empty($placeAddress)) {
                foreach ($candidates as $cand) {
                    $candPlAddr = (string)($cand['place_address'] ?? '');
                    if ($candPlAddr !== '' && (mb_stripos($candPlAddr, $placeAddress) !== false || mb_stripos($placeAddress, $candPlAddr) !== false)) {
                        $matchedProduct = $cand;
                        break;
                    }
                }
            }

            // 3. Khớp tên quán
            if (!$matchedProduct && !empty($placeName)) {
                foreach ($candidates as $cand) {
                    $candPlName = (string)($cand['place_name'] ?? '');
                    if ($candPlName !== '' && (mb_stripos($candPlName, $placeName) !== false || mb_stripos($placeName, $candPlName) !== false)) {
                        $matchedProduct = $cand;
                        break;
                    }
                }
            }

            // 4. Nếu không khớp quán cụ thể nhưng có sản phẩm trùng tên
            if (!$matchedProduct) {
                $matchedProduct = $candidates[0];
            }
        }

        if ($matchedProduct) {
            // Nếu sản phẩm chưa có giá mặc định mà lần này có truyền giá, cập nhật luôn giá mặc định cho sản phẩm
            if ($price !== null && $price >= 0 && $matchedProduct['default_price'] === null) {
                $this->update((int)$matchedProduct['id'], ['default_price' => $price]);
                $matchedProduct['default_price'] = round($price, 2);
            }
            return $matchedProduct;
        }

        // 5. Nếu chưa có sản phẩm trong hệ thống -> Tự động thêm mới
        // Xác định hoặc tạo place nếu có thông tin quán/địa điểm
        $placeId = null;
        if (!empty($placeName) || !empty($placeAddress)) {
            $placeModel = new Place($this->db);
            $allPlaces = $placeModel->all($groupId);

            $matchedPlace = null;
            if (!empty($placeName) && !empty($placeAddress)) {
                foreach ($allPlaces as $pl) {
                    $pName = (string)($pl['name'] ?? '');
                    $pAddr = (string)($pl['address'] ?? '');
                    if (
                        ($pName !== '' && (mb_stripos($pName, $placeName) !== false || mb_stripos($placeName, $pName) !== false)) &&
                        ($pAddr !== '' && (mb_stripos($pAddr, $placeAddress) !== false || mb_stripos($placeAddress, $pAddr) !== false))
                    ) {
                        $matchedPlace = $pl;
                        break;
                    }
                }
            }

            if (!$matchedPlace && !empty($placeAddress)) {
                foreach ($allPlaces as $pl) {
                    $pAddr = (string)($pl['address'] ?? '');
                    if ($pAddr !== '' && (mb_stripos($pAddr, $placeAddress) !== false || mb_stripos($placeAddress, $pAddr) !== false)) {
                        $matchedPlace = $pl;
                        break;
                    }
                }
            }

            if (!$matchedPlace && !empty($placeName)) {
                foreach ($allPlaces as $pl) {
                    $pName = (string)($pl['name'] ?? '');
                    if ($pName !== '' && (mb_stripos($pName, $placeName) !== false || mb_stripos($placeName, $pName) !== false)) {
                        $matchedPlace = $pl;
                        break;
                    }
                }
            }

            if ($matchedPlace) {
                $placeId = (int)$matchedPlace['id'];
            } else {
                $newPlName = !empty($placeName) ? $placeName : (!empty($placeAddress) ? $placeAddress : 'Quán mới');
                $newPlace = $placeModel->create($newPlName, $placeAddress, $groupId);
                $placeId = (int)$newPlace['id'];
            }
        }

        // Tạo sản phẩm mới
        return $this->create($productName, $price, null, $groupId, $placeId);
    }
}
