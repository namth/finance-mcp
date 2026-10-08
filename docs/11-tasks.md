# Kế Hoạch Triển Khai & Nhiệm Vụ (Implementation Tasks & DoD)

## 1. Danh Sách Nhiệm Vụ Chi Tiết Cho Tính Năng Mua Chung (FEAT-001)

### Milestone 1: CSDL & Core Model Backend
- [x] **Task 1.1:** Viết file migration SQL (`schema_groupbuy.sql`) tạo các bảng `group_buy_events`, `group_buy_items`, `group_buy_registrations`, `group_buy_registration_items`.
- [x] **Task 1.2:** Tạo class `SimpleFinance\Models\GroupBuy` xử lý CRUD sự kiện, lấy danh sách món, lưu đăng ký, cập nhật trạng thái thu tiền và tính năng chuyển sang Giao dịch nhóm.
- [x] **DoD Kiểm tra:** Chạy test script PHP kiểm tra kết nối CSDL và tạo thành công 1 sự kiện mẫu kèm các món/size.

### Milestone 2: Giao Diện Quản Trị & Tạo Sự Kiện (Admin Side)
- [x] **Task 2.1:** Tạo trang `group_buys.php` hiển thị danh sách các sự kiện mua chung của nhóm hiện tại, nút mở Modal tạo sự kiện với tính năng thêm động nhiều món/size.
- [x] **Task 2.2:** Cập nhật thanh điều hướng trong `includes/header.php` và `includes/footer.php` bổ sung liên kết "Mua Chung" có icon trực quan.
- [x] **Task 2.3:** Tạo trang `group_buy_detail.php` cho Admin theo dõi danh sách đăng ký, tổng hợp số lượng theo từng size/món, nút copy link công khai, công tắc tick "Đã thu tiền" realtime qua AJAX, và nút "Chốt sự kiện thành hóa đơn nhóm".
- [x] **DoD Kiểm tra:** Đăng nhập vào admin, tạo sự kiện mua áo đồng phục thành công, thấy link công khai và bảng đơn hàng trống.

### Milestone 3: Giao Diện Đăng Ký Công Khai & VietQR Tức Thì (Public Side)
- [x] **Task 3.1:** Xây dựng trang `event.php?token=...` chuẩn Mobile-first, hiển thị banner ảnh sản phẩm, thông tin sự kiện, danh mục món & các tùy chọn/size, trường nhập họ tên, SĐT, ghi chú (toàn bộ ô input nền trắng 100%).
- [x] **Task 3.2:** Tự động tính tổng tiền realtime theo số lượng lựa chọn.
- [x] **Task 3.3:** Xử lý gửi đơn đăng ký qua AJAX vào `ajax_action.php`, sinh popup/modal VietQR chuẩn kèm đầy đủ số tiền, STK người tạo, cú pháp chuyển khoản và nút "Tôi đã chuyển khoản".
- [x] **DoD Kiểm tra:** Dùng trình duyệt ẩn danh mở link, chọn 2 áo size M (150k x 2) -> hiển thị tổng tiền 300k, mã VietQR quét đúng số tiền 300,000 VND.

### Milestone 4: Tích Hợp Chuyển Hóa Đơn, MCP Tools & Kiểm Thử Toàn Diện
- [x] **Task 4.1:** Hoàn thiện luồng bấm "Chốt sự kiện & Tạo Giao Dịch": Tự động sinh `transaction` trong nhóm, phân bổ người chi tiêu là Admin, chuyển trạng thái sự kiện sang `converted`.
- [x] **Task 4.2:** Tích hợp 4 MCP Tools vào `src/McpServer.php` (`group_buy_list`, `group_buy_create`, `group_buy_get`, `group_buy_register`) cho phép AI Agent tương tác và đăng ký tham gia sự kiện trực tiếp.
- [x] **Task 4.3:** Viết bộ test tự động `tests/test_groupbuy.php` kiểm tra toàn bộ luồng từ tạo sự kiện, đăng ký, tính tiền, tick thu tiền, chốt giao dịch đến thực thi MCP tool.
- [x] **DoD Kiểm tra:** Chạy `php tests/test_groupbuy.php` pass 100%.
