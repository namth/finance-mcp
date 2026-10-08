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
// TEST 7: Kiểm thử đổi hạn chót (updateDeadline) và xóa sự kiện (deleteEvent)
echo "\n[7/8] Kiểm thử Admin đổi hạn chót sự kiện... ";
$newDeadline = date('Y-m-d H:i:s', strtotime('+3 days'));
$updRes = $groupBuy->updateDeadline($event2['id'], $newDeadline);
assert($updRes['success'] === true, "updateDeadline phải trả về success = true");
$reloadedEvent = $groupBuy->getEventById($event2['id']);
assert($reloadedEvent['deadline'] === $newDeadline, "Deadline mới phải được lưu chính xác");
echo "✅ PASS (Hạn chót mới: {$newDeadline})\n";

// TEST 8: Kiểm thử xóa đơn đăng ký nhầm (deleteRegistration)
echo "[8/9] Kiểm thử Admin xóa đơn đăng ký nhầm của người dùng... ";
$regToDeleteId = $reg['registration_id'];
$delRegRes = $groupBuy->deleteRegistration($regToDeleteId);
assert($delRegRes === true, "deleteRegistration phải trả về true");
$regsAfterDel = $groupBuy->getRegistrations($event['id']);
$found = false;
foreach ($regsAfterDel as $r) {
    if ((int)$r['id'] === $regToDeleteId) $found = true;
}
assert($found === false, "Đơn đăng ký vừa xóa không được còn trong danh sách");
echo "✅ PASS (Đơn đăng ký #{$regToDeleteId} đã bị xóa hoàn toàn)\n";

echo "[9/11] Kiểm thử Admin xóa sự kiện mua chung... ";
$delRes = $groupBuy->deleteEvent($event2['id']);
assert($delRes === true, "deleteEvent phải trả về true");
$deletedEvent = $groupBuy->getEventById($event2['id']);
assert($deletedEvent === null, "Sự kiện sau khi xóa getEventById phải trả về null");
echo "✅ PASS (Sự kiện và danh sách đơn đã được dọn sạch)\n";

// TEST 10: Kiểm thử toggleDelivered (Đã phát / nhận hàng)
echo "[10/11] Kiểm thử Toggle Đã phát / Đã nhận hàng (toggleDelivered)... ";
$event3 = $groupBuy->createEvent(
    1, 1,
    'Sự kiện test phát hàng',
    'Mô tả',
    null,
    date('Y-m-d H:i:s', strtotime('+2 hours')),
    [
        ['name' => 'Mũ len', 'option_name' => 'Freesize', 'price' => 50000]
    ]
);
$regDeliv = $groupBuy->register(
    $event3['public_token'],
    'Trần Văn B',
    '0909090909',
    '',
    [['item_id' => $event3['items'][0]['id'], 'quantity' => 1]]
);
$toggleDelivRes = $groupBuy->toggleDelivered($regDeliv['registration_id'], true);
assert($toggleDelivRes['is_delivered'] === 1, "is_delivered phải là 1 sau khi tick");
$regs3 = $groupBuy->getRegistrations($event3['id']);
assert((int)$regs3[0]['is_delivered'] === 1, "DB phải lưu is_delivered = 1");
assert(!empty($regs3[0]['delivered_at']), "delivered_at phải có giá trị ngày giờ");
$toggleDelivBack = $groupBuy->toggleDelivered($regDeliv['registration_id'], false);
assert($toggleDelivBack['is_delivered'] === 0, "is_delivered phải về 0 sau khi bỏ tick");
echo "✅ PASS (Toggle giao nhận hàng thành công)\n";

// TEST 11: Kiểm thử sự kiện quá hạn deadline -> tự động đóng và chặn đăng ký
echo "[11/11] Kiểm thử tự động đóng đơn khi quá hạn chót deadline... ";
$pastDeadline = date('Y-m-d H:i:s', strtotime('-10 minutes'));
$eventExpired = $groupBuy->createEvent(
    1, 1,
    'Sự kiện đã hết hạn',
    'Quá hạn',
    null,
    $pastDeadline,
    [
        ['name' => 'Áo thun cũ', 'option_name' => 'Size M', 'price' => 100000]
    ]
);
$fetchedExpired = $groupBuy->getEventById($eventExpired['id']);
assert(!empty($fetchedExpired['is_expired']), "Sự kiện có deadline quá khứ phải có is_expired = true");

$blocked = false;
try {
    $groupBuy->register(
        $eventExpired['public_token'],
        'Người đến muộn',
        '0911111111',
        '',
        [['item_id' => $eventExpired['items'][0]['id'], 'quantity' => 1]]
    );
} catch (\Exception $e) {
    if (str_contains($e->getMessage(), 'hết hạn')) {
        $blocked = true;
    }
}
assert($blocked === true, "Đăng ký sự kiện quá hạn phải bị từ chối với thông báo hết hạn");
echo "✅ PASS (Chặn đăng ký thành công khi sự kiện đã quá hạn chót)\n";

echo "\n🎉 TẤT CẢ 12/12 CA KIỂM THỬ ĐỀU ĐẠT CHUẨN 100% THÀNH CÔNG!\n";
echo "========================================================\n";
