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

use SimpleFinance\Models\Product;

$productModel = new Product();
$flashSuccess = '';
$flashError = '';

$currentGroupId = (int)($_SESSION['current_group_id'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $name = trim((string)($_POST['name'] ?? ''));
            $priceInput = trim((string)($_POST['default_price'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));

            if (empty($name)) {
                throw new \InvalidArgumentException("Vui lòng nhập tên sản phẩm/dịch vụ.");
            }

            $price = ($priceInput !== '') ? (float)$priceInput : null;
            $productModel->create($name, $price, $desc ?: null, $currentGroupId);
            $flashSuccess = "Đã thêm sản phẩm \"{$name}\" thành công!";
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $priceInput = trim((string)($_POST['default_price'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));

            $price = ($priceInput !== '') ? (float)$priceInput : null;
            $productModel->update($id, [
                'name'          => $name,
                'default_price' => $price,
                'description'   => $desc ?: null,
            ]);
            $flashSuccess = "Đã cập nhật sản phẩm #{$id} thành công!";
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $productModel->delete($id);
            $flashSuccess = "Đã xóa sản phẩm #{$id} thành công!";
        }
    } catch (\Throwable $e) {
        $flashError = $e->getMessage();
    }
}

$products = [];
try {
    $products = $productModel->all($currentGroupId);
} catch (\Throwable $e) {
    $flashError = "Lỗi kết nối database: " . $e->getMessage();
}

$pageTitle = "Quản Lý Sản Phẩm & Dịch Vụ - SimpleFinance";
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Danh Mục Sản Phẩm & Dịch Vụ</h1>
        <p class="text-xs text-slate-500 mt-1">Các món ăn, đồ uống, dịch vụ hoặc chi phí được sử dụng trong các giao dịch</p>
    </div>
    <button onclick="openAddProductModal()" class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/20 transition">
        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Thêm Sản Phẩm Mới
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
    <?php if (empty($products)): ?>
        <div class="py-12 text-center text-slate-400">
            Chưa có sản phẩm hoặc dịch vụ nào. Hãy nhấn "Thêm Sản Phẩm Mới" để tạo.
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase">
                    <tr>
                        <th class="px-6 py-3.5 text-left">ID</th>
                        <th class="px-6 py-3.5 text-left">Tên Sản Phẩm / Dịch Vụ</th>
                        <th class="px-6 py-3.5 text-right">Giá Mặc Định</th>
                        <th class="px-6 py-3.5 text-left">Mô Tả</th>
                        <th class="px-6 py-3.5 text-left">Ngày Tạo</th>
                        <th class="px-6 py-3.5 text-right">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    <?php foreach ($products as $p): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-slate-400 text-xs font-mono">
                                #<?= $p['id'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-bold text-slate-900"><?= htmlspecialchars($p['name']) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <?php if ($p['default_price'] !== null): ?>
                                    <span class="font-bold text-emerald-700"><?= number_format($p['default_price']) ?> đ</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                        Giá linh hoạt khi dùng
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-slate-500 max-w-xs truncate text-xs">
                                <?= htmlspecialchars($p['description'] ?: '—') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-400 font-normal">
                                <?= htmlspecialchars(substr($p['created_at'], 0, 10)) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right space-x-2">
                                <button type="button" 
                                        onclick="openEditProductModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', '<?= $p['default_price'] !== null ? $p['default_price'] : '' ?>', '<?= htmlspecialchars(addslashes($p['description'] ?? '')) ?>')"
                                        class="px-2.5 py-1 text-xs font-semibold text-slate-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition border border-slate-200">
                                    Sửa
                                </button>
                                <form method="POST" action="products.php" class="inline" onsubmit="return confirm('Bạn có chắc muốn xóa sản phẩm này?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
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

<!-- Modal Thêm / Sửa Sản Phẩm -->
<div id="productModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-bold text-slate-900" id="modalTitle">Thêm Sản Phẩm / Dịch Vụ Mới</h3>
            <button onclick="closeProductModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form method="POST" action="products.php" class="mt-4 space-y-4">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="productId" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Tên Sản Phẩm / Dịch Vụ *</label>
                <input type="text" name="name" id="inputName" required placeholder="Ví dụ: Cơm trưa văn phòng, Bia, Tiền sân..." class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Giá Mặc Định (VNĐ)</label>
                <input type="number" name="default_price" id="inputPrice" min="0" step="500" placeholder="Để trống nếu giá linh hoạt tùy lần dùng" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm">
                <p class="text-[11px] text-slate-400 mt-1">Nếu để trống, người tạo hóa đơn sẽ tự nhập giá thực tế tại thời điểm sử dụng.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Mô Tả / Ghi Chú</label>
                <textarea name="description" id="inputDesc" rows="2" placeholder="Ghi chú thêm về món này..." class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm"></textarea>
            </div>

            <div class="pt-2 flex justify-end space-x-3">
                <button type="button" onclick="closeProductModal()" class="px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-xl transition">
                    Hủy
                </button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-md shadow-emerald-600/20 transition">
                    Lưu Sản Phẩm
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddProductModal() {
    document.getElementById('modalTitle').textContent = 'Thêm Sản Phẩm / Dịch Vụ Mới';
    document.getElementById('formAction').value = 'create';
    document.getElementById('productId').value = '';
    document.getElementById('inputName').value = '';
    document.getElementById('inputPrice').value = '';
    document.getElementById('inputDesc').value = '';
    document.getElementById('productModal').classList.remove('hidden');
}

function openEditProductModal(id, name, price, desc) {
    document.getElementById('modalTitle').textContent = 'Cập Nhật Sản Phẩm #' + id;
    document.getElementById('formAction').value = 'update';
    document.getElementById('productId').value = id;
    document.getElementById('inputName').value = name;
    document.getElementById('inputPrice').value = price;
    document.getElementById('inputDesc').value = desc;
    document.getElementById('productModal').classList.remove('hidden');
}

function closeProductModal() {
    document.getElementById('productModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
