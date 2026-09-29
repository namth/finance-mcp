<?php

namespace SimpleFinance\Models;

use PDO;
use SimpleFinance\Database;
use SimpleFinance\Security\Crypto;
use InvalidArgumentException;

class User
{
    private PDO $db;
    private static bool $schemaChecked = false;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->ensureBankSchema();
    }

    /**
     * Tự động kiểm tra và thêm các cột ngân hàng, liên kết member, debt_token nếu chưa có
     */
    public function ensureBankSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }

        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                // 1. Kiểm tra users
                $userCols = $this->db->query("PRAGMA table_info(users)")->fetchAll();
                $existingUserCols = array_column($userCols, 'name');

                if (!in_array('bank_bin', $existingUserCols)) {
                    $this->db->exec("ALTER TABLE users ADD COLUMN bank_bin TEXT DEFAULT NULL");
                }
                if (!in_array('bank_name', $existingUserCols)) {
                    $this->db->exec("ALTER TABLE users ADD COLUMN bank_name TEXT DEFAULT NULL");
                }
                if (!in_array('bank_account_no', $existingUserCols)) {
                    $this->db->exec("ALTER TABLE users ADD COLUMN bank_account_no TEXT DEFAULT NULL");
                }
                if (!in_array('bank_account_name', $existingUserCols)) {
                    $this->db->exec("ALTER TABLE users ADD COLUMN bank_account_name TEXT DEFAULT NULL");
                }

                // 2. Kiểm tra members
                $memberStmt = $this->db->query("PRAGMA table_info(members)");
                if ($memberStmt) {
                    $memCols = array_column($memberStmt->fetchAll(), 'name');
                    if (!in_array('user_id', $memCols)) {
                        $this->db->exec("ALTER TABLE members ADD COLUMN user_id INTEGER DEFAULT NULL");
                    }
                }

                // 3. Kiểm tra debts
                $debtStmt = $this->db->query("PRAGMA table_info(debts)");
                if ($debtStmt) {
                    $debtCols = array_column($debtStmt->fetchAll(), 'name');
                    if (!in_array('debt_token', $debtCols)) {
                        $this->db->exec("ALTER TABLE debts ADD COLUMN debt_token TEXT DEFAULT NULL");
                    }
                    if (!in_array('payment_notified_at', $debtCols)) {
                        $this->db->exec("ALTER TABLE debts ADD COLUMN payment_notified_at DATETIME DEFAULT NULL");
                    }
                }
            } else {
                // MySQL / MariaDB
                // 1. Cột ngân hàng trong users
                $stmt = $this->db->query("SHOW COLUMNS FROM `users` LIKE 'bank_bin'");
                if (!$stmt->fetch()) {
                    $this->db->exec("
                        ALTER TABLE `users` 
                        ADD COLUMN `bank_bin` VARCHAR(20) DEFAULT NULL AFTER `api_key`,
                        ADD COLUMN `bank_name` VARCHAR(100) DEFAULT NULL AFTER `bank_bin`,
                        ADD COLUMN `bank_account_no` VARCHAR(50) DEFAULT NULL AFTER `bank_name`,
                        ADD COLUMN `bank_account_name` VARCHAR(100) DEFAULT NULL AFTER `bank_account_no`
                    ");
                }

                // 2. Cột user_id trong members
                $stmtMem = $this->db->query("SHOW COLUMNS FROM `members` LIKE 'user_id'");
                if (!$stmtMem->fetch()) {
                    $this->db->exec("ALTER TABLE `members` ADD COLUMN `user_id` INT UNSIGNED DEFAULT NULL AFTER `id`, ADD INDEX (`user_id`)");
                }

                // 3. Cột debt_token và payment_notified_at trong debts
                $stmtDebt = $this->db->query("SHOW COLUMNS FROM `debts` LIKE 'debt_token'");
                if (!$stmtDebt->fetch()) {
                    $this->db->exec("
                        ALTER TABLE `debts` 
                        ADD COLUMN `debt_token` VARCHAR(64) DEFAULT NULL AFTER `amount`,
                        ADD COLUMN `payment_notified_at` DATETIME DEFAULT NULL AFTER `debt_token`,
                        ADD INDEX (`debt_token`)
                    ");
                }
            }

            self::$schemaChecked = true;
        } catch (\Throwable $e) {
            // Bỏ qua lỗi DDL
        }
    }

    /**
     * Tạo tài khoản người dùng mới kèm các mốc ký ức bí mật được mã hóa
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

            // 4. Tự động liên kết hoặc tạo bản ghi trong bảng members
            try {
                $stmtFindMem = $this->db->prepare("SELECT id FROM members WHERE LOWER(name) = :name OR LOWER(email) = :email LIMIT 1");
                $stmtFindMem->execute([':name' => strtolower($fullName), ':email' => $email]);
                $memRow = $stmtFindMem->fetch();
                if ($memRow) {
                    $this->db->prepare("UPDATE members SET user_id = :uid WHERE id = :id")->execute([':uid' => $userId, ':id' => $memRow['id']]);
                } else {
                    $this->db->prepare("INSERT INTO members (user_id, name, email) VALUES (:uid, :name, :email)")->execute([
                        ':uid'   => $userId,
                        ':name'  => $fullName,
                        ':email' => $email,
                    ]);
                }
            } catch (\Throwable $e) {}

            $this->db->commit();
            return $this->findById($userId);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, username, email, full_name, api_key, 
                   bank_bin, bank_name, bank_account_no, bank_account_name, created_at 
            FROM users 
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUsernameOrEmail(string $login): ?array
    {
        $login = strtolower(trim($login));
        $stmt = $this->db->prepare("
            SELECT `id`, `username`, `email`, `full_name`, `api_key`,
                   `bank_bin`, `bank_name`, `bank_account_no`, `bank_account_name`, `created_at` 
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
        $stmt = $this->db->prepare("
            SELECT id, username, email, full_name, api_key, 
                   bank_bin, bank_name, bank_account_no, bank_account_name, created_at 
            FROM users 
            WHERE api_key = :api_key
        ");
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
        $res = $stmt->execute([
            ':full_name' => $fullName,
            ':email'     => $email,
            ':id'        => $userId,
        ]);

        // Cập nhật hoặc liên kết thành viên
        try {
            $this->db->prepare("UPDATE members SET user_id = :uid WHERE (LOWER(email) = :email OR LOWER(name) = :name) AND user_id IS NULL")
                     ->execute([':uid' => $userId, ':email' => $email, ':name' => strtolower($fullName)]);
        } catch (\Throwable $e) {}

        return $res;
    }

    /**
     * Cập nhật thông tin tài khoản ngân hàng của người dùng (VietQR)
     */
    public function updateBankInfo(
        int $userId,
        ?string $bankBin,
        ?string $bankName,
        ?string $accountNo,
        ?string $accountName
    ): bool {
        $bankBin = $bankBin ? trim($bankBin) : null;
        $bankName = $bankName ? trim($bankName) : null;
        $accountNo = $accountNo ? preg_replace('/[^0-9a-zA-Z]/', '', trim($accountNo)) : null;
        $accountName = $accountName ? mb_strtoupper(trim($accountName), 'UTF-8') : null;

        $stmt = $this->db->prepare("
            UPDATE users 
            SET bank_bin = :bin, bank_name = :name, bank_account_no = :acc, bank_account_name = :acc_name 
            WHERE id = :id
        ");
        $res = $stmt->execute([
            ':bin'      => $bankBin,
            ':name'     => $bankName,
            ':acc'      => $accountNo,
            ':acc_name' => $accountName,
            ':id'       => $userId,
        ]);

        // Đảm bảo liên kết thành viên tương ứng
        $user = $this->findById($userId);
        if ($user) {
            try {
                $this->db->prepare("
                    UPDATE members SET user_id = :uid 
                    WHERE (LOWER(email) = :email OR LOWER(name) = :name) AND (user_id IS NULL OR user_id = :uid)
                ")->execute([
                    ':uid'   => $userId,
                    ':email' => strtolower($user['email']),
                    ':name'  => strtolower($user['full_name']),
                ]);
            } catch (\Throwable $e) {}
        }

        return $res;
    }
}
