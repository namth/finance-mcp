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

use SimpleFinance\Models\Place;
use SimpleFinance\Models\Product;
use SimpleFinance\Models\Member;
use SimpleFinance\Models\Transaction;

echo "=== TEST TÍNH NĂNG QUÁN & ĐỊA ĐIỂM (PLACES & PRODUCTS) ===\n\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Bảng members
$pdo->exec("
CREATE TABLE members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    phone TEXT,
    email TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// Bảng products
$pdo->exec("
CREATE TABLE products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL DEFAULT 1,
    place_id INTEGER DEFAULT NULL,
    name TEXT NOT NULL,
    default_price REAL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// Bảng transactions
$pdo->exec("
CREATE TABLE transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL DEFAULT 1,
    title TEXT NOT NULL,
    payer_id INTEGER NOT NULL,
    total_amount REAL NOT NULL DEFAULT 0.00,
    status TEXT NOT NULL DEFAULT 'completed',
    note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// Bảng transaction_items
$pdo->exec("
CREATE TABLE transaction_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    price REAL NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    subtotal REAL NOT NULL DEFAULT 0.00,
    note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// Bảng transaction_item_members
$pdo->exec("
CREATE TABLE transaction_item_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    member_id INTEGER NOT NULL,
    share_amount REAL NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

$placeModel = new Place($pdo);
$productModel = new Product($pdo);
$memberModel = new Member($pdo);
$txModel = new Transaction($pdo);

// 1. Test tạo Quán
echo "1. Test tạo Quán mới... ";
$place1 = $placeModel->create("Highlands Coffee", "123 Lê Lợi, Q1", 1);
assert($place1['id'] > 0);
assert($place1['name'] === "Highlands Coffee");
assert($place1['address'] === "123 Lê Lợi, Q1");

$place2 = $placeModel->create("Phở Thìn", "13 Lò Đúc, Hà Nội", 1);
assert($place2['id'] > 0);
echo "OK!\n";

// 2. Test lấy danh sách quán theo nhóm
echo "2. Test lấy danh sách quán... ";
$placesGroup1 = $placeModel->all(1);
assert(count($placesGroup1) === 2);
echo "OK!\n";

// 3. Test cập nhật quán
echo "3. Test cập nhật quán... ";
$updatedPlace = $placeModel->update($place1['id'], [
    'name'    => "Highlands Coffee Hàm Nghi",
    'address' => "45 Hàm Nghi, Q1",
]);
assert($updatedPlace['name'] === "Highlands Coffee Hàm Nghi");
assert($updatedPlace['address'] === "45 Hàm Nghi, Q1");
echo "OK!\n";

// 4. Test tạo sản phẩm gắn với Quán
echo "4. Test tạo sản phẩm gắn quán... ";
$prod1 = $productModel->create("Phin Sữa Đá", 35000, "Cà phê truyền thống", 1, $place1['id']);
assert($prod1['id'] > 0);
assert($prod1['place_id'] == $place1['id']);
assert($prod1['place_name'] === "Highlands Coffee Hàm Nghi");
assert($prod1['place_address'] === "45 Hàm Nghi, Q1");

$prod2 = $productModel->create("Phở Tái Lăn", 75000, "Đặc sản", 1, $place2['id']);
assert($prod2['place_name'] === "Phở Thìn");
echo "OK!\n";

// 5. Test Transaction có chứa sản phẩm gắn Quán
echo "5. Test chi tiết hóa đơn (Transaction detail) hiển thị Quán... ";
$m1 = $memberModel->create("Nam", "0901234567");
$m2 = $memberModel->create("Bình", "0909876543");

$tx = $txModel->create(
    "Cà phê sáng",
    $m1['id'],
    [
        [
            'product_id' => $prod1['id'],
            'price'      => 35000,
            'quantity'   => 2,
            'member_ids' => [$m1['id'], $m2['id']],
        ]
    ],
    'completed',
    'Gặp mặt đầu tuần',
    1
);

assert($tx['id'] > 0);
assert(count($tx['items']) === 1);
$item = $tx['items'][0];
assert($item['product_name'] === "Phin Sữa Đá");
assert($item['place_name'] === "Highlands Coffee Hàm Nghi");
assert($item['place_address'] === "45 Hàm Nghi, Q1");
echo "OK!\n";

// 6. Test xóa Quán và an toàn dữ liệu sản phẩm
echo "6. Test xóa Quán và gỡ place_id ở sản phẩm... ";
$deleted = $placeModel->delete($place1['id']);
assert($deleted === true);
$prodCheck = $productModel->find($prod1['id']);
assert($prodCheck['place_id'] === null);
assert($prodCheck['place_name'] === null);
echo "OK!\n";

echo "\n============================================\n";
echo " TẤT CẢ CÁC BƯỚC TEST QUÁN & ĐỊA ĐIỂM ĐỀU PASS!\n";
echo "============================================\n";
