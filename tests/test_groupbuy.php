<?php

require_once __DIR__ . '/../src/Database.php';

spl_autoload_register(function ($class) {
    $prefix = 'SimpleFinance\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use SimpleFinance\Models\GroupBuy;
use SimpleFinance\Models\User;
use SimpleFinance\Models\Group;
use SimpleFinance\Models\Member;
use SimpleFinance\McpServer;

echo "========================================================\n";
echo "🧪 BẮT ĐẦU KIỂM THỬ TỰ ĐỘNG TÍNH NĂNG MUA CHUNG (FEAT-001)\n";
echo "========================================================\n\n";

// 1. Tạo CSDL SQLite trong bộ nhớ để test độc lập
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Tạo các bảng cơ bản giả lập
$pdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        email TEXT UNIQUE,
        full_name TEXT,
        api_key TEXT UNIQUE,
        bank_bin TEXT,
        bank_name TEXT,
        bank_account_no TEXT,
        bank_account_name TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE groups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        description TEXT,
        owner_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE group_members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER,
        user_id INTEGER,
        display_name TEXT,
        role TEXT,
        joined_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER,
        user_id INTEGER,
        name TEXT,
        phone TEXT,
        email TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER,
        payer_id INTEGER,
        title TEXT,
        total_amount REAL,
        note TEXT,
        status TEXT DEFAULT 'completed',
        spent_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
");

// 2. Chèn dữ liệu mẫu User, Group, Member
$pdo->exec("
    INSERT INTO users (id, username, email, full_name, api_key, bank_bin, bank_account_no, bank_account_name)
    VALUES (1, 'namtran', 'nam@test.com', 'Nam Trần', 'key_123', '970422', '0987654321', 'TRAN HOANG NAM');

    INSERT INTO groups (id, name, owner_id) VALUES (1, 'Team Công Ty SimpleFinance', 1);
    INSERT INTO group_members (group_id, user_id, display_name, role) VALUES (1, 1, 'Nam Trần', 'owner');
    INSERT INTO members (id, group_id, user_id, name) VALUES (1, 1, 1, 'Nam Trần');
");

// Gán kết nối sqlite bộ nhớ cho toàn bộ các model sử dụng
\SimpleFinance\Database::setConnection($pdo);

$groupBuy = new GroupBuy($pdo);

// TEST 1: Tạo sự kiện mua chung
echo "[1/6] Kiểm thử tạo sự kiện mua chung kèm danh mục món/size... ";
$event = $groupBuy->createEvent(
    1, 1,
    'Áo Thun Polo SimpleFinance 2026',
    'Đặt may áo nhóm đi du lịch',
    'https://example.com/polo.jpg',
    '2026-10-15 20:00:00',
    [
        ['name' => 'Áo Polo', 'option_name' => 'Size S (<52kg)', 'price' => 150000],
        ['name' => 'Áo Polo', 'option_name' => 'Size M (53-62kg)', 'price' => 150000],
        ['name' => 'Áo Polo', 'option_name' => 'Size XL (>73kg)', 'price' => 160000],
    ]
);

assert(!empty($event['id']), "Event ID phải được tạo");
assert(!empty($event['public_token']), "Public token phải được sinh");
assert(count($event['items']) === 3, "Phải có đúng 3 phân loại size");
assert($event['bank_account_no'] === '0987654321', "Phải tự động kế thừa STK của creator");
echo "✅ PASS (Event #{$event['id']}, Token: {$event['public_token']})\n";

// TEST 2: Đăng ký đặt hàng công khai & Sinh VietQR
echo "[2/6] Kiểm thử khách hàng đăng ký chọn 2 món & sinh VietQR... ";
$itemS = $event['items'][0]['id'];
$itemXL = $event['items'][2]['id'];

$reg = $groupBuy->register(
    $event['public_token'],
    'Nguyễn Văn A',
    '0912345678',
    'Lấy form rộng rãi nhé',
    [
        ['item_id' => $itemS, 'quantity' => 1],
        ['item_id' => $itemXL, 'quantity' => 2], // 150k + 160k * 2 = 470k
    ]
);

assert($reg['success'] === true, "Đăng ký phải thành công");
assert($reg['total_amount'] == 470000, "Tổng tiền phải bằng 470,000 VND");
assert(str_contains($reg['qr_url'], 'img.vietqr.io'), "QR URL phải theo chuẩn VietQR");
assert(!empty($reg['reg_token']), "Phải có mã token đơn hàng");
echo "✅ PASS (Tổng tiền: 470.000 ₫, QR URL sinh chuẩn xác)\n";

// TEST 3: Khách hàng thông báo đã chuyển tiền
echo "[3/6] Kiểm thử người dùng bấm 'Tôi đã chuyển khoản'... ";
$notified = $groupBuy->notifyPaid($reg['reg_token']);
assert($notified === true, "Phải ghi nhận cờ thông báo");
$regList = $groupBuy->getRegistrations($event['id']);
assert((int)$regList[0]['is_notified_paid'] === 1, "is_notified_paid phải là 1");
assert((int)$regList[0]['is_paid'] === 0, "is_paid ban đầu phải là 0");
echo "✅ PASS (Đã ghi nhận khách báo chuyển tiền)\n";

// TEST 4: Admin tick xác nhận Đã thu tiền
echo "[4/6] Kiểm thử Admin tick Toggle 'Đã thu tiền' qua AJAX... ";
$toggleRes = $groupBuy->togglePaid($reg['registration_id'], true);
assert($toggleRes['is_paid'] === 1, "Trạng thái thu tiền phải chuyển thành 1");
$regList2 = $groupBuy->getRegistrations($event['id']);
assert((int)$regList2[0]['is_paid'] === 1, "Bản ghi phải lưu is_paid = 1");
echo "✅ PASS (Admin xác nhận thu tiền thành công)\n";

// TEST 5: Thống kê số lượng gom theo size
echo "[5/6] Kiểm thử thống kê tổng hợp số lượng cần gom hàng theo size... ";
$breakdown = $groupBuy->getSummaryBreakdown($event['id']);
$sQty = 0; $xlQty = 0;
foreach ($breakdown as $b) {
    if (str_contains($b['option_name'], 'Size S')) $sQty = (int)$b['total_quantity'];
    if (str_contains($b['option_name'], 'Size XL')) $xlQty = (int)$b['total_quantity'];
}
assert($sQty === 1, "Size S phải có 1 cái");
assert($xlQty === 2, "Size XL phải có 2 cái");
echo "✅ PASS (Size S: {$sQty}, Size XL: {$xlQty})\n";

// TEST 6: Chốt sự kiện thành Giao dịch nhóm (Transaction)
echo "[6/6] Kiểm thử Chốt sự kiện & Chuyển đổi thành Hóa đơn Giao dịch nhóm... ";
$convertRes = $groupBuy->convertToTransaction($event['id'], 1);
assert($convertRes['success'] === true, "Chốt phải thành công");
assert($convertRes['transaction_id'] > 0, "Phải tạo ra Transaction ID");
assert($convertRes['total_amount'] == 470000, "Tổng tiền giao dịch bằng tổng đơn");

$updatedEvent = $groupBuy->getEventById($event['id']);
assert($updatedEvent['status'] === 'converted', "Trạng thái sự kiện phải là 'converted'");
echo "✅ PASS (Đã tạo Giao dịch #{$convertRes['transaction_id']})\n";

// TEST MCP TOOL: Kiểm thử MCP Server thực thi tool group_buy_register
echo "\n🤖 Kiểm thử MCP Tool 'group_buy_register' qua McpServer... ";
$mockUser = ['id' => 1, 'username' => 'namtran', 'full_name' => 'Nam Trần', 'api_key' => 'key_123'];
$mcp = new McpServer($mockUser);

// Tạo thêm 1 sự kiện mới để test qua MCP
$event2 = $groupBuy->createEvent(
    1, 1,
    'Gom Mua Trà Sữa Chiều Nay',
    'Chọn món và topping',
    null, null,
    [
        ['name' => 'Trà Sữa Oolong', 'option_name' => 'Size M (Ít Đường)', 'price' => 35000],
        ['name' => 'Trà Đào Cam Sả', 'option_name' => 'Size L', 'price' => 45000]
    ]
);

$mcpReq = [
    'jsonrpc' => '2.0',
    'id'      => 'req-mcp-1',
    'method'  => 'tools/call',
    'params'  => [
        'name'      => 'group_buy_register',
        'arguments' => [
            'token'            => $event2['public_token'],
            'participant_name' => 'Lê Quỳnh Nga',
            'participant_phone'=> '0933333333',
            'note'             => '70% đá giúp mình',
            'items'            => [
                ['item_id' => $event2['items'][0]['id'], 'quantity' => 2], // 35k x 2 = 70k
            ]
        ]
    ]
];

$mcpRes = $mcp->handleRequest($mcpReq);
assert(!empty($mcpRes['result']), "Mcp Response result không được rỗng");
$mcpData = json_decode($mcpRes['result']['content'][0]['text'], true);
assert($mcpData['success'] === true, "MCP Đăng ký phải thành công");
assert($mcpData['total_amount'] == 70000, "MCP Đăng ký tổng tiền phải là 70,000 VND");
echo "✅ PASS (MCP AI Agent đăng ký thành công đơn 70.000 ₫)\n";

echo "\n🎉 TẤT CẢ 7/7 CA KIỂM THỬ ĐỀU ĐẠT CHUẨN 100% THÀNH CÔNG!\n";
echo "========================================================\n";
