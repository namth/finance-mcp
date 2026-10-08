# Tổng Quan Hệ Thống SimpleFinance (System Overview)

## 1. Sứ Mệnh & Mục Tiêu Dự Án
SimpleFinance là hệ thống quản lý chi tiêu nhóm, phân bổ chi phí theo món/thành viên, tự động cấn trừ công nợ tối ưu 2 chiều và tích hợp thanh toán VietQR động. Hệ thống phục vụ cả giao diện Web Admin bảo mật cao (xác thực mốc ký ức thời gian) và giao thức MCP Server cho AI Agent tự động hóa.

## 2. Kiến Trúc Phân Tầng
- **Tầng Giao Diện (Presentation Layer):** PHP Server-Side Rendering kết hợp AJAX, responsive UI, Mobile-first cho các trang công khai (Thanh toán nợ `pay.php`, Đăng ký Mua Chung `event.php`).
- **Tầng Nghiệp Vụ (Business Logic Layer):**
  - `DebtManager`: Thuật toán cấn trừ nợ 2 chiều và xử lý VietQR.
  - `GroupBuyManager` / `GroupBuy`: Quản lý vòng đời sự kiện mua chung, kiểm soát danh sách đăng ký và chuyển đổi hóa đơn.
  - `McpServer`: Cung cấp tools cho các mô hình ngôn ngữ lớn (Gemini, Claude, Antigravity).
- **Tầng Dữ Liệu (Data Layer):** PDO MySQL / SQLite, mã hóa dữ liệu nhạy cảm bằng chuẩn AES-256-CBC.
