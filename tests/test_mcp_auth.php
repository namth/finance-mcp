<?php

/**
 * Test authentication on McpServer JSON-RPC directly
 */

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

use SimpleFinance\Security\Crypto;
use SimpleFinance\Models\User;
use SimpleFinance\McpServer;

echo "Test MCP Protocol & Tools...\n";

// Set up in-memory sqlite
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
$u = $userModel->create('admin', 'admin@example.com', 'Administrator', [
    'Sinh nhật' => '10-10-1990',
    'Ngày cưới' => '12-12-2020',
    'Tốt nghiệp' => '20-05-2012'
]);

$server = new McpServer($u);

// 1. group_create
$res = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 10,
    'method' => 'tools/call',
    'params' => [
        'name' => 'group_create',
        'arguments' => ['name' => 'Chi tiêu Team Dev', 'description' => 'Team engineering']
    ]
]);
assert(empty($res['result']['isError']), "group_create should succeed");
$createdGroup = json_decode($res['result']['content'][0]['text'], true);
assert($createdGroup['name'] === 'Chi tiêu Team Dev');
echo "group_create: OK\n";

// 2. member_create
$res = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 11,
    'method' => 'tools/call',
    'params' => [
        'name' => 'member_create',
        'arguments' => ['name' => 'Bình An', 'group_id' => $createdGroup['id']]
    ]
]);
assert(empty($res['result']['isError']), "member_create should succeed");
echo "member_create: OK\n";

// 3. product_create
$res = $server->handleRequest([
    'jsonrpc' => '2.0',
    'id' => 12,
    'method' => 'tools/call',
    'params' => [
        'name' => 'product_create',
        'arguments' => ['name' => 'Cà phê sáng', 'default_price' => 35000, 'group_id' => $createdGroup['id']]
    ]
]);
assert(empty($res['result']['isError']), "product_create should succeed");
echo "product_create: OK\n";

echo "TẤT CẢ TEST MCP TOOL THÀNH CÔNG!\n";
