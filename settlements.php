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

use SimpleFinance\Models\Settlement;
use SimpleFinance\DebtManager;

$settlementModel = new Settlement();
$debtManager = new DebtManager();

$flashSuccess = '';
$flashError = '';

$currentGroupId = (int)($_SESSION['current_group_id'] ?? 1);

// Tùy chọn tính toán lại toàn bộ công nợ nếu người dùng yêu cầu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'recalculate') {
    try {
        $debtManager->recalculateAll($currentGroupId);
        $flashSuccess = "Đã tính toán lại toàn bộ bảng nợ của nhóm thành công!";
    } catch (\Throwable $e) {
        $flashError = $e->getMessage();
    }
}

$settlements = [];
try {
    $settlements = $settlementModel->all(null, 100, 0, $currentGroupId);
} catch (\Throwable $e) {
    $flashError = "Lỗi kết nối database: " . $e->getMessage();
}

$pageTitle = "Lịch Sử Gạch Nợ & Thanh Toán - SimpleFinance";
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Lịch Sử Gạch Nợ & Thanh Toán</h1>
        <p class="text-xs text-slate-500 mt-1">Toàn bộ các lần thành viên chuyển khoản hoặc trả tiền mặt thanh toán công nợ</p>
    </div>
    <form method="POST" action="settlements.php" onsubmit="return confirm('Bạn có chắc muốn tính toán lại toàn bộ công nợ từ đầu dựa trên tất cả giao dịch và lịch sử trả nợ?');">
        <input type="hidden" name="action" value="recalculate">
        <button type="submit" class="inline-flex items-center px-3.5 py-2 text-xs font-semibold rounded-xl text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm transition">
            <svg class="w-4 h-4 mr-1.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Tái Tính Toán Toàn Bộ Nợ
        </button>
    </form>
</div>

<?php if ($flashSuccess): ?>
    <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center space-x-2">
        <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <span class="font-medium"><?= htmlspecialchars($flashSuccess) ?></span>
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

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <?php if (empty($settlements)): ?>
        <div class="py-12 text-center text-slate-400">
            Chưa có đợt gạch nợ nào được thực hiện.
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase">
                    <tr>
                        <th class="px-6 py-3.5 text-left">Mã</th>
                        <th class="px-6 py-3.5 text-left">Người Trả Tiền</th>
                        <th class="px-6 py-3.5 text-center">Hình thức</th>
                        <th class="px-6 py-3.5 text-left">Người Nhận Tiền</th>
                        <th class="px-6 py-3.5 text-right">Số Tiền Đã Thanh Toán</th>
                        <th class="px-6 py-3.5 text-left">Ghi Chú</th>
                        <th class="px-6 py-3.5 text-right">Thời Gian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    <?php foreach ($settlements as $s): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-slate-400 text-xs font-mono">
                                #<?= $s['id'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-bold text-slate-900"><?= htmlspecialchars($s['debtor_name']) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Thanh toán &rarr;
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-bold text-emerald-800"><?= htmlspecialchars($s['creditor_name']) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <span class="text-base font-bold text-emerald-600">
                                    +<?= number_format($s['amount']) ?> đ
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-600 text-xs max-w-xs truncate">
                                <?= htmlspecialchars($s['note'] ?: '—') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs text-slate-400 font-normal">
                                <?= htmlspecialchars(substr($s['settled_at'], 0, 16)) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
