# SimpleFinance - Kiến Trúc Tổng Thể & Ngữ Cảnh Dự Án (CONTEXT.md)

## 1. Giới thiệu Dự án
SimpleFinance là ứng dụng web PHP hiện đại dành cho quản lý chi tiêu nhóm, tính toán cấn trừ nợ 2 chiều, theo dõi lịch sử thanh toán qua VietQR, tích hợp MCP Server cho các AI Assistant (Gemini, Claude Desktop, Antigravity, Cursor).

## 2. Công nghệ Cốt lõi
- **Ngôn ngữ:** PHP 8.x (thuần, kiến trúc OOP clean, không framework nặng nề).
- **Cơ sở dữ liệu:** MySQL (InnoDB) / SQLite (hỗ trợ cho test môi trường độc lập), kết nối qua PDO Singleton (`SimpleFinance\Database`).
- **Mã hóa:** AES-256-CBC (`SimpleFinance\Security\Crypto`).
- **Frontend UI:** Tailwind CSS / Bootstrap CSS hiện đại, Responsive UI, Hỗ trợ tạo VietQR chuẩn NAPAS 24/7.
- **Tích hợp AI:** Giao thức MCP (Model Context Protocol) JSON-RPC 2.0 (`server.php`, `mcp.php`, `src/McpServer.php`).

## 3. Bản đồ Phân hệ & Tính năng Chính
1. **Multi-User & Multi-Group:** Tài khoản riêng biệt, xác thực bảo mật 2 bước (mốc ký ức thời gian), chuyển đổi nhóm (`switch_group.php`).
2. **Quản lý Thành viên (Members):** Liên kết thành viên trong nhóm với tài khoản user hoặc thành viên nội bộ.
3. **Quản lý Món hàng (Products):** Danh mục sản phẩm, đơn giá mặc định, nơi mua (`Place`).
4. **Hóa đơn & Chia tiền (Transactions):** Tạo hóa đơn, chia tiền theo món (items split), chia đều hoặc chia theo phần trăm/số tiền.
5. **Cấn trừ Nợ & Thanh toán (Debts & Settlements):** Thuật toán tối ưu dòng nợ 2 chiều, sinh link thanh toán công khai `pay.php?token=...`, tạo mã VietQR tự động.
6. **Sự kiện Mua Chung (Group Buy Events - FEAT-001):** Tạo sự kiện mua chung linh hoạt, chia sẻ link công khai cho mọi người đăng ký, tự động tính tổng tiền & sinh VietQR, kiểm soát trạng thái thu tiền và chốt hóa đơn vào nhóm.

## 4. Danh mục Tài liệu Kiến trúc (`/docs`)
- [`01-overview.md`](file:///Users/namtran/Local%20Sites/simplefinance/docs/01-overview.md): Tổng quan kiến trúc & giải pháp
- [`02-modules-and-features.md`](file:///Users/namtran/Local%20Sites/simplefinance/docs/02-modules-and-features.md): Bảng phân rã tính năng & RBAC
- [`03-screens-and-ui.md`](file:///Users/namtran/Local%20Sites/simplefinance/docs/03-screens-and-ui.md): Danh mục màn hình, luồng UX & 4 trạng thái
- [`06-database-schema.md`](file:///Users/namtran/Local%20Sites/simplefinance/docs/06-database-schema.md): Cấu trúc bảng CSDL & quan hệ
- [`07-api-contracts.md`](file:///Users/namtran/Local%20Sites/simplefinance/docs/07-api-contracts.md): Hợp đồng API REST, AJAX & MCP Tools
- [`11-tasks.md`](file:///Users/namtran/Local%20Sites/simplefinance/docs/11-tasks.md): Lộ trình triển khai & Tiêu chuẩn nghiệm thu (DoD)
- [`12-business-rules.md`](file:///Users/namtran/Local%20Sites/simplefinance/docs/12-business-rules.md): Quy tắc nghiệp vụ & State Machine
- [`13-test-cases-and-qa.md`](file:///Users/namtran/Local%20Sites/simplefinance/docs/13-test-cases-and-qa.md): Kịch bản kiểm thử tự động & QA
- [`features/group-buy-events.md`](file:///Users/namtran/Local%20Sites/simplefinance/docs/features/group-buy-events.md): Hồ sơ đặc tả chi tiết Tính năng Mua Chung
