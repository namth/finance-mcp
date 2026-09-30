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

echo "=== TEST MCP PAYMENT QR CODE & VIETQR ===\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL UNIQUE,
    full_name TEXT NOT NULL,
    api_key TEXT NOT NULL UNIQUE,
    bank_bin TEXT DEFAULT NULL,
    bank_name TEXT DEFAULT NULL,
    bank_account_no TEXT DEFAULT NULL,
    bank_account_name TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE user_timeline_events (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, event_name TEXT NOT NULL, event_date_encrypted TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE groups (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, description TEXT, owner_id INTEGER NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE group_members (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL, user_id INTEGER NOT NULL, display_name TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'member', joined_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(group_id, user_id));
CREATE TABLE members (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER DEFAULT NULL, name TEXT NOT NULL, phone TEXT, email TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE places (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL DEFAULT 1, name TEXT NOT NULL, address TEXT, map_url TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL DEFAULT 1, place_id INTEGER, name TEXT NOT NULL, default_price NUMERIC, description TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL DEFAULT 1, place_id INTEGER, title TEXT NOT NULL, payer_id INTEGER NOT NULL, total_amount NUMERIC NOT NULL DEFAULT 0.00, status TEXT NOT NULL DEFAULT 'completed', note TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE transaction_items (id INTEGER PRIMARY KEY AUTOINCREMENT, transaction_id INTEGER NOT NULL, product_id INTEGER NOT NULL, price NUMERIC NOT NULL, quantity INTEGER NOT NULL DEFAULT 1, subtotal NUMERIC NOT NULL DEFAULT 0.00, note TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE transaction_item_members (id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER NOT NULL, member_id INTEGER NOT NULL, share_amount NUMERIC NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE debts (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL DEFAULT 1, debtor_id INTEGER NOT NULL, creditor_id INTEGER NOT NULL, amount NUMERIC NOT NULL DEFAULT 0.00, debt_token TEXT DEFAULT NULL, payment_notified_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(group_id, debtor_id, creditor_id));
CREATE TABLE debt_settlements (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL DEFAULT 1, debtor_id INTEGER NOT NULL, creditor_id INTEGER NOT NULL, amount NUMERIC NOT NULL, note TEXT, settled_at DATETIME DEFAULT CURRENT_TIMESTAMP);
");
\SimpleFinance\Database::setConnection($pdo);

$userModel = new User();
$u = $userModel->create('admin', 'admin@example.com', 'Nam Tran', [
    'Sinh nhật' => '10-10-1990',
    'Ngày cưới' => '12-12-2020',
    'Tốt nghiệp' => '20-05-2012'
]);

// Cập nhật thông tin ngân hàng cho User 1 (Nam Tran)
$userModel->updateBankInfo($u['id'], '970422', 'MBBank', '0988776655', 'TRAN HOANG NAM');

// Thêm thành viên 2 (Bình Lê)
$pdo->exec("INSERT INTO members (name, phone, email) VALUES ('Bình Lê', '0902', 'binh@example.com')");
$pdo->exec("UPDATE members SET user_id = 1 WHERE id = 1"); // Gán member 1 với User 1

// Tạo nợ: Bình Lê (2) nợ Nam Trần (1) 150,000đ
$debtManager = new \SimpleFinance\DebtManager($pdo);
$debtManager->addDebt(2, 1, 150000, 1);

$server = new McpServer($u);

// 1. Kiểm tra debt_summary tự động sinh qr_image_url và pay_url
echo "1. Kiểm tra debt_summary... ";
$resSummary = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'tools/call',
    'params' => [
        'name' => 'debt_summary',
        'arguments' => ['group_id' => 1],
    ],
]);
assert(empty($resSummary['result']['isError']), "debt_summary phải thành công");
$debts = json_decode($resSummary['result']['content'][0]['text'], true);
assert(count($debts) === 1, "Phải có 1 khoản nợ");
$d = $debts[0];
assert(!empty($d['pay_url']), "Phải có pay_url");
assert(!empty($d['qr_image_url']), "Phải có qr_image_url");
assert(str_contains($d['qr_image_url'], '970422-0988776655-compact2.png'), "QR URL phải chứa BIN và STK MBBank");
assert(str_contains($d['qr_image_url'], 'amount=150000'), "QR URL phải chứa số tiền 150000");
echo "OK!\n";
echo "   Link QR: {$d['qr_image_url']}\n";
echo "   Link Pay: {$d['pay_url']}\n";

// 2. Kiểm tra tool payment_qr_get theo debt_id
echo "2. Kiểm tra payment_qr_get theo debt_id... ";
$resQr = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 2,
    'method' => 'tools/call',
    'params' => [
        'name' => 'payment_qr_get',
        'arguments' => [
            'debt_id' => (int)$d['id'],
        ],
    ],
]);
assert(empty($resQr['result']['isError']), "payment_qr_get theo debt_id phải thành công");
$qrData = json_decode($resQr['result']['content'][0]['text'], true);
assert($qrData['success'] === true);
assert(!empty($qrData['qr_image_url']));
assert(!empty($qrData['summary_text']));
echo "OK!\n";
echo "--- Báo cáo payment_qr_get ---\n";
echo $qrData['summary_text'] . "\n";
echo "------------------------------\n";

// 3. Kiểm tra payment_qr_get theo creditor_id và debtor_id
echo "3. Kiểm tra payment_qr_get theo creditor_id và debtor_id... ";
$resQr2 = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 3,
    'method' => 'tools/call',
    'params' => [
        'name' => 'payment_qr_get',
        'arguments' => [
            'creditor_id' => 1,
            'debtor_id'   => 2,
        ],
    ],
]);
assert(empty($resQr2['result']['isError']));
$qrData2 = json_decode($resQr2['result']['content'][0]['text'], true);
assert($qrData2['amount'] == 150000);
echo "OK!\n";

// 4. Kiểm tra payment_qr_get với tài khoản ngân hàng tùy chỉnh (custom bank)
echo "4. Kiểm tra payment_qr_get với tài khoản tùy chỉnh... ";
$resQr3 = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 4,
    'method' => 'tools/call',
    'params' => [
        'name' => 'payment_qr_get',
        'arguments' => [
            'amount'            => 50000,
            'memo'              => 'Tien cafe',
            'bank_bin'          => '970436', // Vietcombank
            'bank_account_no'   => '1234567890',
            'bank_account_name' => 'LE VAN BINH',
        ],
    ],
]);
assert(empty($resQr3['result']['isError']));
$qrData3 = json_decode($resQr3['result']['content'][0]['text'], true);
assert(str_contains($qrData3['qr_image_url'], '970436-1234567890-compact2.png'));
assert($qrData3['amount'] == 50000);
echo "OK!\n";

echo "\n>>> TẤT CẢ TEST PAYMENT QR MCP TOOL HOÀN THÀNH XUẤT SẮC! <<<\n";
