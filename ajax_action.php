<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nạp tự động các lớp
spl_autoload_register(function ($class) {
    $prefix = 'SimpleFinance\\';
    $baseDir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use SimpleFinance\Models\Place;
use SimpleFinance\Models\Product;
use SimpleFinance\Models\Transaction;
use SimpleFinance\Models\GroupBuy;
use SimpleFinance\DebtManager;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ']);
    exit;
}

$action = trim((string)($_POST['action'] ?? ''));

// Danh sách các action công khai (không cần đăng nhập)
$publicActions = ['group_buy_submit', 'group_buy_notify_paid'];

// Kiểm tra phiên đăng nhập nếu là action nội bộ
if (!in_array($action, $publicActions)) {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để thực hiện hành động này.']);
        exit;
    }
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentGroupId = (int)($_SESSION['current_group_id'] ?? 1);

try {
    $groupBuyModel = new GroupBuy();

    switch ($action) {
        // ==========================================
        // 1. PUBLIC: ĐĂNG KÝ MUA CHUNG TỪ TRANG EVENT.PHP
        // ==========================================
        case 'group_buy_submit':
            $token = trim((string)($_POST['token'] ?? ''));
            $name = trim((string)($_POST['name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $note = trim((string)($_POST['note'] ?? ''));
            $itemsJson = $_POST['items'] ?? '[]';
            
            $items = is_array($itemsJson) ? $itemsJson : json_decode($itemsJson, true);
            if (!is_array($items)) {
                $items = [];
            }

            if (empty($token)) {
                throw new \InvalidArgumentException("Token sự kiện không hợp lệ.");
            }

            $regResult = $groupBuyModel->register($token, $name, $phone ?: null, $note ?: null, $items);

            echo json_encode([
                'success'      => true,
                'message'      => 'Đăng ký thành công! Vui lòng quét mã QR để chuyển khoản.',
                'registration' => $regResult,
            ]);
            exit;

        // ==========================================
        // 2. PUBLIC: BÁO ĐÃ CHUYỂN KHOẢN
        // ==========================================
        case 'group_buy_notify_paid':
            $regToken = trim((string)($_POST['reg_token'] ?? ''));
            if (empty($regToken)) {
                throw new \InvalidArgumentException("Mã đơn đăng ký không hợp lệ.");
            }

            $success = $groupBuyModel->notifyPaid($regToken);
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Đã ghi nhận thông báo chuyển tiền của bạn!' : 'Không tìm thấy đơn hàng.',
            ]);
            exit;

        // ==========================================
        // 3. ADMIN: TICK TOGGLE ĐÃ THU TIỀN
        // ==========================================
        case 'group_buy_toggle_paid':
            $regId = (int)($_POST['registration_id'] ?? 0);
            if ($regId <= 0) {
                throw new \InvalidArgumentException("ID đơn đăng ký không hợp lệ.");
            }

            $isPaid = isset($_POST['is_paid']) ? (bool)$_POST['is_paid'] : null;
            $res = $groupBuyModel->togglePaid($regId, $isPaid);

            echo json_encode([
                'success' => true,
                'data'    => $res,
                'message' => $res['is_paid'] === 1 ? 'Đã xác nhận thu tiền!' : 'Đã bỏ xác nhận thu tiền.',
            ]);
            exit;

        // ==========================================
        // 4. ADMIN: CHỐT SỰ KIỆN THÀNH GIAO DỊCH NHÓM
        // ==========================================
        case 'group_buy_convert_to_transaction':
            $eventId = (int)($_POST['event_id'] ?? 0);
            if ($eventId <= 0) {
                throw new \InvalidArgumentException("ID sự kiện không hợp lệ.");
            }

            $res = $groupBuyModel->convertToTransaction($eventId, $currentUserId);

            echo json_encode([
                'success'        => true,
                'transaction_id' => $res['transaction_id'],
                'message'        => $res['message'],
                'redirect_url'   => "transaction_detail.php?id={$res['transaction_id']}",
            ]);
            exit;

        // ==========================================
        // 5. CÁC HÀNH ĐỘNG CŨ (PLACE, PRODUCT, TRANSACTION)
        // ==========================================
        case 'quick_place_create':
            $placeName = trim((string)($_POST['place_name'] ?? ''));
            $placeAddress = trim((string)($_POST['place_address'] ?? ''));
            $mapUrl = trim((string)($_POST['map_url'] ?? ''));

            if (empty($placeName)) {
                throw new \InvalidArgumentException("Vui lòng nhập tên quán / địa điểm.");
            }

            $placeModel = new Place();
            $newPlace = $placeModel->create($placeName, $placeAddress ?: null, $currentGroupId, $mapUrl ?: null);

            echo json_encode([
                'success' => true,
                'message' => "Đã tạo quán '{$placeName}' thành công!",
                'place'   => $newPlace,
            ]);
            exit;

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
