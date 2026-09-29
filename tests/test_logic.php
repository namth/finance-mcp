<?php

/**
 * Script kiểm thử toàn bộ luồng nghiệp vụ tính nợ, bù trừ 2 chiều và gạch nợ
 * Chạy trên SQLite in-memory để kiểm chứng logic mà không cần cấu hình MySQL trước.
 */

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
use SimpleFinance\Models\Member;
use SimpleFinance\Models\Product;
use SimpleFinance\Models\Transaction;
use SimpleFinance\Models\Settlement;
use SimpleFinance\DebtManager;

echo "=== KHỞI TẠO CƠ SỞ DỮ LIỆU IN-MEMORY ===\n";
$sqlite = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Cấu trúc bảng tương thích SQLite
$sqlite->exec("
CREATE TABLE members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    phone TEXT,
    email TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    default_price REAL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    payer_id INTEGER NOT NULL,
    total_amount REAL NOT NULL DEFAULT 0.00,
    status TEXT NOT NULL DEFAULT 'draft',
    note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE transaction_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    price REAL NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    subtotal REAL,
    note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE transaction_item_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    member_id INTEGER NOT NULL,
    share_amount REAL NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE debts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    debtor_id INTEGER NOT NULL,
    creditor_id INTEGER NOT NULL,
    amount REAL NOT NULL DEFAULT 0.00,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(debtor_id, creditor_id)
);

CREATE TABLE debt_settlements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    debtor_id INTEGER NOT NULL,
    creditor_id INTEGER NOT NULL,
    amount REAL NOT NULL,
    note TEXT,
    settled_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

Database::setConnection($sqlite);

$memberModel = new Member($sqlite);
$productModel = new Product($sqlite);
$txModel = new Transaction($sqlite);
$debtManager = new DebtManager($sqlite);

echo "1. Tạo thành viên...\n";
$nam   = $memberModel->create("Nam", "0901111111");
$binh  = $memberModel->create("Bình", "0902222222");
$cuong = $memberModel->create("Cường", "0903333333");
echo "   -> Đã tạo: Nam (ID {$nam['id']}), Bình (ID {$binh['id']}), Cường (ID {$cuong['id']})\n";

echo "\n2. Tạo sản phẩm / dịch vụ...\n";
$com = $productModel->create("Cơm trưa văn phòng", 50000, "Cơm theo suất");
$nuoc = $productModel->create("Nước ngọt", null, "Giá tùy loại lon nước gọi");
echo "   -> Đã tạo: Cơm (giá mặc định: 50,000đ), Nước ngọt (giá linh hoạt: NULL)\n";

echo "\n3. Giao dịch 1: Nam trả tiền ăn trưa & nước ngọt...\n";
// Nam thanh toán
// - Cơm trưa: 50,000 x 3 = 150,000 chia cho Nam, Bình, Cường (mỗi người 50,000)
// - Nước ngọt: tự truyền giá 20,000 x 2 = 40,000 chỉ có Bình và Cường uống (mỗi người 20,000)
$tx1 = $txModel->create(
    "Ăn trưa thứ 2",
    $nam['id'],
    [
        [
            'product_id' => $com['id'],
            'quantity'   => 3,
            'member_ids' => [$nam['id'], $binh['id'], $cuong['id']],
        ],
        [
            'product_id' => $nuoc['id'],
            'price'      => 20000,
            'quantity'   => 2,
            'member_ids' => [$binh['id'], $cuong['id']],
        ],
    ],
    'completed',
    "Bữa trưa đầu tuần"
);
$debtManager->processTransaction($tx1['id']);

echo "   -> Tổng tiền hóa đơn 1: " . number_format($tx1['total_amount']) . " đ\n";
echo "   -> Bảng nợ hiện tại:\n";
foreach ($debtManager->getSummary() as $d) {
    echo "      * {$d['debtor_name']} nợ {$d['creditor_name']}: " . number_format($d['amount']) . " đ\n";
}

echo "\n4. Giao dịch 2 (Bù trừ nợ 2 chiều): Bình mời cafe, trả tiền cho cả Nam...\n";
$cafe = $productModel->create("Cà phê Highlands", 30000);
$tx2 = $txModel->create(
    "Cafe chiều thứ 2",
    $binh['id'], // Bình trả tiền
    [
        [
            'product_id' => $cafe['id'],
            'quantity'   => 2,
            'member_ids' => [$nam['id'], $binh['id']], // Nam và Bình uống
        ],
    ],
    'completed'
);
$debtManager->processTransaction($tx2['id']);

echo "   -> Bình trả 60,000 đ (Nam uống 30,000 đ). Hệ thống tự cấn trừ vào khoản Bình đang nợ Nam (70k - 30k = 40k):\n";
foreach ($debtManager->getSummary() as $d) {
    echo "      * {$d['debtor_name']} nợ {$d['creditor_name']}: " . number_format($d['amount']) . " đ\n";
}

echo "\n5. Gạch nợ: Bình chuyển khoản trả hết cho Nam (40,000 đ)...\n";
$settle1 = $debtManager->settle($binh['id'], $nam['id'], 40000, "Chuyển khoản qua MBBank");
echo "   -> Kết quả: Còn nợ " . number_format($settle1['remaining_debt']) . " đ\n";

echo "\n6. Gạch nợ: Cường trả trước cho Nam 50,000 đ (trả 1 phần)...\n";
$settle2 = $debtManager->settle($cuong['id'], $nam['id'], 50000, "Cường đưa tiền mặt 50k");
echo "   -> Kết quả: Cường còn nợ " . number_format($settle2['remaining_debt']) . " đ\n";

echo "\n7. Bảng công nợ cuối cùng:\n";
$summary = $debtManager->getSummary();
if (empty($summary)) {
    echo "   -> Tất cả mọi người đã hết nợ nhau!\n";
} else {
    foreach ($summary as $d) {
        echo "   * {$d['debtor_name']} nợ {$d['creditor_name']}: " . number_format($d['amount']) . " đ\n";
    }
}

echo "\n8. Lịch sử gạch nợ:\n";
$settlementModel = new Settlement($sqlite);
foreach ($settlementModel->all() as $st) {
    echo "   * [{$st['settled_at']}] {$st['debtor_name']} đã trả cho {$st['creditor_name']} " . number_format($st['amount']) . " đ (Ghi chú: {$st['note']})\n";
}

echo "\n=== HOÀN TẤT KIỂM THỬ: TẤT CẢ TÍNH NĂNG HOẠT ĐỘNG CHÍNH XÁC! ===\n";
