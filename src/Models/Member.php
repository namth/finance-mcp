<?php

namespace SimpleFinance\Models;

use PDO;
use SimpleFinance\Database;
use InvalidArgumentException;

class Member
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function create(string $name, ?string $phone = null, ?string $email = null): array
    {
        $name = trim($name);
        if (empty($name)) {
            throw new InvalidArgumentException("Tên thành viên không được để trống.");
        }

        $stmt = $this->db->prepare("INSERT INTO `members` (`name`, `phone`, `email`) VALUES (:name, :phone, :email)");
        $stmt->execute([
            ':name'  => $name,
            ':phone' => $phone ? trim($phone) : null,
            ':email' => $email ? trim($email) : null,
        ]);

        $id = (int)$this->db->lastInsertId();
        return $this->find($id);
    }

    public function all(): array
    {
        $stmt = $this->db->query("SELECT * FROM `members` ORDER BY `id` ASC");
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM `members` WHERE `id` = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $id, array $data): ?array
    {
        $member = $this->find($id);
        if (!$member) {
            return null;
        }

        $fields = [];
        $params = [':id' => $id];

        if (array_key_exists('name', $data)) {
            $name = trim((string)$data['name']);
            if (empty($name)) {
                throw new InvalidArgumentException("Tên thành viên không được để trống.");
            }
            $fields[] = "`name` = :name";
            $params[':name'] = $name;
        }

        if (array_key_exists('phone', $data)) {
            $fields[] = "`phone` = :phone";
            $params[':phone'] = $data['phone'] ? trim((string)$data['phone']) : null;
        }

        if (array_key_exists('email', $data)) {
            $fields[] = "`email` = :email";
            $params[':email'] = $data['email'] ? trim((string)$data['email']) : null;
        }

        if (!empty($fields)) {
            $sql = "UPDATE `members` SET " . implode(', ', $fields) . " WHERE `id` = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM `members` WHERE `id` = :id");
        return $stmt->execute([':id' => $id]);
    }
}
