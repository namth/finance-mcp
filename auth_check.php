<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
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

use SimpleFinance\Models\Group;
use SimpleFinance\Models\User;

$userModel = new User();
$groupModel = new Group();

$currentUser = $userModel->findById((int)$_SESSION['user_id']);
if (!$currentUser) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

$_SESSION['user'] = $currentUser;

// Nạp danh sách nhóm của user
$userGroups = $groupModel->getUserGroups((int)$currentUser['id']);

// Nếu chưa chọn nhóm hoặc nhóm đang chọn không còn quyền truy cập
if (empty($_SESSION['current_group_id']) || !in_array((int)$_SESSION['current_group_id'], array_column($userGroups, 'id'))) {
    if (!empty($userGroups)) {
        $_SESSION['current_group_id'] = (int)$userGroups[0]['id'];
        $currentGroup = $userGroups[0];
    } else {
        // Tự động tạo nhóm mặc định nếu user chưa có nhóm nào
        $createdGroup = $groupModel->create("Chi Tiêu của " . $currentUser['full_name'], (int)$currentUser['id'], "Nhóm mặc định");
        $_SESSION['current_group_id'] = (int)$createdGroup['id'];
        $userGroups = [$createdGroup];
        $currentGroup = $createdGroup;
    }
} else {
    foreach ($userGroups as $g) {
        if ((int)$g['id'] === (int)$_SESSION['current_group_id']) {
            $currentGroup = $g;
            break;
        }
    }
}
