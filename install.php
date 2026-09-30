<?php

/**
 * SimpleFinance - Trình Cài Đặt Hệ Thống (Installation Wizard)
 * Chỉ chạy khi cơ sở dữ liệu chưa có bảng. Nếu đã có bảng, tự động chuyển về login.php.
 */

// Đặt timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bật error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'SimpleFinance\\';
    $baseDir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use SimpleFinance\Database;
use SimpleFinance\Models\User;
use SimpleFinance\Models\Member;

$dbError = null;
$pdo = null;

try {
    $pdo = Database::getConnection();
} catch (\Throwable $e) {
    $dbError = $e->getMessage();
}

// 1. Nếu không kết nối được CSDL: Hiển thị hướng dẫn cấu hình config.php
if ($dbError) {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cài Đặt SimpleFinance - Lỗi Kết Nối CSDL</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-50 flex items-center justify-center min-h-screen p-4 font-sans">
        <div class="max-w-md w-full bg-white p-6 sm:p-8 rounded-2xl shadow-xl border border-slate-200 text-center">
            <div class="w-14 h-14 bg-red-100 text-red-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-slate-900">Không Thể Kết Nối MySQL</h1>
            <p class="text-xs text-slate-500 mt-1">Vui lòng kiểm tra lại thông tin database trong file config.php</p>

            <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-xl text-xs font-mono text-red-700 text-left break-all">
                <?= htmlspecialchars($dbError) ?>
            </div>

            <div class="mt-4 p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-600 text-left space-y-1">
                <p class="font-bold text-slate-800">Cần làm:</p>
                <p>&bull; Mở file <code class="font-mono text-emerald-700 bg-emerald-50 px-1 rounded">config.php</code> trên server.</p>
                <p>&bull; Điền đúng <span class="font-mono text-slate-700">username</span>, <span class="font-mono text-slate-700">password</span>, <span class="font-mono text-slate-700">dbname</span>.</p>
            </div>

            <a href="install.php" class="mt-6 block w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition shadow-sm">
                Tải Lại Trang Cài Đặt
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 2. Kiểm tra xem CSDL đã có bảng chưa
// Nếu đã có bảng `users`, out ngay ra trang login.php theo yêu cầu
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $hasUsersTable = (bool)$stmt->fetch();

    if ($hasUsersTable) {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
        if ($count > 0) {
            // Đã cài đặt hoàn chỉnh -> Chuyển về login
            header('Location: login.php');
            exit;
        }
    }
} catch (\Throwable $e) {
    // Nếu có lỗi truy vấn, tiếp tục để cho phép cài đặt
}

$errorMessage = '';
$installedSuccess = false;
$adminUser = null;

