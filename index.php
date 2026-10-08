<?php

require_once __DIR__ . '/auth_check.php';

spl_autoload_register(function ($class) {
    $prefix = 'SimpleFinance\\';
    $baseDir = __DIR__ . '/src/';
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
use SimpleFinance\DebtManager;
use SimpleFinance\BankList;

$dbError = null;
$members = [];
$products = [];
$transactions = [];
$debts = [];
$totalSpent = 0;
$flashSuccess = '';
$flashError = '';

$currentGroupId = (int)($_SESSION['current_group_id'] ?? 1);

try {
    $memberModel = new Member();
    $productModel = new Product();
    $txModel = new Transaction();
    $debtManager = new DebtManager();

    // Xử lý action Gạch nợ (Settle Debt)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'settle') {
        $debtorId = (int)($_POST['debtor_id'] ?? 0);
        $creditorId = (int)($_POST['creditor_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $note = trim((string)($_POST['note'] ?? ''));

        if ($debtorId > 0 && $creditorId > 0 && $amount > 0) {
            $settleRes = $debtManager->settle($debtorId, $creditorId, $amount, $note, $currentGroupId);
            $flashSuccess = "Đã gạch nợ thành công số tiền " . number_format($amount) . " đ!";
        } else {
            $flashError = "Vui lòng nhập số tiền hợp lệ (> 0).";
        }
    }

    $members = $memberModel->all();
    $products = $productModel->all($currentGroupId);
    $transactions = $txModel->all(null, 5, 0, $currentGroupId); // 5 gần nhất của nhóm
    $debts = $debtManager->getSummary(null, $currentGroupId);

    foreach ($txModel->all('completed', 1000, 0, $currentGroupId) as $t) {
        $totalSpent += (float)$t['total_amount'];
    }
} catch (\Throwable $e) {
    $dbError = $e->getMessage();
}

$pageTitle = "Bảng Điều Khiển - SimpleFinance";
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($dbError): ?>
    <div class="mb-8 p-6 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 shadow-sm">
        <div class="flex items-start space-x-3">
            <svg class="h-6 w-6 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div>
                <h3 class="text-base font-bold text-amber-900">Chưa kết nối được với Cơ sở Dữ liệu MySQL</h3>
                <p class="mt-1 text-sm text-amber-800 leading-relaxed">
                    Hệ thống chưa thể đọc dữ liệu: <code class="bg-amber-100 px-2 py-0.5 rounded font-mono text-xs"><?= htmlspecialchars($dbError) ?></code>
                </p>
                <div class="mt-4 flex flex-wrap gap-3 text-xs">
                    <a href="config.php" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-amber-600 text-white font-semibold hover:bg-amber-700 transition">
                        Kiểm tra config.php
                    </a>
                    <span class="text-amber-700 self-center">Hãy đảm bảo bạn đã tạo Database và chạy lệnh import từ file <code class="font-mono font-bold">schema.sql</code></span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($flashSuccess): ?>
    <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between">
        <div class="flex items-center space-x-2">
            <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span class="font-medium"><?= htmlspecialchars($flashSuccess) ?></span>
        </div>
    </div>
<?php endif; ?>

<?php if ($flashError): ?>
    <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center space-x-2">
        <svg class="w-5 h-5 text-rose-600" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <span><?= htmlspecialchars($flashError) ?></span>
    </div>
<?php endif; ?>

<!-- 4 Thẻ Thống Kê Tổng Quan (2 cột trên mobile, 4 cột trên desktop) -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-5 mb-6 sm:mb-8">
    <!-- Thẻ 1: Tổng chi tiêu -->
    <div class="bg-white p-3.5 sm:p-6 rounded-2xl border border-slate-200 shadow-2xs flex items-center justify-between">
        <div>
            <p class="text-[10px] sm:text-xs font-semibold text-slate-500 uppercase tracking-wider">Tổng Đã Chi</p>
            <p class="mt-0.5 sm:mt-1 text-base sm:text-2xl font-bold text-slate-900"><?= number_format($totalSpent) ?> <span class="text-xs sm:text-sm font-normal text-slate-500">đ</span></p>
        </div>
        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
    </div>

    <!-- Thẻ 2: Thành viên -->
    <div class="bg-white p-3.5 sm:p-6 rounded-2xl border border-slate-200 shadow-2xs flex items-center justify-between">
        <div>
            <p class="text-[10px] sm:text-xs font-semibold text-slate-500 uppercase tracking-wider">Thành Viên</p>
            <p class="mt-0.5 sm:mt-1 text-base sm:text-2xl font-bold text-slate-900"><?= count($members) ?> <span class="text-xs sm:text-sm font-normal text-slate-500">người</span></p>
        </div>
        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
        </div>
    </div>

    <!-- Thẻ 3: Sản phẩm dịch vụ -->
    <div class="bg-white p-3.5 sm:p-6 rounded-2xl border border-slate-200 shadow-2xs flex items-center justify-between">
        <div>
            <p class="text-[10px] sm:text-xs font-semibold text-slate-500 uppercase tracking-wider">Sản Phẩm</p>
            <p class="mt-0.5 sm:mt-1 text-base sm:text-2xl font-bold text-slate-900"><?= count($products) ?> <span class="text-xs sm:text-sm font-normal text-slate-500">mục</span></p>
        </div>
        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
            </svg>
        </div>
    </div>

    <!-- Thẻ 4: Giao dịch -->
    <div class="bg-white p-3.5 sm:p-6 rounded-2xl border border-slate-200 shadow-2xs flex items-center justify-between">
        <div>
            <p class="text-[10px] sm:text-xs font-semibold text-slate-500 uppercase tracking-wider">Tổng Đợt Chi</p>
            <p class="mt-0.5 sm:mt-1 text-base sm:text-2xl font-bold text-slate-900"><?= count($transactions) ?> <span class="text-xs sm:text-sm font-normal text-slate-500">đợt</span></p>
        </div>
        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
        </div>
    </div>
</div>

<!-- Lối Tắt Sự Kiện Mua Chung Mới (FEAT-001) -->
<div class="mb-6 sm:mb-8 bg-gradient-to-r from-emerald-600 via-teal-600 to-indigo-700 rounded-2xl p-4 sm:p-6 text-white shadow-md shadow-emerald-900/10 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div class="flex items-center space-x-3 sm:space-x-4">
        <div class="w-12 h-12 rounded-2xl bg-white/15 backdrop-blur-md flex items-center justify-center text-white flex-shrink-0 text-xl font-bold">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
            </svg>
        </div>
        <div>
            <div class="flex items-center space-x-2">
                <span class="px-2 py-0.5 rounded-full bg-white/20 text-[10px] font-black uppercase tracking-wider">Mới</span>
                <h3 class="text-base sm:text-lg font-black tracking-tight">Sự Kiện Mua Chung & Gom Đơn</h3>
            </div>
            <p class="text-xs sm:text-sm text-emerald-100 mt-0.5">Tạo trang mua chung cho nhóm, chia sẻ link công khai cho mọi người chọn size, VietQR tự động & chốt hóa đơn.</p>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2.5">
        <a href="group_buys.php" class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-white text-emerald-800 text-xs sm:text-sm font-black shadow-sm hover:bg-emerald-50 active:scale-95 transition">
            + Tạo Sự Kiện Mua Chung
        </a>
    </div>
</div>

<!-- Khối Bảng Tổng Kết Công Nợ Tự Động -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-6 sm:mb-8">
    <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-3 bg-slate-50/50">
        <div>
            <h2 class="text-base sm:text-lg font-bold text-slate-900 flex items-center space-x-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block flex-shrink-0"></span>
                <span>Bảng Tổng Kết Công Nợ</span>
            </h2>
            <p class="text-[11px] sm:text-xs text-slate-500 mt-0.5">Tự động cấn trừ 2 chiều theo thời gian thực</p>
        </div>
        <div class="flex items-center space-x-2">
            <a href="settlements.php" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 transition">
                Xem lịch sử gạch nợ &rarr;
            </a>
        </div>
    </div>

    <?php if (empty($debts)): ?>
        <div class="py-10 sm:py-12 text-center px-4">
            <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <p class="text-sm font-semibold text-slate-800">Tất cả mọi người đã sòng phẳng!</p>
            <p class="text-xs text-slate-500 mt-1">Không có khoản nợ nào tồn đọng trong nhóm.</p>
        </div>
    <?php else: ?>
        <!-- Giao diện Thẻ trên Mobile (< md) -->
        <div class="md:hidden divide-y divide-slate-100">
            <?php foreach ($debts as $d): ?>
                <?php 
                    $qrDataJson = json_encode([
                        'debt_id'             => $d['id'],
                        'debtor_name'         => $d['debtor_name'],
                        'creditor_name'       => $d['creditor_name'],
                        'amount'              => (float)$d['amount'],
                        'token'               => $d['debt_token'] ?? '',
                        'bank_bin'            => $d['bank_bin'] ?? '',
                        'bank_name'           => $d['bank_name'] ?? '',
                        'bank_account_no'     => $d['bank_account_no'] ?? '',
                        'bank_account_name'   => $d['bank_account_name'] ?? $d['creditor_name'],
                        'payment_notified_at' => $d['payment_notified_at'] ?? null,
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                ?>
                <div class="p-4 space-y-3">
                    <!-- Người nợ -> Người nhận -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="w-7 h-7 rounded-full bg-rose-100 text-rose-700 font-bold text-xs flex items-center justify-center flex-shrink-0">
                                <?= mb_substr($d['debtor_name'], 0, 1, 'UTF-8') ?>
                            </div>
                            <span class="font-bold text-slate-900 text-xs sm:text-sm"><?= htmlspecialchars($d['debtor_name']) ?></span>
                        </div>

                        <div class="flex items-center space-x-1 text-slate-400 text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100">
                            <span>nợ</span>
                            <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                            </svg>
                        </div>

                        <div class="flex items-center space-x-2">
                            <div class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-700 font-bold text-xs flex items-center justify-center flex-shrink-0">
                                <?= mb_substr($d['creditor_name'], 0, 1, 'UTF-8') ?>
                            </div>
                            <span class="font-bold text-slate-900 text-xs sm:text-sm"><?= htmlspecialchars($d['creditor_name']) ?></span>
                        </div>
                    </div>

                    <!-- Số tiền & Trạng thái -->
                    <div class="flex items-center justify-between pt-1">
                        <div>
                            <span class="text-lg font-black text-rose-600"><?= number_format($d['amount']) ?> đ</span>
                            <span class="text-[10px] text-slate-400 block"><?= htmlspecialchars(substr($d['updated_at'], 0, 16)) ?></span>
                        </div>

                        <?php if (!empty($d['payment_notified_at'])): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300 animate-pulse">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1"></span>
                                Đã báo CK
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Thao tác trên mobile: 2 nút to dễ bấm -->
                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <button type="button" 
                                onclick='openQrModal(<?= $qrDataJson ?>)'
                                class="w-full flex items-center justify-center py-2 px-3 rounded-xl text-xs font-bold text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200 active:scale-95 transition">
                            <svg class="w-3.5 h-3.5 mr-1 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                            </svg>
                            Mã VietQR
                        </button>
                        <button type="button" 
                                onclick="openSettleModal(<?= $d['debtor_id'] ?>, '<?= htmlspecialchars(addslashes($d['debtor_name'])) ?>', <?= $d['creditor_id'] ?>, '<?= htmlspecialchars(addslashes($d['creditor_name'])) ?>', <?= $d['amount'] ?>)"
                                class="w-full flex items-center justify-center py-2 px-3 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm active:scale-95 transition">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Gạch Nợ
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Giao diện Bảng trên Desktop (>= md) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase">
                    <tr>
                        <th class="px-6 py-3 text-left">Người nợ (Debtor)</th>
                        <th class="px-6 py-3 text-center">Quan hệ</th>
                        <th class="px-6 py-3 text-left">Chủ nợ (Creditor)</th>
                        <th class="px-6 py-3 text-right">Số tiền nợ</th>
                        <th class="px-6 py-3 text-right">Cập nhật</th>
                        <th class="px-6 py-3 text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    <?php foreach ($debts as $d): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full bg-rose-100 text-rose-700 font-bold text-xs flex items-center justify-center">
                                        <?= mb_substr($d['debtor_name'], 0, 1, 'UTF-8') ?>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-slate-900"><?= htmlspecialchars($d['debtor_name']) ?></div>
                                        <div class="text-xs text-slate-400"><?= htmlspecialchars($d['debtor_phone'] ?? 'Không có SĐT') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-xs text-slate-400">
                                <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 font-medium inline-flex items-center">
                                    nợ
                                    <svg class="w-3.5 h-3.5 ml-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                    </svg>
                                </span>
                                <?php if (!empty($d['payment_notified_at'])): ?>
                                    <div class="mt-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300 animate-pulse" title="Con nợ đã báo chuyển khoản lúc <?= htmlspecialchars(substr($d['payment_notified_at'], 0, 16)) ?>">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1"></span>
                                            Đã báo CK
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 font-bold text-xs flex items-center justify-center">
                                        <?= mb_substr($d['creditor_name'], 0, 1, 'UTF-8') ?>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-slate-900"><?= htmlspecialchars($d['creditor_name']) ?></div>
                                        <div class="text-xs text-slate-400"><?= htmlspecialchars($d['creditor_phone'] ?? 'Không có SĐT') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <span class="text-base font-bold text-rose-600">
                                    <?= number_format($d['amount']) ?> đ
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs text-slate-400 font-normal">
                                <?= htmlspecialchars(substr($d['updated_at'], 0, 16)) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center space-x-1.5">
                                    <button type="button" 
                                            onclick='openQrModal(<?= json_encode([
                                                'debt_id'             => $d['id'],
                                                'debtor_name'         => $d['debtor_name'],
                                                'creditor_name'       => $d['creditor_name'],
                                                'amount'              => (float)$d['amount'],
                                                'token'               => $d['debt_token'] ?? '',
                                                'bank_bin'            => $d['bank_bin'] ?? '',
                                                'bank_name'           => $d['bank_name'] ?? '',
                                                'bank_account_no'     => $d['bank_account_no'] ?? '',
                                                'bank_account_name'   => $d['bank_account_name'] ?? $d['creditor_name'],
                                                'payment_notified_at' => $d['payment_notified_at'] ?? null,
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                            class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 hover:border-slate-400 shadow-2xs transition"
                                            title="Xem mã QR thanh toán hoặc lấy link gửi con nợ">
                                        <svg class="w-3.5 h-3.5 mr-1 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                                        </svg>
                                        Mã QR
                                    </button>
                                    <button type="button" 
                                            onclick="openSettleModal(<?= $d['debtor_id'] ?>, '<?= htmlspecialchars(addslashes($d['debtor_name'])) ?>', <?= $d['creditor_id'] ?>, '<?= htmlspecialchars(addslashes($d['creditor_name'])) ?>', <?= $d['amount'] ?>)"
                                            class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Gạch Nợ
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Giao Dịch Gần Đây -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-6 sm:mb-8">
    <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
        <h2 class="text-sm sm:text-base font-bold text-slate-900">Giao Dịch Gần Đây</h2>
        <a href="transactions.php" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 transition">
            Xem tất cả (<?= count($transactions) ?>) &rarr;
        </a>
    </div>

    <?php if (empty($transactions)): ?>
        <div class="py-8 text-center text-sm text-slate-400">
            Chưa có đợt chi tiêu nào được tạo.
        </div>
    <?php else: ?>
        <div class="divide-y divide-slate-100 text-sm">
            <?php foreach ($transactions as $tx): ?>
                <div class="p-3.5 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 sm:gap-3 hover:bg-slate-50 transition">
                    <div class="flex items-start space-x-3">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600 flex-shrink-0 font-bold text-xs">
                            #<?= $tx['id'] ?>
                        </div>
                        <div>
                            <div class="font-semibold text-slate-900 text-sm sm:text-base"><?= htmlspecialchars($tx['title']) ?></div>
                            <div class="text-[11px] sm:text-xs text-slate-500 mt-0.5">
                                Người trả: <span class="font-semibold text-emerald-700"><?= htmlspecialchars($tx['payer_name']) ?></span> &bull; Ngày chi: <span class="font-semibold text-slate-700"><?= date('d/m/Y', strtotime($tx['spent_at'] ?? $tx['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between sm:justify-end space-x-3 pt-1 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                        <span class="text-sm sm:text-base font-bold text-slate-900"><?= number_format($tx['total_amount']) ?> đ</span>
                        <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full <?= $tx['status'] === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                            <?= $tx['status'] === 'completed' ? 'Hoàn thành' : 'Nháp' ?>
                        </span>
                        <a href="transaction_detail.php?id=<?= $tx['id'] ?>" class="text-xs font-semibold text-slate-700 hover:text-emerald-700 transition px-2.5 py-1 rounded-lg border border-slate-200 hover:border-emerald-300 bg-white">
                            Chi tiết
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Gạch Nợ / Thanh Toán Nhanh -->
<div id="settleModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-bold text-slate-900">Gạch Nợ / Xác Nhận Thanh Toán</h3>
            <button onclick="closeSettleModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form method="POST" action="index.php" class="mt-4 space-y-4">
            <input type="hidden" name="action" value="settle">
            <input type="hidden" name="debtor_id" id="modalDebtorId">
            <input type="hidden" name="creditor_id" id="modalCreditorId">

            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm">
                <div class="flex justify-between items-center text-xs text-slate-500 mb-1">
                    <span>Người trả tiền:</span>
                    <span>Người nhận:</span>
                </div>
                <div class="flex justify-between items-center font-bold text-slate-800">
                    <span id="modalDebtorName" class="text-rose-700"></span>
                    <span class="text-slate-400 text-xs font-normal">&rarr;</span>
                    <span id="modalCreditorName" class="text-emerald-700"></span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Số tiền thanh toán (VNĐ)</label>
                <input type="number" 
                       name="amount" 
                       id="modalAmount" 
                       required 
                       min="1" 
                       step="1"
                       class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-base font-bold text-slate-900">
                <p class="text-[11px] text-slate-400 mt-1">Có thể thanh toán toàn bộ hoặc trả trước một phần.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Ghi chú thanh toán</label>
                <input type="text" 
                       name="note" 
                       placeholder="Ví dụ: Chuyển khoản Vietcombank, tiền mặt..." 
                       class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm">
            </div>

            <div class="pt-2 flex justify-end space-x-3">
                <button type="button" onclick="closeSettleModal()" class="px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-xl transition">
                    Hủy
                </button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-md shadow-emerald-600/20 transition">
                    Xác nhận Gạch Nợ
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================== -->
<!-- MODAL XEM MÃ VIETQR & LINK CHIA SẺ THANH TOÁN -->
<!-- ============================================== -->
<div id="qrModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-bold text-slate-900 flex items-center">
                <svg class="w-5 h-5 mr-1.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                </svg>
                Mã VietQR & Link Trả Nợ
            </h3>
            <button type="button" onclick="closeQrModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="mt-4 space-y-4">
            <!-- Tóm tắt khoản nợ -->
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                <div class="flex justify-between items-center text-slate-500 mb-1">
                    <span>Người nợ:</span>
                    <span>Người nhận (Chủ nợ):</span>
                </div>
                <div class="flex justify-between items-center font-bold text-slate-800 text-sm">
                    <span id="qrDebtorName" class="text-rose-700"></span>
                    <span class="text-slate-400 text-xs font-normal">&rarr;</span>
                    <span id="qrCreditorName" class="text-emerald-700"></span>
                </div>
                <div class="mt-2 pt-2 border-t border-slate-200/60 flex justify-between items-center">
                    <span class="text-slate-500">Số tiền nợ:</span>
                    <span id="qrAmountDisplay" class="text-base font-extrabold text-rose-600"></span>
                </div>
            </div>

            <!-- Trường hợp đã cài đặt ngân hàng -->
            <div id="qrContentWithBank" class="space-y-4 text-center">
                <div class="inline-block bg-white p-2.5 rounded-2xl shadow-sm border border-slate-200">
                    <img id="qrModalImg" src="" alt="VietQR" class="w-56 h-auto mx-auto rounded-lg">
                </div>

                <div class="text-left bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs space-y-1.5">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Ngân hàng:</span>
                        <span id="qrBankName" class="font-bold text-slate-800"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500">Số tài khoản:</span>
                        <div class="flex items-center space-x-1.5">
                            <span id="qrAccountNo" class="font-mono font-bold text-emerald-800"></span>
                            <button type="button" onclick="copyFromElem('qrAccountNo', 'Đã chép số tài khoản!')" class="text-emerald-600 hover:text-emerald-800" title="Sao chép">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                            </button>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Chủ tài khoản:</span>
                        <span id="qrAccountName" class="font-bold text-slate-800 uppercase"></span>
                    </div>
                </div>

                <!-- Link chia sẻ công khai -->
                <div class="text-left space-y-1">
                    <label class="block text-[11px] font-semibold text-slate-700 uppercase">Link thanh toán gửi cho con nợ:</label>
                    <div class="flex items-center space-x-2">
                        <input type="text" readonly id="qrShareLink" class="w-full px-3 py-2 text-xs font-mono rounded-xl border border-slate-300 bg-slate-50 select-all text-slate-700">
                        <button type="button" onclick="copyShareLink()" class="px-3.5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl whitespace-nowrap shadow-sm transition">
                            Sao Chép
                        </button>
                    </div>
                </div>

                <!-- Các nút hành động nhanh -->
                <div class="grid grid-cols-2 gap-2 pt-1">
                    <button type="button" onclick="copyReminderText()" class="w-full py-2 px-3 text-xs font-semibold rounded-xl text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition flex items-center justify-center space-x-1">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <span>Chép Tin Nhắc Nợ</span>
                    </button>
                    <a id="qrDownloadBtn" href="" download="" target="_blank" class="w-full py-2 px-3 text-xs font-semibold rounded-xl text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition flex items-center justify-center space-x-1">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        <span>Tải Ảnh QR</span>
                    </a>
                </div>
            </div>

            <!-- Trường hợp chủ nợ chưa cấu hình ngân hàng -->
            <div id="qrContentNoBank" class="hidden text-center py-4 space-y-3">
                <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h4 class="text-sm font-bold text-slate-800">Chưa Cấu Hình Tài Khoản Ngân Hàng</h4>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Chủ nợ (<strong id="noBankCreditorName"></strong>) chưa cài đặt STK ngân hàng trong trang Cá Nhân nên hệ thống chưa thể tự tạo mã VietQR.
                </p>
                <div class="pt-2">
                    <a href="profile.php" class="inline-flex items-center px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition shadow-sm">
                        Đến Trang Cá Nhân Cài Đặt STK &rarr;
                    </a>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 mt-5 flex justify-end">
            <button type="button" onclick="closeQrModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition">
                Đóng
            </button>
        </div>
    </div>
</div>

<!-- Toast Copy -->
<div id="copyToast" class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-slate-900 text-white px-4 py-2 rounded-xl text-xs font-semibold shadow-2xl opacity-0 pointer-events-none transition-opacity duration-200 flex items-center space-x-1.5 z-50">
    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
    </svg>
    <span id="copyToastText">Đã sao chép!</span>
</div>

<script>
let currentQrData = null;

function removeVietnameseAccents(str) {
    if (!str) return '';
    return str.normalize('NFD')
              .replace(/[\u0300-\u036f]/g, '')
              .replace(/đ/g, 'd').replace(/Đ/g, 'D')
              .replace(/[^a-zA-Z0-9 ]/g, '');
}

function openQrModal(data) {
    currentQrData = data;
    document.getElementById('qrDebtorName').textContent = data.debtor_name;
    document.getElementById('qrCreditorName').textContent = data.creditor_name;
    document.getElementById('qrAmountDisplay').textContent = Number(data.amount).toLocaleString() + ' đ';

    const hasBank = data.bank_bin && data.bank_account_no;
    const withBankSec = document.getElementById('qrContentWithBank');
    const noBankSec = document.getElementById('qrContentNoBank');

    // Tạo link công khai
    const host = window.location.origin;
    const path = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
    const shareUrl = `${host}${path}/pay.php?token=${encodeURIComponent(data.token)}`;
    document.getElementById('qrShareLink').value = shareUrl;

    if (hasBank) {
        withBankSec.classList.remove('hidden');
        noBankSec.classList.add('hidden');

        document.getElementById('qrBankName').textContent = data.bank_name || data.bank_bin;
        document.getElementById('qrAccountNo').textContent = data.bank_account_no;
        document.getElementById('qrAccountName').textContent = data.bank_account_name || data.creditor_name;

        const cleanMemo = removeVietnameseAccents(`${data.debtor_name} tra no ${data.creditor_name} SF`).trim();
        const qrUrl = `https://img.vietqr.io/image/${data.bank_bin}-${data.bank_account_no}-compact2.png?amount=${Math.round(data.amount)}&addInfo=${encodeURIComponent(cleanMemo)}&accountName=${encodeURIComponent(data.bank_account_name || data.creditor_name)}`;

        const img = document.getElementById('qrModalImg');
        img.src = qrUrl;

        const dlBtn = document.getElementById('qrDownloadBtn');
        dlBtn.href = qrUrl;
        dlBtn.download = `vietqr_${data.debt_id}.png`;
    } else {
        withBankSec.classList.add('hidden');
        noBankSec.classList.remove('hidden');
        document.getElementById('noBankCreditorName').textContent = data.creditor_name;
    }

    document.getElementById('qrModal').classList.remove('hidden');
}

function closeQrModal() {
    document.getElementById('qrModal').classList.add('hidden');
}

function copyShareLink() {
    const input = document.getElementById('qrShareLink');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        showToast('Đã chép link thanh toán gửi con nợ!');
    });
}

function copyReminderText() {
    if (!currentQrData) return;
    const link = document.getElementById('qrShareLink').value;
    const amountStr = Number(currentQrData.amount).toLocaleString() + ' đ';
    const text = `Chào ${currentQrData.debtor_name}, mình gửi thông tin thanh toán khoản nợ ${amountStr}. Bạn quét mã QR hoặc mở link này để thanh toán nhé: ${link}`;
    navigator.clipboard.writeText(text).then(() => {
        showToast('Đã chép tin nhắn nhắc nợ vào bộ nhớ tạm!');
    });
}

function copyFromElem(elemId, msg) {
    const text = document.getElementById(elemId).textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
        showToast(msg);
    });
}

function showToast(text) {
    const toast = document.getElementById('copyToast');
    const toastText = document.getElementById('copyToastText');
    toastText.textContent = text;
    toast.classList.remove('opacity-0', 'pointer-events-none');
    toast.classList.add('opacity-100');
    setTimeout(() => {
        toast.classList.remove('opacity-100');
        toast.classList.add('opacity-0', 'pointer-events-none');
    }, 2000);
}

function openSettleModal(debtorId, debtorName, creditorId, creditorName, amount) {
    document.getElementById('modalDebtorId').value = debtorId;
    document.getElementById('modalCreditorId').value = creditorId;
    document.getElementById('modalDebtorName').textContent = debtorName;
    document.getElementById('modalCreditorName').textContent = creditorName;
    document.getElementById('modalAmount').value = Math.round(amount);
    document.getElementById('settleModal').classList.remove('hidden');
}

function closeSettleModal() {
    document.getElementById('settleModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

