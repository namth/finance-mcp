<?php

namespace SimpleFinance;

use SimpleFinance\Models\Member;
use SimpleFinance\Models\Product;
use SimpleFinance\Models\Transaction;
use SimpleFinance\Models\Settlement;
use SimpleFinance\Models\Group;
use SimpleFinance\Models\User;
use Throwable;

class McpServer
{
    private array $tools = [];
    private ?array $currentUser = null;

    public function __construct(?array $currentUser = null)
    {
        $this->currentUser = $currentUser;
        $this->registerTools();
    }

    /**
     * Khởi chạy vòng lặp xử lý STDIN/STDOUT cho giao thức MCP qua stdio
     */
    public function run(): void
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        while (($line = fgets(STDIN)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $request = json_decode($line, true);
            if (!is_array($request)) {
                $err = $this->buildError(null, -32700, "Parse error: Invalid JSON");
                echo json_encode($err, JSON_UNESCAPED_UNICODE) . "\n";
                flush();
                continue;
            }

            $response = $this->handleRequest($request);
            if ($response !== null) {
                echo json_encode($response, JSON_UNESCAPED_UNICODE) . "\n";
                flush();
            }
        }
    }

    /**
     * Xử lý request JSON-RPC và trả về response array
     */
    public function handleRequest(array $req): ?array
    {
        $id = $req['id'] ?? null;
        $method = $req['method'] ?? '';
        $params = $req['params'] ?? [];

        // Xử lý thông báo (Notification) không cần trả lời nếu không có ID
        if ($id === null && str_starts_with($method, 'notifications/')) {
            return null;
        }

        switch ($method) {
            case 'initialize':
                return $this->buildResult($id, [
                    'protocolVersion' => '2024-11-05',
                    'capabilities'    => [
                        'tools' => new \stdClass(),
                    ],
                    'serverInfo'      => [
                        'name'    => 'simplefinance-mcp',
                        'version' => '2.0.0',
                    ],
                ]);

            case 'ping':
                return $this->buildResult($id, new \stdClass());

            case 'tools/list':
                return $this->buildResult($id, [
                    'tools' => array_values($this->tools),
                ]);

            case 'tools/call':
                $toolName = $params['name'] ?? '';
                $arguments = (array)($params['arguments'] ?? []);
                return $this->executeTool($id, $toolName, $arguments);

            default:
                return $this->buildError($id, -32601, "Method not found: {$method}");
        }
    }

    private function getEffectiveGroupId(array $args): int
    {
        $groupModel = new Group();
        $userId = $this->currentUser ? (int)$this->currentUser['id'] : null;

        if (isset($args['group_id']) && (int)$args['group_id'] > 0) {
            $groupId = (int)$args['group_id'];
            if ($userId && !$groupModel->userHasAccess($groupId, $userId)) {
                throw new \InvalidArgumentException("Bạn không có quyền truy cập vào nhóm #{$groupId}.");
            }
            return $groupId;
        }

        if ($userId) {
            $userGroups = $groupModel->getUserGroups($userId);
            if (!empty($userGroups)) {
                return (int)$userGroups[0]['id'];
            }
            // Tạo nhóm mặc định nếu chưa có
            $g = $groupModel->create("Chi Tiêu của " . $this->currentUser['full_name'], $userId);
            return (int)$g['id'];
        }

        return 1; // Fallback
    }

    /**
     * Tự động tổng hợp và tính toán chi tiết phần tiền mỗi thành viên phải trả,
     * đồng thời tạo báo cáo văn bản tóm tắt thân thiện cho người dùng/AI.
     */
    private function buildTransactionSplitSummary(array $tx): array
    {
        $payerId = (int)($tx['payer_id'] ?? 0);
        $payerName = $tx['payer_name'] ?? "Thành viên #{$payerId}";
        $totalAmount = (float)($tx['total_amount'] ?? 0);
        $title = $tx['title'] ?? 'Giao dịch';
        $placeName = $tx['place_name'] ?? null;
        $placeAddress = $tx['place_address'] ?? null;

        $memberShares = [];
        $itemsBreakdown = [];

        foreach ($tx['items'] ?? [] as $item) {
            $pName = $item['product_name'] ?? 'Món';
            $qty = max(1, (int)($item['quantity'] ?? 1));
            $price = (float)($item['price'] ?? 0);
            $subtotal = (!empty($item['subtotal']) && (float)$item['subtotal'] > 0) ? (float)$item['subtotal'] : round($price * $qty, 2);
            $sharedNames = [];

            foreach ($item['members'] ?? [] as $m) {
                $mId = (int)$m['member_id'];
                $mName = $m['member_name'] ?? "Thành viên #{$mId}";
                $sAmount = (float)($m['share_amount'] ?? 0);

                $sharedNames[] = $mName;
                if (!isset($memberShares[$mId])) {
                    $memberShares[$mId] = [
                        'member_id'   => $mId,
                        'member_name' => $mName,
                        'total_share' => 0.0,
                    ];
                }
                $memberShares[$mId]['total_share'] = round($memberShares[$mId]['total_share'] + $sAmount, 2);
            }

            $itemsBreakdown[] = [
                'product_name' => $pName,
                'quantity'     => $qty,
                'price'        => $price,
                'subtotal'     => $subtotal,
                'shared_by'    => $sharedNames,
                'per_person'   => count($sharedNames) > 0 ? round($subtotal / count($sharedNames), 2) : 0,
            ];
        }

        $spentAtRaw = $tx['spent_at'] ?? $tx['created_at'] ?? null;
        $spentAtFormatted = $spentAtRaw ? date('d/m/Y', strtotime($spentAtRaw)) : date('d/m/Y');

        // Tạo văn bản tóm tắt rõ ràng bằng tiếng Việt cho người dùng/AI
        $lines = [];
        $lines[] = "🧾 Giao dịch: \"{$title}\" (Ngày chi: {$spentAtFormatted})" . ($placeName ? " tại {$placeName}" . ($placeAddress ? " ({$placeAddress})" : "") : "");
        $lines[] = "💰 Tổng hóa đơn: " . number_format($totalAmount, 0, ',', '.') . " đ";
        $lines[] = "👤 Người thanh toán: {$payerName} (đã thanh toán toàn bộ " . number_format($totalAmount, 0, ',', '.') . " đ)";
        $lines[] = "📊 Kết quả hệ thống tự động phân chia tiền:";

        foreach ($memberShares as $mId => $m) {
            $formattedAmount = number_format($m['total_share'], 0, ',', '.') . " đ";
            if ($mId === $payerId) {
                $lines[] = "  • {$m['member_name']}: {$formattedAmount} (phần tiền tự chi trả)";
            } else {
                $lines[] = "  • {$m['member_name']}: {$formattedAmount} (cần trả lại cho {$payerName})";
            }
        }

        if (!empty($itemsBreakdown)) {
            $lines[] = "🍽️ Chi tiết từng món:";
            foreach ($itemsBreakdown as $idx => $ib) {
                $stFormatted = number_format($ib['subtotal'], 0, ',', '.') . " đ";
                $perFormatted = number_format($ib['per_person'], 0, ',', '.') . " đ/người";
                $membersStr = implode(', ', $ib['shared_by']);
                $num = $idx + 1;
                $lines[] = "  {$num}. {$ib['product_name']} x{$ib['quantity']}: {$stFormatted} [{$membersStr}] -> {$perFormatted}";
            }
        }

        return [
            'total_amount'    => $totalAmount,
            'payer_id'        => $payerId,
            'payer_name'      => $payerName,
            'spent_at'        => $spentAtRaw,
            'member_shares'   => array_values($memberShares),
            'items_breakdown' => $itemsBreakdown,
            'summary_text'    => implode("\n", $lines),
        ];
    }

