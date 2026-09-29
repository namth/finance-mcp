<?php

require_once __DIR__ . '/auth_check.php';

use SimpleFinance\Models\Group;
use SimpleFinance\Models\User;

$groupModel = new Group();
$userModel = new User();
$userId = (int)$_SESSION['user_id'];

$flashSuccess = '';
$flashError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_group') {
            $name = trim((string)($_POST['name'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));

            if (empty($name)) {
                throw new \InvalidArgumentException("Vui lòng nhập tên nhóm.");
            }

            $newGroup = $groupModel->create($name, $userId, $desc ?: null);
            $_SESSION['current_group_id'] = (int)$newGroup['id'];
            $flashSuccess = "Đã tạo nhóm \"{$name}\" thành công!";
        } elseif ($action === 'add_member') {
            $groupId = (int)($_POST['group_id'] ?? 0);
            $userLookup = trim((string)($_POST['user_lookup'] ?? ''));

            if (!$groupModel->userHasAccess($groupId, $userId)) {
                throw new \InvalidArgumentException("Bạn không có quyền thêm thành viên vào nhóm này.");
            }

            $targetUser = $userModel->findByUsernameOrEmail($userLookup);
            if (!$targetUser) {
                throw new \InvalidArgumentException("Không tìm thấy người dùng với username hoặc email: {$userLookup}");
            }

            $groupModel->addMember($groupId, (int)$targetUser['id'], $targetUser['full_name']);
            $flashSuccess = "Đã thêm thành viên \"{$targetUser['full_name']}\" vào nhóm!";
        } elseif ($action === 'remove_member') {
            $groupId = (int)($_POST['group_id'] ?? 0);
            $targetUserId = (int)($_POST['target_user_id'] ?? 0);

            if (!$groupModel->userHasAccess($groupId, $userId)) {
                throw new \InvalidArgumentException("Bạn không có quyền thực hiện thao tác này.");
            }

            $groupModel->removeMember($groupId, $targetUserId);
            $flashSuccess = "Đã xóa thành viên khỏi nhóm!";
        }
    } catch (\Throwable $e) {
        $flashError = $e->getMessage();
    }
}

$myGroups = $groupModel->getUserGroups($userId);
$currentGroupId = (int)($_SESSION['current_group_id'] ?? 0);

$pageTitle = "Quản Lý Nhóm Chi Tiêu - SimpleFinance";
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Quản Lý Nhóm Chi Tiêu (Groups)</h1>
        <p class="text-xs text-slate-500 mt-0.5">Tạo nhóm và mời các thành viên cùng tham gia ghi chi tiêu, tính công nợ</p>
    </div>
    <button onclick="openCreateGroupModal()" class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/20 transition">
        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Tạo Nhóm Mới
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

