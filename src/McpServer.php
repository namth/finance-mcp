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
                    return $productModel->all($groupId);
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
                    $groupId = $this->getEffectiveGroupId($args);

                    $tx = $txModel->create($title, $payerId, $items, $status, $note, $groupId);

                    if ($status === 'completed') {
                        $debtManager->processTransaction((int)$tx['id']);
                    }

                    return [
                        'transaction'   => $tx,
                        'group_id'      => $groupId,
                        'debts_updated' => ($status === 'completed'),
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
                'description' => 'Lấy danh sách tất cả các sản phẩm và dịch vụ trong nhóm.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'group_id' => ['type' => 'integer', 'description' => 'ID nhóm (tùy chọn)'],
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
                'description' => 'Tạo giao dịch chi tiêu mới gồm nhiều sản phẩm/dịch vụ, chỉ định người thanh toán và phân bổ thành viên tham gia từng món theo nhóm.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'title'    => ['type' => 'string', 'description' => 'Tiêu đề giao dịch'],
                        'payer_id' => ['type' => 'integer', 'description' => 'ID của thành viên đứng ra thanh toán toàn bộ hóa đơn'],
                        'group_id' => ['type' => 'integer', 'description' => 'ID nhóm chi tiêu (tùy chọn, mặc định là nhóm chính)'],
                        'status'   => [
                            'type'        => 'string',
                            'enum'        => ['draft', 'completed'],
                            'description' => 'Trạng thái giao dịch. Mặc định là "completed" (tính nợ ngay)',
                            'default'     => 'completed',
                        ],
                        'note'     => ['type' => 'string', 'description' => 'Ghi chú thêm về giao dịch'],
                        'items'    => [
                            'type'        => 'array',
                            'description' => 'Danh sách sản phẩm/dịch vụ trong hóa đơn',
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'product_id' => ['type' => 'integer', 'description' => 'ID sản phẩm/dịch vụ'],
                                    'price'      => ['type' => 'number', 'description' => 'Giá thực tế cho lần dùng này'],
                                    'quantity'   => ['type' => 'integer', 'description' => 'Số lượng (mặc định 1)', 'default' => 1],
                                    'note'       => ['type' => 'string', 'description' => 'Ghi chú cho món này'],
                                    'member_ids' => [
                                        'type'        => 'array',
                                        'description' => 'Danh sách ID các thành viên cùng sử dụng',
                                        'items'       => ['type' => 'integer'],
                                    ],
                                ],
                                'required' => ['product_id', 'member_ids'],
                            ],
                        ],
                    ],
                    'required' => ['title', 'payer_id', 'items'],
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
