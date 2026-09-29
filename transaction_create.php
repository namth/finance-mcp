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

use SimpleFinance\Models\Member;
use SimpleFinance\Models\Product;
use SimpleFinance\Models\Transaction;
use SimpleFinance\DebtManager;

$memberModel = new Member();
$productModel = new Product();
$txModel = new Transaction();
$debtManager = new DebtManager();

$currentGroupId = (int)($_SESSION['current_group_id'] ?? 1);

$members = $memberModel->all();
$products = $productModel->all($currentGroupId);

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim((string)($_POST['title'] ?? ''));
        $payerId = (int)($_POST['payer_id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'completed');
        $note = trim((string)($_POST['note'] ?? ''));
        $rawItems = $_POST['items'] ?? [];

        if (empty($title)) {
            throw new \InvalidArgumentException("Vui lòng nhập tiêu đề giao dịch.");
        }
        if ($payerId <= 0) {
            throw new \InvalidArgumentException("Vui lòng chọn người thanh toán hóa đơn.");
        }
        if (empty($rawItems) || !is_array($rawItems)) {
            throw new \InvalidArgumentException("Vui lòng thêm ít nhất một sản phẩm/dịch vụ.");
        }

        $items = [];
        foreach ($rawItems as $idx => $rItem) {
            $prodId = (int)($rItem['product_id'] ?? 0);
            $price = isset($rItem['price']) && $rItem['price'] !== '' ? (float)$rItem['price'] : null;
            $quantity = isset($rItem['quantity']) ? max(1, (int)$rItem['quantity']) : 1;
            $memberIds = isset($rItem['members']) && is_array($rItem['members']) ? array_map('intval', $rItem['members']) : [];

            if ($prodId <= 0) {
                continue; // Bỏ qua dòng trống
            }

            if (empty($memberIds)) {
                throw new \InvalidArgumentException("Món thứ #" . ($idx + 1) . " chưa chọn thành viên nào tham gia sử dụng.");
            }

            $items[] = [
                'product_id' => $prodId,
                'price'      => $price,
                'quantity'   => $quantity,
                'member_ids' => $memberIds,
                'note'       => trim((string)($rItem['note'] ?? '')),
            ];
        }

        if (empty($items)) {
            throw new \InvalidArgumentException("Cần có ít nhất 1 món hợp lệ trong hóa đơn.");
        }

        // Tạo giao dịch
        $createdTx = $txModel->create($title, $payerId, $items, $status, $note ?: null, $currentGroupId);

        // Tự động tính nợ nếu completed
        if ($status === 'completed') {
            $debtManager->processTransaction((int)$createdTx['id']);
        }

        header("Location: transaction_detail.php?id=" . $createdTx['id'] . "&created=1");
        exit;
    } catch (\Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = "Tạo Giao Dịch Chi Tiêu Mới - SimpleFinance";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto mb-10">
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="transactions.php" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 flex items-center mb-1">
                &larr; Quay lại danh sách
            </a>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Tạo Đợt Chi Tiêu / Hóa Đơn Mới</h1>
            <p class="text-xs text-slate-500 mt-0.5">Chọn người thanh toán, thêm các món và chọn thành viên cùng chia tiền</p>
        </div>
    </div>

    <?php if ($errorMessage): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center space-x-2">
            <svg class="w-5 h-5 text-rose-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span class="font-medium"><?= htmlspecialchars($errorMessage) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="transaction_create.php" id="txForm" class="space-y-6">
        <!-- Khối 1: Thông tin chung hóa đơn -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">1. Thông Tin Hóa Đơn</h2>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Tiêu Đề Chi Tiêu *</label>
                    <input type="text" name="title" required placeholder="Ví dụ: Ăn trưa bún đậu, Tiền phòng hát..." class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm font-medium">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Người Đứng Ra Thanh Toán *</label>
                    <select name="payer_id" required class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm font-bold text-emerald-800 bg-emerald-50/50">
                        <option value="">-- Chọn thành viên thanh toán --</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['phone'] ?: 'Không có SĐT') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Trạng Thái</label>
                    <select name="status" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm">
                        <option value="completed" selected>Hoàn thành (Tự động tính nợ ngay)</option>
                        <option value="draft">Bản nháp (Chưa tính nợ)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Ghi Chú</label>
                    <input type="text" name="note" placeholder="Địa điểm, dịp liên hoan..." class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm">
                </div>
            </div>
        </div>

        <!-- Khối 2: Danh sách các món trong giao dịch -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">2. Chi Tiết Sản Phẩm & Phân Bổ Thành Viên</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Mỗi món có thể có nhiều người tham gia, tiền sẽ được chia đều tự động</p>
                </div>
                <button type="button" onclick="addItemRow()" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-800 hover:bg-emerald-200 transition">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    + Thêm Món
                </button>
            </div>

            <!-- Danh sách các hàng món (Container) -->
            <div id="itemsContainer" class="space-y-4">
                <!-- Javascript sẽ tự động thêm hàng đầu tiên vào đây -->
            </div>
        </div>

        <!-- Nút Submit -->
        <div class="flex justify-end space-x-3 pt-2">
            <a href="transactions.php" class="px-5 py-2.5 rounded-xl border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                Hủy
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-600/20 transition">
                Lưu & Cập Nhật Công Nợ
            </button>
        </div>
    </form>
</div>

<!-- Dữ liệu JS dùng để render -->
<script>
const availableProducts = <?= json_encode($products) ?>;
const availableMembers = <?= json_encode($members) ?>;
let itemIndex = 0;

function addItemRow() {
    const container = document.getElementById('itemsContainer');
    const idx = itemIndex++;

    const card = document.createElement('div');
    card.id = `itemRow_${idx}`;
    card.className = "p-4 rounded-xl border border-slate-200 bg-slate-50/60 space-y-3 relative";

    let productOptions = `<option value="">-- Chọn sản phẩm/dịch vụ --</option>`;
    availableProducts.forEach(p => {
        const priceLabel = p.default_price !== null ? `(${Number(p.default_price).toLocaleString()} đ)` : `(Giá tùy nhập)`;
        productOptions += `<option value="${p.id}" data-price="${p.default_price !== null ? p.default_price : ''}">${p.name} ${priceLabel}</option>`;
    });

    let memberCheckboxes = '';
    availableMembers.forEach(m => {
        memberCheckboxes += `
            <label class="inline-flex items-center space-x-1.5 p-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-700 cursor-pointer hover:border-emerald-300">
                <input type="checkbox" name="items[${idx}][members][]" value="${m.id}" checked class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 member-cb-${idx}">
                <span>${m.name}</span>
            </label>
        `;
    });

    card.innerHTML = `
        <div class="flex items-center justify-between pb-2 border-b border-slate-200/60">
            <span class="text-xs font-bold text-slate-600 uppercase tracking-wide">Món #${idx + 1}</span>
            <button type="button" onclick="removeItemRow(${idx})" class="text-xs text-rose-600 hover:text-rose-800 font-semibold">
                &times; Xóa món này
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            <div class="sm:col-span-6">
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Sản Phẩm / Dịch Vụ *</label>
                <select name="items[${idx}][product_id]" required onchange="onProductSelect(${idx}, this)" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 focus:border-emerald-500 text-sm font-medium">
                    ${productOptions}
                </select>
            </div>

            <div class="sm:col-span-4">
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Đơn Giá Thực Tế (VNĐ) *</label>
                <input type="number" name="items[${idx}][price]" id="price_${idx}" required min="0" step="500" placeholder="Đơn giá" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 focus:border-emerald-500 text-sm font-bold text-slate-900">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Số Lượng</label>
                <input type="number" name="items[${idx}][quantity]" value="1" min="1" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 focus:border-emerald-500 text-sm text-center">
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="text-[11px] font-semibold text-slate-600 uppercase">Thành viên tham gia dùng món này (Chia đều chi phí):</label>
                <div class="space-x-2 text-[11px]">
                    <button type="button" onclick="toggleAllMembers(${idx}, true)" class="text-emerald-700 hover:underline">Chọn tất cả</button>
                    <span class="text-slate-300">|</span>
                    <button type="button" onclick="toggleAllMembers(${idx}, false)" class="text-slate-500 hover:underline">Bỏ chọn</button>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                ${memberCheckboxes}
            </div>
        </div>
    `;

    container.appendChild(card);
}

function removeItemRow(idx) {
    const row = document.getElementById(`itemRow_${idx}`);
    if (row) {
        row.remove();
    }
}

function onProductSelect(idx, selectElem) {
    const selectedOption = selectElem.options[selectElem.selectedIndex];
    const defaultPrice = selectedOption.getAttribute('data-price');
    const priceInput = document.getElementById(`price_${idx}`);
    if (defaultPrice) {
        priceInput.value = defaultPrice;
    } else {
        priceInput.value = '';
        priceInput.placeholder = 'Nhập giá cho lần dùng này';
    }
}

function toggleAllMembers(idx, isChecked) {
    const checkboxes = document.querySelectorAll(`.member-cb-${idx}`);
    checkboxes.forEach(cb => cb.checked = isChecked);
}

// Khởi tạo ngay 1 hàng khi mở trang
document.addEventListener('DOMContentLoaded', () => {
    addItemRow();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
