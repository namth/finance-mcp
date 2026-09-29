<?php

namespace SimpleFinance\Models;

use PDO;
use SimpleFinance\Database;
use InvalidArgumentException;

class Group
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function create(string $name, int $ownerId, ?string $description = null): array
    {
        $name = trim($name);
        if (empty($name)) {
            throw new InvalidArgumentException("Tên nhóm không được để trống.");
        }

        $userModel = new User($this->db);
        $owner = $userModel->findById($ownerId);
        if (!$owner) {
            throw new InvalidArgumentException("Người tạo nhóm không tồn tại.");
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO `groups` (`name`, `description`, `owner_id`)
                VALUES (:name, :description, :owner_id)
            ");
            $stmt->execute([
                ':name'        => $name,
                ':description' => $description ? trim($description) : null,
                ':owner_id'    => $ownerId,
            ]);
            $groupId = (int)$this->db->lastInsertId();

            // Thêm owner vào bảng group_members
            $stmtMember = $this->db->prepare("
                INSERT INTO `group_members` (`group_id`, `user_id`, `display_name`, `role`)
                VALUES (:group_id, :user_id, :display_name, 'owner')
            ");
            $stmtMember->execute([
                ':group_id'     => $groupId,
                ':user_id'      => $ownerId,
                ':display_name' => $owner['full_name'],
            ]);

            $this->db->commit();
            return $this->find($groupId);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function find(int $groupId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT g.*, u.full_name AS owner_name, u.username AS owner_username
            FROM `groups` g
            JOIN `users` u ON g.owner_id = u.id
            WHERE g.id = :id
        ");
        $stmt->execute([':id' => $groupId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getUserGroups(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT g.*, gm.display_name, gm.role, gm.joined_at,
                   (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS total_members
            FROM `groups` g
            JOIN `group_members` gm ON g.id = gm.group_id
            WHERE gm.user_id = :uid
            ORDER BY g.id DESC
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getMembers(int $groupId): array
    {
        $stmt = $this->db->prepare("
            SELECT gm.*, u.username, u.email, u.full_name
            FROM `group_members` gm
            JOIN `users` u ON gm.user_id = u.id
            WHERE gm.group_id = :gid
            ORDER BY gm.id ASC
        ");
        $stmt->execute([':gid' => $groupId]);
        return $stmt->fetchAll();
    }

    public function addMember(int $groupId, int $userId, ?string $displayName = null, string $role = 'member'): bool
    {
        $userModel = new User($this->db);
        $user = $userModel->findById($userId);
        if (!$user) {
            throw new InvalidArgumentException("Người dùng không tồn tại.");
        }

        $displayName = $displayName ? trim($displayName) : $user['full_name'];

        $stmtCheck = $this->db->prepare("SELECT id FROM `group_members` WHERE `group_id` = :gid AND `user_id` = :uid");
        $stmtCheck->execute([':gid' => $groupId, ':uid' => $userId]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            $stmt = $this->db->prepare("UPDATE `group_members` SET `display_name` = :name, `role` = :role WHERE id = :id");
            return $stmt->execute([':name' => $displayName, ':role' => $role, ':id' => $existing['id']]);
        } else {
            $stmt = $this->db->prepare("INSERT INTO `group_members` (`group_id`, `user_id`, `display_name`, `role`) VALUES (:gid, :uid, :name, :role)");
            return $stmt->execute([':gid' => $groupId, ':uid' => $userId, ':name' => $displayName, ':role' => $role]);
        }
    }

    public function addMemberByUsernameOrEmail(int $groupId, string $usernameOrEmail, ?string $displayName = null, string $role = 'member'): bool
    {
        $userModel = new User($this->db);
        $user = $userModel->findByUsernameOrEmail($usernameOrEmail);
        if (!$user) {
            throw new InvalidArgumentException("Không tìm thấy người dùng với username hoặc email: {$usernameOrEmail}");
        }
        return $this->addMember($groupId, (int)$user['id'], $displayName ?: $user['full_name'], $role);
    }

    public function removeMember(int $groupId, int $userId): bool
    {
        $group = $this->find($groupId);
        if ($group && (int)$group['owner_id'] === $userId) {
            throw new InvalidArgumentException("Không thể xóa người sáng lập nhóm ra khỏi nhóm.");
        }

        $stmt = $this->db->prepare("DELETE FROM `group_members` WHERE `group_id` = :gid AND `user_id` = :uid");
        return $stmt->execute([':gid' => $groupId, ':uid' => $userId]);
    }

    public function userHasAccess(int $groupId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT id FROM `group_members`
            WHERE `group_id` = :gid AND `user_id` = :uid
        ");
        $stmt->execute([':gid' => $groupId, ':uid' => $userId]);
        return (bool)$stmt->fetch();
    }
}
