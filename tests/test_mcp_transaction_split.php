<?php

spl_autoload_register(function ($class) {
    $prefix = 'SimpleFinance\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require_once $file;
});

require_once __DIR__ . '/../src/Security/Crypto.php';
require_once __DIR__ . '/../src/Database.php';

use SimpleFinance\Models\User;
use SimpleFinance\McpServer;

echo "=== TEST MCP TRANSACTION AUTO-SPLIT & SUMMARY ===\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("
CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, email TEXT NOT NULL UNIQUE, full_name TEXT NOT NULL, api_key TEXT NOT NULL UNIQUE, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE user_timeline_events (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, event_name TEXT NOT NULL, event_date_encrypted TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE groups (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, description TEXT, owner_id INTEGER NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE group_members (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL, user_id INTEGER NOT NULL, display_name TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'member', joined_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(group_id, user_id));
CREATE TABLE members (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, phone TEXT, email TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE places (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL DEFAULT 1, name TEXT NOT NULL, address TEXT, map_url TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL DEFAULT 1, place_id INTEGER, name TEXT NOT NULL, default_price NUMERIC, description TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL DEFAULT 1, place_id INTEGER, title TEXT NOT NULL, payer_id INTEGER NOT NULL, total_amount NUMERIC NOT NULL DEFAULT 0.00, status TEXT NOT NULL DEFAULT 'completed', note TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE transaction_items (id INTEGER PRIMARY KEY AUTOINCREMENT, transaction_id INTEGER NOT NULL, product_id INTEGER NOT NULL, price NUMERIC NOT NULL, quantity INTEGER NOT NULL DEFAULT 1, subtotal NUMERIC NOT NULL DEFAULT 0.00, note TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE transaction_item_members (id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER NOT NULL, member_id INTEGER NOT NULL, share_amount NUMERIC NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE debts (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL DEFAULT 1, debtor_id INTEGER NOT NULL, creditor_id INTEGER NOT NULL, amount NUMERIC NOT NULL DEFAULT 0.00, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(group_id, debtor_id, creditor_id));
CREATE TABLE debt_settlements (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL DEFAULT 1, debtor_id INTEGER NOT NULL, creditor_id INTEGER NOT NULL, amount NUMERIC NOT NULL, note TEXT, settled_at DATETIME DEFAULT CURRENT_TIMESTAMP);
");
\SimpleFinance\Database::setConnection($pdo);

$userModel = new User();
$u = $userModel->create('admin', 'admin@example.com', 'Admin Nam', [
    'Sinh nhật' => '10-10-1990',
    'Ngày cưới' => '12-12-2020',
    'Tốt nghiệp' => '20-05-2012'
]);

// userModel->create đã tạo sẵn nhóm và thành viên 1 (Admin Nam)
$pdo->exec("UPDATE members SET name = 'Nam Trần' WHERE id = 1");
$pdo->exec("INSERT INTO members (name, phone) VALUES ('Bình Lê', '0902')");
$pdo->exec("INSERT INTO members (name, phone) VALUES ('An Nguyễn', '0903')");

$server = new McpServer($u);

// 1. Kiểm tra Schema của transaction_create không có 'shares' và mô tả tự động chia tiền
$toolsRes = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'tools/list',
]);
$tools = $toolsRes['result']['tools'];
$txCreateTool = null;
foreach ($tools as $t) {
    if ($t['name'] === 'transaction_create') {
        $txCreateTool = $t;
        break;
    }
}
assert(!empty($txCreateTool), "Phải tìm thấy tool transaction_create");
$itemProps = $txCreateTool['inputSchema']['properties']['items']['items']['properties'];
assert(!isset($itemProps['shares']), "Schema transaction_create KHÔNG ĐƯỢC có shares");
assert(isset($itemProps['member_ids']), "Schema transaction_create phải có member_ids");
echo "✓ Schema kiểm tra thành công: 'shares' đã được ẩn, 'member_ids' nêu rõ tự động chia tiền.\n";

// 2. Gọi tool transaction_create chỉ truyền member_ids (không truyền shares)
// Món 1: Cà phê đá 30.000đ x 1 chia cho Nam (1), Bình (2) -> mỗi người 15.000đ
// Món 2: Trà sữa 60.000đ x 1 chia cho Nam (1), Bình (2), An (3) -> mỗi người 20.000đ
// Tổng tiền: 90.000đ do Nam trả
// Nam: 15.000 + 20.000 = 35.000đ
// Bình: 15.000 + 20.000 = 35.000đ (nợ Nam 35.000đ)
// An: 20.000đ (nợ Nam 20.000đ)
$callRes = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 2,
    'method' => 'tools/call',
    'params' => [
        'name' => 'transaction_create',
        'arguments' => [
            'title' => 'Cafe sáng Team',
            'payer_id' => 1,
            'place_name' => 'Highlands Coffee',
            'place_address' => 'Mai Chí Thọ, Q2',
            'status' => 'completed',
            'items' => [
                [
                    'product_name' => 'Cà phê đá',
                    'price' => 30000,
                    'quantity' => 1,
                    'member_ids' => [1, 2],
                ],
                [
                    'product_name' => 'Trà sữa sen',
                    'price' => 60000,
                    'quantity' => 1,
                    'member_ids' => [1, 2, 3],
                ],
            ],
        ],
    ],
]);

assert(empty($callRes['result']['isError']), "transaction_create phải thành công");
$resData = json_decode($callRes['result']['content'][0]['text'], true);

assert($resData['success'] === true, "success phải là true");
assert(!empty($resData['summary_text']), "Phải có summary_text");
assert(!empty($resData['split_summary']), "Phải có split_summary");
assert($resData['split_summary']['total_amount'] == 90000, "Tổng tiền phải là 90000");

// Kiểm tra chi tiết chia tiền từng thành viên
$shares = $resData['split_summary']['member_shares'];
$shareByMember = [];
foreach ($shares as $s) {
    $shareByMember[$s['member_id']] = $s['total_share'];
}

assert($shareByMember[1] == 35000, "Nam phần tiền là 35000");
assert($shareByMember[2] == 35000, "Bình phần tiền là 35000");
assert($shareByMember[3] == 20000, "An phần tiền là 20000");

echo "✓ Tự động tính toán chia tiền thành công:\n";
echo "  Tổng: {$resData['split_summary']['total_amount']}đ\n";
echo "  Nam: {$shareByMember[1]}đ\n";
echo "  Bình: {$shareByMember[2]}đ\n";
echo "  An: {$shareByMember[3]}đ\n";
echo "--- Báo cáo summary_text tạo ra ---\n";
echo $resData['summary_text'] . "\n";
echo "-----------------------------------\n";

// 3. Kiểm tra công nợ được cập nhật tự động
$debtRes = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 3,
    'method' => 'tools/call',
    'params' => [
        'name' => 'debt_summary',
        'arguments' => ['group_id' => 1],
    ],
]);
$debtData = json_decode($debtRes['result']['content'][0]['text'], true);
// Bình nợ Nam 35000, An nợ Nam 20000
$debtsFound = 0;
foreach ($debtData as $d) {
    if ($d['debtor_id'] == 2 && $d['creditor_id'] == 1 && $d['amount'] == 35000) $debtsFound++;
    if ($d['debtor_id'] == 3 && $d['creditor_id'] == 1 && $d['amount'] == 20000) $debtsFound++;
}
assert($debtsFound === 2, "Bảng nợ phải ghi nhận Bình nợ 35k và An nợ 20k");
echo "✓ Bảng công nợ khớp chính xác với kết quả tự động tính toán!\n";

