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
use SimpleFinance\Models\Place;

$productModel = new Product();
$placeModel = new Place();
$flashSuccess = '';
$flashError = '';

$currentGroupId = (int)($_SESSION['current_group_id'] ?? 1);

// Xử lý các thao tác POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // --- 1. Tạo nhanh quán qua AJAX hoặc submit thường ---
        if ($action === 'place_create') {
            $placeName = trim((string)($_POST['place_name'] ?? ''));
            $placeAddress = trim((string)($_POST['place_address'] ?? ''));
            $mapUrl = trim((string)($_POST['place_map_url'] ?? ($_POST['map_url'] ?? '')));

            if (empty($placeName)) {
                throw new \InvalidArgumentException("Vui lòng nhập tên quán / địa điểm.");
            }

            $newPlace = $placeModel->create($placeName, $placeAddress ?: null, $currentGroupId, $mapUrl ?: null);

            // Nếu gọi qua AJAX (từ nút Thêm quán nhanh trong form sản phẩm)
            if (!empty($_POST['is_ajax'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'place'   => $newPlace,
                ]);
                exit;
            }

            $flashSuccess = "Đã thêm quán \"{$placeName}\" thành công!";
        } elseif ($action === 'place_update') {
            $placeId = (int)($_POST['place_id'] ?? 0);
            $placeName = trim((string)($_POST['place_name'] ?? ''));
            $placeAddress = trim((string)($_POST['place_address'] ?? ''));
            $mapUrl = trim((string)($_POST['place_map_url'] ?? ($_POST['map_url'] ?? '')));

            if (empty($placeName)) {
                throw new \InvalidArgumentException("Tên quán không được để trống.");
            }

            $placeModel->update($placeId, [
                'name'    => $placeName,
                'address' => $placeAddress ?: null,
                'map_url' => $mapUrl ?: null,
            ]);
            $flashSuccess = "Đã cập nhật thông tin quán thành công!";
        } elseif ($action === 'place_delete') {
            $placeId = (int)($_POST['place_id'] ?? 0);
            $placeModel->delete($placeId);
            $flashSuccess = "Đã xóa quán thành công!";
        } elseif ($action === 'create') {
            // --- 2. Thêm sản phẩm ---
            $name = trim((string)($_POST['name'] ?? ''));
            $priceInput = trim((string)($_POST['default_price'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            $placeId = !empty($_POST['place_id']) ? (int)$_POST['place_id'] : null;

            if (empty($name)) {
                throw new \InvalidArgumentException("Vui lòng nhập tên sản phẩm/dịch vụ.");
            }

            $price = ($priceInput !== '') ? (float)$priceInput : null;
            $productModel->create($name, $price, $desc ?: null, $currentGroupId, $placeId);
            $flashSuccess = "Đã thêm sản phẩm \"{$name}\" thành công!";
        } elseif ($action === 'update') {
            // --- 3. Cập nhật sản phẩm ---
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $priceInput = trim((string)($_POST['default_price'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            $placeId = !empty($_POST['place_id']) ? (int)$_POST['place_id'] : null;

            $price = ($priceInput !== '') ? (float)$priceInput : null;
            $productModel->update($id, [
                'name'          => $name,
                'place_id'      => $placeId,
                'default_price' => $price,
                'description'   => $desc ?: null,
            ]);
            $flashSuccess = "Đã cập nhật sản phẩm #{$id} thành công!";
        } elseif ($action === 'delete') {
            // --- 4. Xóa sản phẩm ---
            $id = (int)($_POST['id'] ?? 0);
            $productModel->delete($id);
            $flashSuccess = "Đã xóa sản phẩm #{$id} thành công!";
        }
    } catch (\Throwable $e) {
        if (!empty($_POST['is_ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
            exit;
        }
        $flashError = $e->getMessage();
    }
}

$products = [];
$places = [];
try {
    $products = $productModel->all($currentGroupId);
    $places = $placeModel->all($currentGroupId);
} catch (\Throwable $e) {
    $flashError = "Lỗi kết nối database: " . $e->getMessage();
}

$pageTitle = "Quản Lý Sản Phẩm & Quán - SimpleFinance";
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 mb-6">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Danh Mục Sản Phẩm & Quán</h1>
        <p class="text-xs text-slate-500 mt-1">Các món ăn, đồ uống, dịch vụ gắn liền với địa điểm quán để chia hóa đơn</p>
    </div>
    <div class="flex flex-wrap sm:flex-nowrap items-center gap-2">
        <button onclick="openManagePlacesModal()" class="flex-1 sm:flex-initial inline-flex items-center justify-center px-3.5 py-2 text-xs sm:text-sm font-semibold rounded-xl text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 active:scale-95 shadow-2xs transition">
            <svg class="w-4 h-4 mr-1.5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            Quán (<?= count($places) ?>)
        </button>
        <button onclick="openAddProductModal()" class="flex-1 sm:flex-initial inline-flex items-center justify-center px-3.5 sm:px-4 py-2 text-xs sm:text-sm font-semibold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/20 active:scale-95 transition">
            <svg class="w-4 h-4 mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Thêm Món Mới
        </button>
    </div>
</div>

<?php if ($flashSuccess): ?>
    <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center space-x-2">
        <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <span class="font-medium"><?= htmlspecialchars($flashSuccess) ?></span>
    </div>
<?php endif; ?>

<?php if ($flashError): ?>
    <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center space-x-2">
        <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <span><?= htmlspecialchars($flashError) ?></span>
    </div>
<?php endif; ?>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <?php if (empty($products)): ?>
        <div class="py-12 text-center text-slate-400 px-4">
            Chưa có sản phẩm hoặc dịch vụ nào. Hãy nhấn "Thêm Món Mới" để tạo.
        </div>
    <?php else: ?>
        <!-- Giao diện Thẻ trên Mobile (< md) -->
        <div class="md:hidden divide-y divide-slate-100">
            <?php foreach ($products as $p): ?>
                <div class="p-4 space-y-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center space-x-1.5">
                                <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 font-mono text-[10px]">#<?= $p['id'] ?></span>
                                <h3 class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($p['name']) ?></h3>
                            </div>
                            <?php if (!empty($p['place_name'])): ?>
                                <div class="flex items-center text-xs text-emerald-700 font-medium mt-1">
                                    <svg class="w-3.5 h-3.5 mr-1 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <span class="truncate"><?= htmlspecialchars($p['place_name']) ?></span>
                                    <?php if (!empty($p['place_address'])): ?>
                                        <span class="text-slate-400 text-[11px] ml-1 truncate">(<?= htmlspecialchars($p['place_address']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-[11px] text-slate-400 italic mt-0.5">Chưa gắn quán</p>
                            <?php endif; ?>
                        </div>

                        <div class="text-right flex-shrink-0">
                            <?php if ($p['default_price'] !== null): ?>
                                <span class="text-base font-extrabold text-emerald-700"><?= number_format($p['default_price']) ?> đ</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                    Giá linh hoạt
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($p['description'])): ?>
                        <p class="text-xs text-slate-500 line-clamp-2"><?= htmlspecialchars($p['description']) ?></p>
                    <?php endif; ?>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-xs">
                        <span class="text-[11px] text-slate-400">Tạo: <?= htmlspecialchars(substr($p['created_at'], 0, 10)) ?></span>
                        <div class="flex items-center space-x-1.5">
                            <button type="button" 
                                    onclick="openEditProductModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', '<?= $p['place_id'] ?? '' ?>', '<?= $p['default_price'] !== null ? $p['default_price'] : '' ?>', '<?= htmlspecialchars(addslashes($p['description'] ?? '')) ?>')"
                                    class="px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-50 hover:bg-slate-100 rounded-xl transition border border-slate-200">
                                Sửa
                            </button>
                            <form method="POST" action="products.php" class="inline" onsubmit="return confirm('Bạn có chắc muốn xóa món này?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="px-2.5 py-1.5 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-xl transition border border-rose-200">
                                    Xóa
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Giao diện Bảng trên Desktop (>= md) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase">
                    <tr>
                        <th class="px-6 py-3.5 text-left">ID</th>
                        <th class="px-6 py-3.5 text-left">Tên Sản Phẩm / Dịch Vụ</th>
                        <th class="px-6 py-3.5 text-left">Quán / Địa Điểm</th>
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
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if (!empty($p['place_name'])): ?>
                                    <div class="flex flex-col">
                                        <div class="flex items-center text-xs font-semibold text-slate-800">
                                            <svg class="w-3.5 h-3.5 mr-1 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            </svg>
                                            <span><?= htmlspecialchars($p['place_name']) ?></span>
                                        </div>
                                        <?php if (!empty($p['place_address'])): ?>
                                            <span class="text-[11px] text-slate-400 font-normal truncate max-w-xs mt-0.5">
                                                <?= htmlspecialchars($p['place_address']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400 italic">Chưa gắn quán</span>
                                <?php endif; ?>
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
                                        onclick="openEditProductModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', '<?= $p['place_id'] ?? '' ?>', '<?= $p['default_price'] !== null ? $p['default_price'] : '' ?>', '<?= htmlspecialchars(addslashes($p['description'] ?? '')) ?>')"
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

<!-- ============================================== -->
<!-- 1. MODAL THÊM / SỬA SẢN PHẨM                  -->
<!-- ============================================== -->
<div id="productModal" class="fixed inset-0 z-40 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-bold text-slate-900" id="modalTitle">Thêm Sản Phẩm / Dịch Vụ Mới</h3>
            <button type="button" onclick="closeProductModal()" class="text-slate-400 hover:text-slate-600">
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
                <input type="text" name="name" id="inputName" required placeholder="Ví dụ: Cơm trưa văn phòng, Cà phê đen, Tiền sân..." class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm">
            </div>

            <!-- Trường chọn Quán / Địa điểm -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-semibold text-slate-700 uppercase">Quán / Địa Điểm</label>
                    <button type="button" onclick="openQuickAddPlaceModal()" class="text-xs text-emerald-600 hover:text-emerald-700 font-semibold flex items-center">
                        <svg class="w-3.5 h-3.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        + Thêm quán mới
                    </button>
                </div>
                <select name="place_id" id="inputPlaceId" onchange="onPlaceSelectChange()" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm">
                    <option value="">-- Không gắn quán (tự do) --</option>
                    <?php foreach ($places as $pl): ?>
                        <option value="<?= $pl['id'] ?>" data-address="<?= htmlspecialchars($pl['address'] ?? '') ?>">
                            <?= htmlspecialchars($pl['name']) ?><?= !empty($pl['address']) ? ' (' . htmlspecialchars($pl['address']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="placeAddressPreview" class="hidden mt-1.5 text-xs text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-100 flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1.5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span id="placeAddressText" class="truncate"></span>
                </div>
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

<!-- ============================================== -->
<!-- 2. MODAL TẠO NHANH QUÁN TRONG FORM SẢN PHẨM    -->
<!-- ============================================== -->
<div id="quickAddPlaceModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-bold text-slate-900 flex items-center">
                <svg class="w-4 h-4 mr-1.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Thêm Quán Mới Nhanh
            </h3>
            <button type="button" onclick="closeQuickAddPlaceModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div id="quickPlaceError" class="hidden mt-3 p-3 bg-rose-50 text-rose-700 text-xs rounded-xl border border-rose-200"></div>

        <form id="quickPlaceForm" onsubmit="handleQuickPlaceSubmit(event)" class="mt-4 space-y-3.5">
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Tên Quán *</label>
                <input type="text" id="quickPlaceName" required placeholder="Ví dụ: Highlands Coffee, Phở 10 Lý Quốc Sư..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Địa Chỉ / Địa Điểm</label>
                <input type="text" id="quickPlaceAddress" placeholder="Ví dụ: 123 Lê Lợi, Quận 1..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Link Google Maps <span class="text-slate-400 font-normal normal-case">(Tùy chọn)</span></label>
                <input type="url" id="quickPlaceMapUrl" placeholder="https://maps.app.goo.gl/... hoặc https://google.com/maps/..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm">
            </div>

            <div class="pt-2 flex justify-end space-x-2.5">
                <button type="button" onclick="closeQuickAddPlaceModal()" class="px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition">
                    Hủy
                </button>
                <button type="submit" id="btnSaveQuickPlace" class="px-4 py-2.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-md shadow-emerald-600/20 active:scale-95 transition flex items-center">
                    <span>Thêm & Chọn Luôn</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================== -->
<!-- 3. MODAL QUẢN LÝ DANH SÁCH QUÁN                -->
<!-- ============================================== -->
<div id="managePlacesModal" class="fixed inset-0 z-40 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4">
    <div class="bg-white rounded-3xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl border border-slate-200 max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 flex-shrink-0">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center">
                    <svg class="w-5 h-5 mr-1.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Quản Lý Danh Sách Quán / Địa Điểm
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Xem danh sách, thêm mới hoặc chỉnh sửa địa chỉ các quán của nhóm</p>
            </div>
            <button type="button" onclick="closeManagePlacesModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Khối Thêm / Sửa Quán trong modal -->
        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 mt-4 mb-4 flex-shrink-0">
            <h4 class="text-xs font-bold text-slate-800 uppercase mb-2" id="placeFormTitle">Thêm Quán Mới</h4>
            <form method="POST" action="products.php" class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                <input type="hidden" name="action" id="actionPlaceForm" value="place_create">
                <input type="hidden" name="place_id" id="editPlaceId" value="">

                <div class="sm:col-span-4">
                    <input type="text" name="place_name" id="editPlaceName" required placeholder="Tên quán (bắt buộc)..." class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div class="sm:col-span-3">
                    <input type="text" name="place_address" id="editPlaceAddress" placeholder="Địa chỉ (ví dụ: 123 Lê Lợi)..." class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div class="sm:col-span-3">
                    <input type="url" name="place_map_url" id="editPlaceMapUrl" placeholder="Link Google Maps..." class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div class="sm:col-span-2 flex items-center space-x-1">
                    <button type="submit" id="btnSubmitPlaceForm" class="w-full py-1.5 px-3 text-xs font-bold rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 transition">
                        Lưu
                    </button>
                    <button type="button" id="btnCancelEditPlace" onclick="resetPlaceForm()" class="hidden py-1.5 px-2 text-xs font-semibold rounded-lg text-slate-600 hover:bg-slate-200 transition">
                        Hủy
                    </button>
                </div>
            </form>
        </div>

        <!-- Danh sách các quán -->
        <div class="overflow-y-auto flex-1 border border-slate-100 rounded-xl">
            <?php if (empty($places)): ?>
                <div class="py-8 text-center text-xs text-slate-400">
                    Chưa có quán nào trong danh sách. Hãy thêm quán đầu tiên ở trên!
                </div>
            <?php else: ?>
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-100 text-slate-600 font-semibold uppercase sticky top-0">
                        <tr>
                            <th class="px-4 py-2.5 text-left">Tên Quán</th>
                            <th class="px-4 py-2.5 text-left">Địa Chỉ</th>
                            <th class="px-4 py-2.5 text-left">Bản Đồ</th>
                            <th class="px-4 py-2.5 text-right">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php foreach ($places as $pl): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-4 py-3 whitespace-nowrap font-bold text-slate-900">
                                    <?= htmlspecialchars($pl['name']) ?>
                                </td>
                                <td class="px-4 py-3 text-slate-500">
                                    <?= htmlspecialchars($pl['address'] ?: '—') ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-500">
                                    <?php if (!empty($pl['map_url'])): ?>
                                        <a href="<?= htmlspecialchars($pl['map_url']) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-xs font-semibold text-emerald-600 hover:text-emerald-700 hover:underline">
                                            🗺️ Mở map ↗
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-300">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right space-x-1">
                                    <button type="button" 
                                            onclick="editPlaceRow(<?= $pl['id'] ?>, '<?= htmlspecialchars(addslashes($pl['name'])) ?>', '<?= htmlspecialchars(addslashes($pl['address'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($pl['map_url'] ?? '')) ?>')"
                                            class="px-2 py-1 text-slate-600 hover:text-emerald-700 hover:bg-emerald-50 rounded transition border border-slate-200">
                                        Sửa
                                    </button>
                                    <form method="POST" action="products.php" class="inline" onsubmit="return confirm('Bạn có chắc muốn xóa quán này? Sản phẩm thuộc quán này sẽ chuyển về trạng thái không gắn quán.');">
                                        <input type="hidden" name="action" value="place_delete">
                                        <input type="hidden" name="place_id" value="<?= $pl['id'] ?>">
                                        <button type="submit" class="px-2 py-1 text-rose-600 hover:bg-rose-50 rounded transition border border-slate-200 hover:border-rose-300">
                                            Xóa
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="pt-4 border-t border-slate-100 flex justify-end flex-shrink-0 mt-3">
            <button type="button" onclick="closeManagePlacesModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition">
                Đóng
            </button>
        </div>
    </div>
</div>

<script>
// --- Quản lý Sản Phẩm ---
function openAddProductModal() {
    document.getElementById('modalTitle').textContent = 'Thêm Sản Phẩm / Dịch Vụ Mới';
    document.getElementById('formAction').value = 'create';
    document.getElementById('productId').value = '';
    document.getElementById('inputName').value = '';
    document.getElementById('inputPlaceId').value = '';
    document.getElementById('inputPrice').value = '';
    document.getElementById('inputDesc').value = '';
    onPlaceSelectChange();
    document.getElementById('productModal').classList.remove('hidden');
}

function openEditProductModal(id, name, placeId, price, desc) {
    document.getElementById('modalTitle').textContent = 'Cập Nhật Sản Phẩm #' + id;
    document.getElementById('formAction').value = 'update';
    document.getElementById('productId').value = id;
    document.getElementById('inputName').value = name;
    document.getElementById('inputPlaceId').value = placeId || '';
    document.getElementById('inputPrice').value = price;
    document.getElementById('inputDesc').value = desc;
    onPlaceSelectChange();
    document.getElementById('productModal').classList.remove('hidden');
}

function closeProductModal() {
    document.getElementById('productModal').classList.add('hidden');
}

function onPlaceSelectChange() {
    const select = document.getElementById('inputPlaceId');
    const selectedOption = select.options[select.selectedIndex];
    const address = selectedOption ? selectedOption.getAttribute('data-address') : '';
    const preview = document.getElementById('placeAddressPreview');
    const previewText = document.getElementById('placeAddressText');

    if (address && address.trim() !== '') {
        previewText.textContent = address;
        preview.classList.remove('hidden');
    } else {
        preview.classList.add('hidden');
    }
}

// --- Quick Add Place (Modal nhanh trong form sản phẩm) ---
function openQuickAddPlaceModal() {
    document.getElementById('quickPlaceError').classList.add('hidden');
    document.getElementById('quickPlaceName').value = '';
    document.getElementById('quickPlaceAddress').value = '';
    document.getElementById('quickPlaceMapUrl').value = '';
    document.getElementById('quickAddPlaceModal').classList.remove('hidden');
}

function closeQuickAddPlaceModal() {
    document.getElementById('quickAddPlaceModal').classList.add('hidden');
}

async function handleQuickPlaceSubmit(e) {
    e.preventDefault();
    const nameInput = document.getElementById('quickPlaceName');
    const addressInput = document.getElementById('quickPlaceAddress');
    const mapUrlInput = document.getElementById('quickPlaceMapUrl');
    const btn = document.getElementById('btnSaveQuickPlace');
    const errBox = document.getElementById('quickPlaceError');

    const name = nameInput.value.trim();
    const address = addressInput.value.trim();
    const mapUrl = mapUrlInput ? mapUrlInput.value.trim() : '';

    if (!name) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="animate-spin mr-1.5">⌛</span> Đang lưu...';
    errBox.classList.add('hidden');

    try {
        const formData = new FormData();
        formData.append('action', 'place_create');
        formData.append('is_ajax', '1');
        formData.append('place_name', name);
        formData.append('place_address', address);
        formData.append('place_map_url', mapUrl);

        const res = await fetch('products.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await res.json();
        if (data.success && data.place) {
            // Thêm option mới vào select place_id và tự động chọn
            const select = document.getElementById('inputPlaceId');
            const opt = document.createElement('option');
            opt.value = data.place.id;
            opt.setAttribute('data-address', data.place.address || '');
            opt.setAttribute('data-map-url', data.place.map_url || '');
            opt.textContent = data.place.name + (data.place.address ? ` (${data.place.address})` : '');
            select.appendChild(opt);
            select.value = data.place.id;

            onPlaceSelectChange();
            closeQuickAddPlaceModal();
        } else {
            throw new Error(data.message || 'Không thể tạo quán mới.');
        }
    } catch (err) {
        errBox.textContent = err.message || 'Lỗi kết nối khi tạo quán.';
        errBox.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Thêm & Chọn Luôn';
    }
}

// --- Quản Lý Danh Sách Quán (Modal riêng) ---
function openManagePlacesModal() {
    resetPlaceForm();
    document.getElementById('managePlacesModal').classList.remove('hidden');
}

function closeManagePlacesModal() {
    document.getElementById('managePlacesModal').classList.add('hidden');
}

function editPlaceRow(id, name, address, mapUrl = '') {
    document.getElementById('placeFormTitle').textContent = 'Sửa Thông Tin Quán #' + id;
    document.getElementById('actionPlaceForm').value = 'place_update';
    document.getElementById('editPlaceId').value = id;
    document.getElementById('editPlaceName').value = name;
    document.getElementById('editPlaceAddress').value = address;
    document.getElementById('editPlaceMapUrl').value = mapUrl;
    document.getElementById('btnSubmitPlaceForm').textContent = 'Cập nhật';
    document.getElementById('btnCancelEditPlace').classList.remove('hidden');
}

function resetPlaceForm() {
    document.getElementById('placeFormTitle').textContent = 'Thêm Quán Mới';
    document.getElementById('actionPlaceForm').value = 'place_create';
    document.getElementById('editPlaceId').value = '';
    document.getElementById('editPlaceName').value = '';
    document.getElementById('editPlaceAddress').value = '';
    document.getElementById('editPlaceMapUrl').value = '';
    document.getElementById('btnSubmitPlaceForm').textContent = 'Lưu';
    document.getElementById('btnCancelEditPlace').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
