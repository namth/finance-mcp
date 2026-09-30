<?php
spl_autoload_register(function ($class) {
    $prefix = 'SimpleFinance\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use SimpleFinance\Database;
use SimpleFinance\McpServer;
use SimpleFinance\Models\Member;
use SimpleFinance\Models\Place;
use SimpleFinance\Models\Product;
use SimpleFinance\Models\Transaction;
use SimpleFinance\DebtManager;

echo "=== TEST TRANSACTION UPDATE / DELETE & LOCATION RESOLUTION ===\n";

// Khởi tạo SQLite in-memory để test độc lập
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Chạy schema tối thiểu
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `users` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `username` TEXT,
        `full_name` TEXT,
        `email` TEXT,
        `api_key` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS `groups` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `name` TEXT,
        `owner_id` INTEGER,
        `description` TEXT
    );
    CREATE TABLE IF NOT EXISTS `group_members` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `group_id` INTEGER NOT NULL,
        `user_id` INTEGER NOT NULL,
        `display_name` TEXT NOT NULL,
        `role` TEXT NOT NULL DEFAULT 'member',
        `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(group_id, user_id)
    );
    CREATE TABLE IF NOT EXISTS `members` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `user_id` INTEGER,
        `name` TEXT,
        `phone` TEXT,
        `email` TEXT
    );
    CREATE TABLE IF NOT EXISTS `places` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `group_id` INTEGER NOT NULL DEFAULT 1,
        `name` TEXT NOT NULL,
        `address` TEXT,
        `map_url` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS `products` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `group_id` INTEGER NOT NULL DEFAULT 1,
        `place_id` INTEGER DEFAULT NULL,
        `name` TEXT NOT NULL,
        `default_price` REAL DEFAULT NULL,
        `description` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS `transactions` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `group_id` INTEGER NOT NULL DEFAULT 1,
        `place_id` INTEGER DEFAULT NULL,
        `title` TEXT NOT NULL,
        `payer_id` INTEGER NOT NULL,
        `total_amount` REAL NOT NULL DEFAULT 0.00,
        `status` TEXT NOT NULL DEFAULT 'completed',
        `note` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS `transaction_items` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `transaction_id` INTEGER NOT NULL,
        `product_id` INTEGER NOT NULL,
        `price` REAL NOT NULL,
        `quantity` INTEGER NOT NULL DEFAULT 1,
        `note` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS `transaction_item_members` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `item_id` INTEGER NOT NULL,
        `member_id` INTEGER NOT NULL,
        `share_amount` REAL NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS `debts` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `group_id` INTEGER NOT NULL DEFAULT 1,
        `debtor_id` INTEGER NOT NULL,
        `creditor_id` INTEGER NOT NULL,
        `amount` REAL NOT NULL,
        `debt_token` TEXT,
        `payment_notified_at` DATETIME DEFAULT NULL,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS `debt_settlements` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `group_id` INTEGER NOT NULL DEFAULT 1,
        `debtor_id` INTEGER NOT NULL,
        `creditor_id` INTEGER NOT NULL,
        `amount` REAL NOT NULL,
        `note` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    );
");

// Thiết lập kết nối
Database::setConnection($pdo);

$userModel = new \SimpleFinance\Models\User($pdo);
$groupModel = new \SimpleFinance\Models\Group($pdo);
$memberModel = new Member($pdo);
$placeModel = new Place($pdo);
$productModel = new Product($pdo);
$txModel = new Transaction($pdo);
$debtManager = new DebtManager($pdo);

// 1. Tạo dữ liệu mẫu
$pdo->exec("INSERT INTO users (id, username, email, full_name, api_key) VALUES (1, 'admin', 'admin@example.com', 'Admin User', 'sf_usr_test123')");
$u = ['id' => 1, 'username' => 'admin', 'full_name' => 'Admin User'];
$g = $groupModel->create('Nhóm Du Lịch', (int)$u['id']);
$gid = (int)$g['id'];

$m1 = $memberModel->create('Nam Trần', null, 'nam@example.com');
$m2 = $memberModel->create('Bình Lê', null, 'binh@example.com');
$m3 = $memberModel->create('Cường Vũ', null, 'cuong@example.com');

echo "✓ Tạo nhóm & 3 thành viên: #{$m1['id']}, #{$m2['id']}, #{$m3['id']}\n";

// 2. Tạo sẵn 1 quán ở đường Mai Chí Thọ và 1 sản phẩm tại đó
$place1 = $placeModel->create('The Coffee House Mai Chí Thọ', 'Đường Mai Chí Thọ, Quận 2', $gid);
$prod1 = $productModel->create('Cà phê sữa đá', 35000, 'Cà phê MCT', $gid, (int)$place1['id']);

// Tạo 1 quán khác ở quận 1 có cùng tên sản phẩm nhưng giá khác
$place2 = $placeModel->create('The Coffee House Đồng Khởi', 'Đường Đồng Khởi, Quận 1', $gid);
$prod2 = $productModel->create('Cà phê sữa đá', 45000, 'Cà phê Q1', $gid, (int)$place2['id']);

// 3. Test resolveProduct với các cấp độ ưu tiên
// Priority 1: Khớp tên món + địa chỉ "Mai Chí Thọ" (kể cả tên quán chỉ ghi tắt hoặc không ghi)
$resolved = $productModel->resolveProduct('Cà phê sữa đá', null, 'đường Mai Chí Thọ', null, $gid);
assert($resolved['id'] === $prod1['id'], "Phải khớp prod1 ở Mai Chí Thọ");
echo "✓ Khớp sản phẩm theo địa chỉ quán (Mai Chí Thọ): ID={$resolved['id']}, Giá={$resolved['default_price']}\n";

// Priority 2: Khớp tên món + địa chỉ Đồng Khởi
$resolvedQ1 = $productModel->resolveProduct('Cà phê sữa đá', null, 'Đồng Khởi', null, $gid);
assert($resolvedQ1['id'] === $prod2['id'], "Phải khớp prod2 ở Đồng Khởi");
echo "✓ Khớp sản phẩm theo địa chỉ quán (Đồng Khởi): ID={$resolvedQ1['id']}, Giá={$resolvedQ1['default_price']}\n";

// 4. Test tự động tạo mới quán và sản phẩm nếu chưa có
$newProd = $productModel->resolveProduct('Bạc xỉu đặc biệt', 'Highlands Mai Chí Thọ', 'Đường Mai Chí Thọ', 40000, $gid);
assert($newProd['name'] === 'Bạc xỉu đặc biệt');
assert((float)$newProd['default_price'] === 40000.0);
echo "✓ Tự động tạo sản phẩm mới + quán mới: ID={$newProd['id']}, Tên={$newProd['name']}, Quán={$newProd['place_name']}\n";

// 5. Test MCP Server: transaction_create lấy giá từ catalog
$server = new McpServer(['id' => $u['id'], 'username' => 'admin', 'full_name' => 'Admin User']);

$createReq = [
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'tools/call',
    'params' => [
        'name' => 'transaction_create',
        'arguments' => [
            'group_id' => $gid,
            'title' => 'Cafe sáng Mai Chí Thọ',
            'payer_id' => $m1['id'], // Nam trả
            'place_address' => 'Mai Chí Thọ',
            'items' => [
                [
                    'product_name' => 'Cà phê sữa đá', // Không truyền price -> phải tự lấy 35000 của Mai Chí Thọ
                    'quantity' => 2,
                    'member_ids' => [$m1['id'], $m2['id']], // Nam và Bình uống
                ]
            ]
        ]
    ]
];

$resCreate = $server->handleRequest($createReq);
assert($resCreate['result']['isError'] === false);
$resData = json_decode($resCreate['result']['content'][0]['text'], true);
$tx = $resData['transaction'];
$txId = (int)$tx['id'];
assert((float)$tx['total_amount'] === 70000.0, "Tổng tiền 2 ly * 35000 = 70000");
echo "✓ Tạo transaction thành công tự động lấy giá catalog (70,000đ)\n";

// Kiểm tra nợ: Bình nợ Nam 35,000đ
$debts = $debtManager->getSummary(null, $gid);
assert(count($debts) === 1);
assert((float)$debts[0]['amount'] === 35000.0);
echo "✓ Bảng công nợ: {$debts[0]['debtor_name']} nợ {$debts[0]['creditor_name']} 35,000đ\n";

// 6. Test MCP Server: transaction_update sửa giá sản phẩm
// Giả sử giá ly cà phê thực tế là 40,000đ (tổng 80,000đ -> Bình nợ 40,000đ)
$updateReq = [
    'jsonrpc' => '2.0',
    'id' => 2,
    'method' => 'tools/call',
    'params' => [
        'name' => 'transaction_update',
        'arguments' => [
            'id' => $txId,
            'group_id' => $gid,
            'title' => 'Cafe sáng Mai Chí Thọ (đã sửa giá)',
            'items' => [
                [
                    'product_name' => 'Cà phê sữa đá',
                    'price' => 40000,
                    'quantity' => 2,
                    'member_ids' => [$m1['id'], $m2['id']],
                ]
            ]
        ]
    ]
];

$resUpdate = $server->handleRequest($updateReq);
assert($resUpdate['result']['isError'] === false);
$resUpData = json_decode($resUpdate['result']['content'][0]['text'], true);
$upTx = $resUpData['transaction'];
assert((float)$upTx['total_amount'] === 80000.0);

// Kiểm tra nợ sau update: Bình phải nợ Nam 40,000đ
$debtsAfterUpdate = $debtManager->getSummary(null, $gid);
assert(count($debtsAfterUpdate) === 1);
assert((float)$debtsAfterUpdate[0]['amount'] === 40000.0, "Bình phải nợ 40,000đ sau khi sửa giá");
echo "✓ transaction_update thành công: Tổng tiền mới 80,000đ, nợ tự tính lại = 40,000đ\n";

// 7. Test MCP Server: transaction_delete xóa giao dịch
$delReq = [
    'jsonrpc' => '2.0',
    'id' => 3,
    'method' => 'tools/call',
    'params' => [
        'name' => 'transaction_delete',
        'arguments' => [
            'id' => $txId,
            'group_id' => $gid,
        ]
    ]
];

$resDel = $server->handleRequest($delReq);
assert($resDel['result']['isError'] === false);

// Kiểm tra nợ sau delete: nợ phải = 0
$debtsAfterDel = $debtManager->getSummary(null, $gid);
assert(count($debtsAfterDel) === 0, "Sau khi xóa giao dịch, công nợ phải về 0");
echo "✓ transaction_delete thành công: Đã xóa giao dịch và công nợ tự động về 0\n";

// 8. Test MCP Server: product_list với bộ lọc quán (Place Resolution & Filtering)
echo "\n--- TEST PRODUCT_LIST WITH PLACE FILTERING ---\n";

// 8.1. Lọc theo quán Mai Chí Thọ: phải ra giá 35,000đ của quán Mai Chí Thọ
$listReqMCT = [
    'jsonrpc' => '2.0',
    'id' => 4,
    'method' => 'tools/call',
    'params' => [
        'name' => 'product_list',
        'arguments' => [
            'group_id' => $gid,
            'place' => 'Mai Chí Thọ',
        ]
    ]
];
$resMCT = $server->handleRequest($listReqMCT);
assert($resMCT['result']['isError'] === false);
$dataMCT = json_decode($resMCT['result']['content'][0]['text'], true);
assert($dataMCT['filter_mode'] === 'by_place');
assert($dataMCT['place_identified']['id'] === $place1['id']);
assert(count($dataMCT['products']) >= 1);
$foundMCT = false;
foreach ($dataMCT['products'] as $p) {
    if ($p['name'] === 'Cà phê sữa đá') {
        assert((float)$p['default_price'] === 35000.0, "Giá ở Mai Chí Thọ phải là 35,000đ");
        $foundMCT = true;
    }
}
assert($foundMCT);
echo "✓ product_list xác định quán Mai Chí Thọ thành công: lấy đúng sản phẩm với giá 35,000đ\n";

// 8.2. Lọc theo quán Đồng Khởi: phải ra giá 45,000đ của quán Đồng Khởi
$listReqDK = [
    'jsonrpc' => '2.0',
    'id' => 5,
    'method' => 'tools/call',
    'params' => [
        'name' => 'product_list',
        'arguments' => [
            'group_id' => $gid,
            'place' => 'Đồng Khởi',
        ]
    ]
];
$resDK = $server->handleRequest($listReqDK);
assert($resDK['result']['isError'] === false);
$dataDK = json_decode($resDK['result']['content'][0]['text'], true);
assert($dataDK['filter_mode'] === 'by_place');
assert($dataDK['place_identified']['id'] === $place2['id']);
assert(count($dataDK['products']) === 1);
assert((float)$dataDK['products'][0]['default_price'] === 45000.0, "Giá ở Đồng Khởi phải là 45,000đ");
echo "✓ product_list xác định quán Đồng Khởi thành công: lấy đúng sản phẩm với giá 45,000đ\n";

// 8.3. Không xác định được quán (quán không tồn tại) -> fallback lấy danh sách sản phẩm phù hợp trong nhóm
$listReqUnknown = [
    'jsonrpc' => '2.0',
    'id' => 6,
    'method' => 'tools/call',
    'params' => [
        'name' => 'product_list',
        'arguments' => [
            'group_id' => $gid,
            'place' => 'Quán Vỉa Hè Không Tồn Tại',
        ]
    ]
];
$resUnknown = $server->handleRequest($listReqUnknown);
assert($resUnknown['result']['isError'] === false);
$dataUnknown = json_decode($resUnknown['result']['content'][0]['text'], true);
assert($dataUnknown['filter_mode'] === 'group_fallback');
assert($dataUnknown['place_identified'] === null);
assert(count($dataUnknown['products']) >= 2, "Fallback phải trả về toàn bộ sản phẩm trong nhóm");
echo "✓ product_list không xác định được quán -> Fallback lấy danh sách sản phẩm trong nhóm thành công\n";

// 8.4. Lọc theo tên sản phẩm kết hợp quán
$listReqFilter = [
    'jsonrpc' => '2.0',
    'id' => 7,
    'method' => 'tools/call',
    'params' => [
        'name' => 'product_list',
        'arguments' => [
            'group_id' => $gid,
            'place' => 'Mai Chí Thọ',
            'name' => 'Bạc xỉu',
        ]
    ]
];
$resFilter = $server->handleRequest($listReqFilter);
assert($resFilter['result']['isError'] === false);
$dataFilter = json_decode($resFilter['result']['content'][0]['text'], true);
assert(count($dataFilter['products']) === 1);
assert($dataFilter['products'][0]['name'] === 'Bạc xỉu đặc biệt');
echo "✓ product_list kết hợp lọc quán và tên sản phẩm thành công\n";

// --- TEST 9: UPDATE ITEM PRICE & FULL TRANSACTION EDIT ---
echo "\n--- TEST 9: UPDATE ITEM PRICE & FULL TRANSACTION EDIT ---\n";
// Tạo 1 transaction mới
$txNew = $txModel->create(
    "Test Edit Transaction",
    $m1['id'],
    [
        [
            'product_id' => $prod1['id'],
            'price' => 30000,
            'quantity' => 2,
            'member_ids' => [$m1['id'], $m2['id']]
        ]
    ],
    'completed',
    'Ghi chú test',
    $gid,
    null,
    null,
    $place1['id']
);
$txNewId = (int)$txNew['id'];
$debtManager->processTransaction($txNewId);

// Test 9.1: updateItemPrice
$createdTx = $txModel->find($txNewId);
$itemId = (int)$createdTx['items'][0]['id'];
$txModel->updateItemPrice($txNewId, $itemId, 35000, 3); // 35k * 3 = 105k
$debtManager->recalculateAll($gid);

$txAfterPriceUpdate = $txModel->find($txNewId);
assert((float)$txAfterPriceUpdate['total_amount'] === 105000.0, "Tổng tiền sau updateItemPrice phải là 105,000");
assert((float)$txAfterPriceUpdate['items'][0]['price'] === 35000.0);
assert((int)$txAfterPriceUpdate['items'][0]['quantity'] === 3);
echo "✓ updateItemPrice hoạt động chính xác (tổng tiền 105,000đ, chia lại cho thành viên)\n";

// Test 9.2: Full transaction edit with new place & items
$txUpdated = $txModel->update($txNewId, [
    'title' => 'Đã sửa toàn bộ giao dịch',
    'payer_id' => $m2['id'],
    'place_id' => $place2['id'],
    'status' => 'completed',
    'items' => [
        [
            'product_id' => $prod2['id'],
            'price' => 45000,
            'quantity' => 1,
            'member_ids' => [$m1['id'], $m2['id'], $m3['id']],
            'note' => 'Món mới sửa'
        ]
    ]
], $gid);
$debtManager->recalculateAll($gid);

assert($txUpdated['title'] === 'Đã sửa toàn bộ giao dịch');
assert((int)$txUpdated['payer_id'] === (int)$m2['id']);
assert((int)$txUpdated['place_id'] === (int)$place2['id']);
assert((float)$txUpdated['total_amount'] === 45000.0);
assert(count($txUpdated['items']) === 1);
assert((int)$txUpdated['items'][0]['product_id'] === (int)$prod2['id']);
assert(count($txUpdated['items'][0]['members']) === 3);
echo "✓ Transaction::update sửa toàn bộ giao dịch (quán, người trả, món, thành viên) thành công\n";

// Test 9.3: Place with Google Maps URL
$placeWithMap = $placeModel->create('Quán Cafe View Đẹp', '123 Nguyễn Huệ', $gid, 'https://maps.app.goo.gl/example123');
assert($placeWithMap['map_url'] === 'https://maps.app.goo.gl/example123');

$placeModel->update((int)$placeWithMap['id'], ['map_url' => 'https://maps.app.goo.gl/updated456']);
$checkPlace = $placeModel->find((int)$placeWithMap['id']);
assert($checkPlace['map_url'] === 'https://maps.app.goo.gl/updated456');

// Gán quán này vào transaction và kiểm tra place_map_url
$txMapTest = $txModel->create('Hẹn hò cafe', $m1['id'], [
    [
        'product_id' => $prod1['id'],
        'price' => 35000,
        'quantity' => 1,
        'member_ids' => [$m1['id']]
    ]
], 'completed', null, $gid, null, null, (int)$placeWithMap['id']);

$txFound = $txModel->find((int)$txMapTest['id']);
assert($txFound['place_map_url'] === 'https://maps.app.goo.gl/updated456');
echo "✓ Place map_url (Google Maps link) lưu trữ, cập nhật và hiển thị trong transaction thành công\n";

echo "\n>>> TẤT CẢ CÁC BÀI TEST ĐÃ VƯỢT QUA XUẤT SẮC! <<<\n";

