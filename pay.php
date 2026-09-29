<?php

spl_autoload_register(function ($class) {
    $prefix = 'SimpleFinance\\';
    $baseDir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use SimpleFinance\DebtManager;
use SimpleFinance\BankList;

$debtManager = new DebtManager();
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));

// Xử lý xác nhận đã chuyển khoản
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'notify') {
    $res = $debtManager->notifyPayment($token);
    if (!empty($_POST['is_ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $res]);
        exit;
    }
    header("Location: pay.php?token=" . urlencode($token) . "&notified=1");
    exit;
}

$debt = null;
if (!empty($token)) {
    $debt = $debtManager->getDebtByToken($token);
}

// Hàm bỏ dấu tiếng Việt cho nội dung chuyển tiền ngân hàng
function removeVietnameseAccents(string $str): string {
    $unicode = [
        'a'=>'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
        'd'=>'đ',
        'e'=>'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
        'i'=>'í|ì|ỉ|ĩ|ị',
        'o'=>'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
        'u'=>'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
        'y'=>'ý|ỳ|ỷ|ỹ|ỵ',
        'A'=>'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ặ|Ằ|Ẳ|Ẵ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
        'D'=>'Đ',
        'E'=>'É|È|Ả|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
        'I'=>'Í|Ì|Ỉ|Ĩ|Ị',
        'O'=>'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
        'U'=>'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
        'Y'=>'Ý|Ỳ|Ỷ|Ỹ|Ỵ',
    ];
    foreach ($unicode as $nonAccent => $accent) {
        $str = preg_replace("/($accent)/i", $nonAccent, $str);
    }
    return preg_replace('/[^a-zA-Z0-9 ]/', '', $str);
}

$pageTitle = "Thanh Toán Công Nợ - SimpleFinance";
?>
<!DOCTYPE html>
<html lang="vi" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="h-full flex flex-col justify-between py-6 px-4 sm:px-6">

<div class="max-w-md w-full mx-auto space-y-5">
    <!-- Header Logo -->
    <div class="text-center space-y-1">
        <div class="inline-flex items-center space-x-2 text-emerald-700 font-black text-xl">
            <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-md shadow-emerald-600/30 text-sm">
                SF
            </div>
            <span>SimpleFinance</span>
        </div>
        <p class="text-xs text-slate-500 font-medium">Cổng Thanh Toán & Chia Tiền Nhóm Thông Minh</p>
    </div>

    <?php if (!$debt): ?>
        <!-- Khi không tìm thấy khoản nợ -->
        <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-xl text-center space-y-4">
            <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h2 class="text-lg font-bold text-slate-900">Khoản Nợ Đã Được Thanh Toán Hoặc Không Tồn Tại</h2>
            <p class="text-xs text-slate-500 leading-relaxed">
                Đường dẫn thanh toán này không tìm thấy khoản nợ còn tồn đọng. Rất có thể hai bên đã sòng phẳng xong xuôi!
            </p>
            <div class="pt-2">
                <a href="login.php" class="inline-flex items-center px-4 py-2 text-xs font-bold text-emerald-700 bg-emerald-50 rounded-xl hover:bg-emerald-100 transition">
                    Đăng nhập SimpleFinance &rarr;
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php
            $debtorName = $debt['debtor_name'];
            $creditorName = $debt['creditor_name'];
            $amount = (float)$debt['amount'];
            $bankBin = $debt['bank_bin'] ?? '';
            $bankName = $debt['bank_name'] ?? 'Chưa cập nhật';
            $accountNo = $debt['bank_account_no'] ?? '';
            $accountName = $debt['bank_account_name'] ?? $creditorName;

            // Nội dung chuyển khoản chuẩn không dấu
            $rawContent = "{$debtorName} tra no {$creditorName} SF";
            $transferContent = trim(removeVietnameseAccents($rawContent));

            $hasBank = !empty($bankBin) && !empty($accountNo);
            $qrUrl = $hasBank ? BankList::generateVietQrUrl($bankBin, $accountNo, $amount, $transferContent, $accountName, 'compact2') : null;
        ?>

        <!-- Thẻ thanh toán chính -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-xl overflow-hidden">
            <!-- Header thẻ -->
            <div class="bg-gradient-to-r from-emerald-600 to-teal-700 p-6 text-white text-center relative">
                <span class="inline-block text-[11px] font-bold uppercase tracking-wider px-3 py-1 rounded-full bg-white/20 backdrop-blur-sm mb-2">
                    Yêu Cầu Thanh Toán Nợ
                </span>
                
                <!-- Người trả -> Người nhận -->
                <div class="flex items-center justify-center space-x-3 mt-1">
                    <div class="text-right">
                        <p class="text-[11px] text-emerald-100">Người nợ</p>
                        <p class="text-base font-bold text-white"><?= htmlspecialchars($debtorName) ?></p>
                    </div>
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                    </div>
                    <div class="text-left">
                        <p class="text-[11px] text-emerald-100">Người nhận (Chủ nợ)</p>
                        <p class="text-base font-bold text-white"><?= htmlspecialchars($creditorName) ?></p>
                    </div>
                </div>

                <!-- Số tiền nợ lớn -->
                <div class="mt-4 pt-3 border-t border-white/15">
                    <p class="text-xs text-emerald-100 uppercase tracking-wider font-semibold">Số tiền cần thanh toán</p>
                    <p class="text-3xl font-extrabold tracking-tight mt-0.5">
                        <?= number_format($amount) ?> <span class="text-xl font-normal text-emerald-200">đ</span>
                    </p>
                </div>
            </div>

            <div class="p-6 space-y-5">
                <?php if ($hasBank && $qrUrl): ?>
                    <!-- Khung Mã QR VietQR -->
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 text-center space-y-3">
                        <p class="text-xs font-bold text-slate-700 flex items-center justify-center">
                            <svg class="w-4 h-4 mr-1 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                            </svg>
                            Quét Mã QR Bằng Bất Kỳ Ứng Dụng Ngân Hàng
                        </p>
                        
                        <div class="inline-block bg-white p-3 rounded-2xl shadow-sm border border-slate-200">
                            <img src="<?= htmlspecialchars($qrUrl) ?>" alt="Mã VietQR Thanh Toán" class="w-64 h-auto mx-auto rounded-lg">
                        </div>

                        <div class="flex justify-center">
                            <a href="<?= htmlspecialchars($qrUrl) ?>" download="vietqr_<?= $debt['id'] ?>.png" target="_blank" 
                               class="inline-flex items-center px-3.5 py-1.5 rounded-xl text-xs font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-2xs transition">
                                <svg class="w-3.5 h-3.5 mr-1 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Tải Ảnh Mã QR Về Máy
                            </a>
                        </div>
                    </div>

                    <!-- Bảng Chi Tiết Tài Khoản -->
                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-slate-500">Ngân hàng:</span>
                            <span class="font-bold text-slate-900"><?= htmlspecialchars($bankName) ?></span>
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-slate-500">Số tài khoản:</span>
                            <div class="flex items-center space-x-2">
                                <span class="font-mono font-bold text-emerald-800 text-sm" id="accNumberText"><?= htmlspecialchars($accountNo) ?></span>
                                <button type="button" onclick="copyText('<?= htmlspecialchars($accountNo) ?>', 'Đã chép số tài khoản!')" class="p-1 rounded-md text-emerald-700 hover:bg-emerald-100 transition" title="Sao chép STK">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-slate-500">Chủ tài khoản:</span>
                            <span class="font-bold text-slate-900 uppercase"><?= htmlspecialchars($accountName) ?></span>
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-slate-500">Nội dung CK:</span>
                            <div class="flex items-center space-x-2">
                                <span class="font-mono font-bold text-slate-800 text-xs" id="memoText"><?= htmlspecialchars($transferContent) ?></span>
                                <button type="button" onclick="copyText('<?= htmlspecialchars($transferContent) ?>', 'Đã chép nội dung chuyển khoản!')" class="p-1 rounded-md text-emerald-700 hover:bg-emerald-100 transition" title="Sao chép nội dung">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Khi chủ nợ chưa cài đặt STK -->
                    <div class="p-5 rounded-2xl bg-amber-50 border border-amber-200 text-center space-y-2">
                        <svg class="w-8 h-8 text-amber-600 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <h4 class="text-sm font-bold text-amber-900">Chủ Nợ Chưa Cài Đặt STK Ngân Hàng</h4>
                        <p class="text-xs text-amber-800 leading-relaxed">
                            <strong><?= htmlspecialchars($creditorName) ?></strong> chưa cập nhật thông tin tài khoản ngân hàng trong hệ thống. Vui lòng liên hệ trực tiếp để lấy số tài khoản chuyển tiền.
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Trạng thái thông báo chuyển khoản & Nút bấm -->
                <div class="pt-2 border-t border-slate-100">
                    <?php if (!empty($debt['payment_notified_at'])): ?>
                        <div id="notifiedStatusBox" class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center space-x-2">
                            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <span class="font-bold">Đã gửi thông báo cho chủ nợ!</span>
                                <p class="text-[11px] text-emerald-700 mt-0.5">Xác nhận lúc <?= htmlspecialchars(substr($debt['payment_notified_at'], 0, 16)) ?>. Chủ nợ sẽ kiểm tra và gạch nợ.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div id="notifyActionSection">
                            <form id="notifyPaymentForm" onsubmit="handleNotifySubmit(event)">
                                <button type="submit" id="btnNotifyPayment" class="w-full py-3 px-4 rounded-xl text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/25 transition flex items-center justify-center space-x-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>Tôi Đã Chuyển Khoản Xong</span>
                                </button>
                            </form>
                            <p class="text-[11px] text-center text-slate-400 mt-2">
                                Sau khi chuyển tiền xong, hãy bấm nút này để thông báo cho <?= htmlspecialchars($creditorName) ?> kiểm tra và gạch nợ.
                            </p>
                        </div>

                        <div id="notifiedStatusBox" class="hidden p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center space-x-2">
                            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <span class="font-bold">Đã gửi thông báo cho chủ nợ thành công!</span>
                                <p class="text-[11px] text-emerald-700 mt-0.5"><?= htmlspecialchars($creditorName) ?> sẽ kiểm tra biến động số dư và tiến hành gạch nợ cho bạn.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Footer Copyright -->
    <div class="text-center text-[11px] text-slate-400">
        &copy; <?= date('Y') ?> SimpleFinance &bull; Quản lý công nợ & chi tiêu nhóm thông minh
    </div>
</div>

<!-- Toast Copy Thông Báo -->
<div id="copyToast" class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-slate-900 text-white px-4 py-2 rounded-xl text-xs font-semibold shadow-2xl opacity-0 pointer-events-none transition-opacity duration-200 flex items-center space-x-1.5">
    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
    </svg>
    <span id="copyToastText">Đã sao chép!</span>
</div>

<script>
function copyText(text, message = 'Đã sao chép vào bộ nhớ tạm!') {
    navigator.clipboard.writeText(text).then(() => {
        showToast(message);
    }).catch(() => {
        const temp = document.createElement('input');
        temp.value = text;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
        showToast(message);
    });
}

function showToast(text) {
    const toast = document.getElementById('copyToast');
    const toastText = document.getElementById('copyToastText');
    toastText.textContent = text;
    toast.classList.remove('opacity-0', 'pointer-events-none');
    toast.classList.add('opacity-100');
    setTimeout(() => {
        toast.classList.remove('opacity-100');
        toast.classList.add('opacity-0', 'pointer-events-none');
    }, 2000);
}

async function handleNotifySubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('btnNotifyPayment');
    const actionSection = document.getElementById('notifyActionSection');
    const statusBox = document.getElementById('notifiedStatusBox');

    btn.disabled = true;
    btn.innerHTML = '<span class="animate-spin mr-1.5">⌛</span> Đang gửi thông báo...';

    try {
        const formData = new FormData();
        formData.append('action', 'notify');
        formData.append('is_ajax', '1');
        formData.append('token', '<?= htmlspecialchars(addslashes($token)) ?>');

        const res = await fetch('pay.php', {
            method: 'POST',
            body: formData,
        });

        const data = await res.json();
        if (data.success) {
            actionSection.classList.add('hidden');
            statusBox.classList.remove('hidden');
            showToast('Đã gửi thông báo cho chủ nợ!');
        } else {
            alert('Không thể cập nhật trạng thái. Vui lòng thử lại.');
            btn.disabled = false;
            btn.innerHTML = 'Tôi Đã Chuyển Khoản Xong';
        }
    } catch (err) {
        // Fallback submit form thông thường
        e.target.submit();
    }
}
</script>

</body>
</html>
