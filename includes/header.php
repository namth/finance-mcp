<?php
require_once __DIR__ . '/../auth_check.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$currentGroupId = (int)($_SESSION['current_group_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="vi" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'SimpleFinance - Quản Lý Chi Tiêu & Công Nợ' ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#059669',
                        secondary: '#10B981',
                    }
                }
            }
        }
    </script>
    <style>
        /* Ngăn iOS Safari tự động phóng to (zoom) khi focus vào input/select */
        @media screen and (max-width: 768px) {
            input[type="text"], input[type="number"], input[type="search"], input[type="password"], input[type="email"], input[type="url"], select, textarea {
                font-size: 16px !important;
            }
        }
        /* Hỗ trợ khoảng an toàn cho iPhone (tai thỏ / thanh home gạch đáy) */
        .safe-bottom {
            padding-bottom: calc(0.5rem + env(safe-area-inset-bottom, 0px));
        }
        .touch-scroll {
            -webkit-overflow-scrolling: touch;
        }
    </style>
</head>
<body class="h-full flex flex-col font-sans text-slate-800 bg-slate-50 min-h-screen">

    <!-- Top Navigation Bar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-2xs">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
            <div class="flex justify-between h-15 sm:h-16 items-center">
                <!-- Logo & Brand & Group Selector -->
                <div class="flex items-center space-x-2 sm:space-x-4">
                    <a href="index.php" class="flex-shrink-0 flex items-center space-x-2">
                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-emerald-600 flex items-center justify-center text-white shadow-md shadow-emerald-600/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <span class="font-black text-sm sm:text-base text-slate-900 tracking-tight hidden xs:inline-block">SimpleFinance</span>
                    </a>

                    <!-- Dropdown Chuyển Đổi Nhóm Làm Việc (Hỗ trợ chạm click mượt trên Mobile) -->
                    <div class="relative" id="groupDropdownContainer">
                        <button type="button" 
                                id="groupDropdownBtn" 
                                onclick="toggleGroupDropdown(event)"
                                class="inline-flex items-center space-x-1 sm:space-x-1.5 px-2.5 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-bold hover:bg-emerald-100 transition active:scale-95">
                            <svg class="w-3.5 h-3.5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <span class="max-w-[100px] sm:max-w-[180px] truncate"><?= htmlspecialchars($currentGroup['name'] ?? 'Chưa chọn nhóm') ?></span>
                            <svg class="w-3 h-3 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- Menu dropdown groups -->
                        <div id="groupDropdownMenu" class="hidden absolute left-0 mt-1.5 w-64 rounded-2xl bg-white border border-slate-200 shadow-xl py-2 z-50">
                            <div class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">Chọn nhóm làm việc:</div>
                            <div class="max-h-52 overflow-y-auto">
                                <?php foreach ($userGroups as $ug): ?>
                                    <a href="switch_group.php?id=<?= $ug['id'] ?>" class="flex items-center justify-between px-3 py-2.5 text-xs hover:bg-slate-50 transition <?= (int)$ug['id'] === $currentGroupId ? 'font-bold text-emerald-700 bg-emerald-50/70' : 'text-slate-700' ?>">
                                        <span class="truncate"><?= htmlspecialchars($ug['name']) ?></span>
                                        <?php if ((int)$ug['id'] === $currentGroupId): ?>
                                            <span class="w-2 h-2 rounded-full bg-emerald-600 flex-shrink-0"></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <div class="border-t border-slate-100 mt-1 pt-1.5 px-2">
                                <a href="groups.php" class="block px-2 py-2 rounded-xl text-xs font-semibold text-emerald-700 hover:bg-emerald-50 text-center transition">
                                    + Quản lý & Tạo nhóm mới
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Nav Links -->
                    <nav class="hidden lg:flex space-x-1 items-center">
                        <a href="index.php" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $currentPage === 'index.php' ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            Tổng Quan
                        </a>
                        <a href="transactions.php" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= in_array($currentPage, ['transactions.php', 'transaction_create.php', 'transaction_detail.php', 'transaction_edit.php']) ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            Giao Dịch
                        </a>
                        <a href="products.php" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $currentPage === 'products.php' ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            Sản Phẩm & Quán
                        </a>
                        <a href="members.php" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $currentPage === 'members.php' ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            Thành Viên
                        </a>
                        <a href="settlements.php" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $currentPage === 'settlements.php' ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            Gạch Nợ
                        </a>
                        <a href="group_buys.php" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= in_array($currentPage, ['group_buys.php', 'group_buy_detail.php']) ? 'bg-slate-100 text-slate-900 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            Mua Chung
                        </a>
                        <a href="groups.php" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $currentPage === 'groups.php' ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            Nhóm
                        </a>
                    </nav>
                </div>

                <!-- Right Actions -->
                <div class="flex items-center space-x-2">
                    <a href="transaction_create.php" class="hidden sm:inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-bold rounded-xl shadow-xs text-white bg-emerald-600 hover:bg-emerald-700 transition">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Thêm Chi Tiêu
                    </a>

                    <!-- Profile Link -->
                    <a href="profile.php" class="inline-flex items-center space-x-1.5 px-2.5 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 transition" title="Tài khoản & Khóa MCP">
                        <div class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] flex items-center justify-center font-bold">
                            <?= mb_substr($currentUser['full_name'], 0, 1, 'UTF-8') ?>
                        </div>
                        <span class="hidden md:inline max-w-[90px] truncate"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                    </a>

                    <a href="logout.php" class="inline-flex items-center p-1.5 sm:px-2.5 sm:py-1.5 border border-slate-200 text-xs font-medium rounded-xl text-slate-600 hover:text-rose-600 hover:bg-rose-50 transition" title="Đăng xuất">
                        <svg class="w-4 h-4 sm:mr-1 text-slate-400 hover:text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        <span class="hidden sm:inline">Thoát</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <script>
        function toggleGroupDropdown(e) {
            e.stopPropagation();
            const menu = document.getElementById('groupDropdownMenu');
            menu.classList.toggle('hidden');
        }

        document.addEventListener('click', function(e) {
            const container = document.getElementById('groupDropdownContainer');
            const menu = document.getElementById('groupDropdownMenu');
            if (menu && !menu.classList.contains('hidden')) {
                if (container && !container.contains(e.target)) {
                    menu.classList.add('hidden');
                }
            }
        });
    </script>

    <!-- Main Content Area (Đã chừa padding bottom 24 để không bị thanh điều hướng mobile che) -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-3.5 sm:px-6 lg:px-8 py-4 sm:py-8 pb-28 lg:pb-8">
