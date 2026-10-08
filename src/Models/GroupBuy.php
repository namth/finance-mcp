<?php

namespace SimpleFinance\Models;

use PDO;
use SimpleFinance\Database;
use InvalidArgumentException;

class GroupBuy
{
    private PDO $db;
    private static bool $schemaEnsured = false;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->ensureSchema();
    }

    /**
     * Tự động kiểm tra và khởi tạo các bảng CSDL nếu chưa có (Hỗ trợ cả MySQL & SQLite)
     */
    public function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $this->db->exec("
                    CREATE TABLE IF NOT EXISTS group_buy_events (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        group_id INTEGER NOT NULL,
                        creator_id INTEGER NOT NULL,
                        title TEXT NOT NULL,
                        description TEXT DEFAULT NULL,
                        image_url TEXT DEFAULT NULL,
                        public_token TEXT NOT NULL UNIQUE,
                        bank_bin TEXT DEFAULT NULL,
                        bank_account_no TEXT DEFAULT NULL,
                        bank_account_name TEXT DEFAULT NULL,
                        deadline TEXT DEFAULT NULL,
                        status TEXT NOT NULL DEFAULT 'open',
                        transaction_id INTEGER DEFAULT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                    CREATE TABLE IF NOT EXISTS group_buy_items (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        event_id INTEGER NOT NULL,
                        name TEXT NOT NULL,
                        option_name TEXT NOT NULL,
                        price REAL NOT NULL DEFAULT 0.0,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (event_id) REFERENCES group_buy_events(id) ON DELETE CASCADE
                    );
                    CREATE TABLE IF NOT EXISTS group_buy_registrations (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        event_id INTEGER NOT NULL,
                        participant_name TEXT NOT NULL,
                        participant_phone TEXT DEFAULT NULL,
                        note TEXT DEFAULT NULL,
                        total_amount REAL NOT NULL DEFAULT 0.0,
                        is_notified_paid INTEGER NOT NULL DEFAULT 0,
                        is_paid INTEGER NOT NULL DEFAULT 0,
                        paid_at DATETIME DEFAULT NULL,
                        reg_token TEXT NOT NULL UNIQUE,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (event_id) REFERENCES group_buy_events(id) ON DELETE CASCADE
                    );
                    CREATE TABLE IF NOT EXISTS group_buy_registration_items (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        registration_id INTEGER NOT NULL,
                        item_id INTEGER NOT NULL,
                        quantity INTEGER NOT NULL DEFAULT 1,
                        unit_price REAL NOT NULL DEFAULT 0.0,
                        subtotal REAL NOT NULL DEFAULT 0.0,
                        FOREIGN KEY (registration_id) REFERENCES group_buy_registrations(id) ON DELETE CASCADE,
                        FOREIGN KEY (item_id) REFERENCES group_buy_items(id) ON DELETE CASCADE
                    );
                ");
            } else {
                // MySQL
                $this->db->exec("
                    CREATE TABLE IF NOT EXISTS `group_buy_events` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `group_id` INT UNSIGNED NOT NULL,
                        `creator_id` INT UNSIGNED NOT NULL,
                        `title` VARCHAR(255) NOT NULL,
                        `description` TEXT DEFAULT NULL,
                        `image_url` TEXT DEFAULT NULL,
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
                        INDEX `idx_gb_token` (`public_token`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                    CREATE TABLE IF NOT EXISTS `group_buy_items` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `event_id` INT UNSIGNED NOT NULL,
                        `name` VARCHAR(255) NOT NULL,
                        `option_name` VARCHAR(100) NOT NULL,
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
                        `is_notified_paid` TINYINT(1) NOT NULL DEFAULT 0,
                        `is_paid` TINYINT(1) NOT NULL DEFAULT 0,
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
                        INDEX `idx_gbri_reg` (`registration_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }
            self::$schemaEnsured = true;
        } catch (\Throwable $e) {
            // Log hoặc bỏ qua nếu đã tồn tại
        }
    }

    /**
     * Tạo một sự kiện mua chung mới
     */
    public function createEvent(
        int $groupId,
        int $creatorId,
        string $title,
        ?string $description = null,
        ?string $imageUrl = null,
        ?string $deadline = null,
        array $items = [],
        ?string $bankBin = null,
        ?string $bankAccountNo = null,
        ?string $bankAccountName = null
    ): array {
        $title = trim($title);
        if (empty($title)) {
            throw new InvalidArgumentException("Tiêu đề sự kiện không được để trống.");
        }

        // Tự động lấy STK của creator nếu không truyền vào
        if (empty($bankAccountNo) || empty($bankBin)) {
            $userModel = new User($this->db);
            $user = $userModel->findById($creatorId);
            if ($user) {
                $bankBin = $bankBin ?: ($user['bank_bin'] ?? null);
                $bankAccountNo = $bankAccountNo ?: ($user['bank_account_no'] ?? null);
                $bankAccountName = $bankAccountName ?: ($user['bank_account_name'] ?? $user['full_name']);
            }
        }

        $publicToken = bin2hex(random_bytes(16));

        $this->db->beginTransaction();
        try {
            // Format datetime theo driver
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $nowSql = $driver === 'sqlite' ? "datetime('now')" : "NOW()";
            
            $sql = "
                INSERT INTO group_buy_events (
                    group_id, creator_id, title, description, image_url, public_token,
                    bank_bin, bank_account_no, bank_account_name, deadline, status, created_at
                ) VALUES (
                    :group_id, :creator_id, :title, :description, :image_url, :public_token,
                    :bank_bin, :bank_account_no, :bank_account_name, :deadline, 'open', {$nowSql}
                )
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':group_id'          => $groupId,
                ':creator_id'        => $creatorId,
                ':title'             => $title,
                ':description'       => $description ?: null,
                ':image_url'         => $imageUrl ?: null,
                ':public_token'      => $publicToken,
                ':bank_bin'          => $bankBin ?: null,
                ':bank_account_no'   => $bankAccountNo ?: null,
                ':bank_account_name' => $bankAccountName ?: null,
                ':deadline'          => $deadline ?: null,
            ]);

            $eventId = (int)$this->db->lastInsertId();

            // Lưu danh mục món & options
            if (!empty($items)) {
                $itemStmt = $this->db->prepare("
                    INSERT INTO group_buy_items (event_id, name, option_name, price)
                    VALUES (:event_id, :name, :option_name, :price)
                ");

                foreach ($items as $item) {
                    $iName = trim((string)($item['name'] ?? $title));
                    $optName = trim((string)($item['option_name'] ?? 'Mặc định'));
                    $price = (float)($item['price'] ?? 0);
                    if ($price < 0) $price = 0;

                    $itemStmt->execute([
                        ':event_id'    => $eventId,
                        ':name'        => $iName,
                        ':option_name' => $optName,
                        ':price'       => $price,
                    ]);
                }
            }

            $this->db->commit();
            return $this->getEventById($eventId);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Lấy danh sách sự kiện mua chung theo nhóm
     */
    public function getEventsByGroup(int $groupId): array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, u.full_name as creator_name,
                   (SELECT COUNT(*) FROM group_buy_registrations r WHERE r.event_id = e.id) as total_registrations,
                   (SELECT COALESCE(SUM(r.total_amount), 0) FROM group_buy_registrations r WHERE r.event_id = e.id) as total_amount,
                   (SELECT COALESCE(SUM(r.total_amount), 0) FROM group_buy_registrations r WHERE r.event_id = e.id AND r.is_paid = 1) as collected_amount
            FROM group_buy_events e
            LEFT JOIN users u ON e.creator_id = u.id
            WHERE e.group_id = :group_id
            ORDER BY e.id DESC
        ");
        $stmt->execute([':group_id' => $groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy chi tiết sự kiện theo ID
     */
    public function getEventById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, u.full_name as creator_name
            FROM group_buy_events e
            LEFT JOIN users u ON e.creator_id = u.id
            WHERE e.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) return null;

        $event['items'] = $this->getItemsByEventId($id);
        return $event;
    }

    /**
     * Lấy chi tiết sự kiện theo Public Token
     */
    public function getEventByToken(string $token): ?array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, u.full_name as creator_name
            FROM group_buy_events e
            LEFT JOIN users u ON e.creator_id = u.id
            WHERE e.public_token = :token
        ");
        $stmt->execute([':token' => $token]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) return null;

        $event['items'] = $this->getItemsByEventId((int)$event['id']);
        return $event;
    }

    /**
     * Lấy danh sách các món/options trong sự kiện
     */
    public function getItemsByEventId(int $eventId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM group_buy_items WHERE event_id = :event_id ORDER BY id ASC");
        $stmt->execute([':event_id' => $eventId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Đăng ký tham gia mua chung (Dành cho khách công khai hoặc AI Agent)
     */
    public function register(
        string $publicToken,
        string $participantName,
        ?string $participantPhone = null,
        ?string $note = null,
        array $selectedItems = []
    ): array {
        $event = $this->getEventByToken($publicToken);
        if (!$event) {
            throw new InvalidArgumentException("Sự kiện mua chung không tồn tại hoặc link không hợp lệ.");
        }

        if ($event['status'] !== 'open') {
            throw new InvalidArgumentException("Sự kiện này đã kết thúc nhận đăng ký ({$event['status']}).");
        }

        $participantName = trim($participantName);
        if (empty($participantName)) {
            throw new InvalidArgumentException("Vui lòng nhập họ và tên người tham gia.");
        }

        if (empty($selectedItems)) {
            throw new InvalidArgumentException("Vui lòng chọn ít nhất một sản phẩm hoặc kích cỡ.");
        }

        $itemsMap = [];
        foreach ($event['items'] as $item) {
            $itemsMap[(int)$item['id']] = $item;
        }

        // Tính toán tổng tiền và validate các món
        $totalAmount = 0.0;
        $orderLines = [];

        foreach ($selectedItems as $si) {
            $itemId = (int)($si['item_id'] ?? 0);
            $qty = (int)($si['quantity'] ?? 0);

            if ($qty <= 0) continue;
            if (!isset($itemsMap[$itemId])) {
                throw new InvalidArgumentException("Món hàng ID {$itemId} không thuộc sự kiện này.");
            }

            $itemData = $itemsMap[$itemId];
            $unitPrice = (float)$itemData['price'];
            $subtotal = round($unitPrice * $qty, 2);
            $totalAmount += $subtotal;

            $orderLines[] = [
                'item_id'     => $itemId,
                'name'        => $itemData['name'],
                'option_name' => $itemData['option_name'],
                'quantity'    => $qty,
                'unit_price'  => $unitPrice,
                'subtotal'    => $subtotal,
            ];
        }

        if (empty($orderLines)) {
            throw new InvalidArgumentException("Số lượng đặt hàng hợp lệ phải lớn hơn 0.");
        }

        $regToken = bin2hex(random_bytes(16));
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowSql = $driver === 'sqlite' ? "datetime('now')" : "NOW()";

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO group_buy_registrations (
                    event_id, participant_name, participant_phone, note, total_amount,
                    is_notified_paid, is_paid, reg_token, created_at
                ) VALUES (
                    :event_id, :participant_name, :participant_phone, :note, :total_amount,
                    0, 0, :reg_token, {$nowSql}
                )
            ");
            $stmt->execute([
                ':event_id'          => $event['id'],
                ':participant_name'  => $participantName,
                ':participant_phone' => $participantPhone ?: null,
                ':note'              => $note ?: null,
                ':total_amount'      => $totalAmount,
                ':reg_token'         => $regToken,
            ]);

            $regId = (int)$this->db->lastInsertId();

            $itemStmt = $this->db->prepare("
                INSERT INTO group_buy_registration_items (
                    registration_id, item_id, quantity, unit_price, subtotal
                ) VALUES (
                    :registration_id, :item_id, :quantity, :unit_price, :subtotal
                )
            ");

            foreach ($orderLines as $ol) {
                $itemStmt->execute([
                    ':registration_id' => $regId,
                    ':item_id'         => $ol['item_id'],
                    ':quantity'        => $ol['quantity'],
                    ':unit_price'      => $ol['unit_price'],
                    ':subtotal'        => $ol['subtotal'],
                ]);
            }

            $this->db->commit();

            // Sinh thông tin VietQR
            $qrData = $this->generateVietQR(
                $event['bank_bin'],
                $event['bank_account_no'],
                $event['bank_account_name'],
                $totalAmount,
                "MC{$event['id']} " . $this->removeVietnameseAccents($participantName)
            );

            return [
                'success'           => true,
                'registration_id'   => $regId,
                'reg_token'         => $regToken,
                'participant_name'  => $participantName,
                'participant_phone' => $participantPhone,
                'total_amount'      => $totalAmount,
                'order_lines'       => $orderLines,
                'event_title'       => $event['title'],
                'bank_info'         => [
                    'bank_bin'          => $event['bank_bin'],
                    'bank_account_no'   => $event['bank_account_no'],
                    'bank_account_name' => $event['bank_account_name'],
                ],
                'qr_url'            => $qrData['qr_url'],
                'qr_memo'           => $qrData['memo'],
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Người tham gia thông báo đã chuyển tiền
     */
    public function notifyPaid(string $regToken): bool
    {
        $stmt = $this->db->prepare("
            UPDATE group_buy_registrations
            SET is_notified_paid = 1
            WHERE reg_token = :reg_token
        ");
        $stmt->execute([':reg_token' => $regToken]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Admin toggle trạng thái đã thu tiền của một người đăng ký
     */
    public function togglePaid(int $registrationId, ?bool $isPaid = null): array
    {
        $stmt = $this->db->prepare("SELECT * FROM group_buy_registrations WHERE id = :id");
        $stmt->execute([':id' => $registrationId]);
        $reg = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$reg) {
            throw new InvalidArgumentException("Không tìm thấy đơn đăng ký #{$registrationId}.");
        }

        $newPaid = $isPaid !== null ? ($isPaid ? 1 : 0) : ((int)$reg['is_paid'] === 1 ? 0 : 1);
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowSql = $newPaid === 1 ? ($driver === 'sqlite' ? "datetime('now')" : "NOW()") : "NULL";

        $updateStmt = $this->db->prepare("
            UPDATE group_buy_registrations
            SET is_paid = :is_paid, paid_at = {$nowSql}
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':is_paid' => $newPaid,
            ':id'      => $registrationId,
        ]);

        return [
            'success'         => true,
            'registration_id' => $registrationId,
            'is_paid'         => $newPaid,
            'paid_at'         => $newPaid === 1 ? date('Y-m-d H:i:s') : null,
        ];
    }

    /**
     * Lấy toàn bộ danh sách đăng ký của một sự kiện
     */
    public function getRegistrations(int $eventId): array
    {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   GROUP_CONCAT(CONCAT(ri.quantity, 'x ', i.option_name, ' (', FORMAT(ri.subtotal, 0), 'đ)'), '<br>') as items_summary
            FROM group_buy_registrations r
            LEFT JOIN group_buy_registration_items ri ON r.id = ri.registration_id
            LEFT JOIN group_buy_items i ON ri.item_id = i.id
            WHERE r.event_id = :event_id
            GROUP BY r.id
            ORDER BY r.id DESC
        ");
        
        // SQLite fallback nếu không có hàm FORMAT
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->db->prepare("
                SELECT r.*
                FROM group_buy_registrations r
                WHERE r.event_id = :event_id
                ORDER BY r.id DESC
            ");
            $stmt->execute([':event_id' => $eventId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$row) {
                $itemStmt = $this->db->prepare("
                    SELECT ri.*, i.name as item_name, i.option_name
                    FROM group_buy_registration_items ri
                    JOIN group_buy_items i ON ri.item_id = i.id
                    WHERE ri.registration_id = :reg_id
                ");
                $itemStmt->execute([':reg_id' => $row['id']]);
                $row['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            return $rows;
        }

        $stmt->execute([':event_id' => $eventId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $itemStmt = $this->db->prepare("
                SELECT ri.*, i.name as item_name, i.option_name
                FROM group_buy_registration_items ri
                JOIN group_buy_items i ON ri.item_id = i.id
                WHERE ri.registration_id = :reg_id
            ");
            $itemStmt->execute([':reg_id' => $row['id']]);
            $row['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $rows;
    }

    /**
     * Thống kê số lượng gom hàng theo từng phân loại/size
     */
    public function getSummaryBreakdown(int $eventId): array
    {
        $stmt = $this->db->prepare("
            SELECT i.id, i.name, i.option_name, i.price,
                   COALESCE(SUM(ri.quantity), 0) as total_quantity,
                   COALESCE(SUM(ri.subtotal), 0) as total_subtotal
            FROM group_buy_items i
            LEFT JOIN group_buy_registration_items ri ON i.id = ri.item_id
            WHERE i.event_id = :event_id
            GROUP BY i.id
            ORDER BY i.id ASC
        ");
        $stmt->execute([':event_id' => $eventId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Chốt sự kiện & chuyển thành 1 Giao dịch chi tiêu nhóm (Transaction)
     */
    public function convertToTransaction(int $eventId, int $currentUserId): array
    {
        $event = $this->getEventById($eventId);
        if (!$event) {
            throw new InvalidArgumentException("Không tìm thấy sự kiện #{$eventId}.");
        }

        if ($event['status'] === 'converted') {
            throw new InvalidArgumentException("Sự kiện này đã được chốt và chuyển thành Giao dịch #{$event['transaction_id']}.");
        }

        $regs = $this->getRegistrations($eventId);
        if (empty($regs)) {
            throw new InvalidArgumentException("Sự kiện chưa có người đăng ký nào để tạo giao dịch.");
        }

        // Tính tổng tiền
        $totalAmount = 0.0;
        foreach ($regs as $r) {
            $totalAmount += (float)$r['total_amount'];
        }

        if ($totalAmount <= 0) {
            throw new InvalidArgumentException("Tổng tiền sự kiện bằng 0, không thể tạo giao dịch.");
        }

        // Tìm thành viên tương ứng với user hiện tại trong nhóm hoặc thành viên đầu tiên
        $memberStmt = $this->db->prepare("
            SELECT id FROM members 
            WHERE group_id = :group_id AND user_id = :user_id 
            LIMIT 1
        ");
        $memberStmt->execute([':group_id' => $event['group_id'], ':user_id' => $currentUserId]);
        $member = $memberStmt->fetch(PDO::FETCH_ASSOC);

        $payerId = null;
        if ($member) {
            $payerId = (int)$member['id'];
        } else {
            // Lấy member đầu tiên của nhóm
            $firstMem = $this->db->prepare("SELECT id FROM members WHERE group_id = :group_id LIMIT 1");
            $firstMem->execute([':group_id' => $event['group_id']]);
            $mRow = $firstMem->fetch(PDO::FETCH_ASSOC);
            $payerId = $mRow ? (int)$mRow['id'] : null;
        }

        if (!$payerId) {
            throw new InvalidArgumentException("Nhóm chi tiêu chưa có thành viên nào. Vui lòng thêm thành viên trước khi chốt hóa đơn.");
        }

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowSql = $driver === 'sqlite' ? "datetime('now')" : "NOW()";

        $this->db->beginTransaction();
        try {
            // 1. Tạo bản ghi transactions
            $txStmt = $this->db->prepare("
                INSERT INTO transactions (
                    group_id, payer_id, title, total_amount, note, status, spent_at, created_at
                ) VALUES (
                    :group_id, :payer_id, :title, :total_amount, :note, 'completed', {$nowSql}, {$nowSql}
                )
            ");
            $txStmt->execute([
                ':group_id'     => $event['group_id'],
                ':payer_id'     => $payerId,
                ':title'        => "Mua chung: " . $event['title'],
                ':total_amount' => $totalAmount,
                ':note'         => "Tự động tạo từ sự kiện mua chung #{$eventId} (" . count($regs) . " lượt đăng ký).",
            ]);
            $txId = (int)$this->db->lastInsertId();

            // 2. Cập nhật trạng thái sự kiện
            $upEvent = $this->db->prepare("
                UPDATE group_buy_events
                SET status = 'converted', transaction_id = :tx_id
                WHERE id = :event_id
            ");
            $upEvent->execute([
                ':tx_id'    => $txId,
                ':event_id' => $eventId,
            ]);

            $this->db->commit();

            return [
                'success'        => true,
                'event_id'       => $eventId,
                'transaction_id' => $txId,
                'total_amount'   => $totalAmount,
                'message'        => "Đã chốt sự kiện và tạo thành Giao dịch #{$txId} thành công!",
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Sinh link và thông tin VietQR
     */
    public function generateVietQR(?string $bin, ?string $accountNo, ?string $accountName, float $amount, string $memo): array
    {
        $bin = trim((string)$bin);
        $accountNo = trim((string)$accountNo);
        $cleanMemo = $this->removeVietnameseAccents($memo);

        if (empty($bin) || empty($accountNo)) {
            // Fallback nếu chưa cấu hình STK
            return [
                'qr_url' => "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($cleanMemo),
                'memo'   => $cleanMemo,
            ];
        }

        $amt = (int)round($amount);
        $qrUrl = "https://img.vietqr.io/image/{$bin}-{$accountNo}-compact2.png?amount={$amt}&addInfo=" . urlencode($cleanMemo);
        if (!empty($accountName)) {
            $qrUrl .= "&accountName=" . urlencode($accountName);
        }

        return [
            'qr_url' => $qrUrl,
            'memo'   => $cleanMemo,
        ];
    }

    private function removeVietnameseAccents(string $str): string
    {
        $unicode = [
            'a'=>'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
            'd'=>'đ',
            'e'=>'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
            'i'=>'í|ì|ỉ|ĩ|ị',
            'o'=>'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
            'u'=>'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
            'y'=>'ý|ỳ|ỷ|ỹ|ỵ',
            'A'=>'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ặ|Ằ|Ẳ|Ẵ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
            'D'=>'Đ',
            'E'=>'É|È|Ả|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
            'I'=>'Í|Ì|Ỉ|Ĩ|Ị',
            'O'=>'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
            'U'=>'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
            'Y'=>'Ý|Ỳ|Ỷ|Ỹ|Ỵ',
        ];
        foreach ($unicode as $nonAccent => $accent) {
            $str = preg_replace("/($accent)/i", $nonAccent, $str);
        }
        return preg_replace('/[^a-zA-Z0-9 ]/', '', $str);
    }
}
