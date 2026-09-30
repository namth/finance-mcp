<?php

namespace SimpleFinance;

use PDO;
use SimpleFinance\Models\Transaction;
use SimpleFinance\Models\Settlement;
use InvalidArgumentException;
use RuntimeException;

class DebtManager
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Thêm nợ và tự động bù trừ nợ 2 chiều giữa 2 thành viên trong 1 nhóm
     */
    public function addDebt(int $debtorId, int $creditorId, float $amount, int $groupId = 1): void
    {
        if ($debtorId === $creditorId || $amount <= 0) {
            return;
        }

        $amount = round($amount, 2);

        // 1. Kiểm tra xem có khoản nợ ngược chiều (creditor đang nợ debtor) trong nhóm này hay không
        $stmtRev = $this->db->prepare("
            SELECT `id`, `amount` FROM `debts` 
            WHERE `debtor_id` = :debtor_id AND `creditor_id` = :creditor_id AND `group_id` = :group_id
        ");
        $stmtRev->execute([
            ':debtor_id'   => $creditorId,
            ':creditor_id' => $debtorId,
            ':group_id'    => $groupId,
        ]);
        $reverseDebt = $stmtRev->fetch();

        if ($reverseDebt) {
            $revAmount = (float)$reverseDebt['amount'];

            if ($amount < $revAmount) {
                // Khoản nợ mới nhỏ hơn khoản nợ ngược chiều: giảm nợ ngược chiều
                $newRevAmount = round($revAmount - $amount, 2);
                $stmtUpd = $this->db->prepare("UPDATE `debts` SET `amount` = :amount WHERE `id` = :id");
                $stmtUpd->execute([':amount' => $newRevAmount, ':id' => $reverseDebt['id']]);
                return;
            } elseif (abs($amount - $revAmount) < 0.001) {
                // Hai bên nợ bằng nhau: xóa sạch nợ ngược chiều
                $stmtDel = $this->db->prepare("DELETE FROM `debts` WHERE `id` = :id");
                $stmtDel->execute([':id' => $reverseDebt['id']]);
                return;
            } else {
                // Khoản nợ mới lớn hơn: xóa nợ ngược chiều, phần dôi ra trở thành nợ theo chiều này
                $stmtDel = $this->db->prepare("DELETE FROM `debts` WHERE `id` = :id");
                $stmtDel->execute([':id' => $reverseDebt['id']]);

                $amount = round($amount - $revAmount, 2);
            }
        }

        // 2. Thêm hoặc cộng dồn vào chiều nợ debtor -> creditor trong nhóm
        $stmtCurr = $this->db->prepare("
            SELECT `id`, `amount` FROM `debts` 
            WHERE `debtor_id` = :debtor_id AND `creditor_id` = :creditor_id AND `group_id` = :group_id
        ");
        $stmtCurr->execute([
            ':debtor_id'   => $debtorId,
            ':creditor_id' => $creditorId,
            ':group_id'    => $groupId,
        ]);
        $currentDebt = $stmtCurr->fetch();

        if ($currentDebt) {
            $newAmount = round((float)$currentDebt['amount'] + $amount, 2);
            $stmtUpd = $this->db->prepare("UPDATE `debts` SET `amount` = :amount WHERE `id` = :id");
            $stmtUpd->execute([':amount' => $newAmount, ':id' => $currentDebt['id']]);
        } else {
            $debtToken = bin2hex(random_bytes(16));
            $stmtIns = $this->db->prepare("
                INSERT INTO `debts` (`group_id`, `debtor_id`, `creditor_id`, `amount`, `debt_token`)
                VALUES (:group_id, :debtor_id, :creditor_id, :amount, :token)
            ");
            $stmtIns->execute([
                ':group_id'    => $groupId,
                ':debtor_id'   => $debtorId,
                ':creditor_id' => $creditorId,
                ':amount'      => $amount,
                ':token'       => $debtToken,
            ]);
        }
    }

    /**
     * Tự động tính toán và lưu công nợ cho một giao dịch đã hoàn thành
     */
    public function processTransaction(int $transactionId): void
    {
        $transactionModel = new Transaction($this->db);
        $tx = $transactionModel->find($transactionId);

        if (!$tx) {
            throw new RuntimeException("Giao dịch #{$transactionId} không tồn tại.");
        }

        if ($tx['status'] !== 'completed') {
            return;
        }

        $payerId = (int)$tx['payer_id'];
        $groupId = isset($tx['group_id']) ? (int)$tx['group_id'] : 1;

        foreach ($tx['items'] as $item) {
            foreach ($item['members'] as $m) {
                $memberId = (int)$m['member_id'];
                $shareAmount = (float)$m['share_amount'];

                if ($memberId !== $payerId && $shareAmount > 0) {
                    $this->addDebt($memberId, $payerId, $shareAmount, $groupId);
                }
            }
        }
    }

    /**
     * Gạch nợ / Thanh toán nợ trong nhóm
     */
    public function settle(int $debtorId, int $creditorId, float $amount, ?string $note = null, int $groupId = 1): array
    {
        if ($debtorId === $creditorId) {
            throw new InvalidArgumentException("Người trả và người nhận không thể là cùng một người.");
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException("Số tiền thanh toán phải lớn hơn 0.");
        }

        $amount = round($amount, 2);

        $this->db->beginTransaction();
        try {
            // Lấy nợ hiện tại trong nhóm
            $stmt = $this->db->prepare("
                SELECT `id`, `amount` FROM `debts` 
                WHERE `debtor_id` = :debtor_id AND `creditor_id` = :creditor_id AND `group_id` = :group_id
            ");
            $stmt->execute([
                ':debtor_id'   => $debtorId,
                ':creditor_id' => $creditorId,
                ':group_id'    => $groupId,
            ]);
            $debt = $stmt->fetch();

            $currentDebtAmount = $debt ? (float)$debt['amount'] : 0.00;

            // Giảm nợ hoặc xóa
            if ($debt) {
                if ($amount >= $currentDebtAmount) {
                    $stmtDel = $this->db->prepare("DELETE FROM `debts` WHERE `id` = :id");
                    $stmtDel->execute([':id' => $debt['id']]);
                    $remainingDebt = 0.00;
                } else {
                    $remainingDebt = round($currentDebtAmount - $amount, 2);
                    $stmtUpd = $this->db->prepare("UPDATE `debts` SET `amount` = :amount WHERE `id` = :id");
                    $stmtUpd->execute([':amount' => $remainingDebt, ':id' => $debt['id']]);
                }
            } else {
                $remainingDebt = 0.00;
            }

            // Ghi nhận lịch sử thanh toán
            $settlementModel = new Settlement($this->db);
            $history = $settlementModel->record($debtorId, $creditorId, $amount, $note, $groupId);

            $this->db->commit();

            return [
                'settlement'        => $history,
                'previous_debt'     => $currentDebtAmount,
                'amount_paid'       => $amount,
                'remaining_debt'    => $remainingDebt,
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Lấy danh sách tổng kết ai đang nợ ai bao nhiêu tiền theo nhóm kèm thông tin ngân hàng và mã QR
     */
    public function getSummary(?int $memberId = null, ?int $groupId = null): array
    {
        // Đảm bảo schema debts và users sẵn sàng
        (new Models\User($this->db))->ensureBankSchema();

        $sql = "
            SELECT d.id, d.group_id, d.debtor_id, d.creditor_id, d.amount, d.debt_token, d.payment_notified_at, d.updated_at,
                   deb.name AS debtor_name, deb.phone AS debtor_phone,
                   cred.name AS creditor_name, cred.phone AS creditor_phone,
                   u.bank_bin, u.bank_name, u.bank_account_no, u.bank_account_name
            FROM `debts` d
            JOIN `members` deb ON d.debtor_id = deb.id
            JOIN `members` cred ON d.creditor_id = cred.id
            LEFT JOIN `users` u ON cred.user_id = u.id OR (cred.user_id IS NULL AND (LOWER(cred.email) = LOWER(u.email) OR LOWER(cred.name) = LOWER(u.full_name)))
            WHERE d.amount > 0
        ";
        $params = [];

        if ($groupId !== null) {
            $sql .= " AND d.group_id = :gid";
            $params[':gid'] = $groupId;
        }

        if ($memberId !== null) {
            $sql .= " AND (d.debtor_id = :mid1 OR d.creditor_id = :mid2)";
            $params[':mid1'] = $memberId;
            $params[':mid2'] = $memberId;
        }

        $sql .= " ORDER BY d.amount DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Tự động gán debt_token nếu các khoản nợ cũ chưa có và sinh link QR code
        foreach ($rows as &$r) {
            if (empty($r['debt_token'])) {
                $r['debt_token'] = bin2hex(random_bytes(16));
                try {
                    $this->db->prepare("UPDATE `debts` SET `debt_token` = :token WHERE `id` = :id")
                             ->execute([':token' => $r['debt_token'], ':id' => $r['id']]);
                } catch (\Throwable $e) {}
            }

            $r['pay_url'] = "https://financemcp.oa.io.vn/pay.php?token=" . urlencode($r['debt_token']);

            if (!empty($r['bank_bin']) && !empty($r['bank_account_no'])) {
                $memo = BankList::cleanMemo("{$r['debtor_name']} tra {$r['creditor_name']}");
                $r['qr_image_url'] = BankList::generateVietQrUrl(
                    $r['bank_bin'],
                    $r['bank_account_no'],
                    (float)$r['amount'],
                    $memo,
                    $r['bank_account_name'] ?: $r['creditor_name']
                );
            } else {
                $r['qr_image_url'] = null;
            }
        }

        return $rows;
    }

    /**
     * Lấy thông tin chi tiết một khoản nợ dựa trên token chia sẻ công khai
     */
    public function getDebtByToken(string $token): ?array
    {
        $token = trim($token);
        if (empty($token)) {
            return null;
        }

        (new Models\User($this->db))->ensureBankSchema();

        $sql = "
            SELECT d.id, d.group_id, d.debtor_id, d.creditor_id, d.amount, d.debt_token, d.payment_notified_at, d.updated_at,
                   deb.name AS debtor_name, deb.phone AS debtor_phone,
                   cred.name AS creditor_name, cred.phone AS creditor_phone,
                   u.bank_bin, u.bank_name, u.bank_account_no, u.bank_account_name,
                   g.name AS group_name
            FROM `debts` d
            JOIN `members` deb ON d.debtor_id = deb.id
            JOIN `members` cred ON d.creditor_id = cred.id
            LEFT JOIN `users` u ON cred.user_id = u.id OR (cred.user_id IS NULL AND (LOWER(cred.email) = LOWER(u.email) OR LOWER(cred.name) = LOWER(u.full_name)))
            LEFT JOIN `groups` g ON d.group_id = g.id
            WHERE d.debt_token = :token AND d.amount > 0
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Con nợ bấm nút xác nhận 'Tôi đã chuyển khoản'
     */
    public function notifyPayment(string $token): bool
    {
        $token = trim($token);
        if (empty($token)) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE `debts` 
            SET `payment_notified_at` = CURRENT_TIMESTAMP 
            WHERE `debt_token` = :token AND `amount` > 0
        ");
        return $stmt->execute([':token' => $token]);
    }

    /**
     * Tái tính toán lại toàn bộ nợ từ đầu dựa trên tất cả giao dịch và lịch sử trả nợ theo nhóm
     */
    public function recalculateAll(?int $groupId = null): array
    {
        $this->db->beginTransaction();
        try {
            if ($groupId !== null) {
                $this->db->prepare("DELETE FROM `debts` WHERE `group_id` = :gid")->execute([':gid' => $groupId]);
                $stmtTx = $this->db->prepare("SELECT `id` FROM `transactions` WHERE `status` = 'completed' AND `group_id` = :gid ORDER BY `id` ASC");
                $stmtTx->execute([':gid' => $groupId]);
                $stmtSt = $this->db->prepare("SELECT `debtor_id`, `creditor_id`, `amount`, `group_id` FROM `debt_settlements` WHERE `group_id` = :gid ORDER BY `id` ASC");
                $stmtSt->execute([':gid' => $groupId]);
            } else {
                $this->db->exec("DELETE FROM `debts`");
                $stmtTx = $this->db->query("SELECT `id` FROM `transactions` WHERE `status` = 'completed' ORDER BY `id` ASC");
                $stmtSt = $this->db->query("SELECT `debtor_id`, `creditor_id`, `amount`, `group_id` FROM `debt_settlements` ORDER BY `id` ASC");
            }

            $transactions = $stmtTx->fetchAll();
            foreach ($transactions as $tx) {
                $this->processTransaction((int)$tx['id']);
            }

            $settlements = $stmtSt->fetchAll();
            foreach ($settlements as $st) {
                $debtorId = (int)$st['debtor_id'];
                $creditorId = (int)$st['creditor_id'];
                $amount = (float)$st['amount'];
                $gid = isset($st['group_id']) ? (int)$st['group_id'] : 1;

                $stmt = $this->db->prepare("
                    SELECT `id`, `amount` FROM `debts` 
                    WHERE `debtor_id` = :debtor_id AND `creditor_id` = :creditor_id AND `group_id` = :gid
                ");
                $stmt->execute([':debtor_id' => $debtorId, ':creditor_id' => $creditorId, ':gid' => $gid]);
                $curr = $stmt->fetch();

                if ($curr) {
                    $currAmount = (float)$curr['amount'];
                    if ($amount >= $currAmount) {
                        $this->db->prepare("DELETE FROM `debts` WHERE `id` = :id")->execute([':id' => $curr['id']]);
                    } else {
                        $newAmount = round($currAmount - $amount, 2);
                        $this->db->prepare("UPDATE `debts` SET `amount` = :amount WHERE `id` = :id")
                                 ->execute([':amount' => $newAmount, ':id' => $curr['id']]);
                    }
                }
            }

            $this->db->commit();
            return $this->getSummary(null, $groupId);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
