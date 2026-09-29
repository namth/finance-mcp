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

$memberModel = new Member();
$flashSuccess = '';
$flashError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $name = trim((string)($_POST['name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));

            if (empty($name)) {
                throw new \InvalidArgumentException("Vui lòng nhập tên thành viên.");
            }

            $memberModel->create($name, $phone ?: null, $email ?: null);
            $flashSuccess = "Đã thêm thành viên \"{$name}\" thành công!";
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));

            $memberModel->update($id, [
                'name'  => $name,
                'phone' => $phone ?: null,
                'email' => $email ?: null,
            ]);
            $flashSuccess = "Đã cập nhật thông tin thành viên #{$id} thành công!";
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $memberModel->delete($id);
            $flashSuccess = "Đã xóa thành viên #{$id} thành công!";
        }
    } catch (\Throwable $e) {
        $flashError = $e->getMessage();
    }
}

$members = [];
try {
    $members = $memberModel->all();
} catch (\Throwable $e) {
    $flashError = "Lỗi kết nối database: " . $e->getMessage();
}

$pageTitle = "Quản Lý Thành Viên - SimpleFinance";
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Danh Sách Thành Viên (Users)</h1>
        <p class="text-xs text-slate-500 mt-1">Quản lý những người tham gia vào các đợt chi tiêu và chia tiền</p>
    </div>
    <button onclick="openAddMemberModal()" class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/20 transition">
        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Thêm Thành Viên Mới
    </button>
</div>

<?php if ($flashSuccess): ?>
    <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center space-x-2">
        <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <span class="font-medium"><?= htmlspecialchars($flashSuccess) ?></span>
    </div>
<?php endif; ?>

<?php if ($flashError): ?>
    <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center space-x-2">
        <svg class="w-5 h-5 text-rose-600" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <span><?= htmlspecialchars($flashError) ?></span>
    </div>
<?php endif; ?>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <?php if (empty($members)): ?>
        <div class="py-12 text-center text-slate-400">
            Chưa có thành viên nào trong hệ thống. Hãy nhấn "Thêm Thành Viên Mới" để bắt đầu.
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase">
                    <tr>
                        <th class="px-6 py-3.5 text-left">ID</th>
                        <th class="px-6 py-3.5 text-left">Tên Thành Viên</th>
                        <th class="px-6 py-3.5 text-left">Số Điện Thoại</th>
                        <th class="px-6 py-3.5 text-left">Email</th>
                        <th class="px-6 py-3.5 text-left">Ngày Tham Gia</th>
                        <th class="px-6 py-3.5 text-right">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    <?php foreach ($members as $m): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-slate-400 text-xs font-mono">
                                #<?= $m['id'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center space-x-3">
                                    <div class="w-9 h-9 rounded-full bg-emerald-100 text-emerald-800 font-bold text-xs flex items-center justify-center">
                                        <?= mb_substr($m['name'], 0, 1, 'UTF-8') ?>
                                    </div>
                                    <span class="font-bold text-slate-900"><?= htmlspecialchars($m['name']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-600">
                                <?= htmlspecialchars($m['phone'] ?: '—') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-600">
                                <?= htmlspecialchars($m['email'] ?: '—') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-400 font-normal">
                                <?= htmlspecialchars(substr($m['created_at'], 0, 10)) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right space-x-2">
                                <button type="button" 
                                        onclick="openEditMemberModal(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['name'])) ?>', '<?= htmlspecialchars(addslashes($m['phone'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($m['email'] ?? '')) ?>')"
                                        class="px-2.5 py-1 text-xs font-semibold text-slate-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition border border-slate-200">
                                    Sửa
                                </button>
                                <form method="POST" action="members.php" class="inline" onsubmit="return confirm('Bạn có chắc muốn xóa thành viên này? Hành động này có thể ảnh hưởng đến lịch sử giao dịch.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 rounded-lg transition border border-slate-200 hover:border-rose-300">
                                        Xóa
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Thêm / Sửa Thành Viên -->
<div id="memberModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-bold text-slate-900" id="modalTitle">Thêm Thành Viên Mới</h3>
            <button onclick="closeMemberModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form method="POST" action="members.php" class="mt-4 space-y-4">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="memberId" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Tên Thành Viên *</label>
                <input type="text" name="name" id="inputName" required placeholder="Ví dụ: Nguyễn Văn A" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Số Điện Thoại</label>
                <input type="text" name="phone" id="inputPhone" placeholder="090..." class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Email</label>
                <input type="email" name="email" id="inputEmail" placeholder="example@email.com" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm">
            </div>

            <div class="pt-2 flex justify-end space-x-3">
                <button type="button" onclick="closeMemberModal()" class="px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-xl transition">
                    Hủy
                </button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-md shadow-emerald-600/20 transition">
                    Lưu Thông Tin
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddMemberModal() {
    document.getElementById('modalTitle').textContent = 'Thêm Thành Viên Mới';
    document.getElementById('formAction').value = 'create';
    document.getElementById('memberId').value = '';
    document.getElementById('inputName').value = '';
    document.getElementById('inputPhone').value = '';
    document.getElementById('inputEmail').value = '';
    document.getElementById('memberModal').classList.remove('hidden');
}

function openEditMemberModal(id, name, phone, email) {
    document.getElementById('modalTitle').textContent = 'Cập Nhật Thành Viên #' + id;
    document.getElementById('formAction').value = 'update';
    document.getElementById('memberId').value = id;
    document.getElementById('inputName').value = name;
    document.getElementById('inputPhone').value = phone;
    document.getElementById('inputEmail').value = email;
    document.getElementById('memberModal').classList.remove('hidden');
}

function closeMemberModal() {
    document.getElementById('memberModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
