<?php

namespace SimpleFinance\Models;

use PDO;
use SimpleFinance\Database;
use InvalidArgumentException;
use RuntimeException;

class Transaction
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
        (new Place($this->db))->ensureSchema();
    }

    /**
     * Tạo giao dịch mới gồm nhiều sản phẩm/dịch vụ và phân chia người tham gia
     *
     * @param string $title
     * @param int $payerId Người thanh toán hóa đơn
     * @param array $items Mảng các item
     * @param string $status 'draft' | 'completed'
     * @param string|null $note
     * @param int $groupId
     * @param string|null $placeName
     * @param string|null $placeAddress
     * @param int|null $placeId
     * @return array Chi tiết giao dịch vừa tạo
     */
    public function create(
        string $title,
        int $payerId,
        array $items,
        string $status = 'completed',
        ?string $note = null,
        int $groupId = 1,
        ?string $placeName = null,
        ?string $placeAddress = null,
        ?int $placeId = null
    ): array {
        $title = trim($title);
        if (empty($title)) {
            throw new InvalidArgumentException("Tiêu đề giao dịch không được để trống.");
        }

        // Kiểm tra người trả tiền
        $memberModel = new Member($this->db);
        $payer = $memberModel->find($payerId);
        if (!$payer) {
            throw new InvalidArgumentException("Người thanh toán (payer_id = {$payerId}) không tồn tại.");
        }

        if (empty($items)) {
            throw new InvalidArgumentException("Giao dịch phải có ít nhất một sản phẩm/dịch vụ.");
        }

        $validStatuses = ['draft', 'completed', 'cancelled'];
        if (!in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException("Trạng thái không hợp lệ. Cho phép: " . implode(', ', $validStatuses));
        }

        // Nếu có thông tin tên hoặc địa chỉ quán mà chưa có place_id, tự động nhận diện
        if (($placeId === null || $placeId <= 0) && (!empty($placeName) || !empty($placeAddress))) {
            $pl = (new Place($this->db))->resolvePlace(null, $placeName, $placeAddress, null, $groupId);
            if ($pl) {
                $placeId = (int)$pl['id'];
            }
        }
        $placeId = ($placeId !== null && $placeId > 0) ? $placeId : null;

        $this->db->beginTransaction();

        try {
            // Tạo bản ghi transactions trước với total_amount = 0
            $stmt = $this->db->prepare("
                INSERT INTO `transactions` (`group_id`, `place_id`, `title`, `payer_id`, `total_amount`, `status`, `note`)
                VALUES (:group_id, :place_id, :title, :payer_id, 0.00, :status, :note)
            ");
            $stmt->execute([
                ':group_id' => $groupId,
                ':place_id' => $placeId,
                ':title'    => $title,
                ':payer_id' => $payerId,
                ':status'   => $status,
                ':note'     => $note ? trim($note) : null,
            ]);
            $transactionId = (int)$this->db->lastInsertId();

            // Lưu danh sách items và tính tổng tiền
            $totalAmount = $this->saveTransactionItems($transactionId, $items, $groupId, $placeName, $placeAddress);

            // Cập nhật lại total_amount của transaction
            $stmtUpdateTotal = $this->db->prepare("
                UPDATE `transactions` SET `total_amount` = :total_amount WHERE `id` = :id
            ");
            $stmtUpdateTotal->execute([
                ':total_amount' => round($totalAmount, 2),
                ':id'           => $transactionId,
            ]);

            $this->db->commit();
            return $this->find($transactionId);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Helper lưu danh sách items và các thành viên chia tiền cho một giao dịch.
     * Tự động nhận diện sản phẩm theo tên, vị trí quán hoặc ID, đồng thời lấy giá mặc định nếu không truyền.
     */
    private function saveTransactionItems(
        int $transactionId,
        array $items,
        int $groupId,
        ?string $defaultPlaceName = null,
        ?string $defaultPlaceAddress = null
    ): float {
        $productModel = new Product($this->db);
        $memberModel = new Member($this->db);
        $totalAmount = 0.00;

        foreach ($items as $idx => $item) {
            $product = null;

            // 1. Tìm theo product_id nếu có
            if (!empty($item['product_id']) && is_numeric($item['product_id'])) {
                $product = $productModel->find((int)$item['product_id']);
            }

            // 2. Nếu chưa tìm thấy và có product_name / name -> Tìm thông minh hoặc tự tạo mới
            if (!$product && (!empty($item['product_name']) || !empty($item['name']))) {
                $pName = (string)($item['product_name'] ?? $item['name']);
                $itemPlaceName = !empty($item['place_name']) ? (string)$item['place_name'] : $defaultPlaceName;
                $itemPlaceAddress = !empty($item['place_address']) ? (string)$item['place_address'] : $defaultPlaceAddress;
                $itemPrice = (isset($item['price']) && $item['price'] !== null && $item['price'] !== '') ? (float)$item['price'] : null;
                $product = $productModel->resolveProduct($pName, $itemPlaceName, $itemPlaceAddress, $itemPrice, $groupId);
            }

            if (!$product) {
                $pid = $item['product_id'] ?? 'null';
                throw new InvalidArgumentException("Sản phẩm tại mục #{$idx} (product_id: {$pid}) không tìm thấy hoặc không hợp lệ.");
            }

            $productId = (int)$product['id'];

            // 3. Xác định giá: ưu tiên giá truyền vào lúc dùng, nếu không có thì lấy giá mặc định của sản phẩm
            if (isset($item['price']) && $item['price'] !== null && $item['price'] !== '') {
                $price = (float)$item['price'];
            } elseif ($product['default_price'] !== null) {
                $price = (float)$product['default_price'];
            } else {
                throw new InvalidArgumentException(
                    "Sản phẩm '{$product['name']}' (ID: {$productId}) không có giá mặc định trong danh mục. Vui lòng cung cấp giá tiền 'price' cho lần sử dụng này."
                );
            }

            if ($price < 0) {
                throw new InvalidArgumentException("Giá sản phẩm không được âm.");
            }

            $quantity = isset($item['quantity']) ? max(1, (int)$item['quantity']) : 1;
            $subtotal = round($price * $quantity, 2);
            $totalAmount += $subtotal;
            $itemNote = isset($item['note']) ? trim((string)$item['note']) : null;

            // Lưu transaction_items
            $stmtItem = $this->db->prepare("
                INSERT INTO `transaction_items` (`transaction_id`, `product_id`, `price`, `quantity`, `note`)
                VALUES (:transaction_id, :product_id, :price, :quantity, :note)
            ");
            $stmtItem->execute([
                ':transaction_id' => $transactionId,
                ':product_id'     => $productId,
                ':price'          => $price,
                ':quantity'       => $quantity,
                ':note'           => $itemNote,
            ]);
            $itemId = (int)$this->db->lastInsertId();

            // Xác định danh sách thành viên tham gia
            $memberIds = $item['member_ids'] ?? [];
            if (empty($memberIds) || !is_array($memberIds)) {
                throw new InvalidArgumentException("Mục '{$product['name']}' phải có ít nhất một thành viên tham gia sử dụng (member_ids).");
            }

            // Loại bỏ trùng lặp ID
            $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
            $memberCount = count($memberIds);

            // Kiểm tra các member có tồn tại không
            foreach ($memberIds as $mId) {
                if (!$memberModel->find($mId)) {
                    throw new InvalidArgumentException("Thành viên tham gia (ID = {$mId}) không tồn tại.");
                }
            }

            $shares = $item['shares'] ?? null;
            $stmtMember = $this->db->prepare("
                INSERT INTO `transaction_item_members` (`item_id`, `member_id`, `share_amount`)
                VALUES (:item_id, :member_id, :share_amount)
            ");

            if (!empty($shares) && is_array($shares)) {
                // Người dùng tự chỉ định số tiền chia cho từng người
                foreach ($memberIds as $mId) {
                    $sAmount = isset($shares[$mId]) ? (float)$shares[$mId] : 0.0;
                    if ($sAmount < 0) {
                        throw new InvalidArgumentException("Số tiền chia không được là số âm.");
                    }
                    $stmtMember->execute([
                        ':item_id'      => $itemId,
                        ':member_id'    => $mId,
                        ':share_amount' => round($sAmount, 2),
                    ]);
                }
            } else {
                // Tự động chia đều cho các thành viên
                $sharePerMember = round($subtotal / $memberCount, 2);
                $allocatedTotal = 0.00;

                foreach ($memberIds as $i => $mId) {
                    if ($i === $memberCount - 1) {
                        $currentShare = round($subtotal - $allocatedTotal, 2);
                    } else {
                        $currentShare = $sharePerMember;
                        $allocatedTotal += $currentShare;
                    }

                    $stmtMember->execute([
                        ':item_id'      => $itemId,
                        ':member_id'    => $mId,
                        ':share_amount' => $currentShare,
                    ]);
                }
            }
        }

        return round($totalAmount, 2);
    }

    public function all(?string $status = null, int $limit = 50, int $offset = 0, ?int $groupId = null): array
    {
        $sql = "
            SELECT t.*, m.name AS payer_name, m.phone AS payer_phone,
                   pl.name AS place_name, pl.address AS place_address
            FROM `transactions` t
            JOIN `members` m ON t.payer_id = m.id
            LEFT JOIN `places` pl ON t.place_id = pl.id
        ";
        $conditions = [];
        $params = [];

        if ($groupId !== null) {
            $conditions[] = "t.group_id = :gid";
            $params[':gid'] = $groupId;
        }

        if ($status !== null) {
            $conditions[] = "t.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY t.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, m.name AS payer_name, m.phone AS payer_phone, m.email AS payer_email,
                   pl.name AS place_name, pl.address AS place_address
            FROM `transactions` t
            JOIN `members` m ON t.payer_id = m.id
            LEFT JOIN `places` pl ON t.place_id = pl.id
            WHERE t.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $transaction = $stmt->fetch();

        if (!$transaction) {
            return null;
        }

        // Lấy danh sách items kèm thông tin Quán / Địa điểm nếu có
        $stmtItems = $this->db->prepare("
            SELECT ti.*, p.name AS product_name, p.description AS product_description,
                   pl.name AS place_name, pl.address AS place_address
            FROM `transaction_items` ti
            JOIN `products` p ON ti.product_id = p.id
            LEFT JOIN `places` pl ON p.place_id = pl.id
            WHERE ti.transaction_id = :transaction_id
            ORDER BY ti.id ASC
        ");
        $stmtItems->execute([':transaction_id' => $id]);
        $items = $stmtItems->fetchAll();

        // Lấy danh sách members cho từng item
        foreach ($items as &$item) {
            $stmtM = $this->db->prepare("
                SELECT tim.*, m.name AS member_name, m.phone AS member_phone
                FROM `transaction_item_members` tim
                JOIN `members` m ON tim.member_id = m.id
                WHERE tim.item_id = :item_id
                ORDER BY tim.id ASC
            ");
            $stmtM->execute([':item_id' => $item['id']]);
            $item['members'] = $stmtM->fetchAll();
        }

        $transaction['items'] = $items;
        return $transaction;
    }

    /**
     * Cập nhật thông tin giao dịch (tiêu đề, người trả, trạng thái, ghi chú, danh sách món)
     */
    public function update(int $id, array $data, ?int $groupId = null): ?array
    {
        $tx = $this->find($id);
        if (!$tx) {
            return null;
        }

        if ($groupId !== null && (int)$tx['group_id'] !== $groupId) {
            throw new InvalidArgumentException("Giao dịch #{$id} không thuộc nhóm chi tiêu của bạn.");
        }

        $effectiveGroupId = (int)$tx['group_id'];
        $memberModel = new Member($this->db);

        $fields = [];
        $params = [':id' => $id];

        if (array_key_exists('title', $data)) {
            $title = trim((string)$data['title']);
            if (empty($title)) {
                throw new InvalidArgumentException("Tiêu đề giao dịch không được để trống.");
            }
            $fields[] = "`title` = :title";
            $params[':title'] = $title;
        }

        if (array_key_exists('payer_id', $data)) {
            $payerId = (int)$data['payer_id'];
            if (!$memberModel->find($payerId)) {
                throw new InvalidArgumentException("Người thanh toán (payer_id = {$payerId}) không tồn tại.");
            }
            $fields[] = "`payer_id` = :payer_id";
            $params[':payer_id'] = $payerId;
        }

        if (array_key_exists('place_id', $data)) {
            $placeId = !empty($data['place_id']) && (int)$data['place_id'] > 0 ? (int)$data['place_id'] : null;
            $fields[] = "`place_id` = :place_id";
            $params[':place_id'] = $placeId;
        } elseif (!empty($data['place_name']) || !empty($data['place_address'])) {
            $pl = (new Place($this->db))->resolvePlace(null, $data['place_name'] ?? null, $data['place_address'] ?? null, null, $effectiveGroupId);
            if ($pl) {
                $fields[] = "`place_id` = :place_id";
                $params[':place_id'] = (int)$pl['id'];
            }
        }

        if (array_key_exists('status', $data)) {
            $status = (string)$data['status'];
            $validStatuses = ['draft', 'completed', 'cancelled'];
            if (!in_array($status, $validStatuses, true)) {
                throw new InvalidArgumentException("Trạng thái không hợp lệ. Cho phép: " . implode(', ', $validStatuses));
            }
            $fields[] = "`status` = :status";
            $params[':status'] = $status;
        }

        if (array_key_exists('note', $data)) {
            $note = $data['note'] !== null ? trim((string)$data['note']) : null;
            $fields[] = "`note` = :note";
            $params[':note'] = $note;
        }

        $this->db->beginTransaction();
        try {
            // Nếu có cập nhật danh sách items
            if (array_key_exists('items', $data)) {
                $items = (array)$data['items'];
                if (empty($items)) {
                    throw new InvalidArgumentException("Giao dịch phải có ít nhất một sản phẩm/dịch vụ.");
                }

                // Xóa chi tiết món cũ
                $this->db->prepare("
                    DELETE FROM `transaction_item_members` 
                    WHERE `item_id` IN (SELECT `id` FROM `transaction_items` WHERE `transaction_id` = :tx_id)
                ")->execute([':tx_id' => $id]);

                $this->db->prepare("DELETE FROM `transaction_items` WHERE `transaction_id` = :tx_id")
                         ->execute([':tx_id' => $id]);

                // Lưu danh sách món mới
                $defaultPlaceName = $data['place_name'] ?? null;
                $defaultPlaceAddress = $data['place_address'] ?? null;
                $newTotalAmount = $this->saveTransactionItems($id, $items, $effectiveGroupId, $defaultPlaceName, $defaultPlaceAddress);

                $fields[] = "`total_amount` = :total_amount";
                $params[':total_amount'] = $newTotalAmount;
            }

            if (!empty($fields)) {
                $sql = "UPDATE `transactions` SET " . implode(', ', $fields) . " WHERE `id` = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();
            return $this->find($id);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateStatus(int $id, string $status): ?array
    {
        $validStatuses = ['draft', 'completed', 'cancelled'];
        if (!in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException("Trạng thái không hợp lệ: " . implode(', ', $validStatuses));
        }

        $stmt = $this->db->prepare("UPDATE `transactions` SET `status` = :status WHERE `id` = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);

        return $this->find($id);
    }

    public function delete(int $id, ?int $groupId = null): bool
    {
        $tx = $this->find($id);
        if (!$tx) {
            return false;
        }

        if ($groupId !== null && (int)$tx['group_id'] !== $groupId) {
            throw new InvalidArgumentException("Giao dịch #{$id} không thuộc nhóm chi tiêu của bạn.");
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare("
                DELETE FROM `transaction_item_members` 
                WHERE `item_id` IN (SELECT `id` FROM `transaction_items` WHERE `transaction_id` = :id)
            ")->execute([':id' => $id]);

            $this->db->prepare("DELETE FROM `transaction_items` WHERE `transaction_id` = :id")->execute([':id' => $id]);

            $stmt = $this->db->prepare("DELETE FROM `transactions` WHERE `id` = :id");
            $res = $stmt->execute([':id' => $id]);

            $this->db->commit();
            return $res;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Sửa nhanh giá (và số lượng) của một món trong giao dịch và tự động phân bổ lại số tiền cho các thành viên
     */
    public function updateItemPrice(int $transactionId, int $itemId, float $newPrice, ?int $newQuantity = null): bool
    {
        $tx = $this->find($transactionId);
        if (!$tx) {
            throw new InvalidArgumentException("Giao dịch #{$transactionId} không tồn tại.");
        }

        if ($newPrice < 0) {
            throw new InvalidArgumentException("Đơn giá không được là số âm.");
        }

        // Kiểm tra item có thuộc transaction này không
        $stmtItem = $this->db->prepare("SELECT * FROM `transaction_items` WHERE `id` = :item_id AND `transaction_id` = :tx_id");
        $stmtItem->execute([':item_id' => $itemId, ':tx_id' => $transactionId]);
        $item = $stmtItem->fetch();
        if (!$item) {
            throw new InvalidArgumentException("Món không tồn tại trong giao dịch này.");
        }

        $quantity = ($newQuantity !== null && $newQuantity > 0) ? (int)$newQuantity : (int)$item['quantity'];
        $subtotal = round($newPrice * $quantity, 2);

        $this->db->beginTransaction();
        try {
            // 1. Cập nhật giá và số lượng trong transaction_items
            $stmtUpd = $this->db->prepare("UPDATE `transaction_items` SET `price` = :price, `quantity` = :quantity WHERE `id` = :id");
            $stmtUpd->execute([
                ':price'    => round($newPrice, 2),
                ':quantity' => $quantity,
                ':id'       => $itemId,
            ]);

            // 2. Lấy danh sách thành viên đang dùng món này và chia đều lại số tiền
            $stmtMem = $this->db->prepare("SELECT `id`, `member_id` FROM `transaction_item_members` WHERE `item_id` = :item_id ORDER BY `id` ASC");
            $stmtMem->execute([':item_id' => $itemId]);
            $members = $stmtMem->fetchAll();
            $mCount = count($members);

            if ($mCount > 0) {
                $sharePerMember = round($subtotal / $mCount, 2);
                $allocatedTotal = 0.00;

                foreach ($members as $idx => $m) {
                    if ($idx === $mCount - 1) {
                        $curShare = round($subtotal - $allocatedTotal, 2);
                    } else {
                        $curShare = $sharePerMember;
                        $allocatedTotal += $curShare;
                    }

                    $this->db->prepare("UPDATE `transaction_item_members` SET `share_amount` = :share WHERE `id` = :id")
                             ->execute([':share' => $curShare, ':id' => $m['id']]);
                }
            }

            // 3. Tính lại tổng tiền của transaction
            $stmtTotal = $this->db->prepare("SELECT SUM(price * quantity) AS total FROM `transaction_items` WHERE `transaction_id` = :tx_id");
            $stmtTotal->execute([':tx_id' => $transactionId]);
            $newTotal = (float)($stmtTotal->fetch()['total'] ?? 0.0);

            $this->db->prepare("UPDATE `transactions` SET `total_amount` = :total WHERE `id` = :id")
                     ->execute([':total' => round($newTotal, 2), ':id' => $transactionId]);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
