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
use SimpleFinance\Models\Transaction;
use SimpleFinance\Models\Member;
use SimpleFinance\Models\Group;
use SimpleFinance\McpServer;

echo "=== TEST SPENT_AT (NGÀY PHÁT SINH CHI TIÊU) ===\n";

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
    'primary_birthday' => '1990-01-01',
    'special_date_1' => '2020-05-01',
    'special_date_2' => '2022-09-02'
]);
$apiKey = $u['api_key'];

$txModel = new Transaction();
$memberModel = new Member();
$groupModel = new Group();

// 1. Tạo nhóm & thành viên
$group = $groupModel->create("Nhóm Test Ngày Chi", $u['id'], "Testing spent_at");
$groupId = $group['id'];
$m1Res = $memberModel->create($groupId, "Người Trả", "0901111111");
$m1 = $m1Res['id'];
$m2Res = $memberModel->create($groupId, "Người Ăn", "0902222222");
$m2 = $m2Res['id'];

// 2. Tạo giao dịch với spent_at trong quá khứ (ví dụ 5 ngày trước)
$pastDate = date('Y-m-d', strtotime('-5 days'));
$tx1Res = $txModel->create(
    "Ăn tối tuần trước",
    $m1,
    [
        [
            'product_name' => 'Lẩu dê',
            'price' => 100000,
            'member_ids' => [$m1, $m2]
        ]
    ],
    "completed",
    "Ăn lẩu",
    $groupId,
    null,
    null,
    null,
    $pastDate
);
$txId1 = $tx1Res['id'];

$tx1 = $txModel->find($txId1);
assert(!empty($tx1['spent_at']), "spent_at must not be empty");
assert(strpos($tx1['spent_at'], $pastDate) === 0, "spent_at must match $pastDate");
echo "✓ Tạo Transaction qua Model với spent_at trong quá khứ ($pastDate) thành công!\n";

// 3. Tạo giao dịch hôm nay
$tx2Res = $txModel->create(
    "Cà phê hôm nay",
    $m1,
    [
        [
            'product_name' => 'Cà phê',
            'price' => 50000,
            'member_ids' => [$m1, $m2]
        ]
    ],
    "completed",
    "Hôm nay",
    $groupId
);
$txId2 = $tx2Res['id'];
$tx2 = $txModel->find($txId2);
assert(!empty($tx2['spent_at']), "default spent_at must not be empty");
echo "✓ Tạo Transaction không truyền spent_at mặc định lấy thời gian hiện tại thành công!\n";

// 4. Kiểm tra thứ tự sắp xếp all() phải theo spent_at DESC
$allTx = $txModel->all(null, 50, 0, $groupId);
assert($allTx[0]['id'] == $txId2, "Giao dịch hôm nay phải đứng trước giao dịch quá khứ");
assert($allTx[1]['id'] == $txId1, "Giao dịch quá khứ phải đứng sau");
echo "✓ Sắp xếp theo COALESCE(spent_at, created_at) DESC chính xác!\n";

// 5. Cập nhật spent_at qua Transaction::update
$olderDate = date('Y-m-d', strtotime('-10 days'));
$txModel->update($txId2, [
    'spent_at' => $olderDate
]);
$tx2Updated = $txModel->find($txId2);
assert(strpos($tx2Updated['spent_at'], $olderDate) === 0, "spent_at after update must match $olderDate");
echo "✓ Cập nhật spent_at qua Model thành công!\n";

// 6. Test qua MCP Server
$server = new McpServer($u);

$mcpSpentDate = '2026-09-25';
$mcpRes = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 10,
    'method' => 'tools/call',
    'params' => [
        'name' => 'transaction_create',
        'arguments' => [
            'title' => 'Giao dịch qua MCP ngày 25/09',
            'payer_id' => $m1,
            'spent_at' => $mcpSpentDate,
            'items' => [
                [
                    'product_name' => 'Bún bò',
                    'price' => 60000,
                    'member_ids' => [$m1, $m2]
                ]
            ]
        ]
    ]
]);

