<?php

date_default_timezone_set('Asia/Ho_Chi_Minh');

spl_autoload_register(function ($class) {
    $prefix = 'SimpleFinance\\';
    $baseDir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use SimpleFinance\Models\GroupBuy;

$token = trim((string)($_GET['token'] ?? ''));
$groupBuyModel = new GroupBuy();
$event = null;
$error = null;

if (!empty($token)) {
    $event = $groupBuyModel->getEventByToken($token);
    if (!$event) {
        $error = "Sự kiện mua chung không tồn tại hoặc liên kết đã bị xóa.";
    }
} else {
    $error = "Thiếu mã xác thực sự kiện (token). Vui lòng kiểm tra lại đường dẫn!";
}

$isExpired = ($event && !empty($event['deadline'])) ? (strtotime($event['deadline']) <= time()) : false;
$isClosed = ($event && ($event['status'] !== 'open' || $isExpired));

$registrations = ($event && !empty($event['id'])) ? $groupBuyModel->getRegistrations((int)$event['id']) : [];
$pageTitle = $event ? htmlspecialchars($event['title']) . " - Gom Mua Chung" : "Sự Kiện Mua Chung - SimpleFinance";
?>
<!DOCTYPE html>
<html lang="vi" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Toàn bộ input, textarea, select luôn có nền trắng 100% */
        input, textarea, select {
            background-color: #ffffff !important;
        }
        @media screen and (max-width: 768px) {
            input[type="text"], input[type="number"], select, textarea {
                font-size: 16px !important;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased p-3 sm:p-6 pb-24">

    <div class="max-w-2xl mx-auto space-y-6">

        <!-- Header Brand -->
        <div class="flex items-center justify-between pb-2">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 rounded-xl bg-emerald-600 flex items-center justify-center text-white shadow-md shadow-emerald-600/20 font-black text-sm">
                    SF
                </div>
                <span class="font-bold text-slate-800 text-sm tracking-tight">SimpleFinance • Gom Mua Chung</span>
            </div>
            <?php if ($event): ?>
                <?php if ($event['status'] === 'converted'): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200">
                        <i class="fa-solid fa-receipt text-[11px]"></i> Đã chốt hóa đơn
                    </span>
                <?php elseif ($isClosed): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-300">
                        <i class="fa-solid fa-lock text-[11px]"></i> Đã đóng đơn <?= !empty($event['deadline']) ? '(' . date('H:i d/m', strtotime($event['deadline'])) . ')' : '' ?>
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Đang nhận đơn
                    </span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <!-- Error Card -->
            <div class="bg-white rounded-3xl border border-red-200 p-8 text-center shadow-sm">
                <div class="w-14 h-14 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-3 text-2xl">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <h3 class="text-base font-bold text-slate-900">Không tìm thấy sự kiện</h3>
                <p class="text-xs text-red-600 mt-1 max-w-sm mx-auto"><?= htmlspecialchars($error) ?></p>
            </div>
        <?php else: ?>

            <!-- Event Card Có Ảnh Sản Phẩm -->
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
                <?php if (!empty($event['image_url'])): ?>
                    <div class="relative h-56 sm:h-72 w-full bg-slate-900 overflow-hidden">
                        <img src="<?= htmlspecialchars($event['image_url']) ?>" alt="Ảnh sản phẩm" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                        <div class="absolute bottom-4 left-5 right-5 text-white">
                            <span class="inline-block bg-emerald-600 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded mb-1">
                                Sự kiện mua chung
                            </span>
                            <h1 class="text-xl sm:text-2xl font-black drop-shadow"><?= htmlspecialchars($event['title']) ?></h1>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-gradient-to-r from-emerald-600 to-teal-700 p-6 text-white">
                        <span class="inline-block bg-white/20 text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full mb-2">
                            Sự kiện mua chung
                        </span>
                        <h1 class="text-xl sm:text-2xl font-black"><?= htmlspecialchars($event['title']) ?></h1>
                    </div>
                <?php endif; ?>

                <div class="p-5 sm:p-6 space-y-4">
                    <?php if (!empty($event['description'])): ?>
                        <div class="text-xs sm:text-sm text-slate-600 leading-relaxed whitespace-pre-line bg-slate-50 p-4 rounded-2xl border border-slate-100">
                            <?= htmlspecialchars($event['description']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs pt-1">
                        <div class="p-3 bg-white rounded-2xl border border-slate-200 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[11px]">Người phụ trách:</span>
                                <strong class="text-slate-800"><?= htmlspecialchars($event['creator_name'] ?? 'Admin') ?></strong>
                            </div>
                        </div>

                        <?php if (!empty($event['deadline'])): ?>
                            <div class="p-3 bg-white rounded-2xl border border-slate-200 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                                    <i class="fa-regular fa-clock"></i>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[11px]">Hạn chót đăng ký:</span>
                                    <strong class="text-slate-800"><?= date('H:i - d/m/Y', strtotime($event['deadline'])) ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Bộ Sưu Tập Ảnh Sản Phẩm & Bảng Size Chi Tiết -->
                    <?php
                        $shirtImage = 'uploads/mau_ao_dong_phuc_bach_khoa.jpg';
                        $sizeChartImage = 'uploads/bang_size_ao_cotton.jpg';
                    ?>
                    <div class="space-y-2 pt-2 border-t border-slate-100">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-800 flex items-center gap-1.5">
                                <i class="fa-regular fa-images text-emerald-600"></i>
                                Hình ảnh mẫu áo & Bảng kích thước:
                            </span>
                            <span class="text-[11px] text-slate-400">Bấm ảnh để phóng to</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <!-- Ảnh Mẫu Áo Thực Tế -->
                            <div onclick="openLightbox('<?= htmlspecialchars($shirtImage) ?>', 'Mẫu Áo Đồng Phục CĐ Tin 3,4 Bách Khoa')" class="group relative rounded-2xl overflow-hidden border border-slate-200 bg-slate-100 cursor-pointer aspect-3/4 sm:aspect-4/3 shadow-2xs hover:shadow-md transition">
                                <img src="<?= htmlspecialchars($shirtImage) ?>" alt="Mẫu Áo Thực Tế" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent flex items-end p-2.5 sm:p-3">
                                    <span class="text-white text-[11px] sm:text-xs font-bold flex items-center gap-1">
                                        <i class="fa-solid fa-shirt"></i> Mẫu áo thực tế
                                    </span>
                                </div>
                                <div class="absolute top-2 right-2 w-7 h-7 bg-black/40 rounded-full flex items-center justify-center text-white text-xs opacity-0 group-hover:opacity-100 transition backdrop-blur-xs">
                                    <i class="fa-solid fa-magnifying-glass-plus"></i>
                                </div>
                            </div>

                            <!-- Ảnh Bảng Size Chi Tiết -->
                            <div onclick="openLightbox('<?= htmlspecialchars($sizeChartImage) ?>', 'Bảng Thông Số Size Áo Cotton 250GSM')" class="group relative rounded-2xl overflow-hidden border border-slate-200 bg-slate-100 cursor-pointer aspect-3/4 sm:aspect-4/3 shadow-2xs hover:shadow-md transition">
                                <img src="<?= htmlspecialchars($sizeChartImage) ?>" alt="Bảng Size Áo" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent flex items-end p-2.5 sm:p-3">
                                    <span class="text-white text-[11px] sm:text-xs font-bold flex items-center gap-1">
                                        <i class="fa-solid fa-ruler-combined"></i> Bảng thông số size
                                    </span>
                                </div>
                                <div class="absolute top-2 right-2 w-7 h-7 bg-black/40 rounded-full flex items-center justify-center text-white text-xs opacity-0 group-hover:opacity-100 transition backdrop-blur-xs">
                                    <i class="fa-solid fa-magnifying-glass-plus"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($isClosed): ?>
                <!-- Thông Báo Đã Hết Hạn / Khóa Đơn -->
                <div class="bg-amber-50 border border-amber-200 rounded-3xl p-6 text-center space-y-2 shadow-2xs">
                    <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto text-xl">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-900">Sự kiện đã kết thúc nhận đăng ký</h3>
                    <p class="text-xs text-slate-600 max-w-md mx-auto">
                        <?= $isExpired ? "Thời gian nhận đơn đã hết hạn lúc <strong class='text-slate-900'>" . date('H:i - d/m/Y', strtotime($event['deadline'])) . "</strong>." : "Admin đã khóa đơn sự kiện để tiến hành chốt danh sách gom hàng." ?>
                    </p>
                    <p class="text-[11px] text-slate-400">Bạn vẫn có thể theo dõi danh sách các thành viên đã đăng ký ở bên dưới.</p>
                </div>
            <?php else: ?>
                <!-- Form Đăng Ký (Các ô input nền trắng 100%) -->
                <form id="public-order-form" onsubmit="submitGroupBuyOrder(event)" class="bg-white rounded-3xl border border-slate-200 p-5 sm:p-6 shadow-sm space-y-6">
                    <input type="hidden" id="event-token" value="<?= htmlspecialchars($event['public_token']) ?>">

                <!-- 1. Thông tin cá nhân -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-black">1</span>
                        Thông tin người đặt mua
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Họ và tên <span class="text-red-500">*</span></label>
                            <input type="text" id="order-name" required placeholder="Ví dụ: Nguyễn Văn A" class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none transition shadow-2xs">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Số điện thoại / Nickname Zalo (tùy chọn)</label>
                            <input type="text" id="order-phone" placeholder="Ví dụ: 0912345678 hoặc Nam Trần" class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none transition shadow-2xs">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Ghi chú (tùy chọn)</label>
                        <input type="text" id="order-note" placeholder="Ví dụ: In thêm tên, lấy form rộng rãi..." class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none transition shadow-2xs">
                    </div>
                </div>

                <hr class="border-slate-100">

                <!-- 2. Danh mục món / Size -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-black">2</span>
                            Chọn kích cỡ & số lượng
                        </h3>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="openLightbox('<?= htmlspecialchars($sizeChartImage) ?>', 'Bảng Thông Số Size')" class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold rounded-xl border border-emerald-200 transition">
                                <i class="fa-solid fa-ruler-combined"></i> Bảng Size
                            </button>
                            <span class="text-[11px] text-slate-400">Chọn ít nhất 1 áo</span>
                        </div>
                    </div>

                    <?php
                        $adultItems = [];
                        $kidItems = [];

                        if (!empty($event['items'])) {
                            foreach ($event['items'] as $item) {
                                // Kiểm tra xem có phải size trẻ em không
                                $opt = $item['option_name'];
                                $isKid = str_contains($item['name'], 'Trẻ Em') || preg_match('/Size\s*(100|110|120|130|140|150)\b/i', $opt);
                                if ($isKid) {
                                    $kidItems[] = $item;
                                } else {
                                    $adultItems[] = $item;
                                }
                            }
                        }
                    ?>

                    <!-- Danh sách Size Người Lớn -->
                    <div class="space-y-2.5">
                        <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-1">Size Người Lớn (Nam & Nữ)</div>
                        <?php foreach ($adultItems as $item): ?>
                            <?php
                                // Tách tên size (ví dụ: Size XS) và thông số (ví dụ: 40-50kg | 1m50-1m60)
                                $rawOpt = $item['option_name'];
                                $sizeLabel = $rawOpt;
                                $sizeDesc = '';
                                if (preg_match('/^(Size\s*[A-Z0-9]+)\s*\((.*?)\)$/ui', $rawOpt, $m)) {
                                    $sizeLabel = $m[1];
                                    $sizeDesc = $m[2];
                                }
                            ?>
                            <div class="flex items-center justify-between p-3 sm:p-3.5 border border-slate-200 rounded-2xl hover:border-emerald-300 transition bg-white shadow-2xs">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-center font-black text-xs sm:text-sm flex-shrink-0">
                                        <?= htmlspecialchars(str_replace('Size ', '', $sizeLabel)) ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-sm text-slate-900 flex items-center gap-1.5">
                                            <span><?= htmlspecialchars($sizeLabel) ?></span>
                                            <?php if ($sizeDesc): ?>
                                                <span class="text-xs text-slate-500 font-normal">• <?= htmlspecialchars($sizeDesc) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs text-emerald-600 font-bold mt-0.5"><?= number_format($item['price'], 0, ',', '.') ?> ₫</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
                                    <button type="button" onclick="adjustQty(<?= $item['id'] ?>, -1)" class="w-8 h-8 rounded-xl bg-white border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 active:scale-95 transition">-</button>
                                    <input type="number" id="qty-<?= $item['id'] ?>" data-price="<?= $item['price'] ?>" data-id="<?= $item['id'] ?>" value="0" min="0" readonly class="item-qty-input w-9 sm:w-10 text-center font-bold text-sm bg-white border border-slate-200 rounded-xl py-1 focus:outline-none">
                                    <button type="button" onclick="adjustQty(<?= $item['id'] ?>, 1)" class="w-8 h-8 rounded-xl bg-white border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 active:scale-95 transition">+</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Danh sách Size Trẻ Em (Thu gọn / Accordion) -->
                    <?php if (!empty($kidItems)): ?>
                        <div class="pt-2 border-t border-slate-100">
                            <button type="button" onclick="toggleKidsList()" class="w-full flex items-center justify-between p-3.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-2xl text-left transition">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-child-reaching text-amber-500"></i>
                                    <span class="text-xs font-bold text-slate-800">Size Trẻ Em (Bé từ 10kg - 39kg)</span>
                                    <span class="text-[10px] bg-slate-200 text-slate-600 font-semibold px-2 py-0.5 rounded-full"><?= count($kidItems) ?> size</span>
                                </div>
                                <span id="kids-toggle-icon" class="text-xs text-slate-400 font-bold flex items-center gap-1">
                                    <span id="kids-toggle-text">Bấm để chọn</span>
                                    <i class="fa-solid fa-chevron-down transition duration-200"></i>
                                </span>
                            </button>

                            <div id="kids-size-container" class="hidden mt-2.5 space-y-2.5 pl-1 sm:pl-2">
                                <?php foreach ($kidItems as $item): ?>
                                    <?php
                                        $rawOpt = $item['option_name'];
                                        $sizeLabel = $rawOpt;
                                        $sizeDesc = '';
                                        if (preg_match('/^(Size\s*[A-Z0-9]+)\s*\((.*?)\)$/ui', $rawOpt, $m)) {
                                            $sizeLabel = $m[1];
                                            $sizeDesc = $m[2];
                                        }
                                    ?>
                                    <div class="flex items-center justify-between p-3 border border-slate-200 rounded-2xl hover:border-amber-300 transition bg-white shadow-2xs">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 flex items-center justify-center font-black text-xs flex-shrink-0">
                                                <?= htmlspecialchars(str_replace('Size ', '', $sizeLabel)) ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-sm text-slate-900 flex items-center gap-1.5">
                                                    <span><?= htmlspecialchars($sizeLabel) ?></span>
                                                    <?php if ($sizeDesc): ?>
                                                        <span class="text-xs text-slate-500 font-normal">• <?= htmlspecialchars($sizeDesc) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-xs text-amber-600 font-bold mt-0.5"><?= number_format($item['price'], 0, ',', '.') ?> ₫</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
                                            <button type="button" onclick="adjustQty(<?= $item['id'] ?>, -1)" class="w-8 h-8 rounded-xl bg-white border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 active:scale-95 transition">-</button>
                                            <input type="number" id="qty-<?= $item['id'] ?>" data-price="<?= $item['price'] ?>" data-id="<?= $item['id'] ?>" value="0" min="0" readonly class="item-qty-input w-9 sm:w-10 text-center font-bold text-sm bg-white border border-slate-200 rounded-xl py-1 focus:outline-none">
                                            <button type="button" onclick="adjustQty(<?= $item['id'] ?>, 1)" class="w-8 h-8 rounded-xl bg-white border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 active:scale-95 transition">+</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sticky Footer Total & Submit Bar -->
                <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="w-full sm:w-auto">
                        <span class="text-xs text-slate-500 block">Tổng số tiền cần thanh toán:</span>
                        <span id="display-total" class="text-2xl font-black text-emerald-600">0 ₫</span>
                    </div>
                    <button type="submit" id="btn-submit-order" class="w-full sm:w-auto px-6 py-3.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-bold rounded-2xl shadow-lg shadow-emerald-600/30 transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-qrcode"></i> Đặt Hàng & Quét VietQR
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <!-- Danh Sách Người Đã Đăng Ký Mua Chung -->
        <?php if ($event): ?>
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-2xs">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-users text-emerald-600"></i>
                            Danh Sách Đã Đăng Ký (<?= count($registrations) ?>)
                        </h3>
                        <p class="text-[11px] sm:text-xs text-slate-400 mt-0.5">Các thành viên trong nhóm đã gửi đơn đặt hàng</p>
                    </div>
                    <?php
                        $totalPieces = 0;
                        foreach ($registrations as $reg) {
                            if (!empty($reg['items'])) {
                                foreach ($reg['items'] as $it) {
                                    $totalPieces += (int)$it['quantity'];
                                }
                            }
                        }
                    ?>
                    <?php if ($totalPieces > 0): ?>
                        <span class="px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-xs font-bold">
                            Tổng <?= $totalPieces ?> món
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (empty($registrations)): ?>
                    <div class="p-8 text-center text-slate-400 text-xs">
                        <i class="fa-regular fa-clipboard text-2xl block mb-2 opacity-50"></i>
                        Chưa có ai đăng ký. Hãy là người đầu tiên tham gia sự kiện này!
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($registrations as $idx => $r): ?>
                            <?php
                                $isPaid = (int)$r['is_paid'] === 1;
                                $isNotified = (int)$r['is_notified_paid'] === 1;
                                // Che số điện thoại bảo mật (ví dụ: 0912***789)
                                $phoneDisplay = '';
                                if (!empty($r['participant_phone'])) {
                                    $rawP = trim($r['participant_phone']);
                                    if (strlen($rawP) >= 7) {
                                        $phoneDisplay = substr($rawP, 0, 4) . '***' . substr($rawP, -3);
                                    } else {
                                        $phoneDisplay = $rawP;
                                    }
                                }
                            ?>
                            <div class="p-4 sm:p-4.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/50 transition">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center justify-center font-black text-xs flex-shrink-0 mt-0.5">
                                        <?= mb_strtoupper(mb_substr(trim($r['participant_name']), 0, 1, 'UTF-8'), 'UTF-8') ?>
                                    </div>
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($r['participant_name']) ?></span>
                                            <?php if ($phoneDisplay): ?>
                                                <span class="text-[11px] text-slate-400 font-mono">(<?= htmlspecialchars($phoneDisplay) ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Danh sách size/món đã chọn -->
                                        <div class="flex flex-wrap gap-1.5 pt-0.5">
                                            <?php if (!empty($r['items'])): ?>
                                                <?php foreach ($r['items'] as $it): ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-slate-100 text-slate-700 text-[11px] font-medium border border-slate-200">
                                                        <strong class="text-emerald-700"><?= $it['quantity'] ?>x</strong> <?= htmlspecialchars($it['option_name']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-600"><?= $r['items_summary'] ?? 'Chi tiết món' ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($r['note'])): ?>
                                            <div class="text-[11px] text-slate-500 italic">
                                                <i class="fa-regular fa-comment-dots text-slate-400"></i> <?= htmlspecialchars($r['note']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="flex sm:flex-col items-center sm:items-end justify-between sm:justify-center border-t sm:border-t-0 pt-2 sm:pt-0 border-slate-100 text-right gap-1 flex-shrink-0">
                                    <div class="font-black text-slate-900 text-sm">
                                        <?= number_format($r['total_amount'], 0, ',', '.') ?> ₫
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-wrap justify-end">
                                        <?php if ((int)($r['is_delivered'] ?? 0) === 1): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                                <i class="fa-solid fa-box-open text-[9px]"></i> Đã nhận áo
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($isPaid): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <i class="fa-solid fa-check text-[9px]"></i> Đã thu tiền
                                            </span>
                                        <?php elseif ($isNotified): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                <i class="fa-solid fa-clock text-[9px]"></i> Đã báo CK
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">
                                                Chờ thanh toán
                                            </span>
                                        <?php endif; ?>
                                        <span class="text-[10px] text-slate-400 ml-1"><?= date('H:i d/m', strtotime($r['created_at'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- POPUP / MODAL VIETQR SAU KHI ĐẶT HÀNG -->
    <div id="modal-qr" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl text-center space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto text-xl">
                <i class="fa-solid fa-check"></i>
            </div>
            <div>
                <h3 class="text-lg font-black text-slate-900">Đăng ký thành công!</h3>
                <p class="text-xs text-slate-500 mt-1">Quét mã QR chuyển khoản để thủ quỹ đối soát</p>
            </div>

            <!-- Khối VietQR -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 space-y-3 shadow-2xs">
                <div class="w-52 h-52 mx-auto bg-white p-2 rounded-xl border border-slate-200 flex flex-col items-center justify-center relative shadow-xs">
                    <img id="qr-img" src="" alt="VietQR" class="w-full h-full object-contain">
                    <div class="absolute bottom-1 bg-emerald-600 text-white text-[9px] px-2 py-0.5 rounded-full font-bold">VietQR NAPAS 247</div>
                </div>

                <div class="text-left text-xs space-y-1.5 pt-1">
                    <div class="flex justify-between text-slate-500">
                        <span>Chủ tài khoản:</span>
                        <strong id="qr-acc-name" class="text-slate-900 uppercase">---</strong>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Số tài khoản:</span>
                        <strong id="qr-acc-no" class="text-slate-900 font-mono">---</strong>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Số tiền cần chuyển:</span>
                        <strong id="qr-modal-amount" class="text-emerald-600 font-bold text-sm">---</strong>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Nội dung chuyển:</span>
                        <strong id="qr-memo" class="text-slate-900 font-mono bg-slate-100 px-1 py-0.5 rounded border border-slate-200">---</strong>
                    </div>
                </div>
            </div>

            <!-- Nút báo đã chuyển tiền -->
            <div class="space-y-2 pt-1">
                <input type="hidden" id="current-reg-token" value="">
                <button type="button" id="btn-notify-paid" onclick="notifyTransferDone()" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-2xl shadow-md transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i> Tôi Đã Chuyển Khoản
                </button>
                <button type="button" onclick="closeModalQR()" class="w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-medium rounded-2xl transition">
                    Đóng và xem lại
                </button>
            </div>
        </div>
    </div>

    <!-- POPUP / MODAL LIGHTBOX XEM ẢNH PHÓNG TO -->
    <div id="modal-lightbox" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-3 sm:p-6" onclick="closeLightbox()">
        <div class="relative max-w-2xl w-full flex flex-col items-center" onclick="event.stopPropagation()">
            <button type="button" onclick="closeLightbox()" class="absolute -top-10 right-0 text-white/90 hover:text-white p-2 text-2xl transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="bg-white rounded-3xl overflow-hidden shadow-2xl p-3 w-full space-y-2">
                <div class="text-xs sm:text-sm font-bold text-slate-800 px-2 py-1 text-center truncate" id="lightbox-title">
                    Hình ảnh chi tiết
                </div>
                <div class="max-h-[75vh] overflow-auto rounded-2xl flex items-center justify-center bg-slate-950/5 p-1 border border-slate-100">
                    <img id="lightbox-img" src="" alt="Xem ảnh phóng to" class="max-h-[72vh] w-auto max-w-full object-contain rounded-xl shadow-xs">
                </div>
                <div class="text-center text-[11px] text-slate-400 py-1">
                    Bấm ra ngoài hoặc nút ✕ để đóng
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-6 right-6 bg-slate-900 text-white text-xs px-4 py-3 rounded-2xl shadow-xl hidden transition-all duration-300 flex items-center gap-2 z-50">
        <i class="fa-solid fa-circle-check text-emerald-400"></i>
        <span id="toast-msg">Thông báo thành công</span>
    </div>

    <script>
        function openLightbox(url, title) {
            const img = document.getElementById('lightbox-img');
            const titleEl = document.getElementById('lightbox-title');
            img.src = url;
            titleEl.innerText = title || 'Xem ảnh lớn';
            document.getElementById('modal-lightbox').classList.remove('hidden');
        }

        function closeLightbox() {
            document.getElementById('modal-lightbox').classList.add('hidden');
        }

        function formatVND(amount) {
            return new Intl.NumberFormat('vi-VN').format(amount) + ' ₫';
        }

        function calculateTotal() {
            let total = 0;
            const inputs = document.querySelectorAll('.item-qty-input');
            inputs.forEach(input => {
                const qty = parseInt(input.value || '0', 10);
                const price = parseFloat(input.getAttribute('data-price') || '0');
                total += qty * price;
            });
            document.getElementById('display-total').innerText = formatVND(total);
            return total;
        }

        function toggleKidsList() {
            const container = document.getElementById('kids-size-container');
            const toggleText = document.getElementById('kids-toggle-text');
            const icon = document.querySelector('#kids-toggle-icon i');
            if (container.classList.contains('hidden')) {
                container.classList.remove('hidden');
                toggleText.innerText = 'Thu gọn';
                icon.className = 'fa-solid fa-chevron-up transition duration-200';
            } else {
                container.classList.add('hidden');
                toggleText.innerText = 'Bấm để chọn';
                icon.className = 'fa-solid fa-chevron-down transition duration-200';
            }
        }

        function adjustQty(itemId, delta) {
            const el = document.getElementById('qty-' + itemId);
            if (!el) return;
            let val = parseInt(el.value || '0', 10) + delta;
            if (val < 0) val = 0;
            el.value = val;
            calculateTotal();
        }

        function submitGroupBuyOrder(e) {
            e.preventDefault();
            const total = calculateTotal();
            if (total <= 0) {
                showToast('Vui lòng chọn ít nhất 1 sản phẩm trước khi gửi đặt hàng!');
                return;
            }

            const token = document.getElementById('event-token').value;
            const name = document.getElementById('order-name').value.trim();
            const phone = document.getElementById('order-phone').value.trim();
            const note = document.getElementById('order-note').value.trim();

            const items = [];
            document.querySelectorAll('.item-qty-input').forEach(input => {
                const qty = parseInt(input.value || '0', 10);
                if (qty > 0) {
                    items.push({
                        item_id: parseInt(input.getAttribute('data-id'), 10),
                        quantity: qty
                    });
                }
            });

            const btn = document.getElementById('btn-submit-order');
            btn.disabled = true;
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...`;

            const formData = new FormData();
            formData.append('action', 'group_buy_submit');
            formData.append('token', token);
            formData.append('name', name);
            formData.append('phone', phone);
            formData.append('note', note);
            formData.append('items', JSON.stringify(items));

            fetch('ajax_action.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = `<i class="fa-solid fa-qrcode"></i> Đặt Hàng & Quét VietQR`;

                if (data.success && data.registration) {
                    const reg = data.registration;
                    document.getElementById('current-reg-token').value = reg.reg_token;
                    document.getElementById('qr-img').src = reg.qr_url;
                    document.getElementById('qr-modal-amount').innerText = formatVND(reg.total_amount);
                    document.getElementById('qr-memo').innerText = reg.qr_memo;
                    document.getElementById('qr-acc-name').innerText = reg.bank_info?.bank_account_name || 'Admin';
                    document.getElementById('qr-acc-no').innerText = reg.bank_info?.bank_account_no || '---';

                    document.getElementById('modal-qr').classList.remove('hidden');
                } else {
                    showToast(data.message || 'Đăng ký thất bại, vui lòng thử lại.');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = `<i class="fa-solid fa-qrcode"></i> Đặt Hàng & Quét VietQR`;
                showToast('Lỗi kết nối máy chủ: ' + err.message);
            });
        }

        function closeModalQR() {
            document.getElementById('modal-qr').classList.add('hidden');
            location.reload();
        }

        function notifyTransferDone() {
            const regToken = document.getElementById('current-reg-token').value;
            if (!regToken) return;

            const btn = document.getElementById('btn-notify-paid');
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Đang gửi thông báo...`;

            const formData = new FormData();
            formData.append('action', 'group_buy_notify_paid');
            formData.append('reg_token', regToken);

            fetch('ajax_action.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.className = "w-full py-3 bg-slate-200 text-slate-600 text-xs font-bold rounded-2xl cursor-default";
                    btn.innerHTML = `<i class="fa-solid fa-circle-check text-emerald-600"></i> Đã thông báo đến người tổ chức`;
                    showToast('Đã ghi nhận thông báo chuyển tiền!');
                } else {
                    btn.innerHTML = `<i class="fa-solid fa-paper-plane"></i> Tôi Đã Chuyển Khoản`;
                    showToast(data.message || 'Không thể thông báo, vui lòng thử lại.');
                }
            })
            .catch(err => {
                btn.innerHTML = `<i class="fa-solid fa-paper-plane"></i> Tôi Đã Chuyển Khoản`;
                showToast('Lỗi gửi thông báo: ' + err.message);
            });
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            document.getElementById('toast-msg').innerText = msg;
            toast.classList.remove('hidden');
            setTimeout(() => {
                toast.classList.add('hidden');
            }, 3500);
        }
    </script>
</body>
</html>
