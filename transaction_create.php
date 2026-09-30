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
use SimpleFinance\Models\Place;
use SimpleFinance\Models\Transaction;
use SimpleFinance\DebtManager;

$memberModel = new Member();
$productModel = new Product();
$placeModel = new Place();
$txModel = new Transaction();
$debtManager = new DebtManager();

$currentGroupId = (int)($_SESSION['current_group_id'] ?? 1);

$members = $memberModel->all();
$products = $productModel->all($currentGroupId);
$places = $placeModel->all($currentGroupId);

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim((string)($_POST['title'] ?? ''));
        $payerId = (int)($_POST['payer_id'] ?? 0);
        $placeId = !empty($_POST['place_id']) && (int)$_POST['place_id'] > 0 ? (int)$_POST['place_id'] : null;
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

        // Tạo giao dịch kèm place_id nếu có
        $createdTx = $txModel->create($title, $payerId, $items, $status, $note ?: null, $currentGroupId, null, null, $placeId);

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
            <p class="text-xs text-slate-500 mt-0.5">Chọn người thanh toán, địa điểm quán, các món và chọn thành viên cùng chia tiền</p>
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
                    <input type="text" name="title" id="txTitle" required placeholder="Ví dụ: Ăn trưa bún đậu, Cafe sáng, Tiền phòng hát..." class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm font-medium">
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
                    <input type="text" name="note" placeholder="Dịp gặp mặt, chi tiết..." class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm">
                </div>
            </div>
        </div>

        <!-- Khối 2: Quán / Địa Điểm (Tùy chọn) -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">2. Quán / Địa Điểm <span class="text-slate-400 font-normal text-xs">(Không bắt buộc)</span></h2>
                    <p class="text-xs text-slate-500 mt-0.5">Chọn quán để lọc đúng đơn giá món tại quán đó hoặc tạo nhanh quán mới</p>
                </div>
                <button type="button" onclick="openQuickPlaceModal()" class="inline-flex items-center px-3 py-1.5 text-xs font-bold rounded-lg text-emerald-700 bg-emerald-50 border border-emerald-300 hover:bg-emerald-100 transition shadow-2xs">
                    <svg class="w-3.5 h-3.5 mr-1 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Tạo Quán Mới
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                <div class="sm:col-span-8">
                    <select name="place_id" id="placeSelect" onchange="onPlaceChange(this.value)" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm font-medium bg-white">
                        <option value="">-- Không chọn quán / Ăn uống tự do --</option>
                        <?php foreach ($places as $pl): ?>
                            <option value="<?= $pl['id'] ?>" data-name="<?= htmlspecialchars($pl['name']) ?>" data-address="<?= htmlspecialchars($pl['address'] ?: '') ?>">
                                📍 <?= htmlspecialchars($pl['name']) ?><?= $pl['address'] ? ' (' . htmlspecialchars($pl['address']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sm:col-span-4" id="addProdForPlaceBtnWrap" style="display: none;">
                    <button type="button" onclick="openQuickProductModal()" class="w-full inline-flex items-center justify-center px-3.5 py-2.5 text-xs font-bold rounded-xl text-emerald-800 bg-emerald-100 hover:bg-emerald-200 border border-emerald-300 transition shadow-2xs">
                        <svg class="w-3.5 h-3.5 mr-1 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        + Thêm Món Cho Quán Này
                    </button>
                </div>
            </div>

            <div id="selectedPlaceNotice" class="hidden text-xs text-emerald-800 bg-emerald-50 px-3 py-2 rounded-lg border border-emerald-200 flex items-center justify-between">
                <div class="flex items-center space-x-1.5 truncate">
                    <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span id="selectedPlaceLabel" class="font-bold truncate"></span>
                </div>
                <span class="text-[11px] text-emerald-600 font-semibold whitespace-nowrap ml-2">Đã ưu tiên các món của quán lên đầu danh sách</span>
            </div>
        </div>

        <!-- Khối 3: Danh sách các món trong giao dịch -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">3. Chi Tiết Sản Phẩm & Phân Bổ Thành Viên</h2>
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
                <!-- Javascript sẽ tự động thêm hàng vào đây -->
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

<!-- Modal 1: Tạo Nhanh Quán Mới -->
<div id="quickPlaceModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-slate-100 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-base font-bold text-slate-900">Tạo Quán / Địa Điểm Mới</h3>
            <button type="button" onclick="closeQuickPlaceModal()" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
        </div>
        <form onsubmit="handleQuickPlaceSubmit(event)" class="space-y-3">
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Tên Quán *</label>
                <input type="text" id="quickPlaceName" required placeholder="Ví dụ: The Coffee House, Highlands, Cơm Tấm..." class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm font-medium">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Địa Chỉ / Vị Trí</label>
                <input type="text" id="quickPlaceAddress" placeholder="Ví dụ: đường Mai Chí Thọ, Quận 2" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm">
            </div>
            <div class="flex justify-end space-x-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeQuickPlaceModal()" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">Hủy</button>
                <button type="submit" id="quickPlaceSubmitBtn" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-xs font-bold text-white shadow-md">Tạo & Chọn Quán</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Thêm Món Mới Cho Quán Đang Chọn -->
<div id="quickProductModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-slate-100 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-base font-bold text-slate-900">Thêm Món Mới Cho Quán</h3>
                <p class="text-xs text-emerald-700 font-medium mt-0.5" id="quickProductPlaceName"></p>
            </div>
            <button type="button" onclick="closeQuickProductModal()" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
        </div>
        <form onsubmit="handleQuickProductSubmit(event)" class="space-y-3">
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Tên Món / Dịch Vụ *</label>
                <input type="text" id="quickProductName" required placeholder="Ví dụ: Cà phê muối, Bạc xỉu..." class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm font-medium">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Đơn Giá Mặc Định (VNĐ)</label>
                <input type="number" id="quickProductPrice" min="0" step="500" placeholder="Ví dụ: 35000" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm font-bold text-emerald-800">
            </div>
            <div class="flex justify-end space-x-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeQuickProductModal()" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">Hủy</button>
                <button type="submit" id="quickProductSubmitBtn" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-xs font-bold text-white shadow-md">Lưu & Thêm Vào Đơn</button>
            </div>
        </form>
    </div>
</div>

<!-- Dữ liệu JS dùng để render -->
<script>
let availableProducts = <?= json_encode($products) ?>;
const availableMembers = <?= json_encode($members) ?>;
let selectedPlaceId = 0;
let itemIndex = 0;

function onPlaceChange(placeId) {
    selectedPlaceId = parseInt(placeId) || 0;
    const placeSelect = document.getElementById('placeSelect');
    const selectedOption = placeSelect.options[placeSelect.selectedIndex];
    const placeName = selectedOption.getAttribute('data-name') || '';
    const placeAddress = selectedOption.getAttribute('data-address') || '';

    const btnWrap = document.getElementById('addProdForPlaceBtnWrap');
    const notice = document.getElementById('selectedPlaceNotice');
    const label = document.getElementById('selectedPlaceLabel');

    if (selectedPlaceId > 0) {
        btnWrap.style.display = 'block';
        notice.classList.remove('hidden');
        label.textContent = placeName + (placeAddress ? ' — ' + placeAddress : '');
        
        // Tự động gợi ý tiêu đề nếu chưa nhập
        const txTitle = document.getElementById('txTitle');
        if (!txTitle.value.trim()) {
            txTitle.value = 'Đi ăn tại ' + placeName;
        }
    } else {
        btnWrap.style.display = 'none';
        notice.classList.add('hidden');
    }

    // Cập nhật lại dropdown cho tất cả các dòng món hiện có
    refreshAllProductSelects();
}

function buildProductOptions(selectedProdId = null) {
    let html = `<option value="">-- Chọn sản phẩm/dịch vụ --</option>`;

    let placeProducts = [];
    let otherProducts = [];

    availableProducts.forEach(p => {
        if (selectedPlaceId > 0 && p.place_id && parseInt(p.place_id) === selectedPlaceId) {
            placeProducts.push(p);
        } else {
            otherProducts.push(p);
        }
    });

    if (selectedPlaceId > 0 && placeProducts.length > 0) {
        html += `<optgroup label="📍 Món của quán đang chọn (${placeProducts.length} món)">`;
        placeProducts.forEach(p => {
            const priceLabel = p.default_price !== null ? `(${Number(p.default_price).toLocaleString()} đ)` : `(Giá tùy nhập)`;
            const isSel = (selectedProdId && parseInt(selectedProdId) === parseInt(p.id)) ? 'selected' : '';
            html += `<option value="${p.id}" data-price="${p.default_price !== null ? p.default_price : ''}" data-place="${p.place_name || ''}" data-address="${p.place_address || ''}" ${isSel}>⭐ ${p.name} ${priceLabel}</option>`;
        });
        html += `</optgroup>`;
    }

    if (otherProducts.length > 0) {
        html += selectedPlaceId > 0 ? `<optgroup label="Các món khác trong hệ thống">` : '';
        otherProducts.forEach(p => {
            const placeLabel = p.place_name ? ` — 📍 ${p.place_name}${p.place_address ? ' (' + p.place_address + ')' : ''}` : '';
            const priceLabel = p.default_price !== null ? `(${Number(p.default_price).toLocaleString()} đ)` : `(Giá tùy nhập)`;
            const isSel = (selectedProdId && parseInt(selectedProdId) === parseInt(p.id)) ? 'selected' : '';
            html += `<option value="${p.id}" data-price="${p.default_price !== null ? p.default_price : ''}" data-place="${p.place_name || ''}" data-address="${p.place_address || ''}" ${isSel}>${p.name}${placeLabel} ${priceLabel}</option>`;
        });
        html += selectedPlaceId > 0 ? `</optgroup>` : '';
    }

    return html;
}

function refreshAllProductSelects() {
    const selects = document.querySelectorAll('.product-select');
    selects.forEach(sel => {
        const curVal = sel.value;
        sel.innerHTML = buildProductOptions(curVal);
    });
}

function addItemRow(preselectProdId = null, prefillPrice = null) {
    const container = document.getElementById('itemsContainer');
    const idx = itemIndex++;

    const card = document.createElement('div');
    card.id = `itemRow_${idx}`;
    card.className = "p-4 rounded-xl border border-slate-200 bg-slate-50/60 space-y-3 relative";

    const productOptions = buildProductOptions(preselectProdId);

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
                <select name="items[${idx}][product_id]" required onchange="onProductSelect(${idx}, this)" class="product-select w-full px-3 py-1.5 rounded-lg border border-slate-300 focus:border-emerald-500 text-sm font-medium bg-white">
                    ${productOptions}
                </select>
                <div id="itemPlaceBadge_${idx}" class="hidden mt-1 text-[11px] text-emerald-700 font-medium flex items-center">
                    <svg class="w-3 h-3 mr-1 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span id="itemPlaceText_${idx}"></span>
                </div>
            </div>

            <div class="sm:col-span-4">
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Đơn Giá Thực Tế (VNĐ) *</label>
                <input type="number" name="items[${idx}][price]" id="price_${idx}" value="${prefillPrice !== null ? prefillPrice : ''}" required min="0" step="500" placeholder="Đơn giá" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 focus:border-emerald-500 text-sm font-bold text-slate-900 bg-white">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Số Lượng</label>
                <input type="number" name="items[${idx}][quantity]" value="1" min="1" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 focus:border-emerald-500 text-sm text-center bg-white">
            </div>
        </div>

        <div>
            <input type="text" name="items[${idx}][note]" placeholder="Ghi chú món này (tùy chọn, vd: ít đường, phần lớn...)" class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-200 text-slate-700 bg-white placeholder-slate-400 focus:border-emerald-500">
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

    if (preselectProdId) {
        const selElem = card.querySelector('.product-select');
        onProductSelect(idx, selElem);
    }
}

function removeItemRow(idx) {
    const row = document.getElementById(`itemRow_${idx}`);
    if (row) {
        row.remove();
    }
}

function onProductSelect(idx, selectElem) {
    const selectedOption = selectElem.options[selectElem.selectedIndex];
    if (!selectedOption) return;

    const defaultPrice = selectedOption.getAttribute('data-price');
    const placeName = selectedOption.getAttribute('data-place');
    const placeAddress = selectedOption.getAttribute('data-address');

    const priceInput = document.getElementById(`price_${idx}`);
    if (defaultPrice) {
        priceInput.value = defaultPrice;
    } else if (!priceInput.value) {
        priceInput.value = '';
        priceInput.placeholder = 'Nhập giá cho lần dùng này';
    }

    const badge = document.getElementById(`itemPlaceBadge_${idx}`);
    const badgeText = document.getElementById(`itemPlaceText_${idx}`);
    if (placeName) {
        badgeText.textContent = placeName + (placeAddress ? ` (${placeAddress})` : '');
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

function toggleAllMembers(idx, isChecked) {
    const checkboxes = document.querySelectorAll(`.member-cb-${idx}`);
    checkboxes.forEach(cb => cb.checked = isChecked);
}

// Modal Tạo Quán
function openQuickPlaceModal() {
    document.getElementById('quickPlaceName').value = '';
    document.getElementById('quickPlaceAddress').value = '';
    document.getElementById('quickPlaceModal').classList.remove('hidden');
    document.getElementById('quickPlaceName').focus();
}

function closeQuickPlaceModal() {
    document.getElementById('quickPlaceModal').classList.add('hidden');
}

async function handleQuickPlaceSubmit(e) {
    e.preventDefault();
    const name = document.getElementById('quickPlaceName').value.trim();
    const address = document.getElementById('quickPlaceAddress').value.trim();
    const btn = document.getElementById('quickPlaceSubmitBtn');

    if (!name) return;
    btn.disabled = true;
    btn.textContent = 'Đang tạo...';

    try {
        const formData = new FormData();
        formData.append('action', 'quick_place_create');
        formData.append('place_name', name);
        formData.append('place_address', address);

        const res = await fetch('ajax_action.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success && data.place) {
            const place = data.place;
            // Thêm vào select
            const placeSelect = document.getElementById('placeSelect');
            const opt = document.createElement('option');
            opt.value = place.id;
            opt.setAttribute('data-name', place.name);
            opt.setAttribute('data-address', place.address || '');
            opt.textContent = `📍 ${place.name}` + (place.address ? ` (${place.address})` : '');
            opt.selected = true;
            placeSelect.appendChild(opt);

            closeQuickPlaceModal();
            onPlaceChange(place.id);
        } else {
            alert(data.message || 'Lỗi khi tạo quán');
        }
    } catch (err) {
        alert('Lỗi kết nối khi tạo quán');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Tạo & Chọn Quán';
    }
}

// Modal Thêm Món Cho Quán
function openQuickProductModal() {
    if (!selectedPlaceId) {
        alert('Vui lòng chọn quán trước');
        return;
    }
    const placeSelect = document.getElementById('placeSelect');
    const selectedOption = placeSelect.options[placeSelect.selectedIndex];
    const placeName = selectedOption.getAttribute('data-name') || '';

    document.getElementById('quickProductPlaceName').textContent = '📍 Quán: ' + placeName;
    document.getElementById('quickProductName').value = '';
    document.getElementById('quickProductPrice').value = '';
    document.getElementById('quickProductModal').classList.remove('hidden');
    document.getElementById('quickProductName').focus();
}

function closeQuickProductModal() {
    document.getElementById('quickProductModal').classList.add('hidden');
}

async function handleQuickProductSubmit(e) {
    e.preventDefault();
    const name = document.getElementById('quickProductName').value.trim();
    const price = document.getElementById('quickProductPrice').value.trim();
    const btn = document.getElementById('quickProductSubmitBtn');

    if (!name) return;
    btn.disabled = true;
    btn.textContent = 'Đang lưu...';

    try {
        const formData = new FormData();
        formData.append('action', 'quick_product_create');
        formData.append('name', name);
        formData.append('price', price);
        formData.append('place_id', selectedPlaceId);

        const res = await fetch('ajax_action.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success && data.product) {
            const newProd = data.product;
            // Thêm vào mảng availableProducts
            availableProducts.unshift(newProd);

            closeQuickProductModal();

            // Tự động thêm ngay 1 hàng mới với món này
            addItemRow(newProd.id, newProd.default_price);
        } else {
            alert(data.message || 'Lỗi khi thêm món');
        }
    } catch (err) {
        alert('Lỗi kết nối khi thêm món');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Lưu & Thêm Vào Đơn';
    }
}

// Khởi tạo ngay 1 hàng khi mở trang
document.addEventListener('DOMContentLoaded', () => {
    addItemRow();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