assert(empty($mcpRes['result']['isError']), "MCP transaction_create must succeed");
$contentJson = json_decode($mcpRes['result']['content'][0]['text'], true);
$mcpTxId = $contentJson['transaction']['id'];
$mcpTx = $txModel->find($mcpTxId);
assert(strpos($mcpTx['spent_at'], $mcpSpentDate) === 0, "MCP created tx spent_at must match $mcpSpentDate");
assert(strpos($contentJson['summary_text'], '25/09/2026') !== false, "summary_text must display formatted spent_at");
echo "✓ MCP transaction_create hỗ trợ spent_at thành công!\n";

// 7. MCP transaction_update spent_at
$newMcpSpentDate = '2026-09-20';
$mcpUpdateRes = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 11,
    'method' => 'tools/call',
    'params' => [
        'name' => 'transaction_update',
        'arguments' => [
            'transaction_id' => $mcpTxId,
            'spent_at' => $newMcpSpentDate
        ]
    ]
]);
if (!empty($mcpUpdateRes['result']['isError'])) {
    echo "ERROR: " . var_export($mcpUpdateRes['result']['content'], true) . "\n";
}
assert(empty($mcpUpdateRes['result']['isError']), "MCP transaction_update must succeed");
$updateJson = json_decode($mcpUpdateRes['result']['content'][0]['text'], true);
$mcpTxUpdated = $txModel->find($mcpTxId);
assert(strpos($mcpTxUpdated['spent_at'], $newMcpSpentDate) === 0, "MCP updated tx spent_at must match $newMcpSpentDate");
assert(strpos($updateJson['summary_text'], '20/09/2026') !== false, "summary_text must display updated spent_at");
echo "✓ MCP transaction_update cập nhật spent_at thành công!\n";

// 8. MCP transaction_date_update với tham số 'date'
$dateViaTool = '2026-09-18';
$mcpDateUpdateRes = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 12,
    'method' => 'tools/call',
    'params' => [
        'name' => 'transaction_date_update',
        'arguments' => [
            'id' => $mcpTxId,
            'date' => $dateViaTool
        ]
    ]
]);
assert(empty($mcpDateUpdateRes['result']['isError']), "transaction_date_update must succeed");
$dateUpdateJson = json_decode($mcpDateUpdateRes['result']['content'][0]['text'], true);
$mcpTxDateUpdated = $txModel->find($mcpTxId);
assert(strpos($mcpTxDateUpdated['spent_at'], $dateViaTool) === 0, "spent_at must match $dateViaTool");
assert(strpos($dateUpdateJson['summary_text'], '18/09/2026') !== false, "summary_text must display 18/09/2026");
echo "✓ MCP transaction_date_update cập nhật ngày thành công!\n";

// 9. MCP alias update_transaction_date
$dateViaAlias = '2026-09-15';
$mcpAliasRes = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 13,
    'method' => 'tools/call',
    'params' => [
        'name' => 'update_transaction_date',
        'arguments' => [
            'transaction_id' => $mcpTxId,
            'spent_at' => $dateViaAlias
        ]
    ]
]);
assert(empty($mcpAliasRes['result']['isError']), "update_transaction_date must succeed");
$aliasJson = json_decode($mcpAliasRes['result']['content'][0]['text'], true);
$mcpTxAliasUpdated = $txModel->find($mcpTxId);
assert(strpos($mcpTxAliasUpdated['spent_at'], $dateViaAlias) === 0, "spent_at must match $dateViaAlias");
assert(strpos($aliasJson['summary_text'], '15/09/2026') !== false, "summary_text must display 15/09/2026");
echo "✓ MCP alias update_transaction_date cập nhật ngày thành công!\n";

echo "\n>>> TẤT CẢ TEST SPENT_AT ĐÃ PASS HOÀN HẢO! <<<\n";