// 3.5 Test transaction_item_update: sửa giá món lẻ (từ 30k thành 15k)
$txId = $resData['transaction']['id'];
$itemUpdateRes = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 35,
    'method' => 'tools/call',
    'params' => [
        'name' => 'transaction_item_update',
        'arguments' => [
            'transaction_id' => $txId,
            'product_name'   => 'Cà phê đá',
            'price'          => 15000,
        ],
    ],
]);
assert(empty($itemUpdateRes['result']['isError']), "transaction_item_update phải thành công");
$itemUpdateData = json_decode($itemUpdateRes['result']['content'][0]['text'], true);
assert($itemUpdateData['success'] === true, "success phải true");
assert($itemUpdateData['split_summary']['total_amount'] == 75000, "Tổng tiền mới sau khi sửa từ 30k về 15k phải là 75,000đ");

// Nam: 7.5k + 20k = 27.5k, Bình: 7.5k + 20k = 27.5k, An: 20k
$newShares = $itemUpdateData['split_summary']['member_shares'];
$newShareByMember = [];
foreach ($newShares as $s) {
    $newShareByMember[$s['member_id']] = $s['total_share'];
}
assert($newShareByMember[1] == 27500, "Nam phải là 27500");
assert($newShareByMember[2] == 27500, "Bình phải là 27500");
assert($newShareByMember[3] == 20000, "An phải là 20000");
echo "✓ transaction_item_update sửa giá món từ 30k về 15k thành công (Tổng mới: 75.000đ, Bình nợ mới: 27.500đ)\n";
echo "--- Báo cáo sau khi sửa giá món ---\n";
echo $itemUpdateData['summary_text'] . "\n";
echo "-----------------------------------\n";

// 3.6 Test sửa giá qua transaction_update trực tiếp
$updateDirectRes = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 36,
    'method' => 'tools/call',
    'params' => [
        'name' => 'transaction_update',
        'arguments' => [
            'id'           => $txId,
            'product_name' => 'Cà phê đá',
            'price'        => 20000,
        ],
    ],
]);
assert(empty($updateDirectRes['result']['isError']), "transaction_update sửa giá đơn lẻ phải thành công");
$updateDirectData = json_decode($updateDirectRes['result']['content'][0]['text'], true);
assert($updateDirectData['split_summary']['total_amount'] == 80000, "Tổng tiền sau khi sửa lên 20k phải là 80k");
echo "✓ transaction_update sửa giá món đơn lẻ cũng hoạt động trơn tru (Tổng mới: 80.000đ)!\n";

// 4. Test transaction_update: cập nhật lại danh sách toàn bộ món
$updateRes = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 4,
    'method' => 'tools/call',
    'params' => [
        'name' => 'transaction_update',
        'arguments' => [
            'id' => $txId,
            'items' => [
                [
                    'product_name' => 'Cà phê đá',
                    'price' => 40000,
                    'quantity' => 1,
                    'member_ids' => [2, 3], // Đổi người dùng sang Bình và An
                ],
            ],
        ],
    ],
]);
$updateData = json_decode($updateRes['result']['content'][0]['text'], true);
assert($updateData['success'] === true, "transaction_update thành công");
assert($updateData['split_summary']['total_amount'] == 40000, "Tổng mới là 40k");
echo "✓ transaction_update tính toán lại thành công:\n";
echo $updateData['summary_text'] . "\n";

echo "\n>>> TẤT CẢ TEST AUTO-SPLIT VÀ SUMMARY TRẢ VỀ ĐỀU THÀNH CÔNG RỰC RỠ! <<<\n";
