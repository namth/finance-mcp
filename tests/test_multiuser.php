<?php

/**
 * Test Suite: Multi-User, AES-256 Memory Proof Login & Multi-Group MCP
 * Sử dụng SQLite In-Memory để test độc lập, nhanh chóng và an toàn
 */

// Đặt timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'SimpleFinance\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

// Giả lập PDO SQLite in-memory cho Database class bằng cách override reflection hoặc tạo schema
echo "====================================================\n";
echo " BẮT ĐẦU TEST TOÀN DIỆN SIMPLEFINANCE MULTI-USER\n";
echo "====================================================\n\n";

// 1. Test Crypto (AES-256-CBC)
echo "[1] Test Mã hóa & Giải mã AES-256-CBC (Security/Crypto.php)... ";
use SimpleFinance\Security\Crypto;

$originalDate = "14-07-1986";
$encrypted = Crypto::encrypt($originalDate);
assert(!empty($encrypted), "Encrypted string cannot be empty");
assert($encrypted !== $originalDate, "Encrypted string must not match original date");
$decrypted = Crypto::decrypt($encrypted);
assert($decrypted === $originalDate, "Decrypted date must match original date");

$apiKey = Crypto::generateApiKey();
assert(str_starts_with($apiKey, 'sf_usr_'), "API key must start with sf_usr_");
assert(strlen($apiKey) === 47, "API key length must be 47 chars");
echo "OK!\n";

// 2. Thiết lập SQLite In-Memory Database mô phỏng cấu trúc MySQL
echo "[2] Khởi tạo SQLite In-Memory mô phỏng schema MySQL... ";
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Tạo bảng SQLite tương thích
$pdo->exec("
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL UNIQUE,
    full_name TEXT NOT NULL,
    api_key TEXT NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_timeline_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    event_name TEXT NOT NULL,
    event_date_encrypted TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    owner_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE group_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    display_name TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'member',
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(group_id, user_id)
);

CREATE TABLE members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    phone TEXT,
    email TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL DEFAULT 1,
    name TEXT NOT NULL,
    default_price NUMERIC,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL DEFAULT 1,
    title TEXT NOT NULL,
    payer_id INTEGER NOT NULL,
    total_amount NUMERIC NOT NULL DEFAULT 0.00,
    status TEXT NOT NULL DEFAULT 'completed',
    note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE transaction_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    price NUMERIC NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    subtotal NUMERIC NOT NULL DEFAULT 0.00,
    note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE transaction_item_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    member_id INTEGER NOT NULL,
    share_amount NUMERIC NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE debts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL DEFAULT 1,
    debtor_id INTEGER NOT NULL,
    creditor_id INTEGER NOT NULL,
    amount NUMERIC NOT NULL DEFAULT 0.00,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(group_id, debtor_id, creditor_id)
);

CREATE TABLE debt_settlements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL DEFAULT 1,
    debtor_id INTEGER NOT NULL,
    creditor_id INTEGER NOT NULL,
    amount NUMERIC NOT NULL,
    note TEXT,
    settled_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

\SimpleFinance\Database::setConnection($pdo);
echo "OK!\n";

// 3. Test Đăng ký người dùng & Lưu mốc thời gian đã mã hóa
echo "[3] Test Đăng ký User 1 (Nam) & User 2 (Linh) kèm Timeline bí mật... ";
use SimpleFinance\Models\User;
use SimpleFinance\Models\Group;

$userModel = new User();

$eventsUser1 = [
    'Sinh nhật của tôi' => '14-07-1986',
    'Sinh nhật vợ tôi' => '21-09-1995',
    'Kỷ niệm ngày cưới' => '20-03-2021',
];
$u1 = $userModel->create('namtran', 'nam@example.com', 'Nam Tran', $eventsUser1);
assert($u1['id'] > 0, "User 1 ID must be > 0");

$eventsUser2 = [
    'Sinh nhật của tôi' => '05-12-1992',
    'Ngày tốt nghiệp' => '15-06-2014',
    'Mua xe đầu tiên' => '10-10-2018',
];
$u2 = $userModel->create('linhnguyen', 'linh@example.com', 'Linh Nguyen', $eventsUser2);
assert($u2['id'] > 0, "User 2 ID must be > 0");

// Kiểm tra trong database: Ngày tháng PHẢI được mã hóa, KHÔNG được lưu plain text
$rawEvents = $pdo->query("SELECT * FROM user_timeline_events WHERE user_id = " . $u1['id'])->fetchAll(PDO::FETCH_ASSOC);
foreach ($rawEvents as $re) {
    assert(!str_contains($re['event_date_encrypted'], '1986') && !str_contains($re['event_date_encrypted'], '1995') && !str_contains($re['event_date_encrypted'], '2021'), "Event date must be encrypted in DB, not plain text!");
}

// Kiểm tra giải mã
$decryptedEvents1 = $userModel->getDecryptedTimelineEvents($u1['id']);
assert(count($decryptedEvents1) === 3, "User 1 must have 3 decrypted events");
assert($decryptedEvents1['Sinh nhật của tôi'] === '14-07-1986', "User 1 event 1 decrypted date matches");
echo "OK!\n";

// 4. Test Nhóm & Phân quyền
echo "[4] Test Quản lý Nhóm và Phân quyền Cô lập... ";
$groupModel = new Group();
$u1Groups = $groupModel->getUserGroups($u1['id']);
assert(count($u1Groups) === 1, "User 1 must have 1 default group created automatically");
$defaultGroup1 = $u1Groups[0];