<div class="space-y-6 mb-12">
    <?php foreach ($myGroups as $g): ?>
        <?php 
            $isActive = ((int)$g['id'] === $currentGroupId); 
            $members = $groupModel->getMembers((int)$g['id']);
            $isOwner = ((int)$g['owner_id'] === $userId);
        ?>
        <div class="bg-white rounded-2xl border <?= $isActive ? 'border-emerald-400 ring-2 ring-emerald-400/20' : 'border-slate-200' ?> shadow-sm p-6 transition">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-slate-100">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl <?= $isActive ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600' ?> flex items-center justify-center font-bold text-sm">
                        <?= mb_substr($g['name'], 0, 1, 'UTF-8') ?>
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <h2 class="text-base font-bold text-slate-900"><?= htmlspecialchars($g['name']) ?></h2>
                            <?php if ($isActive): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                    Đang làm việc
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($g['description'] ?: 'Không có mô tả') ?></p>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <?php if (!$isActive): ?>
                        <a href="switch_group.php?id=<?= $g['id'] ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition border border-emerald-200">
                            Chuyển sang nhóm này
                        </a>
                    <?php endif; ?>
                    <button type="button" onclick="openAddMemberModal(<?= $g['id'] ?>, '<?= htmlspecialchars(addslashes($g['name'])) ?>')" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 transition">
                        + Thêm thành viên
                    </button>
                </div>
            </div>

            <!-- Danh sách thành viên trong nhóm -->
            <div class="mt-4">
                <p class="text-[11px] font-bold uppercase text-slate-400 mb-2">Thành viên trong nhóm (<?= count($members) ?>):</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    <?php foreach ($members as $m): ?>
                        <div class="flex items-center justify-between p-2.5 rounded-xl border border-slate-100 bg-slate-50/70 text-xs">
                            <div class="flex items-center space-x-2 truncate">
                                <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-700 font-bold text-[11px] flex items-center justify-center flex-shrink-0">
                                    <?= mb_substr($m['display_name'], 0, 1, 'UTF-8') ?>
                                </div>
                                <div class="truncate">
                                    <span class="font-bold text-slate-800 block truncate"><?= htmlspecialchars($m['display_name']) ?></span>
                                    <span class="text-[10px] text-slate-400">@<?= htmlspecialchars($m['username']) ?></span>
                                </div>
                            </div>
                            <div class="flex items-center space-x-1.5 ml-2">
                                <span class="text-[10px] px-2 py-0.5 rounded font-semibold <?= $m['role'] === 'owner' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-600' ?>">
                                    <?= $m['role'] === 'owner' ? 'Trưởng nhóm' : 'Thành viên' ?>
                                </span>
                                <?php if ($isOwner && (int)$m['user_id'] !== $userId): ?>
                                    <form method="POST" action="groups.php" onsubmit="return confirm('Bạn có chắc muốn xóa thành viên này khỏi nhóm?');">
                                        <input type="hidden" name="action" value="remove_member">
                                        <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                                        <input type="hidden" name="target_user_id" value="<?= $m['user_id'] ?>">
                                        <button type="submit" class="text-rose-500 hover:text-rose-700 text-xs font-bold px-1">&times;</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal Tạo Nhóm Mới -->
<div id="createGroupModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-bold text-slate-900">Tạo Nhóm Chi Tiêu Mới</h3>
            <button onclick="closeModal('createGroupModal')" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        <form method="POST" action="groups.php" class="mt-4 space-y-4">
            <input type="hidden" name="action" value="create_group">
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Tên Nhóm *</label>
                <input type="text" name="name" required placeholder="Ví dụ: Du Lịch Đà Lạt, Tiền Phòng Trọ..." class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:border-emerald-500 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Mô Tả</label>
                <textarea name="description" rows="2" placeholder="Ghi chú về nhóm chi tiêu..." class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:border-emerald-500 text-sm"></textarea>
            </div>
            <div class="pt-2 flex justify-end space-x-2">
                <button type="button" onclick="closeModal('createGroupModal')" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Hủy</button>
                <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-md">Tạo Nhóm</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Thêm Thành Viên Vào Nhóm -->
<div id="addMemberModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-bold text-slate-900" id="addMemberModalTitle">Thêm Thành Viên Vào Nhóm</h3>
            <button onclick="closeModal('addMemberModal')" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        <form method="POST" action="groups.php" class="mt-4 space-y-4">
            <input type="hidden" name="action" value="add_member">
            <input type="hidden" name="group_id" id="modalGroupId" value="">
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Tên Đăng Nhập hoặc Email *</label>
                <input type="text" name="user_lookup" required placeholder="Nhập username hoặc email của bạn bè" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:border-emerald-500 text-sm">
                <p class="text-[11px] text-slate-400 mt-1">Người này phải đã đăng ký tài khoản trên hệ thống.</p>
            </div>
            <div class="pt-2 flex justify-end space-x-2">
                <button type="button" onclick="closeModal('addMemberModal')" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Hủy</button>
                <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-md">Thêm Vào Nhóm</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateGroupModal() {
    document.getElementById('createGroupModal').classList.remove('hidden');
}
function openAddMemberModal(groupId, groupName) {
    document.getElementById('modalGroupId').value = groupId;
    document.getElementById('addMemberModalTitle').textContent = `Thêm Thành Viên vào "${groupName}"`;
    document.getElementById('addMemberModal').classList.remove('hidden');
}
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
