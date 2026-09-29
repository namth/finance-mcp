<?php

namespace SimpleFinance\Models;

use PDO;
use SimpleFinance\Database;
use SimpleFinance\Security\Crypto;
use InvalidArgumentException;

class User
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Tạo tài khoản người dùng mới kèm các mốc ký ức bí mật được mã hóa
     *
     * @param string $username
     * @param string $email
     * @param string $fullName
     * @param array $timelineEvents Mảng ['Tên sự kiện' => 'DD-MM-YYYY', ...]
     * @return array
     */
    public function create(string $username, string $email, string $fullName, array $timelineEvents): array
    {
        $username = strtolower(trim($username));
        $email    = strtolower(trim($email));
        $fullName = trim($fullName);

        if (empty($username) || !preg_match('/^[a-z0-9_\.]{3,30}$/', $username)) {
            throw new InvalidArgumentException("Tên đăng nhập phải từ 3-30 ký tự (chữ thường, số, dấu chấm hoặc gạch dưới).");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Địa chỉ email không hợp lệ.");
        }

        if (empty($fullName)) {
            throw new InvalidArgumentException("Họ và tên không được để trống.");
        }

        if (count($timelineEvents) < 3) {
            throw new InvalidArgumentException("Vui lòng cung cấp ít nhất 3 mốc sự kiện bí mật để phục vụ đăng nhập.");
        }

        // Kiểm tra trùng lặp
        $stmtCheck = $this->db->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
        $stmtCheck->execute([':username' => $username, ':email' => $email]);
        if ($stmtCheck->fetch()) {
            throw new InvalidArgumentException("Tên đăng nhập hoặc email đã tồn tại trong hệ thống.");
        }

        $apiKey = Crypto::generateApiKey();

        $this->db->beginTransaction();
        try {
            // 1. Tạo User
            $stmtUser = $this->db->prepare("
                INSERT INTO users (username, email, full_name, api_key)
                VALUES (:username, :email, :full_name, :api_key)
            ");
            $stmtUser->execute([
                ':username'  => $username,
                ':email'     => $email,
                ':full_name' => $fullName,
                ':api_key'   => $apiKey,
            ]);
            $userId = (int)$this->db->lastInsertId();

            // 2. Lưu các mốc sự kiện đã mã hóa
            $stmtEvent = $this->db->prepare("
                INSERT INTO user_timeline_events (user_id, event_name, event_date_encrypted)
                VALUES (:user_id, :event_name, :event_date_encrypted)
            ");

            foreach ($timelineEvents as $eventName => $rawDate) {
                $eventName = trim((string)$eventName);
                $rawDate = trim((string)$rawDate);

                if (empty($eventName) || empty($rawDate)) {
                    continue;
                }

                // Mã hóa bằng AES-256-CBC
                $encryptedDate = Crypto::encrypt($rawDate);

                $stmtEvent->execute([
                    ':user_id'              => $userId,
                    ':event_name'           => $eventName,
                    ':event_date_encrypted' => $encryptedDate,
                ]);
            }

            // 3. Tự động tạo 1 nhóm mặc định ban đầu cho user
            $groupName = "Chi Tiêu của " . $fullName;
            $stmtGroup = $this->db->prepare("
                INSERT INTO groups (name, description, owner_id)
                VALUES (:name, :desc, :owner_id)
            ");
            $stmtGroup->execute([
                ':name'     => $groupName,
                ':desc'     => 'Nhóm chi tiêu cá nhân mặc định',
                ':owner_id' => $userId,
            ]);
            $groupId = (int)$this->db->lastInsertId();

            // Thêm user vào nhóm làm owner
            $stmtMember = $this->db->prepare("
                INSERT INTO group_members (group_id, user_id, display_name, role)
                VALUES (:group_id, :user_id, :display_name, 'owner')
            ");
            $stmtMember->execute([
                ':group_id'     => $groupId,
                ':user_id'      => $userId,
                ':display_name' => $fullName,
            ]);

            $this->db->commit();
            return $this->findById($userId);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, username, email, full_name, api_key, created_at FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUsernameOrEmail(string $login): ?array
    {
        $login = strtolower(trim($login));
        $stmt = $this->db->prepare("
            SELECT `id`, `username`, `email`, `full_name`, `api_key`, `created_at` 
            FROM `users` 
            WHERE LOWER(`username`) = :login1 OR LOWER(`email`) = :login2
        ");
        $stmt->execute([':login1' => $login, ':login2' => $login]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByApiKey(string $apiKey): ?array
    {
        $apiKey = trim($apiKey);
        $stmt = $this->db->prepare("SELECT id, username, email, full_name, api_key, created_at FROM users WHERE api_key = :api_key");
        $stmt->execute([':api_key' => $apiKey]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Lấy danh sách các mốc sự kiện đã được giải mã của user
     */
    public function getDecryptedTimelineEvents(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT id, event_name, event_date_encrypted FROM user_timeline_events WHERE user_id = :uid ORDER BY id ASC");
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll();

        $events = [];
        foreach ($rows as $r) {
            $decrypted = Crypto::decrypt($r['event_date_encrypted']);
            if ($decrypted !== null) {
                $events[$r['event_name']] = $decrypted; // 'DD-MM-YYYY'
            }
        }

        return $events;
    }

    public function regenerateApiKey(int $userId): string
    {
        $newKey = Crypto::generateApiKey();
        $stmt = $this->db->prepare("UPDATE users SET api_key = :key WHERE id = :id");
        $stmt->execute([':key' => $newKey, ':id' => $userId]);
        return $newKey;
    }

    public function updateProfile(int $userId, string $fullName, string $email): bool
    {
        $fullName = trim($fullName);
        $email = strtolower(trim($email));

        $stmt = $this->db->prepare("UPDATE users SET full_name = :full_name, email = :email WHERE id = :id");
        return $stmt->execute([
            ':full_name' => $fullName,
            ':email'     => $email,
            ':id'        => $userId,
        ]);
    }
}
