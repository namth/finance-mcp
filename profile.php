<?php

require_once __DIR__ . '/auth_check.php';

use SimpleFinance\Models\User;

$userModel = new User();
$userId = (int)$_SESSION['user_id'];
$user = $userModel->findById($userId);

$flashSuccess = '';
$flashError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'regenerate_key') {
        $newKey = $userModel->regenerateApiKey($userId);
        $user['api_key'] = $newKey;
        $_SESSION['user']['api_key'] = $newKey;
        $flashSuccess = "Đã làm mới API Key thành công! Hãy cập nhật lại cấu hình trong Gemini/Claude.";
    } elseif ($action === 'update_profile') {
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));

        if (!empty($fullName) && !empty($email)) {
            $userModel->updateProfile($userId, $fullName, $email);
            $user['full_name'] = $fullName;
            $user['email'] = $email;
            $_SESSION['user']['full_name'] = $fullName;
            $_SESSION['user']['email'] = $email;
            $flashSuccess = "Đã cập nhật thông tin cá nhân thành công!";
        }
    }
}

$timelineEvents = $userModel->getDecryptedTimelineEvents($userId);

$pageTitle = "Thông Tin Cá Nhân & Kết Nối MCP - SimpleFinance";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto mb-12 space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Tài Khoản & Kết Nối AI (MCP)</h1>
        <p class="text-xs text-slate-500 mt-0.5">Quản lý khóa bí mật kết nối MCP Server cho Gemini và các mốc ký ức</p>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center space-x-2">
            <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span class="font-medium"><?= htmlspecialchars($flashSuccess) ?></span>
        </div>
    <?php endif; ?>

    <!-- Khối 1: Khóa bí mật MCP Server cá nhân (RẤT QUAN TRỌNG) -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
        <div class="flex items-center space-x-3 pb-3 border-b border-slate-100">
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                </svg>
            </div>
            <div>
                <h2 class="text-base font-bold text-slate-900">Khóa Kết Nối MCP Cá Nhân (Personal API Key)</h2>
                <p class="text-xs text-slate-500">Dùng mã này để Gemini/Claude nhận diện đúng tài khoản của bạn và bảo mật tuyệt đối</p>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">API Key của bạn:</label>
            <div class="flex items-center space-x-2">
                <input type="text" readonly id="apiKeyField" value="<?= htmlspecialchars($user['api_key']) ?>" 
                       class="flex-1 px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 font-mono text-xs font-bold text-slate-800 select-all">
                <button type="button" onclick="copyApiKey()" class="px-4 py-2.5 rounded-xl text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition">
                    Sao chép
                </button>
            </div>
        </div>

        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
            <p class="text-xs font-bold text-slate-700">URL kết nối MCP Server cá nhân của bạn:</p>
            <div class="p-2.5 rounded-lg bg-white border border-slate-200 text-xs font-mono text-emerald-800 break-all select-all">
                https://financemcp.oa.io.vn/mcp.php?key=<?= htmlspecialchars($user['api_key']) ?>
            </div>
            <p class="text-[11px] text-slate-500">
                Khi dán URL này vào Gemini hoặc Claude, AI sẽ tự động liên kết với tài khoản <strong><?= htmlspecialchars($user['full_name']) ?></strong> và chỉ truy cập các nhóm của bạn!
            </p>
        </div>

        <div class="pt-1 flex justify-end">
            <form method="POST" action="profile.php" onsubmit="return confirm('Bạn có chắc muốn làm mới API Key? Các ứng dụng AI đang kết nối sẽ cần cập nhật key mới.');">
                <input type="hidden" name="action" value="regenerate_key">
                <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700 hover:underline">
                    Làm mới (Đổi) API Key khác
                </button>
            </form>
        </div>
    </div>

    <!-- Khối 2: Thông tin cá nhân -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
        <h2 class="text-base font-bold text-slate-900 pb-2 border-b border-slate-100">Thông Tin Tài Khoản</h2>
        
        <form method="POST" action="profile.php" class="space-y-4">
            <input type="hidden" name="action" value="update_profile">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Tên Đăng Nhập (Username)</label>
                    <input type="text" readonly value="<?= htmlspecialchars($user['username']) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-100 font-mono text-xs text-slate-500 cursor-not-allowed">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Họ và Tên</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 text-sm">
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition">
                    Cập Nhật Thông Tin
                </button>
            </div>
        </form>
    </div>

    <!-- Khối 3: Danh sách các mốc ký ức bảo mật -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-3">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
            <div>
                <h2 class="text-base font-bold text-slate-900">Mốc Ký Ức Bảo Mật Hiện Tại</h2>
                <p class="text-xs text-slate-500">Các mốc sự kiện được hệ thống dùng để hỏi khi bạn đăng nhập</p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                <?= count($timelineEvents) ?> mốc đã cài đặt
            </span>
        </div>

        <div class="divide-y divide-slate-100">
            <?php foreach ($timelineEvents as $evName => $evDate): ?>
                <div class="py-2.5 flex items-center justify-between text-xs">
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($evName) ?></span>
                    <span class="text-slate-400 font-mono">Đã mã hóa AES-256 (••••••••)</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function copyApiKey() {
    const field = document.getElementById('apiKeyField');
    field.select();
    navigator.clipboard.writeText(field.value);
    alert('Đã sao chép API Key vào bộ nhớ tạm!');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
