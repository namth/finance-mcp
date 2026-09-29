# SimpleFinance - Quản Lý Chi Tiêu Nhóm & Công Nợ Đa Người Dùng (Multi-User MCP + Web Admin)

Hệ thống quản lý chi tiêu nhóm, phân chia chi phí từng món theo thành viên, tự động cấn trừ công nợ 2 chiều và gạch nợ kèm lưu vết lịch sử thanh toán.

Hệ thống hỗ trợ song song:
1. **Kiến trúc Multi-User & Multi-Group:** Mỗi người dùng sở hữu tài khoản riêng, có thể tham gia/tạo nhiều nhóm chi tiêu riêng biệt (Gia đình, Dự án, Du lịch...) với cơ chế cô lập dữ liệu hoàn toàn.
2. **Giao diện Web Admin bảo mật 2 bước:**
   - Bước 1: Nhập Tên đăng nhập hoặc Email.
   - Bước 2: Bàn phím số Numpad PIN code giải đố ngẫu nhiên các **mốc ký ức thời gian bí mật** được mã hóa **AES-256-CBC** trong CSDL.
3. **MCP Server có xác thực API Key:** Tích hợp trực tiếp qua HTTP JSON-RPC / SSE với Gemini, Claude Desktop, Antigravity, Cursor bằng Personal API Key riêng cho từng user.

---

## 1. Cấu Trúc Dự Án

```
simplefinance/
├── config.php                  # Cấu hình CSDL MySQL & app_key bí mật (AES-256)
├── config.example.php          # Mẫu cấu hình
├── schema.sql                  # DDL CSDL ban đầu
├── schema_multiuser.sql        # Migration DDL nâng cấp Multi-User & Multi-Group
├── server.php                  # Entrypoint MCP Server qua STDIN/STDOUT JSON-RPC 2.0
├── mcp.php                     # Entrypoint MCP Server qua HTTP & SSE có xác thực API Key
│
├── auth_check.php              # Middleware xác thực session & quản lý nhóm hoạt động
├── register.php                # Đăng ký tài khoản mới & thiết lập mốc ký ức
├── login.php                   # Đăng nhập 2 bước (Username -> Numpad PIN Ký Ức)
├── logout.php                  # Đăng xuất an toàn
├── profile.php                 # Trang cá nhân: Quản lý Personal API Key & MCP Endpoint
├── groups.php                  # Quản lý nhóm chi tiêu: Tạo nhóm, mời thành viên
├── switch_group.php            # Chuyển đổi qua lại giữa các nhóm
│
├── index.php                   # Dashboard tổng quan & Bảng tổng kết nợ theo nhóm hiện tại
├── members.php                 # Quản lý danh sách thành viên
├── products.php                # Quản lý danh mục hàng hóa / dịch vụ
├── transactions.php            # Danh sách hóa đơn chi tiêu theo nhóm
├── transaction_create.php      # Form tạo hóa đơn & chia tiền theo món
├── transaction_detail.php      # Chi tiết hóa đơn
├── settlements.php             # Lịch sử các lần gạch nợ & thanh toán theo nhóm
│
├── includes/
│   ├── header.php              # Header có Group Switcher dropdown & Avatar profile
│   └── footer.php              # Chân trang
│
├── src/                        # Core backend
│   ├── Database.php            # Kết nối PDO Singleton
│   ├── DebtManager.php         # Logic cấn trừ nợ 2 chiều & gạch nợ theo nhóm
│   ├── McpServer.php           # Xử lý các tool MCP phân quyền theo User
│   ├── Security/Crypto.php     # Mã hóa/giải mã AES-256-CBC & sinh API Key
│   └── Models/                 # User, Group, Member, Product, Transaction, Settlement
└── tests/                      # Bộ kiểm thử tự động (100% Pass)
    ├── test_multiuser.php      # Kiểm thử toàn diện Đăng ký, Mã hóa ký ức, Nhóm, Phân quyền
    └── test_mcp_auth.php       # Kiểm thử xác thực MCP Tools
```

---

## 2. Nâng Cấp Cơ Sở Dữ Liệu

Chạy migration [`schema_multiuser.sql`](schema_multiuser.sql) vào MySQL:
```bash
mysql -u root -p simplefinance < schema_multiuser.sql
```

