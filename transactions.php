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

use SimpleFinance\Models\Transaction;

$txModel = new Transaction();
$flashSuccess = '';
$flashError = '';

$statusFilter = isset($_GET['status']) && in_array($_GET['status'], ['completed', 'draft', 'cancelled']) ? $_GET['status'] : null;

// Xóa giao dịch nếu có yêu cầu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $txModel->delete($id);
        $flashSuccess = "Đã xóa giao dịch #{$id} thành công!";
    } catch (\Throwable $e) {
        $flashError = $e->getMessage();
    }
}

$currentGroupId = (int)($_SESSION['current_group_id'] ?? 1);
$transactions = [];
try {
    $transactions = $txModel->all($statusFilter, 100, 0, $currentGroupId);
} catch (\Throwable $e) {
    $flashError = "Lỗi kết nối database: " . $e->getMessage();
}

$pageTitle = "Danh Sách Giao Dịch Chi Tiêu - SimpleFinance";
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Danh Sách Giao Dịch (Hóa Đơn Chi Tiêu)</h1>
        <p class="text-xs text-slate-500 mt-1">Ghi nhận các lần đi ăn, mua sắm hoặc dịch vụ chung của nhóm</p>
    </div>
    <a href="transaction_create.php" class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/20 transition">
        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Tạo Giao Dịch Chi Tiêu Mới
    </a>
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

<!-- Bộ lọc trạng thái -->
<div class="mb-5 flex space-x-2 text-xs font-semibold">
    <a href="transactions.php" class="px-3 py-1.5 rounded-lg border transition <?= $statusFilter === null ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
        Tất cả
    </a>
    <a href="transactions.php?status=completed" class="px-3 py-1.5 rounded-lg border transition <?= $statusFilter === 'completed' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
        Đã hoàn thành (Tính nợ)
    </a>
    <a href="transactions.php?status=draft" class="px-3 py-1.5 rounded-lg border transition <?= $statusFilter === 'draft' ? 'bg-amber-600 text-white border-amber-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
        Bản nháp
    </a>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <?php if (empty($transactions)): ?>
        <div class="py-12 text-center text-slate-400">
            Chưa có giao dịch nào phù hợp với bộ lọc.
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase">
                    <tr>
                        <th class="px-6 py-3.5 text-left">Mã GD</th>
                        <th class="px-6 py-3.5 text-left">Tiêu Đề Hóa Đơn</th>
                        <th class="px-6 py-3.5 text-left">Người Chi Trả</th>
                        <th class="px-6 py-3.5 text-right">Tổng Số Tiền</th>
                        <th class="px-6 py-3.5 text-center">Trạng Thái</th>
                        <th class="px-6 py-3.5 text-left">Thời Gian</th>
                        <th class="px-6 py-3.5 text-right">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    <?php foreach ($transactions as $t): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-slate-400 text-xs font-mono">
                                #<?= $t['id'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="transaction_detail.php?id=<?= $t['id'] ?>" class="font-bold text-slate-900 hover:text-emerald-700 transition">
                                    <?= htmlspecialchars($t['title']) ?>
                                </a>
                                <?php if (!empty($t['note'])): ?>
                                    <p class="text-xs text-slate-400 truncate max-w-xs"><?= htmlspecialchars($t['note']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    <?= htmlspecialchars($t['payer_name']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <span class="text-base font-bold text-slate-900"><?= number_format($t['total_amount']) ?> đ</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($t['status'] === 'completed'): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                        Hoàn thành
                                    </span>
                                <?php elseif ($t['status'] === 'draft'): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                        Bản nháp
                                    </span>
                                <?php else: ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">
                                        Đã hủy
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-400 font-normal">
                                <?= htmlspecialchars(substr($t['created_at'], 0, 16)) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right space-x-2">
                                <a href="transaction_detail.php?id=<?= $t['id'] ?>" class="px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 rounded-lg transition border border-slate-200 hover:border-emerald-300">
                                    Chi tiết
                                </a>
                                <form method="POST" action="transactions.php" class="inline" onsubmit="return confirm('Bạn có chắc muốn xóa giao dịch này?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 rounded-lg transition border border-slate-200 hover:border-rose-300">
                                        Xóa
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
