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

use SimpleFinance\BankList;
use SimpleFinance\Models\User;
use SimpleFinance\Models\Member;
use SimpleFinance\DebtManager;

echo "=== TEST TÍNH NĂNG TÀI KHOẢN NGÂN HÀNG & MÃ VIETQR TRẢ NỢ ===\n\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Bảng users
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
");

// Bảng members
$pdo->exec("
CREATE TABLE members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER DEFAULT NULL,
    name TEXT NOT NULL,
    phone TEXT,
    email TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// Bảng groups
$pdo->exec("
CREATE TABLE groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    owner_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// Bảng debts
$pdo->exec("
CREATE TABLE debts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL DEFAULT 1,
    debtor_id INTEGER NOT NULL,
    creditor_id INTEGER NOT NULL,
    amount REAL NOT NULL DEFAULT 0.00,
    debt_token TEXT DEFAULT NULL,
    payment_notified_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

$userModel = new User($pdo);
$memberModel = new Member($pdo);
$debtManager = new DebtManager($pdo);

// 1. Test BankList & VietQR Generator
echo "1. Test BankList và hàm sinh VietQR... ";
$banks = BankList::getBanks();
assert(count($banks) >= 25, "Bank list must have >= 25 banks");
$mb = BankList::findByBin('970422');
assert($mb !== null && $mb['short_name'] === 'MBBank', "MBBank lookup by BIN matches");

$qrUrl = BankList::generateVietQrUrl('970422', '0912345678', 50000, 'Nam tra no Binh', 'NGUYEN VAN A');
assert(str_contains($qrUrl, '970422-0912345678-compact2.png'), "QR URL matches BIN and account");
assert(str_contains($qrUrl, 'amount=50000'), "QR URL includes amount");
assert(str_contains($qrUrl, 'addInfo=Nam+tra+no+Binh'), "QR URL includes memo");
echo "OK!\n";

// 2. Test Tạo User và Cập Nhật Tài Khoản Ngân Hàng
echo "2. Test User cập nhật tài khoản ngân hàng... ";
$pdo->exec("
    INSERT INTO users (id, username, email, full_name, api_key) 
    VALUES (1, 'namth', 'nam@test.com', 'Nam Tran', 'sf_usr_test123456789012345678901234567890123456789')
");
$user = $userModel->findById(1);
assert($user !== null);
assert($user['bank_account_no'] === null);

// Cập nhật thông tin ngân hàng
$userModel->updateBankInfo(1, '970422', 'MBBank', '0988776655', 'TRAN HOANG NAM');
$updatedUser = $userModel->findById(1);
assert($updatedUser['bank_bin'] === '970422');
assert($updatedUser['bank_name'] === 'MBBank');
assert($updatedUser['bank_account_no'] === '0988776655');
assert($updatedUser['bank_account_name'] === 'TRAN HOANG NAM');
echo "OK!\n";

// 3. Test liên kết User với Member & Sinh Khoản Nợ
echo "3. Test liên kết Member và sinh Debt Token... ";
$debtor = $memberModel->create('Bình', '0901234567', 'binh@test.com');
$creditor = $memberModel->create('Nam Tran', '0909876543', 'nam@test.com');
// Liên kết creditor với User 1
$pdo->exec("UPDATE members SET user_id = 1 WHERE id = {$creditor['id']}");

// Thêm nợ: Bình nợ Nam 150,000 đ
$debtManager->addDebt($debtor['id'], $creditor['id'], 150000, 1);

$summary = $debtManager->getSummary(null, 1);
assert(count($summary) === 1);
$debtItem = $summary[0];
assert((float)$debtItem['amount'] === 150000.0);
assert(!empty($debtItem['debt_token']), "debt_token must be generated automatically");
assert($debtItem['bank_bin'] === '970422', "Bank BIN must be joined from user");
assert($debtItem['bank_account_no'] === '0988776655', "Account no must be joined from user");
echo "OK!\n";

// 4. Test Tra cứu nợ bằng Token công khai (cho pay.php)
echo "4. Test Tra cứu nợ bằng token (pay.php logic)... ";
$token = $debtItem['debt_token'];
$publicDebt = $debtManager->getDebtByToken($token);
assert($publicDebt !== null);
assert($publicDebt['debtor_name'] === 'Bình');
assert($publicDebt['creditor_name'] === 'Nam Tran');
assert((float)$publicDebt['amount'] === 150000.0);
assert($publicDebt['bank_bin'] === '970422');
assert($publicDebt['payment_notified_at'] === null);
echo "OK!\n";

// 5. Test Con nợ bấm nút 'Tôi đã chuyển khoản'
echo "5. Test Con nợ bấm 'Tôi đã chuyển khoản'... ";
$notified = $debtManager->notifyPayment($token);
assert($notified === true);

$checkNotified = $debtManager->getDebtByToken($token);
assert(!empty($checkNotified['payment_notified_at']), "payment_notified_at must be populated");
echo "OK!\n";

echo "\n=======================================================\n";
echo " TẤT CẢ TEST TÀI KHOẢN NGÂN HÀNG & VIETQR ĐỀU PASS 100%!\n";
echo "=======================================================\n";