// User 1 tạo nhóm mới "Chuyến đi Đà Lạt"
$dalatGroup = $groupModel->create("Chuyến đi Đà Lạt", $u1['id'], "Đi chơi cuối tuần");
assert($groupModel->userHasAccess($dalatGroup['id'], $u1['id']), "User 1 has access to Da Lat group");
assert(!$groupModel->userHasAccess($dalatGroup['id'], $u2['id']), "User 2 DOES NOT have access to Da Lat group initially");

// User 1 mời User 2 vào nhóm Đà Lạt
$added = $groupModel->addMemberByUsernameOrEmail($dalatGroup['id'], 'linhnguyen');
assert($added, "User 2 added successfully to Da Lat group");
assert($groupModel->userHasAccess($dalatGroup['id'], $u2['id']), "User 2 now has access to Da Lat group");
assert(!$groupModel->userHasAccess($defaultGroup1['id'], $u2['id']), "User 2 still CANNOT access User 1's private default group");
echo "OK!\n";

// 5. Test Giao dịch & Công nợ theo từng nhóm riêng biệt
echo "[5] Test Ghi nhận chi tiêu và Tính công nợ theo nhóm (Debt Isolation)... ";
use SimpleFinance\Models\Product;
use SimpleFinance\Models\Transaction;
use SimpleFinance\DebtManager;

$memberModel = new \SimpleFinance\Models\Member();
$mNam = $memberModel->create('Nam Tran');
$mLinh = $memberModel->create('Linh Nguyen');

$productModel = new Product();
$pKhachSan = $productModel->create('Tiền Khách Sạn', 1000000, null, (int)$dalatGroup['id']);

$txModel = new Transaction();
// Nam trả 1.000.000 tiền khách sạn chia đôi cho Nam và Linh (mỗi người 500k)
$tx = $txModel->create(
    'Khách sạn 2 đêm',
    (int)$mNam['id'],
    [
        [
            'product_id' => (int)$pKhachSan['id'],
            'price'      => 1000000,
            'quantity'   => 1,
            'member_ids' => [(int)$mNam['id'], (int)$mLinh['id']],
            'shares'     => [
                $mNam['id'] => 500000,
                $mLinh['id'] => 500000,
            ]
        ]
    ],
    'completed',
    'Phòng 2 đêm',
    (int)$dalatGroup['id']
);

$debtMgr = new DebtManager();
$debtMgr->processTransaction((int)$tx['id'], (int)$dalatGroup['id']);

// Linh nợ Nam 500k trong nhóm Đà Lạt
$summaryDalat = $debtMgr->getSummary(null, (int)$dalatGroup['id']);
assert(count($summaryDalat) === 1, "There should be 1 active debt in Da Lat group");
assert((float)$summaryDalat[0]['amount'] === 500000.0, "Debt amount must be 500,000");
assert($summaryDalat[0]['debtor_name'] === 'Linh Nguyen', "Debtor is Linh");
assert($summaryDalat[0]['creditor_name'] === 'Nam Tran', "Creditor is Nam");

// Kiểm tra nhóm mặc định của User 1: Phải hoàn toàn sạch sẽ, không bị lẫn công nợ
$summaryDefault = $debtMgr->getSummary(null, (int)$defaultGroup1['id']);
assert(count($summaryDefault) === 0, "Default group must have 0 debts");
echo "OK!\n";

// 6. Test MCP Server với Authenticated User
echo "[6] Test MCP Server với API Key của User 1 và User 2... ";
use SimpleFinance\McpServer;

// Khởi tạo MCP Server với User 1
$mcp1 = new McpServer($u1);
$mcp2 = new McpServer($u2);

// Test tools/list
$resTools = $mcp1->handleRequest(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
assert(isset($resTools['result']['tools']), "tools/list must return tools array");
$toolNames = array_column($resTools['result']['tools'], 'name');
assert(in_array('group_list', $toolNames), "group_list tool must exist");
assert(in_array('group_create', $toolNames), "group_create tool must exist");
assert(in_array('transaction_create', $toolNames), "transaction_create tool must exist");
assert(in_array('debt_summary', $toolNames), "debt_summary tool must exist");

// User 1 gọi group_list: Phải thấy 2 nhóm (Default + Đà Lạt)
$resGroup1 = $mcp1->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 2,
    'method' => 'tools/call',
    'params' => [
        'name' => 'group_list',
        'arguments' => new stdClass()
    ]
]);
$groupData1 = json_decode($resGroup1['result']['content'][0]['text'], true);
assert(count($groupData1) === 2, "User 1 should see 2 groups via MCP");

// User 2 gọi group_list: Phải thấy 2 nhóm (Default User 2 + Đà Lạt)
$resGroup2 = $mcp2->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 3,
    'method' => 'tools/call',
    'params' => [
        'name' => 'group_list',
        'arguments' => new stdClass()
    ]
]);
$groupData2 = json_decode($resGroup2['result']['content'][0]['text'], true);
assert(count($groupData2) === 2, "User 2 should see 2 groups via MCP");
$u2GroupIds = array_column($groupData2, 'id');
assert(!in_array($defaultGroup1['id'], $u2GroupIds), "User 2 MCP cannot see User 1's private default group");

// User 2 cố ý truy cập nhóm riêng của User 1 bằng group_id: Phải bị từ chối
$resIllegal = $mcp2->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 4,
    'method' => 'tools/call',
    'params' => [
        'name' => 'debt_summary',
        'arguments' => ['group_id' => $defaultGroup1['id']]
    ]
]);
assert($resIllegal['result']['isError'] === true, "Unauthorized group access must return isError: true");
assert(str_contains($resIllegal['result']['content'][0]['text'], 'không có quyền truy cập'), "Error message must indicate forbidden access");
echo "OK!\n";

echo "\n====================================================\n";
echo " TẤT CẢ CÁC BƯỚC TEST ĐÃ THÀNH CÔNG RỰC RỠ (100% PASS)!\n";
echo "====================================================\n";
