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

foreach ($registrations as $r) {
    $totalExpected += (float)$r['total_amount'];
    if ((int)$r['is_paid'] === 1) {
        $totalCollected += (float)$r['total_amount'];
    }
}

foreach ($breakdown as $b) {
    $totalItemsCount += (int)$b['total_quantity'];
}

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
                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center text-2xl font-bold flex-shrink-0">
                        <i class="fa-solid fa-shirt"></i>
                    </div>
                <?php endif; ?>

                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <?php if ($event['status'] === 'converted'): ?>
                            <span class="px-2.5 py-0.5 bg-purple-100 text-purple-800 rounded-full text-xs font-black">ĐÃ CHỐT HÓA ĐƠN #<?= $event['transaction_id'] ?></span>
                        <?php elseif ($event['status'] === 'closed'): ?>
                            <span class="px-2.5 py-0.5 bg-slate-100 text-slate-700 rounded-full text-xs font-bold">ĐÃ ĐÓNG ĐƠN</span>
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
                    <i class="fa-regular fa-copy"></i> Sao chép Link Đặt
                </button>
                <a href="event.php?token=<?= htmlspecialchars($event['public_token']) ?>" target="_blank" class="px-3.5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-2xl transition flex items-center gap-1.5 shadow-2xs">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Mở Trang Đăng Ký
                </a>
                <?php if ($event['status'] !== 'converted'): ?>
                    <button type="button" onclick="convertToTransaction(<?= $event['id'] ?>)" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-black rounded-2xl shadow-lg shadow-emerald-600/30 transition flex items-center gap-1.5">
                        <i class="fa-solid fa-file-invoice-dollar"></i> Chốt & Tạo Hóa Đơn Nhóm
                    </button>
                <?php else: ?>
                    <a href="transaction_detail.php?id=<?= $event['transaction_id'] ?>" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-black rounded-2xl shadow-md transition flex items-center gap-1.5">
                        <i class="fa-solid fa-receipt"></i> Xem Giao Dịch #<?= $event['transaction_id'] ?>
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

            <!-- Dòng tổng hợp doanh thu đối soát -->
            <div class="flex flex-wrap items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-200 text-xs gap-3">
                <div class="flex items-center gap-4">
                    <span>Tổng số người đặt: <strong class="text-slate-900"><?= $totalOrders ?></strong></span>
                    <span>Tổng tiền dự kiến: <strong class="text-slate-900"><?= number_format($totalExpected, 0, ',', '.') ?> ₫</strong></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-slate-500">Đã thu thực tế:</span>
                    <strong class="text-emerald-700 font-black text-sm" id="stat-collected-amt"><?= number_format($totalCollected, 0, ',', '.') ?> ₫</strong>
                    <span class="text-slate-400">(<?= $totalExpected > 0 ? round(($totalCollected / $totalExpected) * 100) : 0 ?>%)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Bảng Danh Sách Người Đăng Ký & Nút Tick Đã Thu Tiền -->
    <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-2xs">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-sm sm:text-base font-bold text-slate-900">Danh sách đơn đăng ký (<?= count($registrations) ?>)</h2>
                <p class="text-xs text-slate-400">Tick trực tiếp vào ô để xác nhận đã nhận chuyển khoản của từng người</p>
            </div>
        </div>

        <?php if (empty($registrations)): ?>
            <div class="p-12 text-center text-slate-400 text-xs">
                Chưa có ai gửi đăng ký cho sự kiện này. Hãy sao chép liên kết bên trên gửi vào nhóm Zalo/Telegram nhé!
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
                            <th class="py-3 px-4 text-right">Thời gian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($registrations as $r): ?>
                            <?php
                                $isPaid = (int)$r['is_paid'] === 1;
                                $isNotified = (int)$r['is_notified_paid'] === 1;
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
                                <td class="py-3.5 px-4 text-right text-slate-400 text-[11px]">
                                    <?= date('H:i d/m', strtotime($r['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
    function copyPublicLink(url) {
        navigator.clipboard?.writeText(url);
        alert('Đã sao chép link đặt hàng công khai:\n' + url);
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
