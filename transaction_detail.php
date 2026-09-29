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
use SimpleFinance\DebtManager;

$txModel = new Transaction();
$debtManager = new DebtManager();

$id = (int)($_GET['id'] ?? 0);
$tx = $txModel->find($id);

if (!$tx) {
    die("Không tìm thấy giao dịch với ID: {$id}");
}

$flashSuccess = isset($_GET['created']) ? 'Đã tạo giao dịch và tự động cập nhật công nợ thành công!' : '';

// Xử lý xác nhận hoàn thành giao dịch (nếu trước đó là draft)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete') {
    $tx = $txModel->updateStatus($id, 'completed');
    $debtManager->processTransaction($id);
    $flashSuccess = 'Đã xác nhận hoàn thành giao dịch và cập nhật vào bảng công nợ!';
}

// Tổng hợp tổng số tiền mỗi người phải chịu trong giao dịch này
$memberTotals = [];
foreach ($tx['items'] as $item) {
    foreach ($item['members'] as $m) {
        $mId = (int)$m['member_id'];
        $mName = $m['member_name'];
        if (!isset($memberTotals[$mId])) {
            $memberTotals[$mId] = [
                'name'   => $mName,
                'amount' => 0.0,
            ];
        }
        $memberTotals[$mId]['amount'] += (float)$m['share_amount'];
    }
}

$pageTitle = "Chi Tiết Giao Dịch #" . $tx['id'] . " - SimpleFinance";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto mb-10">
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="transactions.php" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 flex items-center mb-1">
                &larr; Danh sách giao dịch
            </a>
            <div class="flex items-center space-x-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Chi Tiết Hóa Đơn #<?= $tx['id'] ?>: <?= htmlspecialchars($tx['title']) ?></h1>
                <?php if ($tx['status'] === 'completed'): ?>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        Hoàn thành
                    </span>
                <?php else: ?>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                        Bản nháp
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($tx['status'] === 'draft'): ?>
            <form method="POST" action="transaction_detail.php?id=<?= $tx['id'] ?>">
                <input type="hidden" name="action" value="complete">
                <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/20 transition">
                    Xác Nhận & Cập Nhật Nợ
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center space-x-2">
            <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span class="font-medium"><?= htmlspecialchars($flashSuccess) ?></span>
        </div>
    <?php endif; ?>

    <!-- Thẻ Tổng Quan Hóa Đơn -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm mb-6 grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div>
            <p class="text-xs font-semibold text-slate-400 uppercase">Người Đứng Ra Trả Tiền</p>
            <p class="text-lg font-bold text-emerald-800 mt-1 flex items-center">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-600 inline-block mr-2"></span>
                <?= htmlspecialchars($tx['payer_name']) ?>
            </p>
            <p class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($tx['payer_phone'] ?: 'Không có SĐT') ?></p>
        </div>

        <div>
            <p class="text-xs font-semibold text-slate-400 uppercase">Tổng Số Tiền Hóa Đơn</p>
            <p class="text-2xl font-black text-slate-900 mt-1"><?= number_format($tx['total_amount']) ?> <span class="text-sm font-normal text-slate-500">đ</span></p>
            <p class="text-xs text-slate-400 mt-0.5"><?= count($tx['items']) ?> món / sản phẩm</p>
        </div>

        <div>
            <p class="text-xs font-semibold text-slate-400 uppercase">Thời Gian & Ghi Chú</p>
            <p class="text-sm font-medium text-slate-700 mt-1"><?= htmlspecialchars($tx['created_at']) ?></p>
            <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($tx['note'] ?: 'Không có ghi chú thêm') ?></p>
        </div>
    </div>

    <!-- Bảng Chi Tiết Từng Món & Ai Sử Dụng -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/50">
            <h2 class="text-base font-bold text-slate-900">Chi Tiết Từng Sản Phẩm & Phân Bổ Thành Viên</h2>
        </div>

        <div class="divide-y divide-slate-200">
            <?php foreach ($tx['items'] as $idx => $item): ?>
                <div class="p-6 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div class="flex items-center space-x-3">
                            <span class="w-7 h-7 rounded-lg bg-slate-100 font-bold text-xs text-slate-600 flex items-center justify-center">
                                <?= $idx + 1 ?>
                            </span>
                            <div>
                                <h3 class="text-base font-bold text-slate-900"><?= htmlspecialchars($item['product_name']) ?></h3>
                                <p class="text-xs text-slate-500">
                                    Đơn giá: <span class="font-semibold text-slate-700"><?= number_format($item['price']) ?> đ</span> &times; Số lượng: <span class="font-semibold text-slate-700"><?= $item['quantity'] ?></span>
                                </p>
                            </div>
                        </div>

                        <div class="text-right">
                            <span class="text-sm font-semibold text-slate-400">Thành tiền: </span>
                            <span class="text-base font-bold text-emerald-700"><?= number_format($item['subtotal']) ?> đ</span>
                        </div>
                    </div>

                    <!-- Danh sách người dùng món này -->
                    <div class="bg-slate-50/80 p-3.5 rounded-xl border border-slate-100">
                        <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide mb-2">
                            Thành viên tham gia món này (<?= count($item['members']) ?> người):
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($item['members'] as $m): ?>
                                <div class="inline-flex items-center space-x-2 px-3 py-1.5 rounded-lg bg-white border border-slate-200 shadow-2xs text-xs">
                                    <span class="font-bold text-slate-800"><?= htmlspecialchars($m['member_name']) ?></span>
                                    <span class="text-slate-300">|</span>
                                    <span class="font-semibold text-rose-600"><?= number_format($m['share_amount']) ?> đ</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Bảng Tổng Kết Chi Phí Mỗi Người Cần Đóng Góp -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3 mb-4">
            Tổng Hợp Phần Chi Phí Của Từng Người Trong Hóa Đơn
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            <?php foreach ($memberTotals as $mId => $info): ?>
                <?php $isPayer = ($mId === (int)$tx['payer_id']); ?>
                <div class="p-4 rounded-xl border <?= $isPayer ? 'border-emerald-300 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/50' ?>">
                    <div class="flex justify-between items-center mb-1">
                        <span class="font-bold text-sm text-slate-900"><?= htmlspecialchars($info['name']) ?></span>
                        <?php if ($isPayer): ?>
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-emerald-600 text-white">Đã trả tiền</span>
                        <?php else: ?>
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-rose-100 text-rose-700">Nợ người trả</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-lg font-black <?= $isPayer ? 'text-emerald-800' : 'text-rose-600' ?>">
                        <?= number_format($info['amount']) ?> <span class="text-xs font-normal text-slate-500">đ</span>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