Migration này sẽ:
- Tạo bảng `users` (tên đăng nhập, email, full_name, api_key).
- Tạo bảng `user_timeline_events` (lưu trữ ngày tháng đã mã hóa AES-256-CBC).
- Tạo bảng `groups` và `group_members`.
- Bổ sung cột `group_id` vào các bảng `products`, `transactions`, `debts`, `debt_settlements`.

---

## 3. Khởi Chạy Web Admin

Khởi chạy web server cục bộ:
```bash
php -S localhost:8000
```
Truy cập: [http://localhost:8000](http://localhost:8000)

1. Nếu chưa có tài khoản: Bấm **Đăng ký** tại `register.php`, nhập thông tin và ít nhất 3 mốc thời gian riêng của bạn.
2. Đăng nhập tại `login.php`:
   - Nhập Username/Email.
   - Nhập đáp án cho 3 câu hỏi ngẫu nhiên qua bàn phím số (Numpad).
3. Sau khi vào hệ thống:
   - Bạn có thể vào **Nhóm chi tiêu (`groups.php`)** để tạo nhóm mới hoặc mời bạn bè.
   - Vào **Cá nhân (`profile.php`)** để lấy đường dẫn MCP kết nối AI.

---

## 4. Kết Nối MCP Với Gemini / Claude / AI Agent

Mỗi tài khoản có một đường dẫn MCP Endpoint cá nhân:
```
https://financemcp.oa.io.vn/mcp.php?key=YOUR_PERSONAL_API_KEY
```

Cấu hình trong `mcpServers`:
```json
{
  "mcpServers": {
    "simplefinance": {
      "url": "https://financemcp.oa.io.vn/mcp.php?key=YOUR_PERSONAL_API_KEY"
    }
  }
}
```

Các tool MCP được hỗ trợ:
- `group_list`, `group_create`
- `member_create`, `member_list`, `member_get`, `member_update`, `member_delete`
- `product_create`, `product_list`, `product_get`, `product_update`, `product_delete`
- `transaction_create`, `transaction_list`, `transaction_get`, `transaction_complete`
- `debt_summary`, `debt_settle`, `settlement_history_list`, `debt_recalculate_all`

---

## 5. API Tự Động Kéo Code Từ GitHub (Auto Deploy Webhook)

Hệ thống cung cấp sẵn endpoint API [`deploy.php`](deploy.php) để tự động chạy `git fetch` và `git reset --hard` cập nhật mã nguồn mới nhất từ GitHub về máy chủ.

### Cấu hình trong `config.php`:
```php
'deploy' => [
    'enabled'      => true,
    'secret_token' => 'sf_dep_8f1a3b5c7e9d2f4a6b8c0e1d3f5a7b9c',
    'branch'       => 'main',
],
```

### Cách 1: Gọi thủ công bằng cURL hoặc AI Assistant (Antigravity):
```bash
# Pull code mới nhất
curl -X POST "https://financemcp.oa.io.vn/deploy.php?token=sf_dep_8f1a3b5c7e9d2f4a6b8c0e1d3f5a7b9c"

# Xem trạng thái git hiện tại trên server
curl "https://financemcp.oa.io.vn/deploy.php?token=sf_dep_8f1a3b5c7e9d2f4a6b8c0e1d3f5a7b9c&action=status"

# Pull code kèm tự động chạy migration database
curl -X POST "https://financemcp.oa.io.vn/deploy.php?token=sf_dep_8f1a3b5c7e9d2f4a6b8c0e1d3f5a7b9c&migrate=1"
```

### Cách 2: Thiết lập GitHub Webhook (Tự động pull mỗi khi Push code):
1. Vào repository GitHub của bạn: **Settings** &rarr; **Webhooks** &rarr; **Add webhook**.
2. **Payload URL:** `https://financemcp.oa.io.vn/deploy.php`
3. **Content type:** `application/json`
4. **Secret:** `sf_dep_8f1a3b5c7e9d2f4a6b8c0e1d3f5a7b9c`
5. **Which events would you like to trigger this webhook?** Chọn `Just the push event`.
6. Bấm **Add webhook**. Từ nay, mỗi khi bạn hoặc trợ lý AI push commit mới lên nhánh `main`, server sẽ tự động cập nhật ngay lập tức!

