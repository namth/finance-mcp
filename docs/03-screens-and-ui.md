# Danh Mục Màn Hình & Trạng Thái Giao Diện (Screens & UI Specs)

## 1. Bảng Kiểm Kê & Trạng Thái Tiến Độ Màn Hình

| Mã Màn Hình | Tên Màn Hình & Đường Dẫn | Phân Hệ | Vai Trò | Trạng Thái Thiết Kế | Trạng Thái Code |
|---|---|---|---|---|---|
| `SCR-AUTH-01` | Đăng nhập (`login.php`) | MOD-AUTH | Guest | APPROVED | DONE |
| `SCR-AUTH-02` | Đăng ký (`register.php`) | MOD-AUTH | Guest | APPROVED | DONE |
| `SCR-DASH-01` | Bảng điều khiển nhóm (`index.php`) | MOD-GROUP | Member | APPROVED | DONE |
| `SCR-TX-01` | Tạo hóa đơn chia tiền (`transaction_create.php`) | MOD-EXPENSE | Member | APPROVED | DONE |
| `SCR-PAY-01` | Thanh toán nợ công khai (`pay.php`) | MOD-SETTLE | Public | APPROVED | DONE |
| `SCR-GB-01` | Danh sách Sự kiện Mua Chung (`group_buys.php`) | MOD-GROUPBUY | Member/Admin | APPROVED | DONE |
| `SCR-GB-02` | Chi tiết Quản lý & Thu tiền Mua Chung (`group_buy_detail.php`) | MOD-GROUPBUY | Creator/Admin | APPROVED | DONE |
| `SCR-GB-03` | Trang Đăng ký Mua Chung Công Khai (`event.php`) | MOD-GROUPBUY | Public Guest | APPROVED | DONE |

---

## 2. Sơ Đồ Điều Hướng Màn Hình (Navigation Flowchart)

```mermaid
flowchart TD
    subgraph Quản trị Nhóm (Authenticated)
        NAV[Thanh Menu Chính] --> GB_LIST["SCR-GB-01: group_buys.php (Danh sách Mua Chung)"]
        GB_LIST -->|Bấm Tạo mới| GB_MODAL["Modal / Form Tạo Sự Kiện Mua Chung"]
        GB_LIST -->|Bấm Quản lý sự kiện| GB_DET["SCR-GB-02: group_buy_detail.php (Quản lý Đơn & Thu Tiền)"]
        GB_DET -->|Chốt sự kiện| TX_DET["transaction_detail.php (Hóa đơn nhóm)"]
    end

    subgraph Công Khai (Public Flow)
        LINK["Link Chia Sẻ: event.php?token=..."] --> GB_PUB["SCR-GB-03: event.php (Đăng ký & Chọn Size)"]
        GB_PUB -->|Bấm Đăng ký| QR_CARD["Modal / Card VietQR Thanh Toán Tức Thì"]
        QR_CARD -->|Bấm 'Tôi đã chuyển khoản'| NOTIFIED["Thông báo Thành công & Chờ xác nhận"]
    end

    GB_DET -.->|Sao chép Link| LINK
```
