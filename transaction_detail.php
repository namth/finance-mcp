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

$flashSuccess = isset($_GET['created']) ? 'Đã tạo giao dịch và tự động cập nhật công nợ thành công!' : (isset($_GET['updated']) ? 'Đã cập nhật thông tin giao dịch thành công!' : '');
$flashError = '';

// Xử lý xác nhận hoàn thành giao dịch (nếu trước đó là draft)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'complete') {
        $tx = $txModel->updateStatus($id, 'completed');
        $debtManager->processTransaction($id);
        $flashSuccess = 'Đã xác nhận hoàn thành giao dịch và cập nhật vào bảng công nợ!';
    } elseif ($_POST['action'] === 'update_item_price') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $newPrice = (float)($_POST['new_price'] ?? 0);
        $newQty = isset($_POST['new_quantity']) && (int)$_POST['new_quantity'] > 0 ? (int)$_POST['new_quantity'] : null;

        try {
            $txModel->updateItemPrice($id, $itemId, $newPrice, $newQty);
            if ($tx['status'] === 'completed') {
                $debtManager->recalculateAll((int)$tx['group_id']);
            }
            $flashSuccess = 'Đã sửa giá món và tự động tính toán lại bảng công nợ!';
            $tx = $txModel->find($id); // nạp lại dữ liệu
        } catch (\Throwable $e) {
            $flashError = $e->getMessage();
        }
    }
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
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <a href="transactions.php" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 flex items-center mb-1">
                &larr; Danh sách giao dịch
            </a>
            <div class="flex items-center space-x-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Hóa Đơn #<?= $tx['id'] ?>: <?= htmlspecialchars($tx['title']) ?></h1>
                <?php if ($tx['status'] === 'completed'): ?>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        Hoàn thành
                    </span>
                <?php elseif ($tx['status'] === 'draft'): ?>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                        Bản nháp
                    </span>
                <?php else: ?>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">
                        Đã hủy
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <a href="transaction_edit.php?id=<?= $tx['id'] ?>" class="inline-flex items-center px-3.5 py-2 text-xs font-bold rounded-xl text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 hover:text-emerald-700 shadow-xs transition">
                <svg class="w-3.5 h-3.5 mr-1.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Sửa Toàn Bộ Giao Dịch
            </a>

            <?php if ($tx['status'] === 'draft'): ?>
                <form method="POST" action="transaction_detail.php?id=<?= $tx['id'] ?>">
                    <input type="hidden" name="action" value="complete">
                    <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/20 transition">
                        Xác Nhận & Cập Nhật Nợ
                    </button>
                </form>
            <?php endif; ?>
        </div>
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
            <span class="font-medium"><?= htmlspecialchars($flashError) ?></span>
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
            <p class="text-xs font-semibold text-slate-400 uppercase">Địa Điểm & Ghi Chú</p>
            <?php if (!empty($tx['place_name'])): ?>
                <p class="text-sm font-bold text-emerald-800 mt-1 flex items-center">
                    <svg class="w-4 h-4 mr-1 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span><?= htmlspecialchars($tx['place_name']) ?></span>
                </p>
                <?php if (!empty($tx['place_address'])): ?>
                    <p class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($tx['place_address']) ?></p>
                <?php endif; ?>
                <?php if (!empty($tx['place_map_url'])): ?>
                    <a href="<?= htmlspecialchars($tx['place_map_url']) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-xs font-semibold text-emerald-600 hover:text-emerald-700 hover:underline mt-1">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                        Xem trên Google Maps ↗
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-sm font-medium text-slate-700 mt-1"><?= htmlspecialchars(substr($tx['created_at'], 0, 16)) ?></p>
            <?php endif; ?>
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
                                <?php if (!empty($item['place_name'])): ?>
                                    <div class="flex items-center text-xs text-emerald-700 font-medium mt-0.5">
                                        <svg class="w-3.5 h-3.5 mr-1 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <span><?= htmlspecialchars($item['place_name']) ?></span>
                                        <?php if (!empty($item['place_address'])): ?>
                                            <span class="text-slate-400 text-[11px] ml-1.5 font-normal">(<?= htmlspecialchars($item['place_address']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="flex items-center space-x-2 mt-1">
                                    <p class="text-xs text-slate-500">
                                        Đơn giá: <span class="font-semibold text-slate-700"><?= number_format($item['price']) ?> đ</span> &times; Số lượng: <span class="font-semibold text-slate-700"><?= $item['quantity'] ?></span>
                                    </p>
                                    <button type="button" onclick="openEditItemPriceModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['product_name'])) ?>', <?= $item['price'] ?>, <?= $item['quantity'] ?>)" class="inline-flex items-center text-[11px] font-semibold text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-2 py-0.5 rounded border border-emerald-200 transition">
                                        <svg class="w-3 h-3 mr-1 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                        </svg>
                                        Sửa giá
                                    </button>
                                </div>
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

<!-- Modal sửa giá nhanh cho món -->
<div id="editPriceModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-slate-100 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-base font-bold text-slate-900" id="modalItemTitle">Sửa Giá Món</h3>
                <p class="text-xs text-slate-400">Đổi giá hoặc số lượng cho món này</p>
            </div>
            <button type="button" onclick="closeEditPriceModal()" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
        </div>

        <form method="POST" action="transaction_detail.php?id=<?= $tx['id'] ?>" id="editPriceForm" class="space-y-4">
            <input type="hidden" name="action" value="update_item_price">
            <input type="hidden" name="item_id" id="modalItemId" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Đơn Giá Mới (VNĐ) *</label>
                <input type="number" name="new_price" id="modalNewPrice" required min="0" step="500" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-base font-bold text-emerald-800">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Số Lượng *</label>
                <input type="number" name="new_quantity" id="modalNewQty" required min="1" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm font-semibold text-slate-800">
            </div>

            <p class="text-[11px] text-slate-400">
                💡 Hệ thống sẽ tự động phân bổ lại số tiền chia đều cho các thành viên và cân bằng lại công nợ.
            </p>

            <div class="flex justify-end space-x-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeEditPriceModal()" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                    Hủy
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-xs font-bold text-white shadow-md shadow-emerald-600/20">
                    Lưu Thay Đổi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditItemPriceModal(itemId, itemName, currentPrice, currentQty) {
    document.getElementById('modalItemId').value = itemId;
    document.getElementById('modalItemTitle').textContent = 'Sửa Giá: ' + itemName;
    document.getElementById('modalNewPrice').value = currentPrice;
    document.getElementById('modalNewQty').value = currentQty;
    document.getElementById('editPriceModal').classList.remove('hidden');
    document.getElementById('modalNewPrice').focus();
}

function closeEditPriceModal() {
    document.getElementById('editPriceModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
