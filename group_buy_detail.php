<?php

$pageTitle = "Chi Tiết Sự Kiện Mua Chung - SimpleFinance";
require_once __DIR__ . '/includes/header.php';

use SimpleFinance\Models\GroupBuy;

$id = (int)($_GET['id'] ?? 0);
$groupBuyModel = new GroupBuy();
$event = $groupBuyModel->getEventById($id);

if (!$event || (int)$event['group_id'] !== $currentGroupId) {
    echo "<div class='p-8 bg-white rounded-3xl border border-red-200 text-center text-red-600 font-bold'>Sự kiện không tồn tại hoặc không thuộc nhóm chi tiêu của bạn.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$registrations = $groupBuyModel->getRegistrations($id);
$breakdown = $groupBuyModel->getSummaryBreakdown($id);

$totalOrders = count($registrations);
$totalCollected = 0.0;
$totalExpected = 0.0;
$totalItemsCount = 0;

$totalDelivered = 0;

foreach ($registrations as $r) {
    $totalExpected += (float)$r['total_amount'];
    if ((int)$r['is_paid'] === 1) {
        $totalCollected += (float)$r['total_amount'];
    }
    if ((int)($r['is_delivered'] ?? 0) === 1) {
        $totalDelivered++;
    }
}

foreach ($breakdown as $b) {
    $totalItemsCount += (int)$b['total_quantity'];
}

$isExpired = !empty($event['deadline']) && strtotime($event['deadline']) <= time();
$publicUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/event.php?token=" . $event['public_token'];
?>

<div class="space-y-6">

    <!-- Breadcrumb & Back -->
    <div class="flex items-center gap-2 text-xs text-slate-500">
        <a href="group_buys.php" class="hover:text-emerald-600">&larr; Quay lại danh sách mua chung</a>
        <span>/</span>
        <span class="text-slate-800 font-bold">Chi tiết sự kiện #<?= $event['id'] ?></span>
    </div>

    <!-- Header Box Sự Kiện -->
    <div class="bg-white rounded-3xl border border-slate-200 p-5 sm:p-6 shadow-2xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-4">
                <?php if (!empty($event['image_url'])): ?>
                    <img src="<?= htmlspecialchars($event['image_url']) ?>" alt="Ảnh sản phẩm" class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl object-cover border border-slate-200 shadow-xs flex-shrink-0">
                <?php else: ?>
                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center text-3xl font-bold flex-shrink-0">
                        <i class="ph ph-t-shirt"></i>
                    </div>
                <?php endif; ?>

                <div>
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <?php if ($event['status'] === 'converted'): ?>
                            <span class="px-2.5 py-0.5 bg-purple-100 text-purple-800 rounded-full text-xs font-black">ĐÃ CHỐT HÓA ĐƠN #<?= $event['transaction_id'] ?></span>
                        <?php elseif ($event['status'] === 'closed'): ?>
                            <span class="px-2.5 py-0.5 bg-slate-100 text-slate-700 rounded-full text-xs font-bold">ĐÃ ĐÓNG ĐƠN</span>
                        <?php elseif ($isExpired): ?>
                            <span class="px-2.5 py-0.5 bg-rose-100 text-rose-800 rounded-full text-xs font-bold flex items-center gap-1">
                                <i class="ph ph-clock-countdown"></i> ĐÃ HẾT HẠN
                            </span>
                        <?php else: ?>
                            <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold">ĐANG NHẬN ĐƠN</span>
                        <?php endif; ?>
                        <span class="text-xs text-slate-400">ID: #GB-<?= $event['id'] ?></span>
                    </div>

                    <h1 class="text-lg sm:text-xl font-black text-slate-900"><?= htmlspecialchars($event['title']) ?></h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Tạo bởi: <strong><?= htmlspecialchars($event['creator_name'] ?? 'Admin') ?></strong> 
                        <?php if (!empty($event['deadline'])): ?>
                            • Hạn chót: <?= date('H:i - d/m/Y', strtotime($event['deadline'])) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Actions Bar -->
            <div class="flex flex-wrap items-center gap-2 pt-2 md:pt-0">
                <button type="button" onclick="copyPublicLink('<?= htmlspecialchars($publicUrl) ?>')" class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-2xl transition flex items-center gap-1.5 shadow-2xs">
                    <i class="ph ph-copy text-sm"></i> Sao chép Link Đặt
                </button>
                <a href="event.php?token=<?= htmlspecialchars($event['public_token']) ?>" target="_blank" class="px-3.5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-2xl transition flex items-center gap-1.5 shadow-2xs">
                    <i class="ph ph-arrow-square-out text-sm"></i> Mở Trang Đăng Ký
                </a>
                <button type="button" onclick="openEditDeadlineModal()" class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-2xl transition flex items-center gap-1.5 shadow-2xs" title="Chỉnh sửa thời gian hết hạn">
                    <i class="ph ph-clock text-amber-600 text-sm"></i> Đổi Hạn Chót
                </button>
                <?php if ($event['status'] !== 'converted'): ?>
                    <button type="button" onclick="convertToTransaction(<?= $event['id'] ?>)" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-black rounded-2xl shadow-lg shadow-emerald-600/30 transition flex items-center gap-1.5">
                        <i class="ph ph-receipt text-sm"></i> Chốt Hóa Đơn
                    </button>
                    <button type="button" onclick="deleteEvent(<?= $event['id'] ?>)" class="px-3 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold rounded-2xl transition flex items-center gap-1.5" title="Xóa sự kiện này">
                        <i class="ph ph-trash text-sm"></i> Xóa
                    </button>
                <?php else: ?>
                    <a href="transaction_detail.php?id=<?= $event['transaction_id'] ?>" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-black rounded-2xl shadow-md transition flex items-center gap-1.5">
                        <i class="ph ph-receipt text-sm"></i> Xem Giao Dịch #<?= $event['transaction_id'] ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Thống Kê Gom Size & Tiền Nhanh -->
        <div class="mt-6 pt-6 border-t border-slate-100 space-y-4">
            <div class="flex items-center justify-between text-xs">
                <span class="font-bold text-slate-700 uppercase tracking-wider">Tổng hợp số lượng gom hàng theo phân loại / size:</span>
                <span class="text-slate-500">Tổng cộng: <strong class="text-slate-900 font-bold"><?= $totalItemsCount ?></strong> món</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-2.5">
                <?php foreach ($breakdown as $b): ?>
                    <div class="p-3 rounded-2xl border <?= (int)$b['total_quantity'] > 0 ? 'bg-emerald-50/40 border-emerald-200' : 'bg-slate-50 border-slate-100' ?>">
                        <span class="text-xs text-slate-600 font-medium block truncate" title="<?= htmlspecialchars($b['option_name']) ?>">
                            <?= htmlspecialchars($b['option_name']) ?>
                        </span>
                        <div class="text-lg font-black <?= (int)$b['total_quantity'] > 0 ? 'text-emerald-700' : 'text-slate-400' ?> mt-0.5">
                            <?= (int)$b['total_quantity'] ?> <span class="text-xs font-normal">món</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Dòng tổng hợp doanh thu đối soát & phát hàng -->
            <div class="flex flex-wrap items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-200 text-xs gap-3">
                <div class="flex items-center gap-4 flex-wrap">
                    <span>Tổng số người đặt: <strong class="text-slate-900"><?= $totalOrders ?></strong></span>
                    <span>Tổng tiền dự kiến: <strong class="text-slate-900"><?= number_format($totalExpected, 0, ',', '.') ?> ₫</strong></span>
                    <span class="border-l border-slate-300 pl-4">Đã phát hàng: <strong class="text-blue-700 font-bold" id="stat-delivered-count"><?= $totalDelivered ?></strong> / <?= $totalOrders ?> đơn</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-slate-500">Đã thu thực tế:</span>
                    <strong class="text-emerald-700 font-black text-sm" id="stat-collected-amt"><?= number_format($totalCollected, 0, ',', '.') ?> ₫</strong>
                    <span class="text-slate-400">(<?= $totalExpected > 0 ? round(($totalCollected / $totalExpected) * 100) : 0 ?>%)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Bảng Danh Sách Người Đăng Ký & Nút Tick Đã Thu Tiền / Phát Hàng -->
    <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-2xs">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm sm:text-base font-bold text-slate-900">Danh sách đơn đăng ký (<?= count($registrations) ?>)</h2>
                <p class="text-xs text-slate-400">Tick trực tiếp vào ô để xác nhận thu tiền hoặc đánh dấu khi đã phát hàng cho người nhận</p>
            </div>
            <button type="button" onclick="openAddOrderModal()" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-bold rounded-2xl transition flex items-center gap-1.5 shadow-sm">
                <i class="ph-bold ph-plus text-sm"></i> Thêm Đơn Đặt
            </button>
        </div>

        <?php if (empty($registrations)): ?>
            <div class="p-12 text-center text-slate-400 text-xs">
                Chưa có ai gửi đăng ký cho sự kiện này. Hãy sao chép liên kết bên trên gửi vào nhóm Zalo/Telegram hoặc bấm "Thêm Đơn Đặt" để tạo đơn trực tiếp nhé!
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase font-bold text-[11px]">
                        <tr>
                            <th class="py-3 px-4">Người tham gia</th>
                            <th class="py-3 px-4">Món / Size đã chọn</th>
                            <th class="py-3 px-4">Số tiền</th>
                            <th class="py-3 px-4 text-center">Trạng thái thanh toán</th>
                            <th class="py-3 px-4 text-center">Phát / Nhận hàng</th>
                            <th class="py-3 px-4 text-right">Thời gian</th>
                            <th class="py-3 px-3 text-center w-20">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($registrations as $r): ?>
                            <?php
                                $isPaid = (int)$r['is_paid'] === 1;
                                $isNotified = (int)$r['is_notified_paid'] === 1;
                                $isDelivered = (int)($r['is_delivered'] ?? 0) === 1;

                                $regPayload = [
                                    'id'                => (int)$r['id'],
                                    'participant_name'  => $r['participant_name'],
                                    'participant_phone' => $r['participant_phone'] ?? '',
                                    'note'              => $r['note'] ?? '',
                                    'is_paid'           => (int)$r['is_paid'],
                                    'is_delivered'      => (int)($r['is_delivered'] ?? 0),
                                    'items'             => array_map(function($it) {
                                        return [
                                            'item_id'  => (int)$it['item_id'],
                                            'quantity' => (int)$it['quantity']
                                        ];
                                    }, $r['items'] ?? [])
                                ];
                            ?>
                            <tr class="hover:bg-slate-50/50 transition <?= $isPaid ? '' : ($isNotified ? 'bg-amber-50/30' : '') ?>">
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($r['participant_name']) ?></div>
                                    <div class="text-[11px] text-slate-400">
                                        <?= htmlspecialchars($r['participant_phone'] ?: 'Không có SĐT') ?>
                                        <?php if (!empty($r['note'])): ?>
                                            • <span class="italic text-slate-500"><?= htmlspecialchars($r['note']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-medium text-slate-700">
                                    <?php if (!empty($r['items'])): ?>
                                        <?php foreach ($r['items'] as $it): ?>
                                            <div><?= $it['quantity'] ?>x <?= htmlspecialchars($it['option_name']) ?> (<?= number_format($it['subtotal'], 0, ',', '.') ?>đ)</div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <?= $r['items_summary'] ?? 'Chi tiết món' ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 font-black text-slate-900 text-sm">
                                    <?= number_format($r['total_amount'], 0, ',', '.') ?> ₫
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer px-3 py-1.5 rounded-full font-bold transition <?= $isPaid ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($isNotified ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-slate-100 text-slate-600') ?>">
                                        <input type="checkbox" 
                                               <?= $isPaid ? 'checked' : '' ?> 
                                               onchange="togglePaidStatus(<?= $r['id'] ?>, this)" 
                                               class="w-4 h-4 rounded text-emerald-600 focus:ring-0">
                                        <span class="status-label-text"><?= $isPaid ? 'Đã thu tiền' : ($isNotified ? 'Khách báo đã CK' : 'Chưa thu tiền') ?></span>
                                    </label>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer px-3 py-1.5 rounded-full font-bold transition <?= $isDelivered ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-slate-100 text-slate-600' ?>">
                                        <input type="checkbox" 
                                               <?= $isDelivered ? 'checked' : '' ?> 
                                               onchange="toggleDeliveredStatus(<?= $r['id'] ?>, this)" 
                                               class="w-4 h-4 rounded text-blue-600 focus:ring-0">
                                        <span class="delivery-label-text"><?= $isDelivered ? 'Đã nhận hàng' : 'Chưa nhận' ?></span>
                                    </label>
                                </td>
                                <td class="py-3.5 px-4 text-right text-slate-400 text-[11px]">
                                    <?= date('H:i d/m', strtotime($r['created_at'])) ?>
                                </td>
                                <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1">
                                        <button type="button" 
                                                onclick='openEditOrderModal(<?= json_encode($regPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)' 
                                                class="p-2 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 rounded-xl transition" 
                                                title="Chỉnh sửa đơn hàng này">
                                            <i class="ph-bold ph-pencil-simple text-base"></i>
                                        </button>
                                        <button type="button" 
                                                onclick="deleteRegistration(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['participant_name'])) ?>')" 
                                                class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition" 
                                                title="Xóa đơn đăng ký này">
                                            <i class="ph-bold ph-trash text-base"></i>
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

    <!-- Modal Đổi Hạn Chót -->
    <div id="modal-edit-deadline" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-3 sm:p-4">
        <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-black text-slate-900">Thay Đổi Hạn Chót Sự Kiện</h3>
                    <p class="text-xs text-slate-400">Gia hạn hoặc rút ngắn thời gian nhận đăng ký</p>
                </div>
                <button type="button" onclick="closeEditDeadlineModal()" class="p-2 rounded-full text-slate-400 hover:bg-slate-100">
                    <i class="ph-bold ph-x text-base"></i>
                </button>
            </div>

            <form id="form-edit-deadline" onsubmit="submitUpdateDeadline(event, <?= (int)$event['id'] ?>)" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Thời hạn mới</label>
                    <input type="datetime-local" id="input-new-deadline" value="<?= !empty($event['deadline']) ? date('Y-m-d\TH:i', strtotime($event['deadline'])) : '' ?>" class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <p class="text-[11px] text-slate-400 mt-1">Để trống nếu muốn bỏ giới hạn thời gian.</p>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeEditDeadlineModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-semibold transition">
                        Hủy bỏ
                    </button>
                    <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md transition">
                        Lưu thời hạn
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Thêm / Chỉnh Sửa Đơn Đặt Hàng -->
    <div id="modal-order-form" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-3 sm:p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full p-5 sm:p-6 shadow-2xl space-y-4 max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 flex-shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                        <i class="ph-bold ph-shopping-cart"></i>
                    </div>
                    <div>
                        <h3 id="order-modal-title" class="text-base font-black text-slate-900">Thêm Đơn Đặt Hàng Mới</h3>
                        <p class="text-xs text-slate-400">Tạo hoặc điều chỉnh đơn đặt cho thành viên</p>
                    </div>
                </div>
                <button type="button" onclick="closeOrderModal()" class="p-2 rounded-full text-slate-400 hover:bg-slate-100">
                    <i class="ph-bold ph-x text-base"></i>
                </button>
            </div>

            <form id="form-order" onsubmit="submitAdminOrder(event, <?= (int)$event['id'] ?>)" class="space-y-4 overflow-y-auto pr-1 flex-1">
                <input type="hidden" id="order-reg-id" value="">

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Họ và tên người đặt <span class="text-rose-500">*</span></label>
                    <input type="text" id="order-name" required placeholder="Ví dụ: Nam Trần, Cô giáo Mai..." class="w-full px-3.5 py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Số điện thoại / Zalo</label>
                        <input type="text" id="order-phone" placeholder="0912345678 (tùy chọn)" class="w-full px-3.5 py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Ghi chú</label>
                        <input type="text" id="order-note" placeholder="Form rộng, bàn 3..." class="w-full px-3.5 py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                </div>

                <!-- Danh mục sản phẩm / size -->
                <div class="pt-2">
                    <label class="block text-xs font-bold text-slate-700 mb-2">Chọn sản phẩm / Phân loại size <span class="text-rose-500">*</span></label>
                    <div class="space-y-2 border border-slate-200 rounded-2xl p-3 bg-slate-50/50 max-h-56 overflow-y-auto">
                        <?php if (empty($event['items'])): ?>
                            <p class="text-xs text-slate-400 text-center py-2">Sự kiện chưa có danh mục sản phẩm.</p>
                        <?php else: ?>
                            <?php foreach ($event['items'] as $it): ?>
                                <div class="flex items-center justify-between p-2 bg-white rounded-xl border border-slate-100 hover:border-slate-200 transition text-xs gap-2">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-bold text-slate-800 truncate" title="<?= htmlspecialchars($it['option_name']) ?>">
                                            <?= htmlspecialchars($it['option_name']) ?>
                                        </div>
                                        <div class="text-[11px] text-emerald-600 font-semibold">
                                            <?= number_format($it['price'], 0, ',', '.') ?> ₫
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <button type="button" onclick="changeItemQty(this, -1)" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold flex items-center justify-center transition active:scale-95 text-xs">
                                            -
                                        </button>
                                        <input type="number" 
                                               min="0" 
                                               value="0" 
                                               data-id="<?= (int)$it['id'] ?>" 
                                               data-price="<?= (float)$it['price'] ?>" 
                                               onchange="recalcAdminOrderTotal()" 
                                               oninput="recalcAdminOrderTotal()" 
                                               class="admin-item-qty w-12 text-center text-xs font-bold bg-white border border-slate-200 rounded-lg py-1 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                        <button type="button" onclick="changeItemQty(this, 1)" class="w-7 h-7 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold flex items-center justify-center transition active:scale-95 text-xs">
                                            +
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tạm tính -->
                <div class="flex items-center justify-between p-3 bg-emerald-50/60 rounded-2xl border border-emerald-100 text-xs">
                    <span class="text-slate-600">Đã chọn: <strong id="admin-modal-total-qty" class="text-slate-900 font-bold">0</strong> món</span>
                    <span class="text-slate-600">Tổng tiền: <strong id="admin-modal-total-amount" class="text-emerald-700 font-black text-sm">0 ₫</strong></span>
                </div>

                <!-- Trạng thái -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <label class="flex items-center gap-2 p-3 bg-slate-50 rounded-2xl border border-slate-200 cursor-pointer text-xs">
                        <input type="checkbox" id="order-is-paid" class="w-4 h-4 rounded text-emerald-600 focus:ring-0">
                        <span class="font-bold text-slate-700">Đã thu tiền</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 bg-slate-50 rounded-2xl border border-slate-200 cursor-pointer text-xs">
                        <input type="checkbox" id="order-is-delivered" class="w-4 h-4 rounded text-blue-600 focus:ring-0">
                        <span class="font-bold text-slate-700">Đã phát / nhận hàng</span>
                    </label>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2 flex-shrink-0">
                    <button type="button" onclick="closeOrderModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-semibold transition">
                        Hủy bỏ
                    </button>
                    <button type="submit" id="btn-submit-admin-order" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white rounded-xl text-xs font-bold shadow-md transition flex items-center gap-1.5">
                        <i class="ph-bold ph-floppy-disk text-sm"></i> Lưu Đơn Hàng
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
    function openAddOrderModal() {
        document.getElementById('order-modal-title').innerText = "Thêm Đơn Đặt Hàng Mới";
        document.getElementById('order-reg-id').value = "";
        document.getElementById('order-name').value = "";
        document.getElementById('order-phone').value = "";
        document.getElementById('order-note').value = "";
        document.getElementById('order-is-paid').checked = false;
        document.getElementById('order-is-delivered').checked = false;

        document.querySelectorAll('.admin-item-qty').forEach(inp => {
            inp.value = 0;
        });

        recalcAdminOrderTotal();
        document.getElementById('modal-order-form').classList.remove('hidden');
        setTimeout(() => document.getElementById('order-name').focus(), 100);
    }

    function openEditOrderModal(regData) {
        document.getElementById('order-modal-title').innerText = `Chỉnh Sửa Đơn Hàng #${regData.id}`;
        document.getElementById('order-reg-id').value = regData.id;
        document.getElementById('order-name').value = regData.participant_name || '';
        document.getElementById('order-phone').value = regData.participant_phone || '';
        document.getElementById('order-note').value = regData.note || '';
        document.getElementById('order-is-paid').checked = (parseInt(regData.is_paid, 10) === 1);
        document.getElementById('order-is-delivered').checked = (parseInt(regData.is_delivered, 10) === 1);

        // Reset tất cả về 0 trước
        document.querySelectorAll('.admin-item-qty').forEach(inp => {
            inp.value = 0;
        });

        // Điền số lượng các items đã chọn
        if (Array.isArray(regData.items)) {
            regData.items.forEach(it => {
                const inp = document.querySelector(`.admin-item-qty[data-id="${it.item_id}"]`);
                if (inp) {
                    inp.value = it.quantity;
                }
            });
        }

        recalcAdminOrderTotal();
        document.getElementById('modal-order-form').classList.remove('hidden');
        setTimeout(() => document.getElementById('order-name').focus(), 100);
    }

    function closeOrderModal() {
        document.getElementById('modal-order-form').classList.add('hidden');
    }

    function changeItemQty(btn, delta) {
        const row = btn.parentElement;
        const inp = row.querySelector('.admin-item-qty');
        if (!inp) return;
        let cur = parseInt(inp.value || '0', 10);
        cur = Math.max(0, cur + delta);
        inp.value = cur;
        recalcAdminOrderTotal();
    }

    function recalcAdminOrderTotal() {
        let totalQty = 0;
        let totalAmt = 0;
        document.querySelectorAll('.admin-item-qty').forEach(inp => {
            const qty = parseInt(inp.value || '0', 10);
            const price = parseFloat(inp.getAttribute('data-price') || '0');
            if (qty > 0) {
                totalQty += qty;
                totalAmt += (qty * price);
            }
        });
        document.getElementById('admin-modal-total-qty').innerText = totalQty;
        document.getElementById('admin-modal-total-amount').innerText = new Intl.NumberFormat('vi-VN').format(totalAmt) + ' ₫';
    }

    function submitAdminOrder(event, eventId) {
        event.preventDefault();

        const regId = document.getElementById('order-reg-id').value;
        const name = document.getElementById('order-name').value.trim();
        const phone = document.getElementById('order-phone').value.trim();
        const note = document.getElementById('order-note').value.trim();
        const isPaid = document.getElementById('order-is-paid').checked ? 1 : 0;
        const isDelivered = document.getElementById('order-is-delivered').checked ? 1 : 0;

        if (!name) {
            alert('Vui lòng nhập họ và tên người đặt.');
            document.getElementById('order-name').focus();
            return;
        }

        const items = [];
        document.querySelectorAll('.admin-item-qty').forEach(inp => {
            const qty = parseInt(inp.value || '0', 10);
            const itemId = parseInt(inp.getAttribute('data-id') || '0', 10);
            if (qty > 0 && itemId > 0) {
                items.push({ item_id: itemId, quantity: qty });
            }
        });

        if (items.length === 0) {
            alert('Vui lòng chọn ít nhất 1 sản phẩm/kích cỡ với số lượng lớn hơn 0.');
            return;
        }

        const btn = document.getElementById('btn-submit-admin-order');
        const origText = btn.innerHTML;
        btn.innerHTML = `<i class="ph-bold ph-spinner animate-spin"></i> Đang lưu...`;
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'group_buy_admin_save_order');
        formData.append('event_id', eventId);
        if (regId) {
            formData.append('registration_id', regId);
        }
        formData.append('participant_name', name);
        formData.append('participant_phone', phone);
        formData.append('note', note);
        formData.append('is_paid', isPaid);
        formData.append('is_delivered', isDelivered);
        formData.append('items', JSON.stringify(items));

        fetch('ajax_action.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Đã lưu đơn đặt hàng thành công!');
                location.reload();
            } else {
                btn.innerHTML = origText;
                btn.disabled = false;
                alert(data.message || 'Lỗi khi lưu đơn hàng.');
            }
        })
        .catch(err => {
            btn.innerHTML = origText;
            btn.disabled = false;
            alert('Lỗi kết nối: ' + err.message);
        });
    }
    function copyPublicLink(url) {
        navigator.clipboard?.writeText(url);
        alert('Đã sao chép link đặt hàng công khai:\n' + url);
    }

    function openEditDeadlineModal() {
        document.getElementById('modal-edit-deadline').classList.remove('hidden');
    }

    function closeEditDeadlineModal() {
        document.getElementById('modal-edit-deadline').classList.add('hidden');
    }

    function submitUpdateDeadline(event, eventId) {
        event.preventDefault();
        const deadlineVal = document.getElementById('input-new-deadline').value;

        const formData = new FormData();
        formData.append('action', 'group_buy_update_deadline');
        formData.append('event_id', eventId);
        formData.append('deadline', deadlineVal);

        fetch('ajax_action.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Đã cập nhật hạn chót!');
                location.reload();
            } else {
                alert(data.message || 'Lỗi cập nhật thời hạn');
            }
        })
        .catch(err => {
            alert('Lỗi kết nối: ' + err.message);
        });
    }

    function deleteEvent(eventId) {
        if (!confirm('Bạn có chắc chắn muốn xóa sự kiện này và toàn bộ danh sách đăng ký liên quan không?\n\nHành động này không thể hoàn tác!')) {
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
                alert(data.message || 'Đã xóa sự kiện!');
                window.location.href = data.redirect_url || 'group_buys.php';
            } else {
                alert(data.message || 'Lỗi khi xóa sự kiện');
            }
        })
        .catch(err => {
            alert('Lỗi kết nối: ' + err.message);
        });
    }

    function deleteRegistration(regId, name) {
        if (!confirm(`Bạn có chắc chắn muốn xóa đơn đăng ký của "${name}" không?\n\nĐơn hàng này sẽ bị xóa khỏi danh sách và bảng tổng hợp.`)) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'group_buy_delete_registration');
        formData.append('registration_id', regId);

        fetch('ajax_action.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Đã xóa đơn đăng ký!');
                location.reload();
            } else {
                alert(data.message || 'Lỗi khi xóa đơn đăng ký');
            }
        })
        .catch(err => {
            alert('Lỗi kết nối: ' + err.message);
        });
    }

    function togglePaidStatus(regId, checkbox) {
        const isPaid = checkbox.checked ? 1 : 0;
        const parentLabel = checkbox.closest('label');
        const textSpan = parentLabel.querySelector('.status-label-text');

        const formData = new FormData();
        formData.append('action', 'group_buy_toggle_paid');
        formData.append('registration_id', regId);
        formData.append('is_paid', isPaid);

        fetch('ajax_action.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (isPaid === 1) {
                    parentLabel.className = "inline-flex items-center gap-1.5 cursor-pointer px-3 py-1.5 rounded-full font-bold transition bg-emerald-50 text-emerald-700 border border-emerald-200";
                    textSpan.innerText = "Đã thu tiền";
                } else {
                    parentLabel.className = "inline-flex items-center gap-1.5 cursor-pointer px-3 py-1.5 rounded-full font-bold transition bg-slate-100 text-slate-600";
                    textSpan.innerText = "Chưa thu tiền";
                }
            } else {
                checkbox.checked = !checkbox.checked;
                alert(data.message || 'Lỗi cập nhật trạng thái thu tiền');
            }
        })
        .catch(err => {
            checkbox.checked = !checkbox.checked;
            alert('Lỗi kết nối: ' + err.message);
        });
    }

    function toggleDeliveredStatus(regId, checkbox) {
        const isDelivered = checkbox.checked ? 1 : 0;
        const parentLabel = checkbox.closest('label');
        const textSpan = parentLabel.querySelector('.delivery-label-text');

        const formData = new FormData();
        formData.append('action', 'group_buy_toggle_delivered');
        formData.append('registration_id', regId);
        formData.append('is_delivered', isDelivered);

        fetch('ajax_action.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (isDelivered === 1) {
                    parentLabel.className = "inline-flex items-center gap-1.5 cursor-pointer px-3 py-1.5 rounded-full font-bold transition bg-blue-50 text-blue-700 border border-blue-200";
                    textSpan.innerText = "Đã nhận hàng";
                } else {
                    parentLabel.className = "inline-flex items-center gap-1.5 cursor-pointer px-3 py-1.5 rounded-full font-bold transition bg-slate-100 text-slate-600";
                    textSpan.innerText = "Chưa nhận";
                }
                // Cập nhật số đếm trên giao diện
                const statEl = document.getElementById('stat-delivered-count');
                if (statEl) {
                    let cur = parseInt(statEl.innerText || '0', 10);
                    cur = isDelivered === 1 ? cur + 1 : Math.max(0, cur - 1);
                    statEl.innerText = cur;
                }
            } else {
                checkbox.checked = !checkbox.checked;
                alert(data.message || 'Lỗi cập nhật trạng thái phát hàng');
            }
        })
        .catch(err => {
            checkbox.checked = !checkbox.checked;
            alert('Lỗi kết nối: ' + err.message);
        });
    }

    function convertToTransaction(eventId) {
        if (!confirm('Bạn có chắc chắn muốn chốt sự kiện này và tạo thành 1 Giao dịch chi tiêu nhóm SimpleFinance không?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'group_buy_convert_to_transaction');
        formData.append('event_id', eventId);

        fetch('ajax_action.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.redirect_url) {
                alert(data.message);
                window.location.href = data.redirect_url;
            } else {
                alert(data.message || 'Lỗi chuyển đổi thành giao dịch');
            }
        })
        .catch(err => {
            alert('Lỗi kết nối: ' + err.message);
        });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
