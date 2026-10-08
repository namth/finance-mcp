<?php

$pageTitle = "Sự Kiện Mua Chung - SimpleFinance";
require_once __DIR__ . '/includes/header.php';

use SimpleFinance\Models\GroupBuy;

$groupBuyModel = new GroupBuy();
$message = null;
$error = null;

// Xử lý tạo sự kiện mua chung mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_event') {
    try {
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $imageUrl = trim((string)($_POST['image_url'] ?? ''));
        $deadline = trim((string)($_POST['deadline'] ?? ''));
        $bankBin = trim((string)($_POST['bank_bin'] ?? ''));
        $bankAccountNo = trim((string)($_POST['bank_account_no'] ?? ''));
        $bankAccountName = trim((string)($_POST['bank_account_name'] ?? ''));

        // Xử lý danh sách món/size
        $items = [];
        $itemNames = $_POST['item_name'] ?? [];
        $optionNames = $_POST['option_name'] ?? [];
        $prices = $_POST['price'] ?? [];

        if (is_array($itemNames)) {
            for ($i = 0; $i < count($itemNames); $i++) {
                $iName = trim((string)($itemNames[$i] ?? ''));
                $optName = trim((string)($optionNames[$i] ?? ''));
                $price = (float)($prices[$i] ?? 0);

                if (!empty($optName)) {
                    $items[] = [
                        'name'        => $iName ?: $title,
                        'option_name' => $optName,
                        'price'       => $price,
                    ];
                }
            }
        }

        if (empty($items)) {
            throw new \InvalidArgumentException("Vui lòng thêm ít nhất một phân loại hoặc kích cỡ sản phẩm.");
        }

        $newEvent = $groupBuyModel->createEvent(
            $currentGroupId,
            (int)$currentUser['id'],
            $title,
            $description ?: null,
            $imageUrl ?: null,
            $deadline ?: null,
            $items,
            $bankBin ?: null,
            $bankAccountNo ?: null,
            $bankAccountName ?: null
        );

        $message = "Đã khởi tạo sự kiện mua chung '{$title}' thành công!";
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

$events = $groupBuyModel->getEventsByGroup($currentGroupId);
?>

<div class="space-y-6">

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Sự Kiện Mua Chung</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Tổ chức gom đơn, chia sẻ link công khai cho mọi người chọn size và đối soát VietQR tự động.</p>
        </div>
        <button type="button" onclick="openCreateModal()" class="inline-flex items-center justify-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs sm:text-sm font-bold rounded-2xl shadow-lg shadow-emerald-600/30 transition gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
            </svg>
            Tạo Sự Kiện Mới
        </button>
    </div>

    <?php if ($message): ?>
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm flex items-center gap-2">
            <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Danh sách Sự Kiện -->
    <?php if (empty($events)): ?>
        <div class="bg-white rounded-3xl border border-slate-200 p-12 text-center shadow-2xs">
            <div class="w-16 h-16 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3 text-2xl">
                <i class="fa-solid fa-cart-shopping"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">Chưa có sự kiện mua chung nào</h3>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Tạo sự kiện đầu tiên để gom đơn mua áo, đồ ăn trưa hoặc quà tặng tập thể cho nhóm.</p>
            <button type="button" onclick="openCreateModal()" class="mt-4 px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 transition">
                + Bắt đầu tạo ngay
            </button>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($events as $ev): ?>
                <?php
                    $isConverted = $ev['status'] === 'converted';
                    $isClosed = $ev['status'] === 'closed';
                    $publicUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/event.php?token=" . $ev['public_token'];
                ?>
                <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-2xs hover:shadow-md transition flex flex-col justify-between">
                    <div>
                        <!-- Ảnh hoặc Banner màu -->
                        <?php if (!empty($ev['image_url'])): ?>
                            <div class="h-40 w-full bg-slate-900 relative overflow-hidden">
                                <img src="<?= htmlspecialchars($ev['image_url']) ?>" alt="Ảnh sự kiện" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent"></div>
                                <div class="absolute top-3 right-3">
                                    <?php if ($isConverted): ?>
                                        <span class="px-2.5 py-1 bg-purple-600/90 text-white rounded-full text-[10px] font-black uppercase tracking-wider backdrop-blur-xs">Đã chốt hóa đơn</span>
                                    <?php elseif ($isClosed): ?>
                                        <span class="px-2.5 py-1 bg-slate-600/90 text-white rounded-full text-[10px] font-black uppercase tracking-wider backdrop-blur-xs">Đã đóng đơn</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 bg-emerald-600/90 text-white rounded-full text-[10px] font-black uppercase tracking-wider backdrop-blur-xs">Đang mở</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="h-24 bg-gradient-to-r from-emerald-600 to-teal-700 p-4 relative flex items-start justify-between text-white">
                                <span class="text-xs font-bold opacity-80">Sự kiện #<?= $ev['id'] ?></span>
                                <?php if ($isConverted): ?>
                                    <span class="px-2 py-0.5 bg-white/20 text-white rounded-full text-[10px] font-bold">Đã chốt hóa đơn</span>
                                <?php elseif ($isClosed): ?>
                                    <span class="px-2 py-0.5 bg-white/20 text-white rounded-full text-[10px] font-bold">Đã đóng đơn</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 bg-white/20 text-white rounded-full text-[10px] font-bold">Đang mở</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="p-5 space-y-3">
                            <h2 class="font-bold text-base text-slate-900 line-clamp-1">
                                <a href="group_buy_detail.php?id=<?= $ev['id'] ?>" class="hover:text-emerald-600 transition">
                                    <?= htmlspecialchars($ev['title']) ?>
                                </a>
                            </h2>
                            <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                                <?= htmlspecialchars($ev['description'] ?: 'Không có mô tả chi tiết.') ?>
                            </p>

                            <!-- Thống kê nhanh -->
                            <div class="grid grid-cols-2 gap-2 pt-2 text-xs">
                                <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                                    <span class="text-[11px] text-slate-400 block">Đã đăng ký:</span>
                                    <strong class="text-slate-800 text-sm font-bold"><?= (int)$ev['total_registrations'] ?> đơn</strong>
                                </div>
                                <div class="p-2.5 bg-emerald-50/50 rounded-xl border border-emerald-100">
                                    <span class="text-[11px] text-emerald-600 block">Đã thu:</span>
                                    <strong class="text-emerald-700 text-sm font-bold"><?= number_format($ev['collected_amount'], 0, ',', '.') ?> ₫</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer card buttons -->
                    <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <button type="button" onclick="copyLink('<?= htmlspecialchars($publicUrl) ?>')" class="px-3 py-2 bg-white border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-100 transition flex items-center gap-1.5" title="Sao chép link gửi Zalo/Messenger">
                                <i class="fa-regular fa-copy text-slate-400"></i> Copy Link
                            </button>
                            <button type="button" onclick="deleteEvent(<?= (int)$ev['id'] ?>)" class="p-2 bg-white border border-slate-200 text-slate-400 hover:text-rose-600 hover:border-rose-200 rounded-xl text-xs transition" title="Xóa sự kiện này">
                                <i class="fa-regular fa-trash-can"></i>
                            </button>
                        </div>
                        <a href="group_buy_detail.php?id=<?= $ev['id'] ?>" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1">
                            Quản lý & Thu tiền &rarr;
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- MODAL TẠO SỰ KIỆN MUA CHUNG MỚI -->
<div id="modal-create-event" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-white rounded-3xl max-w-xl w-full p-5 sm:p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-base sm:text-lg font-black text-slate-900">Tạo Sự Kiện Mua Chung Mới</h3>
                <p class="text-xs text-slate-400">Thiết lập thông tin và danh mục phân loại/size</p>
            </div>
            <button type="button" onclick="closeCreateModal()" class="p-2 rounded-full text-slate-400 hover:bg-slate-100">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create_event">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Tiêu đề sự kiện <span class="text-red-500">*</span></label>
                <input type="text" name="title" required placeholder="Ví dụ: Đặt Áo Đồng Phục Nhóm 2026, Gom Đặt Cơm Trưa..." class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Ảnh sản phẩm / Bảng kích thước (URL)</label>
                <input type="url" name="image_url" placeholder="https://example.com/anh-ao.jpg" class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                <p class="text-[11px] text-slate-400 mt-1">Dán link ảnh chụp mẫu hoặc bảng size để hiển thị trên đầu trang đặt hàng.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Mô tả sự kiện & hướng dẫn chọn size</label>
                <textarea name="description" rows="3" placeholder="Ví dụ: Size S dưới 52kg, Size M từ 53-62kg. Hạn chót đóng đơn lúc 20h thứ 6 tuần này nhé cả nhà!" class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Hạn chót đăng ký (tùy chọn)</label>
                    <input type="datetime-local" name="deadline" class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tài khoản nhận tiền VietQR</label>
                    <input type="text" readonly value="<?= htmlspecialchars(($currentUser['bank_account_no'] ?? '') ? ($currentUser['bank_account_no'] . ' (' . ($currentUser['bank_account_name'] ?? '') . ')') : 'Lấy theo hồ sơ cá nhân') ?>" class="w-full px-3.5 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl text-slate-600">
                </div>
            </div>

            <!-- Danh sách Phân Loại / Kích Cỡ -->
            <div class="pt-2">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">Danh mục phân loại / Kích cỡ & Giá</label>
                    <button type="button" onclick="addOptionRow()" class="text-xs font-bold text-emerald-600 hover:text-emerald-700">+ Thêm phân loại</button>
                </div>
                
                <div id="options-container" class="space-y-2">
                    <div class="flex items-center gap-2 option-row">
                        <input type="text" name="option_name[]" required value="Size S (Dưới 52kg)" placeholder="Tên phân loại / Size" class="flex-1 px-3 py-2 text-xs bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <input type="number" name="price[]" required value="150000" min="0" placeholder="Đơn giá" class="w-28 px-3 py-2 text-xs bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <button type="button" onclick="removeOptionRow(this)" class="p-2 text-slate-400 hover:text-red-500"><i class="fa-solid fa-trash-can"></i></button>
                    </div>
                    <div class="flex items-center gap-2 option-row">
                        <input type="text" name="option_name[]" required value="Size M (53kg - 62kg)" placeholder="Tên phân loại / Size" class="flex-1 px-3 py-2 text-xs bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <input type="number" name="price[]" required value="150000" min="0" placeholder="Đơn giá" class="w-28 px-3 py-2 text-xs bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <button type="button" onclick="removeOptionRow(this)" class="p-2 text-slate-400 hover:text-red-500"><i class="fa-solid fa-trash-can"></i></button>
                    </div>
                    <div class="flex items-center gap-2 option-row">
                        <input type="text" name="option_name[]" required value="Size L (63kg - 72kg)" placeholder="Tên phân loại / Size" class="flex-1 px-3 py-2 text-xs bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <input type="number" name="price[]" required value="150000" min="0" placeholder="Đơn giá" class="w-28 px-3 py-2 text-xs bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <button type="button" onclick="removeOptionRow(this)" class="p-2 text-slate-400 hover:text-red-500"><i class="fa-solid fa-trash-can"></i></button>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-semibold transition">
                    Hủy bỏ
                </button>
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md transition">
                    Tạo Sự Kiện & Lấy Link Chia Sẻ
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateModal() {
        document.getElementById('modal-create-event').classList.remove('hidden');
    }
    function closeCreateModal() {
        document.getElementById('modal-create-event').classList.add('hidden');
    }
    function addOptionRow() {
        const container = document.getElementById('options-container');
        const div = document.createElement('div');
        div.className = "flex items-center gap-2 option-row";
        div.innerHTML = `
            <input type="text" name="option_name[]" required placeholder="Tên phân loại / Size" class="flex-1 px-3 py-2 text-xs bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            <input type="number" name="price[]" required value="0" min="0" placeholder="Đơn giá" class="w-28 px-3 py-2 text-xs bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            <button type="button" onclick="removeOptionRow(this)" class="p-2 text-slate-400 hover:text-red-500"><i class="fa-solid fa-trash-can"></i></button>
        `;
        container.appendChild(div);
    }
    function removeOptionRow(btn) {
        const rows = document.querySelectorAll('.option-row');
        if (rows.length <= 1) {
            alert('Cần giữ lại ít nhất 1 phân loại!');
            return;
        }
        btn.closest('.option-row').remove();
    }
    function copyLink(url) {
        navigator.clipboard?.writeText(url);
        alert('Đã sao chép link sự kiện vào bộ nhớ tạm: ' + url);
    }

    function deleteEvent(eventId) {
        if (!confirm('Bạn có chắc chắn muốn xóa sự kiện này và toàn bộ danh sách đăng ký không?\n\nHành động này không thể hoàn tác!')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'group_buy_delete');
        formData.append('event_id', eventId);

        fetch('ajax_action.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Đã xóa sự kiện thành công!');
                location.reload();
            } else {
                alert(data.message || 'Lỗi khi xóa sự kiện');
            }
        })
        .catch(err => {
            alert('Lỗi kết nối: ' + err.message);
        });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
