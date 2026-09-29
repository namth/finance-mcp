<?php

namespace SimpleFinance\Models;

use PDO;
use SimpleFinance\Database;
use InvalidArgumentException;

class Settlement
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function record(int $debtorId, int $creditorId, float $amount, ?string $note = null, int $groupId = 1): array
    {
        if ($debtorId === $creditorId) {
            throw new InvalidArgumentException("Người nợ và chủ nợ không thể là cùng một người.");
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException("Số tiền gạch nợ phải lớn hơn 0.");
        }

        $stmt = $this->db->prepare("
            INSERT INTO `debt_settlements` (`group_id`, `debtor_id`, `creditor_id`, `amount`, `note`)
            VALUES (:group_id, :debtor_id, :creditor_id, :amount, :note)
        ");
        $stmt->execute([
            ':group_id'    => $groupId,
            ':debtor_id'   => $debtorId,
            ':creditor_id' => $creditorId,
            ':amount'      => round($amount, 2),
            ':note'        => $note ? trim($note) : null,
        ]);

        $id = (int)$this->db->lastInsertId();
        return $this->find($id);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, 
                   d.name AS debtor_name, d.phone AS debtor_phone,
                   c.name AS creditor_name, c.phone AS creditor_phone
            FROM `debt_settlements` s
            JOIN `members` d ON s.debtor_id = d.id
            JOIN `members` c ON s.creditor_id = c.id
            WHERE s.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function all(?int $memberId = null, int $limit = 50, int $offset = 0, ?int $groupId = null): array
    {
        $sql = "
            SELECT s.*, 
                   d.name AS debtor_name, d.phone AS debtor_phone,
                   c.name AS creditor_name, c.phone AS creditor_phone
            FROM `debt_settlements` s
            JOIN `members` d ON s.debtor_id = d.id
            JOIN `members` c ON s.creditor_id = c.id
        ";
        $conditions = [];
        $params = [];

        if ($groupId !== null) {
            $conditions[] = "s.group_id = :gid";
            $params[':gid'] = $groupId;
        }

        if ($memberId !== null) {
            $conditions[] = "(s.debtor_id = :mid OR s.creditor_id = :mid)";
            $params[':mid'] = $memberId;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY s.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
