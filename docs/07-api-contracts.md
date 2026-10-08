# Hợp Đồng Dữ Liệu & Giao Tiếp API (API Contracts)

## 1. Giao Thức AJAX & REST Cho Mua Chung

### 1.1 Đăng ký Mua Chung Công Khai (Public API)
- **Endpoint:** `POST /ajax_action.php`
- **Action:** `action=group_buy_submit`
- **Request Payload:**
  ```json
  {
    "public_token": "a1b2c3d4e5f6...",
    "participant_name": "Nguyễn Văn A",
    "participant_phone": "0987654321",
    "note": "Lấy màu đen giúp mình",
    "items": [
      { "item_id": 10, "quantity": 2 },
      { "item_id": 11, "quantity": 1 }
    ]
  }
  ```
- **Response Success (200):**
  ```json
  {
    "success": true,
    "registration": {
      "id": 105,
      "reg_token": "9f8e7d6c...",
      "participant_name": "Nguyễn Văn A",
      "total_amount": 350000,
      "bank_info": {
        "bank_bin": "970422",
        "bank_account_no": "0123456789",
        "bank_account_name": "NGUYEN VAN ADMIN"
      },
      "qr_url": "https://img.vietqr.io/image/970422-0123456789-compact2.png?amount=350000&addInfo=MC%201%20Nguyen%20Van%20A"
    }
  }
  ```

### 1.2 Báo Đã Chuyển Khoản (Public API)
- **Endpoint:** `POST /ajax_action.php`
- **Action:** `action=group_buy_notify_paid`
- **Request Payload:** `{ "reg_token": "9f8e7d6c..." }`
- **Response Success (200):** `{ "success": true, "message": "Đã ghi nhận thông báo chuyển tiền!" }`

### 1.3 Admin Cập Nhật Trạng Thái Thu Tiền (Private API - Auth Required)
- **Endpoint:** `POST /ajax_action.php`
- **Action:** `action=group_buy_toggle_paid`
- **Request Payload:** `{ "registration_id": 105, "is_paid": 1 }`
- **Response Success (200):** `{ "success": true, "is_paid": 1, "paid_at": "2026-10-08 12:00:00" }`

### 1.4 Chốt Sự Kiện & Chuyển Thành Hóa Đơn Nhóm (Private API - Auth Required)
- **Endpoint:** `POST /ajax_action.php`
- **Action:** `action=group_buy_convert_to_transaction`
- **Request Payload:** `{ "event_id": 1 }`
- **Response Success (200):** `{ "success": true, "transaction_id": 42, "redirect_url": "transaction_detail.php?id=42" }`

---

## 2. Danh Sách Công Cụ MCP (MCP Tools Protocol)
- **`group_buy_list`**: Lấy danh sách sự kiện mua chung trong nhóm hiện tại.
- **`group_buy_create`**: Tạo sự kiện mua chung mới (kèm danh sách món/size, đơn giá, ảnh minh họa).
- **`group_buy_get`**: Lấy chi tiết sự kiện bằng `token` hoặc `event_id`, bao gồm danh sách món, các đơn đăng ký và thống kê số lượng từng size.
- **`group_buy_register`**: Đăng ký tham gia vào sự kiện mua chung cho người dùng (chọn các món/size, số lượng, điền tên) và tự động nhận tổng tiền + link mã VietQR chuyển khoản thanh toán.
