<?php

require_once __DIR__ . '/auth_check.php';

use SimpleFinance\Models\Place;
use SimpleFinance\Models\Product;
use SimpleFinance\Models\Transaction;
use SimpleFinance\DebtManager;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ']);
    exit;
}

$action = trim((string)($_POST['action'] ?? ''));
$currentGroupId = (int)($_SESSION['current_group_id'] ?? 1);

try {
    switch ($action) {
        // --- 1. Tạo nhanh quán / địa điểm ---
        case 'quick_place_create':
            $placeName = trim((string)($_POST['place_name'] ?? ''));
            $placeAddress = trim((string)($_POST['place_address'] ?? ''));

            if (empty($placeName)) {
                throw new \InvalidArgumentException("Vui lòng nhập tên quán / địa điểm.");
            }

            $placeModel = new Place();
            $newPlace = $placeModel->create($placeName, $placeAddress ?: null, $currentGroupId);

            echo json_encode([
                'success' => true,
                'message' => "Đã tạo quán '{$placeName}' thành công!",
                'place'   => $newPlace,
            ]);
            exit;

        // --- 2. Tạo nhanh món / sản phẩm cho quán ---
        case 'quick_product_create':
            $name = trim((string)($_POST['name'] ?? ''));
            $price = isset($_POST['price']) && $_POST['price'] !== '' ? (float)$_POST['price'] : null;
            $placeId = !empty($_POST['place_id']) && (int)$_POST['place_id'] > 0 ? (int)$_POST['place_id'] : null;
            $desc = isset($_POST['description']) ? trim((string)$_POST['description']) : null;

            if (empty($name)) {
                throw new \InvalidArgumentException("Vui lòng nhập tên món / sản phẩm.");
            }
            if ($price !== null && $price < 0) {
                throw new \InvalidArgumentException("Giá món không được là số âm.");
            }

            $productModel = new Product();
            $newProd = $productModel->create($name, $price, $desc, $currentGroupId, $placeId);

            echo json_encode([
                'success' => true,
                'message' => "Đã thêm món '{$name}' thành công!",
                'product' => $newProd,
            ]);
            exit;

        // --- 3. Sửa giá nhanh từng món trong giao dịch ---
        case 'update_item_price':
            $txId = (int)($_POST['transaction_id'] ?? 0);
            $itemId = (int)($_POST['item_id'] ?? 0);
            $newPrice = (float)($_POST['price'] ?? 0);
            $newQuantity = isset($_POST['quantity']) && (int)$_POST['quantity'] > 0 ? (int)$_POST['quantity'] : null;

            if ($txId <= 0 || $itemId <= 0) {
                throw new \InvalidArgumentException("Tham số giao dịch hoặc món không hợp lệ.");
            }

            $txModel = new Transaction();
            $debtManager = new DebtManager();

            $tx = $txModel->find($txId);
            if (!$tx) {
                throw new \InvalidArgumentException("Không tìm thấy giao dịch #{$txId}.");
            }
            if ((int)$tx['group_id'] !== $currentGroupId) {
                throw new \InvalidArgumentException("Giao dịch không thuộc nhóm chi tiêu của bạn.");
            }

            $txModel->updateItemPrice($txId, $itemId, $newPrice, $newQuantity);

            // Tự động tính toán lại bảng công nợ của nhóm nếu giao dịch đã hoàn thành
            $debtsRecalculated = false;
            if ($tx['status'] === 'completed') {
                $debtManager->recalculateAll($currentGroupId);
                $debtsRecalculated = true;
            }

            $updatedTx = $txModel->find($txId);

            echo json_encode([
                'success'            => true,
                'message'            => "Đã cập nhật giá món thành công" . ($debtsRecalculated ? " và tự động cân bằng lại công nợ." : "."),
                'transaction'        => $updatedTx,
                'debts_recalculated' => $debtsRecalculated,
            ]);
            exit;

        default:
            throw new \InvalidArgumentException("Hành động '{$action}' không được hỗ trợ.");
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
    exit;
}
