# Danh Sách Phân Hệ & Tính Năng Hệ Thống (Modules & Features)

## 1. Bản Đồ Phân Hệ Hệ Thống (System Modules Breakdown)

| Mã Phân Hệ | Tên Phân Hệ | Trách Nhiệm Nghiệp Vụ | Trạng Thái |
|---|---|---|---|
| **MOD-AUTH** | Xác Thực & Tài Khoản | Đăng ký, đăng nhập 2 bước bàn phím số PIN ký ức, quản lý profile, API key MCP | Hoàn thành |
| **MOD-GROUP** | Nhóm Chi Tiêu & Thành Viên | Tạo nhóm, chuyển đổi nhóm, mời thành viên, phân quyền vai trò | Hoàn thành |
| **MOD-EXPENSE** | Chi Tiêu & Chia Tiền | Quản lý hóa đơn, chia tiền từng món, chia đều, chia theo số tiền/phần trăm | Hoàn thành |
| **MOD-SETTLE** | Cấn Trừ & Gạch Nợ | Tính toán bù trừ công nợ tối ưu, sinh link thanh toán công khai `pay.php`, VietQR | Hoàn thành |
| **MOD-GROUPBUY** | Sự Kiện Mua Chung | Tạo sự kiện gom mua/đặt đồ chung, chia sẻ link công khai, chọn phân loại/size, VietQR tức thì, đối soát thu tiền & chốt giao dịch | Đang triển khai (FEAT-001) |
| **MOD-MCP** | Giao Tiếp AI Agent | Cung cấp JSON-RPC / SSE tools cho AI tương tác với dữ liệu | Hoàn thành |

---

## 2. Ma Trận Phân Quyền (RBAC Matrix)

| Chức Năng | Khách Vãng Lai (Public) | Thành Viên Nhóm (Member) | Quản Trị Viên (Admin/Owner) |
|---|---|---|---|
| Mở link sự kiện Mua Chung (`event.php?token=...`) | Cho phép | Cho phép | Cho phép |
| Đăng ký chọn size & nhận VietQR thanh toán | Cho phép | Cho phép | Cho phép |
| Tạo sự kiện Mua Chung mới | Từ chối | Cho phép | Cho phép |
| Xem danh sách đơn đăng ký & Thống kê size gom hàng | Từ chối | Chỉ xem sự kiện mình tạo | Toàn quyền xem trong nhóm |
| Tick "Đã thu tiền" của người tham gia | Từ chối | Chỉ sự kiện mình tạo | Cho phép |
| Chốt sự kiện & Chuyển thành Giao dịch Nhóm | Từ chối | Chỉ sự kiện mình tạo | Cho phép |
