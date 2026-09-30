    </main>

    <!-- Footer Desktop -->
    <footer class="bg-white border-t border-slate-200 mt-auto py-6 text-center text-xs text-slate-500 hidden lg:block">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row justify-between items-center space-y-2 sm:space-y-0">
            <p>SimpleFinance MCP & Web Admin &bull; Chạy trên PHP 8.3 & MySQL</p>
            <p class="text-slate-400">Giao diện quản lý chi tiêu nhóm & tự động cấn trừ công nợ</p>
        </div>
    </footer>

    <!-- ============================================== -->
    <!-- THANH ĐIỀU HƯỚNG DƯỚI ĐÁY DÀNH CHO MOBILE (BOTTOM NAV) -->
    <!-- ============================================== -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200 safe-bottom shadow-[0_-4px_20px_rgba(0,0,0,0.06)]">
        <div class="grid grid-cols-5 h-16 items-center px-1">
            <!-- Tab 1: Tổng quan -->
            <a href="index.php" class="flex flex-col items-center justify-center py-1 <?= $currentPage === 'index.php' ? 'text-emerald-600 font-bold' : 'text-slate-500 hover:text-slate-900' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="<?= $currentPage === 'index.php' ? '2.5' : '1.8' ?>" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span class="text-[10px] mt-1">Tổng quan</span>
            </a>

            <!-- Tab 2: Giao dịch -->
            <a href="transactions.php" class="flex flex-col items-center justify-center py-1 <?= in_array($currentPage, ['transactions.php', 'transaction_detail.php', 'transaction_edit.php']) ? 'text-emerald-600 font-bold' : 'text-slate-500 hover:text-slate-900' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="<?= in_array($currentPage, ['transactions.php', 'transaction_detail.php', 'transaction_edit.php']) ? '2.5' : '1.8' ?>" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                <span class="text-[10px] mt-1">Giao dịch</span>
            </a>

            <!-- Nút Nổi Trung Tâm: + Thêm Chi Tiêu -->
            <div class="flex flex-col items-center justify-center -mt-5">
                <a href="transaction_create.php" class="w-13 h-13 rounded-full bg-emerald-600 text-white flex items-center justify-center shadow-lg shadow-emerald-600/40 border-4 border-slate-50 active:scale-90 transition" title="Tạo đợt chi tiêu mới">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                    </svg>
                </a>
                <span class="text-[9px] font-bold text-emerald-700 mt-1">Chi tiêu</span>
            </div>

            <!-- Tab 4: Sản phẩm & Quán -->
            <a href="products.php" class="flex flex-col items-center justify-center py-1 <?= $currentPage === 'products.php' ? 'text-emerald-600 font-bold' : 'text-slate-500 hover:text-slate-900' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="<?= $currentPage === 'products.php' ? '2.5' : '1.8' ?>" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                </svg>
                <span class="text-[10px] mt-1">Sản phẩm</span>
            </a>

            <!-- Tab 5: Menu Khác -->
            <button type="button" onclick="openMobileMoreMenu()" class="flex flex-col items-center justify-center py-1 <?= in_array($currentPage, ['members.php', 'settlements.php', 'groups.php', 'profile.php']) ? 'text-emerald-600 font-bold' : 'text-slate-500 hover:text-slate-900' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
                <span class="text-[10px] mt-1">Thêm</span>
            </button>
        </div>
    </nav>

    <!-- Bottom Sheet / Drawer Menu cho Mobile -->
    <div id="mobileMoreDrawer" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-end lg:hidden" onclick="if(event.target === this) closeMobileMoreMenu()">
        <div class="bg-white rounded-t-3xl w-full p-5 max-h-[85vh] overflow-y-auto safe-bottom shadow-2xl animate-in slide-in-from-bottom duration-200">
            <div class="w-12 h-1.5 bg-slate-300 rounded-full mx-auto mb-4"></div>
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="font-bold text-base text-slate-900">Tính Năng Khác</h3>
                    <p class="text-xs text-slate-400">Chọn mục bạn muốn thao tác</p>
                </div>
                <button type="button" onclick="closeMobileMoreMenu()" class="p-1.5 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="grid grid-cols-2 gap-3 mt-4">
                <a href="members.php" class="flex flex-col items-center p-3.5 rounded-2xl border border-slate-200 bg-slate-50/50 hover:bg-emerald-50 hover:border-emerald-200 transition">
                    <div class="w-10 h-10 rounded-xl bg-sky-100 text-sky-700 flex items-center justify-center mb-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <span class="text-xs font-bold text-slate-800">Thành Viên</span>
                    <span class="text-[10px] text-slate-400 mt-0.5">Danh sách & số dư</span>
                </a>

                <a href="settlements.php" class="flex flex-col items-center p-3.5 rounded-2xl border border-slate-200 bg-slate-50/50 hover:bg-emerald-50 hover:border-emerald-200 transition">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center mb-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <span class="text-xs font-bold text-slate-800">Lịch Sử Gạch Nợ</span>
                    <span class="text-[10px] text-slate-400 mt-0.5">Các đợt trả tiền</span>
                </a>

                <a href="groups.php" class="flex flex-col items-center p-3.5 rounded-2xl border border-slate-200 bg-slate-50/50 hover:bg-emerald-50 hover:border-emerald-200 transition">
                    <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center mb-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <span class="text-xs font-bold text-slate-800">Quản Lý Nhóm</span>
                    <span class="text-[10px] text-slate-400 mt-0.5">Tạo & cài đặt nhóm</span>
                </a>

                <a href="profile.php" class="flex flex-col items-center p-3.5 rounded-2xl border border-slate-200 bg-slate-50/50 hover:bg-emerald-50 hover:border-emerald-200 transition">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center mb-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                        </svg>
                    </div>
                    <span class="text-xs font-bold text-slate-800">Khóa API MCP</span>
                    <span class="text-[10px] text-slate-400 mt-0.5">Cài đặt kết nối AI</span>
                </a>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100">
                <a href="logout.php" class="flex items-center justify-center space-x-2 py-3 rounded-2xl bg-rose-50 text-rose-700 text-xs font-bold hover:bg-rose-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span>Đăng Xuất Tài Khoản</span>
                </a>
            </div>
        </div>
    </div>

    <script>
        function openMobileMoreMenu() {
            const drawer = document.getElementById('mobileMoreDrawer');
            if (drawer) drawer.classList.remove('hidden');
        }
        function closeMobileMoreMenu() {
            const drawer = document.getElementById('mobileMoreDrawer');
            if (drawer) drawer.classList.add('hidden');
        }
    </script>

</body>
</html>