    private function executeTool($id, string $name, array $args): array
    {
        try {
            $memberModel = new Member();
            $productModel = new Product();
            $txModel = new Transaction();
            $debtManager = new DebtManager();
            $settlementModel = new Settlement();
            $groupModel = new Group();

            $resultData = match ($name) {
                // ==================== GROUPS ====================
                'group_list' => (function () use ($groupModel) {
                    if (!$this->currentUser) {
                        return [['id' => 1, 'name' => 'Nhóm Mặc Định', 'role' => 'owner']];
                    }
                    return $groupModel->getUserGroups((int)$this->currentUser['id']);
                })(),

                'group_create' => (function () use ($groupModel, $args) {
                    if (!$this->currentUser) {
                        throw new \InvalidArgumentException("Cần đăng nhập tài khoản để tạo nhóm.");
                    }
                    $name = trim((string)($args['name'] ?? ''));
                    $desc = isset($args['description']) ? trim((string)$args['description']) : null;
                    return $groupModel->create($name, (int)$this->currentUser['id'], $desc);
                })(),

                // ==================== MEMBERS ====================
                'member_create' => (function () use ($memberModel, $args) {
                    $name = (string)($args['name'] ?? '');
                    $phone = isset($args['phone']) ? (string)$args['phone'] : null;
                    $email = isset($args['email']) ? (string)$args['email'] : null;
                    return $memberModel->create($name, $phone, $email);
                })(),

                'member_list' => $memberModel->all(),

                'member_get' => (function () use ($memberModel, $args) {
                    $id = (int)($args['id'] ?? 0);
                    $member = $memberModel->find($id);
                    if (!$member) {
                        throw new \InvalidArgumentException("Không tìm thấy thành viên với ID: {$id}");
                    }
                    return $member;
                })(),

                'member_update' => (function () use ($memberModel, $args) {
                    $id = (int)($args['id'] ?? 0);
                    $res = $memberModel->update($id, $args);
                    if (!$res) {
                        throw new \InvalidArgumentException("Không tìm thấy thành viên với ID: {$id}");
                    }
                    return $res;
                })(),

                'member_delete' => (function () use ($memberModel, $args) {
                    $id = (int)($args['id'] ?? 0);
                    $ok = $memberModel->delete($id);
                    return ['success' => $ok, 'message' => "Đã xóa thành viên #{$id}."];
                })(),

                // ==================== PRODUCTS ====================
                'product_create' => (function () use ($productModel, $args) {
                    $name = (string)($args['name'] ?? '');
                    $price = isset($args['default_price']) && $args['default_price'] !== null ? (float)$args['default_price'] : null;
                    $desc = isset($args['description']) ? (string)$args['description'] : null;
                    $groupId = $this->getEffectiveGroupId($args);
                    return $productModel->create($name, $price, $desc, $groupId);
                })(),

                'product_list' => (function () use ($productModel, $args) {
                    $groupId = $this->getEffectiveGroupId($args);
                    $placeModel = new \SimpleFinance\Models\Place();

                    $placeId = isset($args['place_id']) && (int)$args['place_id'] > 0 ? (int)$args['place_id'] : null;
                    $placeName = isset($args['place_name']) ? (string)$args['place_name'] : null;
                    $placeAddress = isset($args['place_address']) ? (string)$args['place_address'] : null;
                    $placeQuery = isset($args['place']) ? (string)$args['place'] : null;
                    $search = isset($args['name']) ? (string)$args['name'] : (isset($args['search']) ? (string)$args['search'] : null);

                    $matchedPlace = null;
                    $hasPlaceFilter = ($placeId !== null || $placeName !== null || $placeAddress !== null || $placeQuery !== null);

                    if ($hasPlaceFilter) {
                        $matchedPlace = $placeModel->resolvePlace($placeId, $placeName, $placeAddress, $placeQuery, $groupId);
                    }

                    if ($matchedPlace) {
                        // Xác định được quán: lấy toàn bộ danh sách sản phẩm trong quán đó ra (kèm lọc theo tên món nếu có)
                        $products = $productModel->all($groupId, (int)$matchedPlace['id'], $search);
                        return [
                            'place_identified' => $matchedPlace,
                            'filter_mode'      => 'by_place',
                            'message'          => "Đã xác định quán '{$matchedPlace['name']}'" . ($matchedPlace['address'] ? " ({$matchedPlace['address']})" : "") . " và lấy danh sách sản phẩm thuộc quán này.",
                            'total'            => count($products),
                            'products'         => $products,
                        ];
                    } else {
                        // Không xác định quán: lấy danh sách sản phẩm phù hợp trong nhóm
                        $products = $productModel->all($groupId, null, $search);
                        return [
                            'place_identified' => null,
                            'filter_mode'      => 'group_fallback',
                            'message'          => $hasPlaceFilter
                                ? "Không tìm thấy quán phù hợp trong hệ thống, tự động lấy danh sách sản phẩm trong nhóm."
                                : "Danh sách sản phẩm trong nhóm.",
                            'total'            => count($products),
                            'products'         => $products,
                        ];
                    }
                })(),

                'product_get' => (function () use ($productModel, $args) {
                    $id = (int)($args['id'] ?? 0);
                    $prod = $productModel->find($id);
                    if (!$prod) {
                        throw new \InvalidArgumentException("Không tìm thấy sản phẩm với ID: {$id}");
                    }
                    return $prod;
                })(),

                'product_update' => (function () use ($productModel, $args) {
                    $id = (int)($args['id'] ?? 0);
                    $res = $productModel->update($id, $args);
                    if (!$res) {
                        throw new \InvalidArgumentException("Không tìm thấy sản phẩm với ID: {$id}");
                    }
                    return $res;
                })(),

                'product_delete' => (function () use ($productModel, $args) {
                    $id = (int)($args['id'] ?? 0);
                    $ok = $productModel->delete($id);
                    return ['success' => $ok, 'message' => "Đã xóa sản phẩm #{$id}."];
                })(),

                // ==================== TRANSACTIONS ====================
                'transaction_create' => (function () use ($txModel, $debtManager, $args) {
                    $title = (string)($args['title'] ?? '');
                    $payerId = (int)($args['payer_id'] ?? 0);
                    $items = (array)($args['items'] ?? []);
                    $status = (string)($args['status'] ?? 'completed');
                    $note = isset($args['note']) ? (string)$args['note'] : null;
                    $placeName = isset($args['place_name']) ? (string)$args['place_name'] : null;
                    $placeAddress = isset($args['place_address']) ? (string)$args['place_address'] : null;
                    $spentAt = isset($args['spent_at']) ? (string)$args['spent_at'] : null;
                    $groupId = $this->getEffectiveGroupId($args);

                    $tx = $txModel->create($title, $payerId, $items, $status, $note, $groupId, $placeName, $placeAddress, null, $spentAt);

                    if ($status === 'completed') {
                        $debtManager->processTransaction((int)$tx['id']);
                    }

                    $splitSummary = $this->buildTransactionSplitSummary($tx);

                    return [
                        'success'       => true,
                        'message'       => "Đã tạo giao dịch #{$tx['id']} thành công. Hệ thống đã tự động tính toán chi phí và phân chia tiền cho từng thành viên.",
                        'summary_text'  => $splitSummary['summary_text'],
                        'split_summary' => $splitSummary,
                        'transaction'   => $tx,
                        'group_id'      => $groupId,
                        'debts_updated' => ($status === 'completed'),
                    ];
                })(),

                'transaction_update' => (function () use ($txModel, $debtManager, $args) {
                    $id = (int)($args['id'] ?? $args['transaction_id'] ?? 0);
                    $groupId = $this->getEffectiveGroupId($args);

                    $oldTx = $txModel->find($id);
                    if (!$oldTx) {
                        throw new \InvalidArgumentException("Không tìm thấy giao dịch với ID: {$id}");
                    }
                    if ((int)$oldTx['group_id'] !== $groupId) {
                        throw new \InvalidArgumentException("Giao dịch #{$id} không thuộc nhóm chi tiêu của bạn.");
                    }

                    // Nếu caller truyền sửa giá của một món cụ thể ngay trong transaction_update
                    if (isset($args['price']) && (!empty($args['item_id']) || !empty($args['product_name']) || !empty($args['product_id'])) && !isset($args['items'])) {
                        $itemId = (int)($args['item_id'] ?? 0);
                        $productName = trim((string)($args['product_name'] ?? ''));
                        $productId = (int)($args['product_id'] ?? 0);

                        if ($itemId <= 0) {
                            foreach ($oldTx['items'] as $it) {
                                if ($productId > 0 && (int)$it['product_id'] === $productId) {
                                    $itemId = (int)$it['id'];
                                    break;
                                }
                                if (!empty($productName) && mb_stripos($it['product_name'], $productName) !== false) {
                                    $itemId = (int)$it['id'];
                                    break;
                                }
                            }
                        }

                        if ($itemId > 0) {
                            $newPrice = (float)$args['price'];
                            $newQuantity = isset($args['quantity']) && (int)$args['quantity'] > 0 ? (int)$args['quantity'] : null;
                            $txModel->updateItemPrice($id, $itemId, $newPrice, $newQuantity);
                        }
                    }

                    $updatedTx = $txModel->update($id, $args, $groupId);
                    if (!$updatedTx) {
                        throw new \InvalidArgumentException("Không thể cập nhật giao dịch #{$id}.");
                    }

                    // Tự động tính toán lại công nợ nhóm nếu giao dịch trước đó hoặc sau khi sửa ở trạng thái 'completed'
                    $debtsRecalculated = false;
                    if ($oldTx['status'] === 'completed' || $updatedTx['status'] === 'completed') {
                        $debtManager->recalculateAll($groupId);
                        $debtsRecalculated = true;
                    }

                    $splitSummary = $this->buildTransactionSplitSummary($updatedTx);

                    return [
                        'success'            => true,
                        'message'            => "Đã cập nhật giao dịch #{$id} thành công" . ($debtsRecalculated ? " và tự động tính toán lại công nợ của nhóm." : "."),
                        'summary_text'       => $splitSummary['summary_text'],
                        'split_summary'      => $splitSummary,
                        'transaction'        => $updatedTx,
                        'group_id'           => $groupId,
                        'debts_recalculated' => $debtsRecalculated,
                    ];
                })(),

                'transaction_item_update' => (function () use ($txModel, $debtManager, $args) {
                    $transactionId = (int)($args['transaction_id'] ?? $args['id'] ?? 0);
                    if ($transactionId <= 0) {
                        throw new \InvalidArgumentException("Vui lòng cung cấp ID giao dịch (transaction_id).");
                    }
                    $groupId = $this->getEffectiveGroupId($args);

                    $tx = $txModel->find($transactionId);
                    if (!$tx) {
                        throw new \InvalidArgumentException("Không tìm thấy giao dịch với ID: {$transactionId}");
                    }
                    if ((int)$tx['group_id'] !== $groupId) {
                        throw new \InvalidArgumentException("Giao dịch #{$transactionId} không thuộc nhóm chi tiêu của bạn.");
                    }

                    // Tìm item_id trong transaction:
                    $itemId = (int)($args['item_id'] ?? 0);
                    $productName = trim((string)($args['product_name'] ?? ''));
                    $productId = (int)($args['product_id'] ?? 0);

                    if ($itemId <= 0) {
                        // Tìm theo product_id hoặc product_name trong danh sách items của tx
                        foreach ($tx['items'] as $it) {
                            if ($productId > 0 && (int)$it['product_id'] === $productId) {
                                $itemId = (int)$it['id'];
                                break;
                            }
                            if (!empty($productName) && mb_stripos($it['product_name'], $productName) !== false) {
                                $itemId = (int)$it['id'];
                                break;
                            }
                        }
                    }

                    if ($itemId <= 0) {
                        throw new \InvalidArgumentException("Không tìm thấy món cần sửa trong giao dịch #{$transactionId}. Vui lòng cung cấp item_id, product_name hoặc product_id chính xác.");
                    }

                    if (!isset($args['price'])) {
                        throw new \InvalidArgumentException("Vui lòng nhập đơn giá mới (price).");
                    }
                    $newPrice = (float)$args['price'];
                    $newQuantity = isset($args['quantity']) && (int)$args['quantity'] > 0 ? (int)$args['quantity'] : null;

                    $txModel->updateItemPrice($transactionId, $itemId, $newPrice, $newQuantity);

                    $debtsRecalculated = false;
                    if ($tx['status'] === 'completed') {
                        $debtManager->recalculateAll($groupId);
                        $debtsRecalculated = true;
                    }

                    $updatedTx = $txModel->find($transactionId);
                    $splitSummary = $this->buildTransactionSplitSummary($updatedTx);

                    return [
                        'success'            => true,
                        'message'            => "Đã cập nhật giá món trong giao dịch #{$transactionId} thành công" . ($debtsRecalculated ? " và tự động tính toán lại công nợ của nhóm." : "."),
                        'summary_text'       => $splitSummary['summary_text'],
                        'split_summary'      => $splitSummary,
                        'transaction'        => $updatedTx,
                        'group_id'           => $groupId,
                        'debts_recalculated' => $debtsRecalculated,
                    ];
                })(),

                'transaction_delete' => (function () use ($txModel, $debtManager, $args) {
                    $id = (int)($args['id'] ?? 0);
                    $groupId = $this->getEffectiveGroupId($args);

                    $oldTx = $txModel->find($id);
                    if (!$oldTx) {
                        throw new \InvalidArgumentException("Không tìm thấy giao dịch với ID: {$id}");
                    }
                    if ((int)$oldTx['group_id'] !== $groupId) {
                        throw new \InvalidArgumentException("Giao dịch #{$id} không thuộc nhóm chi tiêu của bạn.");
                    }

                    $wasCompleted = ($oldTx['status'] === 'completed');
                    $ok = $txModel->delete($id, $groupId);

                    $debtsRecalculated = false;
                    if ($ok && $wasCompleted) {
                        $debtManager->recalculateAll($groupId);
                        $debtsRecalculated = true;
                    }

                    return [
                        'success'            => $ok,
                        'group_id'           => $groupId,
                        'debts_recalculated' => $debtsRecalculated,
                        'message'            => "Đã xóa giao dịch #{$id}" . ($debtsRecalculated ? " và tự động điều chỉnh lại toàn bộ bảng công nợ." : "."),
                    ];
                })(),

                'transaction_list' => (function () use ($txModel, $args) {
                    $status = isset($args['status']) ? (string)$args['status'] : null;
                    $limit = isset($args['limit']) ? (int)$args['limit'] : 50;
                    $offset = isset($args['offset']) ? (int)$args['offset'] : 0;
                    $groupId = $this->getEffectiveGroupId($args);
                    return $txModel->all($status, $limit, $offset, $groupId);
                })(),

                'transaction_get' => (function () use ($txModel, $args) {
                    $id = (int)($args['id'] ?? 0);
                    $tx = $txModel->find($id);
                    if (!$tx) {
                        throw new \InvalidArgumentException("Không tìm thấy giao dịch với ID: {$id}");
                    }
                    $splitSummary = $this->buildTransactionSplitSummary($tx);
                    $tx['split_summary'] = $splitSummary;
                    $tx['summary_text'] = $splitSummary['summary_text'];
                    return $tx;
                })(),

                'transaction_complete' => (function () use ($txModel, $debtManager, $args) {
                    $id = (int)($args['id'] ?? 0);
                    $tx = $txModel->updateStatus($id, 'completed');
                    if (!$tx) {
                        throw new \InvalidArgumentException("Không tìm thấy giao dịch với ID: {$id}");
                    }
                    $debtManager->processTransaction($id);
                    return [
                        'transaction' => $tx,
                        'message'     => "Đã hoàn thành giao dịch #{$id} và cập nhật bảng công nợ tự động.",
                    ];
                })(),

                // ==================== DEBTS & SETTLEMENTS ====================
                'debt_summary' => (function () use ($debtManager, $args) {
                    $memberId = isset($args['member_id']) ? (int)$args['member_id'] : null;
                    $groupId = $this->getEffectiveGroupId($args);
                    return $debtManager->getSummary($memberId, $groupId);
                })(),

                'debt_settle' => (function () use ($debtManager, $args) {
                    $debtorId = (int)($args['debtor_id'] ?? 0);
                    $creditorId = (int)($args['creditor_id'] ?? 0);
                    $amount = (float)($args['amount'] ?? 0);
                    $note = isset($args['note']) ? (string)$args['note'] : null;
                    $groupId = $this->getEffectiveGroupId($args);

                    return $debtManager->settle($debtorId, $creditorId, $amount, $note, $groupId);
                })(),

                'settlement_history_list' => (function () use ($settlementModel, $args) {
                    $memberId = isset($args['member_id']) ? (int)$args['member_id'] : null;
                    $limit = isset($args['limit']) ? (int)$args['limit'] : 50;
                    $offset = isset($args['offset']) ? (int)$args['offset'] : 0;
                    $groupId = $this->getEffectiveGroupId($args);
                    return $settlementModel->all($memberId, $limit, $offset, $groupId);
                })(),

                'debt_recalculate_all' => (function () use ($debtManager, $args) {
                    $groupId = $this->getEffectiveGroupId($args);
                    $summary = $debtManager->recalculateAll($groupId);
                    return [
                        'message'  => "Đã tính toán lại toàn bộ công nợ cho nhóm #{$groupId} thành công.",
                        'group_id' => $groupId,
                        'debts'    => $summary,
                    ];
                })(),

                'payment_qr_get' => (function () use ($debtManager, $memberModel, $args) {
                    $groupId = $this->getEffectiveGroupId($args);
                    $debtId = (int)($args['debt_id'] ?? 0);
                    $debtToken = trim((string)($args['debt_token'] ?? ''));
                    $debtorId = (int)($args['debtor_id'] ?? 0);
                    $creditorId = (int)($args['creditor_id'] ?? 0);
                    $amount = isset($args['amount']) ? (float)$args['amount'] : 0.0;
                    $memo = trim((string)($args['memo'] ?? ''));

                    $debt = null;
                    if ($debtId > 0) {
                        $summary = $debtManager->getSummary(null, $groupId);
                        foreach ($summary as $s) {
                            if ((int)$s['id'] === $debtId) {
                                $debt = $s;
                                break;
                            }
                        }
                    } elseif (!empty($debtToken)) {
                        $debt = $debtManager->getDebtByToken($debtToken);
                    } elseif ($debtorId > 0 && $creditorId > 0) {
                        $summary = $debtManager->getSummary($debtorId, $groupId);
                        foreach ($summary as $s) {
                            if ((int)$s['debtor_id'] === $debtorId && (int)$s['creditor_id'] === $creditorId) {
                                $debt = $s;
                                break;
                            }
                        }
                    }

                    $creditorName = 'Người nhận';
                    $debtorName = 'Người chuyển';
                    $bankBin = trim((string)($args['bank_bin'] ?? ''));
                    $bankName = '';
                    $accountNo = trim((string)($args['bank_account_no'] ?? ''));
                    $accountName = trim((string)($args['bank_account_name'] ?? ''));

                    if ($debt) {
                        $debtorName = $debt['debtor_name'] ?? 'Người chuyển';
                        $creditorName = $debt['creditor_name'] ?? 'Người nhận';
                        if ($amount <= 0) {
                            $amount = (float)$debt['amount'];
                        }
                        if (empty($bankBin) && !empty($debt['bank_bin'])) {
                            $bankBin = $debt['bank_bin'];
                            $bankName = $debt['bank_name'] ?? '';
                            $accountNo = $debt['bank_account_no'] ?? '';
                            $accountName = $debt['bank_account_name'] ?: $debt['creditor_name'];
                        }
                        $debtToken = $debt['debt_token'] ?? $debtToken;
                    } else {
                        if ($creditorId > 0) {
                            $c = $memberModel->find($creditorId);
                            if ($c) {
                                $creditorName = $c['name'];
                            }
                        }
                        if ($debtorId > 0) {
                            $d = $memberModel->find($debtorId);
                            if ($d) {
                                $debtorName = $d['name'];
                            }
                        }
                    }

                    if (empty($bankBin) || empty($accountNo)) {
                        throw new \InvalidArgumentException("Người nhận ({$creditorName}) chưa cấu hình tài khoản ngân hàng trong hệ thống. Vui lòng cung cấp bank_bin và bank_account_no hoặc yêu cầu {$creditorName} cập nhật thông tin ngân hàng trong trang cá nhân.");
                    }

                    if ($amount <= 0) {
                        throw new \InvalidArgumentException("Vui lòng cung cấp số tiền cần thanh toán (amount).");
                    }

                    $bank = BankList::findByBin($bankBin);
                    if ($bank) {
                        $bankName = $bank['short_name'] . ' (' . $bank['name'] . ')';
                    }

                    if (empty($memo)) {
                        $memo = BankList::cleanMemo("{$debtorName} tra {$creditorName}");
                    } else {
                        $memo = BankList::cleanMemo($memo);
                    }

                    $qrImageUrl = BankList::generateVietQrUrl($bankBin, $accountNo, $amount, $memo, $accountName ?: $creditorName);
                    $payUrl = !empty($debtToken) ? "https://financemcp.oa.io.vn/pay.php?token=" . urlencode($debtToken) : null;

                    $amountFormatted = number_format($amount, 0, ',', '.') . ' đ';

                    $lines = [];
                    $lines[] = "💳 THÔNG TIN THANH TOÁN VIETQR";
                    $lines[] = "• Người nhận: {$creditorName}" . ($accountName ? " ({$accountName})" : "");
                    $lines[] = "• Ngân hàng: {$bankName} (Mã BIN: {$bankBin})";
                    $lines[] = "• Số tài khoản: {$accountNo}";
                    $lines[] = "• Số tiền: {$amountFormatted}";
                    $lines[] = "• Nội dung chuyển khoản: {$memo}";
                    $lines[] = "• Ảnh mã QR VietQR: {$qrImageUrl}";
                    if ($payUrl) {
                        $lines[] = "• Link thanh toán công khai: {$payUrl}";
                    }

                    return [
                        'success'         => true,
                        'qr_image_url'    => $qrImageUrl,
                        'pay_url'         => $payUrl,
                        'amount'          => $amount,
                        'memo'            => $memo,
                        'bank_info'       => [
                            'bank_bin'          => $bankBin,
                            'bank_name'         => $bankName,
                            'bank_account_no'   => $accountNo,
                            'bank_account_name' => $accountName ?: $creditorName,
                        ],
                        'debtor_name'     => $debtorName,
                        'creditor_name'   => $creditorName,
                        'summary_text'    => implode("\n", $lines),
                    ];
                })(),

                default => throw new \InvalidArgumentException("Không hỗ trợ tool: {$name}")
            };

            return $this->buildResult($id, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($resultData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    ]
                ],
                'isError' => false,
            ]);
        } catch (Throwable $e) {
            return $this->buildResult($id, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Lỗi thực thi tool '{$name}': " . $e->getMessage(),
                    ]
                ],
                'isError' => true,
            ]);
        }
    }

    public function buildResult($id, mixed $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ];
    }

    public function buildError($id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
        ];
    }

    private function registerTools(): void
    {
        $this->tools = [
            // Groups
            'group_list' => [
                'name' => 'group_list',
                'description' => 'Lấy danh sách các nhóm chi tiêu mà người dùng hiện tại tham gia.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            'group_create' => [
                'name' => 'group_create',
                'description' => 'Tạo một nhóm chi tiêu mới cho người dùng hiện tại.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name'        => ['type' => 'string', 'description' => 'Tên nhóm (ví dụ: Du Lịch Đà Lạt, Quỹ Gia Đình)'],
                        'description' => ['type' => 'string', 'description' => 'Mô tả chi tiết nhóm (tùy chọn)'],
                    ],
                    'required' => ['name'],
                ],
            ],

            // Members
            'member_create' => [
                'name' => 'member_create',
                'description' => 'Thêm thành viên mới vào hệ thống chi tiêu.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name'  => ['type' => 'string', 'description' => 'Tên thành viên'],
                        'phone' => ['type' => 'string', 'description' => 'Số điện thoại (tùy chọn)'],
                        'email' => ['type' => 'string', 'description' => 'Email (tùy chọn)'],
                    ],
                    'required' => ['name'],
                ],
            ],
            'member_list' => [
                'name' => 'member_list',
                'description' => 'Lấy danh sách tất cả các thành viên trong nhóm.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            'member_get' => [
                'name' => 'member_get',
                'description' => 'Xem chi tiết thông tin một thành viên bằng ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'ID thành viên'],
                    ],
                    'required' => ['id'],
                ],
            ],
            'member_update' => [
                'name' => 'member_update',
                'description' => 'Cập nhật thông tin thành viên (tên, số điện thoại, email).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id'    => ['type' => 'integer', 'description' => 'ID thành viên cần cập nhật'],
                        'name'  => ['type' => 'string', 'description' => 'Tên mới (tùy chọn)'],
                        'phone' => ['type' => 'string', 'description' => 'Số điện thoại mới (tùy chọn)'],
                        'email' => ['type' => 'string', 'description' => 'Email mới (tùy chọn)'],
                    ],
                    'required' => ['id'],
                ],
            ],
            'member_delete' => [
                'name' => 'member_delete',
                'description' => 'Xóa một thành viên khỏi hệ thống.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'ID thành viên cần xóa'],
                    ],
                    'required' => ['id'],
                ],
            ],

            // Products
            'product_create' => [
                'name' => 'product_create',
                'description' => 'Thêm sản phẩm hoặc dịch vụ vào danh mục của nhóm.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name'          => ['type' => 'string', 'description' => 'Tên sản phẩm/dịch vụ'],
                        'default_price' => ['type' => 'number', 'description' => 'Giá mặc định nếu có (để null nếu giá không cố định)'],
                        'description'   => ['type' => 'string', 'description' => 'Mô tả chi tiết sản phẩm/dịch vụ (tùy chọn)'],
                        'group_id'      => ['type' => 'integer', 'description' => 'ID nhóm (tùy chọn, mặc định là nhóm đầu tiên của user)'],
                    ],
                    'required' => ['name'],
                ],
            ],
            'product_list' => [
                'name' => 'product_list',
                'description' => 'Lấy danh sách sản phẩm/dịch vụ. Hỗ trợ xác định Quán/Địa điểm: Nếu xác định được quán thì sẽ lấy tất cả danh sách sản phẩm trong quán đó ra (với đúng giá tiền tại quán đó). Nếu không xác định quán thì sẽ lấy danh sách sản phẩm phù hợp trong nhóm.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'group_id'      => ['type' => 'integer', 'description' => 'ID nhóm chi tiêu (tùy chọn)'],
                        'place'         => ['type' => 'string', 'description' => 'Tên hoặc địa chỉ quán để hệ thống tự nhận diện (ví dụ: "Mai Chí Thọ", "The Coffee House", "Highlands")'],
                        'place_id'      => ['type' => 'integer', 'description' => 'ID cụ thể của quán nếu biết (tùy chọn)'],
                        'place_name'    => ['type' => 'string', 'description' => 'Tên quán (tùy chọn)'],
                        'place_address' => ['type' => 'string', 'description' => 'Địa chỉ hoặc khu vực của quán (tùy chọn, ví dụ: đường Mai Chí Thọ)'],
                        'name'          => ['type' => 'string', 'description' => 'Lọc theo tên sản phẩm/dịch vụ (tùy chọn, ví dụ: "cà phê", "trà đào")'],
                    ],
                ],
            ],
            'product_get' => [
                'name' => 'product_get',
                'description' => 'Xem chi tiết sản phẩm/dịch vụ bằng ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'ID sản phẩm/dịch vụ'],
                    ],
                    'required' => ['id'],
                ],
            ],
            'product_update' => [
                'name' => 'product_update',
                'description' => 'Cập nhật tên, giá mặc định hoặc mô tả sản phẩm.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id'            => ['type' => 'integer', 'description' => 'ID sản phẩm'],
                        'name'          => ['type' => 'string', 'description' => 'Tên mới (tùy chọn)'],
                        'default_price' => ['type' => 'number', 'description' => 'Giá mặc định mới (tùy chọn)'],
                        'description'   => ['type' => 'string', 'description' => 'Mô tả mới (tùy chọn)'],
                    ],
                    'required' => ['id'],
                ],
            ],
            'product_delete' => [
                'name' => 'product_delete',
                'description' => 'Xóa sản phẩm/dịch vụ khỏi danh mục.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'ID sản phẩm cần xóa'],
                    ],
                    'required' => ['id'],
                ],
            ],

            // Transactions
            'transaction_create' => [
                'name' => 'transaction_create',
                'description' => 'Tạo giao dịch chi tiêu mới gồm nhiều sản phẩm/dịch vụ. Hệ thống sẽ TỰ ĐỘNG tính toán chi phí, tự động chia đều tiền từng món cho các thành viên tham gia (member_ids) và trả về bảng chi tiết số tiền mỗi người phải chi trả/nợ.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'title'         => ['type' => 'string', 'description' => 'Tiêu đề giao dịch (ví dụ: Ăn trưa Highlands, Cafe sáng)'],
                        'payer_id'      => ['type' => 'integer', 'description' => 'ID của thành viên đứng ra thanh toán toàn bộ hóa đơn'],
                        'group_id'      => ['type' => 'integer', 'description' => 'ID nhóm chi tiêu (tùy chọn, mặc định là nhóm chính của user)'],
                        'place_name'    => ['type' => 'string', 'description' => 'Tên quán/địa điểm chung của giao dịch (ví dụ: The Coffee House, Highlands Coffee, Phở Thìn)'],
                        'place_address' => ['type' => 'string', 'description' => 'Địa chỉ hoặc vị trí của quán (ví dụ: đường Mai Chí Thọ, Quận 2)'],
                        'status'        => [
                            'type'        => 'string',
                            'enum'        => ['draft', 'completed'],
                            'description' => 'Trạng thái giao dịch. Mặc định là "completed" (tính nợ ngay)',
                            'default'     => 'completed',
                        ],
                        'spent_at'      => ['type' => 'string', 'description' => 'Ngày/thời gian thực tế phát sinh chi tiêu (định dạng YYYY-MM-DD hoặc YYYY-MM-DD HH:mm:ss, ví dụ: "2026-09-28"). Nếu để trống sẽ mặc định là thời điểm hiện tại.'],
                        'note'          => ['type' => 'string', 'description' => 'Ghi chú thêm về giao dịch'],
                        'items'         => [
                            'type'        => 'array',
                            'description' => 'Danh sách sản phẩm/dịch vụ trong hóa đơn',
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'product_id'    => ['type' => 'integer', 'description' => 'ID sản phẩm nếu đã có (tùy chọn nếu đã truyền product_name)'],
                                    'product_name'  => ['type' => 'string', 'description' => 'Tên sản phẩm/dịch vụ (nếu chưa có trong hệ thống, sẽ tự động thêm mới vào danh mục)'],
                                    'price'         => ['type' => 'number', 'description' => 'Giá thực tế cho lần dùng này. Nếu để trống sẽ tự lấy theo giá mặc định của sản phẩm trong danh mục.'],
                                    'quantity'      => ['type' => 'integer', 'description' => 'Số lượng (mặc định 1)', 'default' => 1],
                                    'note'          => ['type' => 'string', 'description' => 'Ghi chú cho món này'],
                                    'place_name'    => ['type' => 'string', 'description' => 'Tên quán cho món này (nếu khác tên quán chung)'],
                                    'place_address' => ['type' => 'string', 'description' => 'Địa chỉ quán cho món này (nếu khác địa chỉ chung)'],
                                    'member_ids'    => [
                                        'type'        => 'array',
                                        'description' => 'Danh sách ID các thành viên cùng sử dụng món này. Lưu ý quan trọng: Hệ thống sẽ TỰ ĐỘNG tính toán và chia đều số tiền của món cho các thành viên này (Người dùng/AI KHÔNG cần và KHÔNG phải tự nhập số tiền chia).',
                                        'items'       => ['type' => 'integer'],
                                    ],
                                ],
                                'required' => ['member_ids'],
                            ],
                        ],
                    ],
                    'required' => ['title', 'payer_id', 'items'],
                ],
            ],
            'transaction_update' => [
                'name' => 'transaction_update',
                'description' => 'Cập nhật hoặc sửa thông tin giao dịch (đổi giá sản phẩm, sửa người trả tiền, thay đổi danh sách món, đổi thành viên tham gia, đổi ngày chi tiêu thực tế, đổi trạng thái). Hệ thống sẽ TỰ ĐỘNG tính toán lại số tiền phân bổ cho từng thành viên và tự động tính toán lại toàn bộ bảng công nợ của nhóm.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id'            => ['type' => 'integer', 'description' => 'ID giao dịch cần cập nhật'],
                        'title'         => ['type' => 'string', 'description' => 'Tiêu đề giao dịch mới (tùy chọn)'],
                        'payer_id'      => ['type' => 'integer', 'description' => 'ID người thanh toán mới (tùy chọn)'],
                        'group_id'      => ['type' => 'integer', 'description' => 'ID nhóm chi tiêu (tùy chọn)'],
                        'place_name'    => ['type' => 'string', 'description' => 'Tên quán/địa điểm mới (tùy chọn)'],
                        'place_address' => ['type' => 'string', 'description' => 'Địa chỉ quán mới (tùy chọn)'],
                        'spent_at'      => ['type' => 'string', 'description' => 'Ngày/thời gian thực tế phát sinh chi tiêu mới (ví dụ: "2026-09-28")'],
                        'status'        => [
                            'type'        => 'string',
                            'enum'        => ['draft', 'completed', 'cancelled'],
                            'description' => 'Trạng thái giao dịch mới (tùy chọn)',
                        ],
                        'note'          => ['type' => 'string', 'description' => 'Ghi chú mới (tùy chọn)'],
                        'items'         => [
                            'type'        => 'array',
                            'description' => 'Danh sách món mới thay thế toàn bộ danh sách cũ (tùy chọn)',
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'product_id'    => ['type' => 'integer', 'description' => 'ID sản phẩm (tùy chọn nếu có product_name)'],
                                    'product_name'  => ['type' => 'string', 'description' => 'Tên món (tùy chọn nếu có product_id)'],
                                    'price'         => ['type' => 'number', 'description' => 'Giá món. Nếu để trống sẽ lấy giá mặc định trong danh mục'],
                                    'quantity'      => ['type' => 'integer', 'description' => 'Số lượng (mặc định 1)', 'default' => 1],
                                    'note'          => ['type' => 'string', 'description' => 'Ghi chú cho món'],
                                    'place_name'    => ['type' => 'string', 'description' => 'Tên quán cho món này'],
                                    'place_address' => ['type' => 'string', 'description' => 'Địa chỉ quán cho món này'],
                                    'member_ids'    => [
                                        'type'        => 'array',
                                        'description' => 'Danh sách ID các thành viên cùng sử dụng món này. Hệ thống TỰ ĐỘNG tính toán và chia đều tiền cho các thành viên.',
                                        'items'       => ['type' => 'integer'],
                                    ],
                                ],
                                'required' => ['member_ids'],
                            ],
                        ],
                    ],
                    'required' => ['id'],
                ],
            ],
            'transaction_item_update' => [
                'name' => 'transaction_item_update',
                'description' => 'Sửa nhanh đơn giá (price) hoặc số lượng (quantity) của một món/sản phẩm cụ thể trong giao dịch (ví dụ: lỡ nhập 30k muốn sửa lại thành 15k). Hệ thống sẽ TỰ ĐỘNG tính lại thành tiền của món, tự động chia đều lại số tiền cho các thành viên cùng dùng món đó, cập nhật tổng hóa đơn và tự động tính lại công nợ.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'transaction_id' => ['type' => 'integer', 'description' => 'ID giao dịch chứa món cần sửa'],
                        'item_id'        => ['type' => 'integer', 'description' => 'ID của món trong giao dịch (tùy chọn nếu truyền product_name hoặc product_id)'],
                        'product_name'   => ['type' => 'string', 'description' => 'Tên món cần sửa (nếu không nhớ item_id, hệ thống sẽ tự động tìm kiếm món theo tên)'],
                        'product_id'     => ['type' => 'integer', 'description' => 'ID sản phẩm của món cần sửa (tùy chọn)'],
                        'price'          => ['type' => 'number', 'description' => 'Đơn giá mới thực tế của món (ví dụ: 15000)'],
                        'quantity'       => ['type' => 'integer', 'description' => 'Số lượng mới (tùy chọn, nếu để trống sẽ giữ nguyên số lượng cũ)'],
                        'group_id'       => ['type' => 'integer', 'description' => 'ID nhóm chi tiêu (tùy chọn)'],
                    ],
                    'required' => ['transaction_id', 'price'],
                ],
            ],
            'transaction_delete' => [
                'name' => 'transaction_delete',
                'description' => 'Xóa một giao dịch khỏi hệ thống. Nếu giao dịch đã hoàn thành và tính nợ, hệ thống sẽ tự động tính toán và điều chỉnh lại toàn bộ bảng công nợ của nhóm.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id'       => ['type' => 'integer', 'description' => 'ID giao dịch cần xóa'],
                        'group_id' => ['type' => 'integer', 'description' => 'ID nhóm chi tiêu (tùy chọn)'],
                    ],
                    'required' => ['id'],
                ],
            ],
            'transaction_list' => [
                'name' => 'transaction_list',
                'description' => 'Lấy danh sách các giao dịch trong nhóm.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'group_id' => ['type' => 'integer', 'description' => 'ID nhóm (tùy chọn)'],
                        'status'   => ['type' => 'string', 'enum' => ['draft', 'completed', 'cancelled']],
                        'limit'    => ['type' => 'integer', 'default' => 50],
                        'offset'   => ['type' => 'integer', 'default' => 0],
                    ],
                ],
            ],
            'transaction_get' => [
                'name' => 'transaction_get',
                'description' => 'Xem chi tiết 1 giao dịch.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'ID giao dịch'],
                    ],
                    'required' => ['id'],
                ],
            ],
            'transaction_complete' => [
                'name' => 'transaction_complete',
                'description' => 'Xác nhận hoàn thành giao dịch và tự động cập nhật công nợ trong nhóm.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'ID giao dịch'],
                    ],
                    'required' => ['id'],
                ],
            ],

            // Debts & Settlements
            'debt_summary' => [
                'name' => 'debt_summary',
                'description' => 'Xem bảng tổng kết công nợ trong nhóm: Ai đang nợ ai bao nhiêu tiền (đã tự cấn trừ 2 chiều).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'group_id'  => ['type' => 'integer', 'description' => 'ID nhóm (tùy chọn)'],
                        'member_id' => ['type' => 'integer', 'description' => 'ID thành viên để lọc (tùy chọn)'],
                    ],
                ],
            ],
            'debt_settle' => [
                'name' => 'debt_settle',
                'description' => 'Gạch nợ khi đã thanh toán công nợ trong nhóm.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'debtor_id'   => ['type' => 'integer', 'description' => 'ID người trả nợ'],
                        'creditor_id' => ['type' => 'integer', 'description' => 'ID người nhận tiền'],
                        'amount'      => ['type' => 'number', 'description' => 'Số tiền thanh toán'],
                        'group_id'    => ['type' => 'integer', 'description' => 'ID nhóm (tùy chọn)'],
                        'note'        => ['type' => 'string', 'description' => 'Ghi chú thanh toán'],
                    ],
                    'required' => ['debtor_id', 'creditor_id', 'amount'],
                ],
            ],
            'settlement_history_list' => [
                'name' => 'settlement_history_list',
                'description' => 'Xem lịch sử các lần gạch nợ trong nhóm.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'group_id'  => ['type' => 'integer', 'description' => 'ID nhóm (tùy chọn)'],
                        'member_id' => ['type' => 'integer', 'description' => 'Lọc theo ID thành viên'],
                        'limit'     => ['type' => 'integer', 'default' => 50],
                        'offset'    => ['type' => 'integer', 'default' => 0],
                    ],
                ],
            ],
            'debt_recalculate_all' => [
                'name' => 'debt_recalculate_all',
                'description' => 'Tính toán lại toàn bộ bảng công nợ từ đầu cho một nhóm chi tiêu.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'group_id' => ['type' => 'integer', 'description' => 'ID nhóm (tùy chọn)'],
                    ],
                ],
            ],
            'payment_qr_get' => [
                'name' => 'payment_qr_get',
                'description' => 'Lấy link ảnh mã QR VietQR (chuẩn Napas/VietQR) và link thanh toán trực tuyến để trả nợ hoặc chuyển tiền. Hỗ trợ lấy theo debt_id, debt_token, hoặc theo creditor_id (người nhận) và debtor_id (người chuyển).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'debt_id'           => ['type' => 'integer', 'description' => 'ID khoản nợ cần thanh toán (nếu có)'],
                        'debt_token'        => ['type' => 'string',  'description' => 'Token thanh toán công khai của khoản nợ (nếu có)'],
                        'creditor_id'       => ['type' => 'integer', 'description' => 'ID người nhận tiền (chủ nợ)'],
                        'debtor_id'         => ['type' => 'integer', 'description' => 'ID người chuyển tiền (con nợ)'],
                        'amount'            => ['type' => 'number',  'description' => 'Số tiền cần chuyển (nếu không truyền sẽ tự lấy theo số tiền nợ)'],
                        'memo'              => ['type' => 'string',  'description' => 'Nội dung chuyển khoản (tùy chọn)'],
                        'bank_bin'          => ['type' => 'string',  'description' => 'Mã BIN ngân hàng nếu muốn chỉ định (ví dụ: 970422 cho MBBank, 970436 cho Vietcombank)'],
                        'bank_account_no'   => ['type' => 'string',  'description' => 'Số tài khoản ngân hàng người nhận nếu muốn chỉ định'],
                        'bank_account_name' => ['type' => 'string',  'description' => 'Tên chủ tài khoản người nhận nếu muốn chỉ định'],
                        'group_id'          => ['type' => 'integer', 'description' => 'ID nhóm chi tiêu (tùy chọn)'],
                    ],
                ],
            ],
        ];
    }
}
