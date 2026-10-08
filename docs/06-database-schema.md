# Cấu Trúc Cơ Sở Dữ Liệu (Database Schema)

## 1. Danh Mục Các Bảng Hiện Hữu
- `users`: Tài khoản người dùng, email, thông tin tài khoản ngân hàng thụ hưởng (`bank_bin`, `bank_account_no`, `bank_account_name`).
- `user_timeline_events`: Mốc thời gian giải đố bảo mật (AES-256-CBC).
- `groups`: Nhóm chi tiêu.
- `group_members`: Thành viên nhóm & phân quyền.
- `members`: Thành viên nội bộ trong nhóm chi tiêu.
- `products`: Danh mục món hàng chi tiêu.
- `transactions`, `transaction_items`, `transaction_item_members`: Hóa đơn & chi tiết chia tiền.
- `debts`, `debt_settlements`: Công nợ và lịch sử thanh toán.

---

## 2. Bổ Sung Bảng Mới Cho Phân Hệ Mua Chung (FEAT-001)

### 2.1 Bảng `group_buy_events`
Lưu trữ thông tin sự kiện mua chung:
- `id`: Khóa chính (INT UNSIGNED).
- `group_id`: Thuộc nhóm nào.
- `creator_id`: Người tạo sự kiện (Admin).
- `title`: Tên sự kiện (VD: "Đặt áo đồng phục công ty").
- `description`: Mô tả chi tiết, hướng dẫn quy đổi size.
- `image_url`: Đường dẫn hình ảnh sản phẩm / mẫu áo / bảng size.
- `public_token`: Mã token 32 ký tự ngẫu nhiên dùng để truy cập link công khai.
- `bank_bin`, `bank_account_no`, `bank_account_name`: Thông tin STK nhận tiền của người tạo.
- `deadline`: Hạn chót đăng ký.
- `status`: `open` (đang mở), `closed` (đã đóng đăng ký), `converted` (đã chốt thành hóa đơn nhóm).
- `transaction_id`: Khóa ngoại trỏ đến `transactions.id` sau khi chốt.

### 2.2 Bảng `group_buy_items`
Lưu danh sách món hàng & các biến thể/size:
- `id`: Khóa chính.
- `event_id`: Liên kết sự kiện.
- `name`: Tên món (VD: "Áo thun cổ tròn").
- `option_name`: Tên phân loại/size (VD: "Size S", "Size M", "Size XL", "Cơm sườn đặc biệt").
- `price`: Đơn giá (DECIMAL 15,2).

### 2.3 Bảng `group_buy_registrations`
Lưu lượt đăng ký của người tham gia:
- `id`: Khóa chính.
- `event_id`: Liên kết sự kiện.
- `participant_name`: Họ tên người đặt.
- `participant_phone`: Số điện thoại liên hệ.
- `note`: Ghi chú riêng.
- `total_amount`: Tổng tiền cần thanh toán.
- `is_notified_paid`: Cờ đánh dấu người dùng đã bấm "Tôi đã chuyển khoản" (0 hoặc 1).
- `is_paid`: Cờ Admin tick xác nhận đã nhận đủ tiền (0 hoặc 1).
- `paid_at`: Thời gian xác nhận thu tiền.
- `reg_token`: Mã định danh đơn hàng riêng biệt.

### 2.4 Bảng `group_buy_registration_items`
Chi tiết món và số lượng từng người chọn:
- `id`: Khóa chính.
- `registration_id`: Khóa ngoại đơn đăng ký.
- `item_id`: Khóa ngoại món hàng.
- `quantity`: Số lượng đặt.
- `unit_price`: Đơn giá tại thời điểm đặt.
- `subtotal`: Thành tiền = `quantity * unit_price`.
