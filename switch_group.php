<?php

require_once __DIR__ . '/auth_check.php';

use SimpleFinance\Models\Group;

$groupId = (int)($_GET['id'] ?? 0);
$groupModel = new Group();

if ($groupId > 0 && $groupModel->userHasAccess($groupId, (int)$_SESSION['user_id'])) {
    $_SESSION['current_group_id'] = $groupId;
}

$redirectUrl = $_SERVER['HTTP_REFERER'] ?? 'index.php';
// Tránh loop chuyển hướng
if (str_contains($redirectUrl, 'switch_group.php')) {
    $redirectUrl = 'index.php';
}

header("Location: {$redirectUrl}");
exit;
