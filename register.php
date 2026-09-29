<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nếu đã đăng nhập thì chuyển vào Dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

spl_autoload_register(function ($class) {
    $prefix = 'SimpleFinance\\';
    $baseDir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use SimpleFinance\Models\User;

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim((string)($_POST['username'] ?? ''));
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));

        $eventNames = $_POST['event_names'] ?? [];
        $eventDates = $_POST['event_dates'] ?? [];

        $timelineEvents = [];
        foreach ($eventNames as $idx => $evName) {
            $name = trim((string)$evName);
            $rawDate = trim((string)($eventDates[$idx] ?? ''));

            if (!empty($name) && !empty($rawDate)) {
                // Nếu datepicker trả về YYYY-MM-DD thì đổi sang DD-MM-YYYY
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rawDate, $m)) {
                    $formattedDate = "{$m[3]}-{$m[2]}-{$m[1]}";
                } else {
                    $formattedDate = $rawDate;
                }
                $timelineEvents[$name] = $formattedDate;
            }
        }

        if (count($timelineEvents) < 3) {
            throw new \InvalidArgumentException("Vui lòng thiết lập ít nhất 3 mốc sự kiện bí mật để dùng khi đăng nhập.");
        }

        $userModel = new User();
        $user = $userModel->create($username, $email, $fullName, $timelineEvents);

        // Tự động đăng nhập
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = $user;
        $_SESSION['flash_message'] = "Đăng ký tài khoản thành công! Chào mừng bạn đến với SimpleFinance.";

        header('Location: index.php');
        exit;
    } catch (\Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký Tài Khoản - SimpleFinance</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-full py-10 px-4 flex flex-col justify-center items-center bg-gradient-to-br from-slate-100 via-emerald-50/40 to-slate-200">

    <div class="w-full max-w-lg">
        <!-- Logo -->
        <div class="text-center mb-6">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-lg shadow-emerald-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">Đăng Ký Tài Khoản Mới</h1>
            <p class="text-xs text-slate-500 mt-1">Hệ thống bảo mật xác thực bằng Ký Ức Thời Gian (Proof of Memory)</p>
        </div>

        <div class="bg-white p-7 sm:p-8 rounded-3xl shadow-xl shadow-slate-200/70 border border-slate-200/80">
            
            <?php if (!empty($errorMessage)): ?>
                <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center space-x-2">
                    <svg class="h-4 w-4 text-rose-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
                    </svg>
                    <span><?= htmlspecialchars($errorMessage) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" class="space-y-6">
                <!-- Phần 1: Thông tin tài khoản -->
                <div class="space-y-4">
                    <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider pb-1 border-b border-slate-100">1. Thông tin cá nhân</h2>
                    
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Họ và Tên *</label>
                        <input type="text" name="full_name" required placeholder="Nguyễn Văn A" 
                               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm font-medium">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Tên Đăng Nhập (Username) *</label>
                            <input type="text" name="username" required placeholder="nguyenvana" pattern="[a-z0-9_\.]{3,30}"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm font-mono lowercase">
                            <span class="text-[10px] text-slate-400">Dùng để đăng nhập (chữ thường, số)</span>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Email *</label>
                            <input type="email" name="email" required placeholder="a@gmail.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm font-medium">
                        </div>
                    </div>
                </div>

                <!-- Phần 2: Thiết lập Ký Ức Bí Mật -->
                <div class="space-y-4 pt-2">
                    <div class="flex items-center justify-between pb-1 border-b border-slate-100">
                        <div>
                            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider">2. Thiết lập Mốc Ký Ức (Thay cho Mật khẩu)</h2>
                            <p class="text-[11px] text-slate-500 mt-0.5">Tất cả ngày tháng sẽ được mã hóa AES-256 an toàn tuyệt đối</p>
                        </div>
                        <button type="button" onclick="addEventRow()" class="text-xs font-bold text-emerald-700 hover:text-emerald-800 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                            + Thêm Mốc
                        </button>
                    </div>

                    <div id="eventsContainer" class="space-y-3">
                        <!-- Mốc 1 -->
                        <div class="flex items-center space-x-2">
                            <input type="text" name="event_names[]" value="sinh nhật của tôi" required placeholder="Tên sự kiện" class="flex-1 px-3 py-2 rounded-xl border border-slate-300 text-xs font-medium">
                            <input type="date" name="event_dates[]" required class="w-36 px-2.5 py-2 rounded-xl border border-slate-300 text-xs font-mono">
                        </div>
                        <!-- Mốc 2 -->
                        <div class="flex items-center space-x-2">
                            <input type="text" name="event_names[]" value="sinh nhật của mẹ tôi" required placeholder="Tên sự kiện" class="flex-1 px-3 py-2 rounded-xl border border-slate-300 text-xs font-medium">
                            <input type="date" name="event_dates[]" required class="w-36 px-2.5 py-2 rounded-xl border border-slate-300 text-xs font-mono">
                        </div>
                        <!-- Mốc 3 -->
                        <div class="flex items-center space-x-2">
                            <input type="text" name="event_names[]" value="kỷ niệm ngày cưới / ngày yêu" required placeholder="Tên sự kiện" class="flex-1 px-3 py-2 rounded-xl border border-slate-300 text-xs font-medium">
                            <input type="date" name="event_dates[]" required class="w-36 px-2.5 py-2 rounded-xl border border-slate-300 text-xs font-mono">
                        </div>
                        <!-- Mốc 4 -->
                        <div class="flex items-center space-x-2">
                            <input type="text" name="event_names[]" value="ngày tốt nghiệp / đi làm" placeholder="Tên sự kiện (tùy chọn)" class="flex-1 px-3 py-2 rounded-xl border border-slate-300 text-xs font-medium">
                            <input type="date" name="event_dates[]" class="w-36 px-2.5 py-2 rounded-xl border border-slate-300 text-xs font-mono">
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3 px-4 rounded-xl text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-600/20 transition">
                        Đăng Ký & Tạo Nhóm Mặc Định
                    </button>
                </div>
            </form>

            <div class="mt-6 text-center text-xs text-slate-500">
                Đã có tài khoản? 
                <a href="login.php" class="font-bold text-emerald-700 hover:underline">
                    Đăng nhập ngay
                </a>
            </div>
        </div>
    </div>

    <script>
    function addEventRow() {
        const container = document.getElementById('eventsContainer');
        const row = document.createElement('div');
        row.className = "flex items-center space-x-2";
        row.innerHTML = `
            <input type="text" name="event_names[]" required placeholder="Tên sự kiện bí mật" class="flex-1 px-3 py-2 rounded-xl border border-slate-300 text-xs font-medium">
            <input type="date" name="event_dates[]" required class="w-36 px-2.5 py-2 rounded-xl border border-slate-300 text-xs font-mono">
            <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-rose-600 px-1 text-sm font-bold">&times;</button>
        `;
        container.appendChild(row);
    }
    </script>
</body>
</html>
