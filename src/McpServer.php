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
                    $groupId = $this->getEffectiveGroupId($args);

                    $tx = $txModel->create($title, $payerId, $items, $status, $note, $groupId, $placeName, $placeAddress);

                    if ($status === 'completed') {
                        $debtManager->processTransaction((int)$tx['id']);
                    }

                    return [
                        'transaction'   => $tx,
                        'group_id'      => $groupId,
                        'debts_updated' => ($status === 'completed'),
                    ];
                })(),

                'transaction_update' => (function () use ($txModel, $debtManager, $args) {
                    $id = (int)($args['id'] ?? 0);
                    $groupId = $this->getEffectiveGroupId($args);

                    $oldTx = $txModel->find($id);
                    if (!$oldTx) {
                        throw new \InvalidArgumentException("Không tìm thấy giao dịch với ID: {$id}");
                    }
                    if ((int)$oldTx['group_id'] !== $groupId) {
                        throw new \InvalidArgumentException("Giao dịch #{$id} không thuộc nhóm chi tiêu của bạn.");
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

                    return [
                        'transaction'        => $updatedTx,
                        'group_id'           => $groupId,
                        'debts_recalculated' => $debtsRecalculated,
                        'message'            => "Đã cập nhật giao dịch #{$id} thành công" . ($debtsRecalculated ? " và tự động tính toán lại công nợ của nhóm." : "."),
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
                'description' => 'Tạo giao dịch chi tiêu mới gồm nhiều sản phẩm/dịch vụ, hỗ trợ nhận diện món ăn/đồ uống theo tên hoặc ID, ưu tiên khớp theo vị trí quán/địa điểm (nếu chưa có trong danh mục sẽ tự động thêm mới), lấy giá mặc định hoặc giá tùy chỉnh, chỉ định người thanh toán và phân chia cho các thành viên.',
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
                                        'description' => 'Danh sách ID các thành viên cùng sử dụng',
                                        'items'       => ['type' => 'integer'],
                                    ],
                                    'shares'        => [
                                        'type'        => 'object',
                                        'description' => 'Phân chia tiền chi tiết theo thành viên {member_id: amount}. Nếu để trống sẽ tự động chia đều.',
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
                'description' => 'Cập nhật hoặc sửa thông tin giao dịch (đổi giá sản phẩm, sửa người trả tiền, thay đổi danh sách món, phân chia lại tiền người tham gia, đổi trạng thái). Hệ thống sẽ tự động tính toán lại toàn bộ bảng công nợ của nhóm.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id'            => ['type' => 'integer', 'description' => 'ID giao dịch cần cập nhật'],
                        'title'         => ['type' => 'string', 'description' => 'Tiêu đề giao dịch mới (tùy chọn)'],
                        'payer_id'      => ['type' => 'integer', 'description' => 'ID người thanh toán mới (tùy chọn)'],
                        'group_id'      => ['type' => 'integer', 'description' => 'ID nhóm chi tiêu (tùy chọn)'],
                        'place_name'    => ['type' => 'string', 'description' => 'Tên quán/địa điểm mới (tùy chọn)'],
                        'place_address' => ['type' => 'string', 'description' => 'Địa chỉ quán mới (tùy chọn)'],
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
                                        'description' => 'Danh sách ID các thành viên cùng sử dụng',
                                        'items'       => ['type' => 'integer'],
                                    ],
                                    'shares'        => [
                                        'type'        => 'object',
                                        'description' => 'Phân chia tiền chi tiết theo thành viên {member_id: amount}',
                                    ],
                                ],
                                'required' => ['member_ids'],
                            ],
                        ],
                    ],
                    'required' => ['id'],
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
        ];
    }
}