// 3. Xử lý khi bấm nút Cài Đặt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? 'admin'));
    $fullName = trim((string)($_POST['full_name'] ?? 'Quản Trị Viên'));
    $email    = trim((string)($_POST['email'] ?? ''));

    $eventNames = $_POST['event_names'] ?? [];
    $eventDates = $_POST['event_dates'] ?? [];

    $timelineEvents = [];
    foreach ($eventNames as $idx => $evName) {
        $name = trim((string)$evName);
        $rawDate = trim((string)($eventDates[$idx] ?? ''));

        if (!empty($name) && !empty($rawDate)) {
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rawDate, $m)) {
                $formattedDate = "{$m[3]}-{$m[2]}-{$m[1]}";
            } else {
                $formattedDate = $rawDate;
            }
            $timelineEvents[$name] = $formattedDate;
        }
    }

    try {
        if (empty($username)) throw new \InvalidArgumentException("Tên đăng nhập không được để trống.");
        if (empty($fullName)) throw new \InvalidArgumentException("Họ tên không được để trống.");
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException("Email không hợp lệ.");
        if (count($timelineEvents) < 3) throw new \InvalidArgumentException("Vui lòng cung cấp ít nhất 3 mốc ký ức bí mật để phục vụ đăng nhập.");

        // Bắt đầu tạo toàn bộ 11 bảng CSDL
        $sqlDdl = "
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `full_name` VARCHAR(100) NOT NULL,
            `api_key` VARCHAR(64) NOT NULL UNIQUE,
            `bank_bin` VARCHAR(20) DEFAULT NULL,
            `bank_name` VARCHAR(100) DEFAULT NULL,
            `bank_account_no` VARCHAR(50) DEFAULT NULL,
            `bank_account_name` VARCHAR(100) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `user_timeline_events` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `event_name` VARCHAR(255) NOT NULL,
            `event_date_encrypted` TEXT NOT NULL COMMENT 'Ngày tháng DD-MM-YYYY đã được mã hóa AES-256-CBC',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `groups` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(150) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `owner_id` INT UNSIGNED NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `group_members` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `group_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `display_name` VARCHAR(100) NOT NULL,
            `role` ENUM('owner', 'admin', 'member') NOT NULL DEFAULT 'member',
            `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_group_user` (`group_id`, `user_id`),
            FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `members` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `name` VARCHAR(100) NOT NULL,
            `phone` VARCHAR(20) DEFAULT NULL,
            `email` VARCHAR(100) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `places` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `group_id` INT UNSIGNED NOT NULL DEFAULT 1,
            `name` VARCHAR(150) NOT NULL,
            `address` VARCHAR(255) DEFAULT NULL,
            `map_url` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (`group_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `products` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `group_id` INT UNSIGNED NOT NULL DEFAULT 1,
            `place_id` INT UNSIGNED DEFAULT NULL,
            `name` VARCHAR(150) NOT NULL,
            `default_price` DECIMAL(15, 2) DEFAULT NULL,
            `description` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (`group_id`),
            INDEX (`place_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `transactions` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `group_id` INT UNSIGNED NOT NULL DEFAULT 1,
            `place_id` INT UNSIGNED DEFAULT NULL,
            `title` VARCHAR(255) NOT NULL,
            `payer_id` INT UNSIGNED NOT NULL,
            `total_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            `status` ENUM('draft', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
            `note` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`group_id`),
            INDEX (`place_id`),
            FOREIGN KEY (`payer_id`) REFERENCES `members`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `transaction_items` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `transaction_id` INT UNSIGNED NOT NULL,
            `product_id` INT UNSIGNED NOT NULL,
            `price` DECIMAL(15, 2) NOT NULL,
            `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
            `subtotal` DECIMAL(15, 2) GENERATED ALWAYS AS (`price` * `quantity`) STORED,
            `note` VARCHAR(255) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `transaction_item_members` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `item_id` INT UNSIGNED NOT NULL,
            `member_id` INT UNSIGNED NOT NULL,
            `share_amount` DECIMAL(15, 2) NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`item_id`) REFERENCES `transaction_items`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `debts` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `group_id` INT UNSIGNED NOT NULL DEFAULT 1,
            `debtor_id` INT UNSIGNED NOT NULL,
            `creditor_id` INT UNSIGNED NOT NULL,
            `amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            `debt_token` VARCHAR(64) DEFAULT NULL,
            `payment_notified_at` DATETIME DEFAULT NULL,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`group_id`),
            INDEX (`debt_token`),
            UNIQUE KEY `uk_group_debtor_creditor` (`group_id`, `debtor_id`, `creditor_id`),
            FOREIGN KEY (`debtor_id`) REFERENCES `members`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`creditor_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `debt_settlements` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `group_id` INT UNSIGNED NOT NULL DEFAULT 1,
            `debtor_id` INT UNSIGNED NOT NULL,
            `creditor_id` INT UNSIGNED NOT NULL,
            `amount` DECIMAL(15, 2) NOT NULL,
            `note` VARCHAR(255) DEFAULT NULL,
            `settled_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (`group_id`),
            FOREIGN KEY (`debtor_id`) REFERENCES `members`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`creditor_id`) REFERENCES `members`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $pdo->exec($sqlDdl);

        // Tạo tài khoản Admin đầu tiên
        $userModel = new User($pdo);
        $adminUser = $userModel->create($username, $email, $fullName, $timelineEvents);

        // Tạo member tương ứng trong bảng members
        $memberModel = new Member($pdo);
        $memberModel->create($fullName, null, $email);

        $installedSuccess = true;
    } catch (\Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài Đặt Hệ Thống - SimpleFinance MCP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="bg-slate-100 flex items-center justify-center min-h-screen p-4 sm:p-6">
    <div class="max-w-xl w-full bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 p-6 sm:p-8 text-white text-center relative">
            <div class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-inner">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight">Khởi Tạo SimpleFinance</h1>
            <p class="text-emerald-100 text-xs mt-1">Cài đặt CSDL tự động & Thiết lập tài khoản Quản trị đầu tiên</p>
        </div>

        <div class="p-6 sm:p-8">
            <?php if ($installedSuccess && $adminUser): ?>
                <!-- THÀNH CÔNG -->
                <div class="text-center py-4 space-y-4">
                    <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto shadow-sm">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>

                    <div>
                        <h2 class="text-xl font-bold text-slate-900">Cài Đặt Thành Công Rực Rỡ!</h2>
                        <p class="text-xs text-slate-500 mt-1">Toàn bộ 11 bảng CSDL đã được tạo và tài khoản Admin đã sẵn sàng.</p>
                    </div>

                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl text-left space-y-2 text-xs">
                        <div class="flex justify-between py-1 border-b border-slate-200">
                            <span class="text-slate-500">Tên đăng nhập:</span>
                            <span class="font-bold text-slate-900 font-mono"><?= htmlspecialchars($adminUser['username']) ?></span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-200">
                            <span class="text-slate-500">Họ và tên:</span>
                            <span class="font-bold text-slate-900"><?= htmlspecialchars($adminUser['full_name']) ?></span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-200">
                            <span class="text-slate-500">Email:</span>
                            <span class="font-semibold text-slate-900"><?= htmlspecialchars($adminUser['email']) ?></span>
                        </div>
                        <div class="pt-2">
                            <span class="text-slate-500 block mb-1">Personal API Key (Dùng kết nối MCP):</span>
                            <code class="block p-2.5 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-xl font-mono text-[11px] break-all select-all">
                                <?= htmlspecialchars($adminUser['api_key']) ?>
                            </code>
                        </div>
                    </div>

                    <a href="login.php" class="block w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-2xl text-sm transition shadow-lg shadow-emerald-600/25">
                        Đến Trang Đăng Nhập &rarr;
                    </a>
                </div>

            <?php else: ?>
                <!-- FORM CÀI ĐẶT -->
                <?php if ($errorMessage): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 text-xs rounded-2xl flex items-start gap-2">
                        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span><?= htmlspecialchars($errorMessage) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <!-- Thông tin cơ bản -->
                    <div class="space-y-3">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">1. Thông tin tài khoản Admin</h2>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Tên đăng nhập (Username) *</label>
                                <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="Ví dụ: admin" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono focus:ring-2 focus:ring-emerald-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Họ và tên *</label>
                                <input type="text" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" placeholder="Ví dụ: Quản Trị Viên" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Địa chỉ Email *</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="admin@domain.com" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                    </div>

                    <!-- Mốc Ký Ức Đăng Nhập -->
                    <div class="space-y-3 pt-4 border-t border-slate-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">2. Mốc Ký Ức Thời Gian Bí Mật</h2>
                                <p class="text-[11px] text-slate-500">Dùng để giải đố số trên Numpad khi đăng nhập. Dữ liệu sẽ được mã hóa AES-256-CBC an toàn.</p>
                            </div>
                        </div>

                        <div id="events-container" class="space-y-2.5">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                                <input type="text" name="event_names[]" value="" placeholder="Mốc ký ức 1 (Ví dụ: Kỷ niệm ngày cưới)" required class="px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none">
                                <input type="date" name="event_dates[]" value="" required class="px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none font-mono">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                                <input type="text" name="event_names[]" value="" placeholder="Mốc ký ức 2 (Ví dụ: Ngày tốt nghiệp)" required class="px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none">
                                <input type="date" name="event_dates[]" value="" required class="px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none font-mono">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                                <input type="text" name="event_names[]" value="" placeholder="Mốc ký ức 3 (Ví dụ: Ngày mua xe đầu tiên)" required class="px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none">
                                <input type="date" name="event_dates[]" value="" required class="px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none font-mono">
                            </div>
                        </div>

                        <button type="button" onclick="addEventRow()" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-1 mt-1">
                            + Thêm mốc ký ức khác
                        </button>
                    </div>

                    <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold rounded-2xl text-sm transition shadow-lg shadow-emerald-600/25">
                        Tạo Bảng CSDL & Kích Hoạt Hệ Thống &rarr;
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function addEventRow() {
            const container = document.getElementById('events-container');
            const div = document.createElement('div');
            div.className = 'grid grid-cols-1 sm:grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200';
            div.innerHTML = `
                <input type="text" name="event_names[]" placeholder="Tên mốc sự kiện mới..." required class="px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none">
                <input type="date" name="event_dates[]" required class="px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none font-mono">
            `;
            container.appendChild(div);
        }
    </script>
</body>
</html>
