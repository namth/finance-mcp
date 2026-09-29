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
    }

    /**
     * Tạo giao dịch mới gồm nhiều sản phẩm/dịch vụ và phân chia người tham gia
     *
     * @param string $title
     * @param int $payerId Người thanh toán hóa đơn
     * @param array $items Mảng các item:
     *                     [
     *                       'product_id' => int,
     *                       'price'      => ?float (nếu null sẽ lấy default_price của product),
     *                       'quantity'   => ?int (mặc định 1),
     *                       'note'       => ?string,
     *                       'member_ids' => array (danh sách ID thành viên tham gia sử dụng món này),
     *                       'shares'     => ?array [member_id => amount] (nếu null sẽ tự chia đều)
     *                     ]
     * @param string $status 'draft' | 'completed'
     * @param string|null $note
     * @return array Chi tiết giao dịch vừa tạo
     */
    public function create(
        string $title,
        int $payerId,
        array $items,
        string $status = 'completed',
        ?string $note = null,
        int $groupId = 1
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

        $productModel = new Product($this->db);

        $this->db->beginTransaction();

        try {
            // Tạo bản ghi transactions trước với total_amount = 0
            $stmt = $this->db->prepare("
                INSERT INTO `transactions` (`group_id`, `title`, `payer_id`, `total_amount`, `status`, `note`)
                VALUES (:group_id, :title, :payer_id, 0.00, :status, :note)
            ");
            $stmt->execute([
                ':group_id' => $groupId,
                ':title'    => $title,
                ':payer_id' => $payerId,
                ':status'   => $status,
                ':note'     => $note ? trim($note) : null,
            ]);
            $transactionId = (int)$this->db->lastInsertId();

            $totalAmount = 0.00;

            // Duyệt từng item
            foreach ($items as $idx => $item) {
                $productId = (int)($item['product_id'] ?? 0);
                $product = $productModel->find($productId);
                if (!$product) {
                    throw new InvalidArgumentException("Sản phẩm tại mục #{$idx} (product_id = {$productId}) không tồn tại.");
                }

                // Xác định giá
                if (isset($item['price']) && $item['price'] !== null && $item['price'] !== '') {
                    $price = (float)$item['price'];
                } elseif ($product['default_price'] !== null) {
                    $price = (float)$product['default_price'];
                } else {
                    throw new InvalidArgumentException(
                        "Sản phẩm '{$product['name']}' (ID: {$productId}) không có giá mặc định. Vui lòng cung cấp giá tiền 'price' cho lần sử dụng này."
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
                    $sumShares = 0.0;
                    foreach ($memberIds as $mId) {
                        $sAmount = isset($shares[$mId]) ? (float)$shares[$mId] : 0.0;
                        if ($sAmount < 0) {
                            throw new InvalidArgumentException("Số tiền chia không được là số âm.");
                        }
                        $sumShares += $sAmount;
                        $stmtMember->execute([
                            ':item_id'      => $itemId,
                            ':member_id'    => $mId,
                            ':share_amount' => round($sAmount, 2),
                        ]);
                    }
                } else {
                    // Tự động chia đều cho các thành viên
                    $sharePerMember = round($subtotal / $memberCount, 2);
                    // Xử lý làm tròn số dư lẻ (cents)
                    $allocatedTotal = 0.00;

                    foreach ($memberIds as $i => $mId) {
                        if ($i === $memberCount - 1) {
                            // Người cuối cùng nhận phần còn lại để khớp chính xác subtotal
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

    public function all(?string $status = null, int $limit = 50, int $offset = 0, ?int $groupId = null): array
    {
        $sql = "
            SELECT t.*, m.name AS payer_name, m.phone AS payer_phone
            FROM `transactions` t
            JOIN `members` m ON t.payer_id = m.id
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
            SELECT t.*, m.name AS payer_name, m.phone AS payer_phone, m.email AS payer_email
            FROM `transactions` t
            JOIN `members` m ON t.payer_id = m.id
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

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM `transactions` WHERE `id` = :id");
        return $stmt->execute([':id' => $id]);
    }
}
