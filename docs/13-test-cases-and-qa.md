# Kịch Bản Kiểm Thử & Tiêu Chuẩn QA (Test Cases & QA)

## 1. Ma Trận Ca Kiểm Thử Cho Tính Năng Mua Chung (FEAT-001)

| Mã Ca Test | Mục Tiêu Kiểm Thử | Dữ Liệu Đầu Vào | Kết Quả Kỳ Vọng | Trạng Thái |
|---|---|---|---|---|
| `TC-GB-01` | Tạo sự kiện mua chung với nhiều món và size | Tên sự kiện, deadline, 2 món (Áo Size S: 150k, Size M: 160k) | Lưu thành công CSDL, sinh `public_token` 32 ký tự | TODO |
| `TC-GB-02` | Mở trang công khai không cần login | Truy cập `event.php?token={valid_token}` | Tải thông tin sự kiện và danh sách món/size | TODO |
| `TC-GB-03` | Người tham gia đăng ký hợp lệ | Tên: "Trần Nam", SĐT: "0901234567", Chọn 1 Size S + 1 Size M | Lưu đăng ký, tính tổng tiền = 310,000đ, trả về link VietQR chính xác | TODO |
| `TC-GB-04` | Người tham gia bấm "Tôi đã chuyển khoản" | Gửi `reg_token` lên endpoint `group_buy_notify_paid` | `is_notified_paid = 1`, phản hồi thành công | TODO |
| `TC-GB-05` | Admin tick "Đã thu tiền" qua AJAX | Đăng nhập Admin, gửi `registration_id`, `is_paid = 1` | `is_paid = 1`, cập nhật `paid_at`, UI phản hồi xanh tức thì | TODO |
| `TC-GB-06` | Chốt sự kiện thành Giao dịch nhóm | Bấm chốt sự kiện | Tạo mới bản ghi trong `transactions`, sự kiện chuyển `status = 'converted'` | TODO |
| `TC-GB-07` | Đăng ký khi sự kiện đã đóng/chốt | Gửi form đăng ký vào sự kiện có `status = 'closed'` | Từ chối đăng ký, trả về thông báo lỗi thân thiện | TODO |

---

## 2. Lệnh Chạy Kiểm Thử Tự Động
```bash
php tests/test_groupbuy.php
```
