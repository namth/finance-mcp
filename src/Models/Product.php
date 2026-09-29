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
    }

    public function create(string $name, ?float $defaultPrice = null, ?string $description = null, int $groupId = 1): array
    {
        $name = trim($name);
        if (empty($name)) {
            throw new InvalidArgumentException("Tên sản phẩm/dịch vụ không được để trống.");
        }

        if ($defaultPrice !== null && $defaultPrice < 0) {
            throw new InvalidArgumentException("Giá sản phẩm không được là số âm.");
        }

        $stmt = $this->db->prepare("INSERT INTO `products` (`group_id`, `name`, `default_price`, `description`) VALUES (:group_id, :name, :default_price, :description)");
        $stmt->execute([
            ':group_id'      => $groupId,
            ':name'          => $name,
            ':default_price' => $defaultPrice !== null ? round($defaultPrice, 2) : null,
            ':description'   => $description ? trim($description) : null,
        ]);

        $id = (int)$this->db->lastInsertId();
        return $this->find($id);
    }

    public function all(?int $groupId = null): array
    {
        if ($groupId !== null) {
            $stmt = $this->db->prepare("SELECT * FROM `products` WHERE `group_id` = :gid ORDER BY `id` ASC");
            $stmt->execute([':gid' => $groupId]);
            return $stmt->fetchAll();
        }
        $stmt = $this->db->query("SELECT * FROM `products` ORDER BY `id` ASC");
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM `products` WHERE `id` = :id");
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
}
