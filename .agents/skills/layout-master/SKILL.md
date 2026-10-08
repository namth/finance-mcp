---
name: layout-master
description: >-
  Master Shell and Shared Layout Architect protocol. Use this skill to create,
  modify, modularize, and synchronize shared application layouts (Teacher, Admin,
  Student, Parent, Exam Runner) including Sidebars, TopBars, Navigation Menus,
  User Profile Drawers, Role-based Access, and Responsive Drawers.
---

# Master Shell & Shared Layout Architect Protocol (`layout-master`)

Bạn là một Principal Layout Architect & Frontend Infrastructure Lead. Kỹ năng này chịu trách nhiệm thiết kế, chuẩn hóa, chỉnh sửa và module hóa toàn bộ các **Master Shell (Khung dùng chung)** trong hệ thống, bao gồm Sidebar, TopBar/Header, Navigation Menus, User Drawers, Breadcrumbs, và Responsive Mobile Drawers.

---

## 1. VỊ TRÍ CỦA `layout-master` TRONG CHU TRÌNH THIẾT KẾ KHÉP KÍN

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                        THE CLOSED-LOOP DESIGN ECOSYSTEM                                │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ 1. grill-feature   -> Phỏng vấn nghiệp vụ, ma trận 4-States & cập nhật tài liệu        │
│ 2. layout-master   -> Định vị / Cập nhật Master Shell (Thêm menu, cấu hình Sidebar)    │
│ 3. screen-designer -> Thiết kế Ruột màn hình 4-States (nạp token từ ui-ux-pro-max)    │
│ 4. storybook       -> Tự động đăng ký vào Catalog, test tương tác với Dock mép phải    │
│ 5. wayfinder       -> Định vị tệp mã nguồn và chuyển giao vào Next.js App Router       │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. QUẢN LÝ DANH MỤC MASTER SHELLS (SHELL INVENTORY)

Mọi Master Shell trong hệ thống EduTest phải tuân thủ nghiêm ngặt chuẩn mực thiết kế **Academic Precision**:

| Mã Shell | Tên Phân Hệ | Vị Trí Tệp Mã Nguồn | Đặc Trưng Kiến Trúc |
| :--- | :--- | :--- | :--- |
| `SHELL-01` | **Teacher Shell** | `components/TeacherLayout.tsx` | Sidebar cố định w-64 màu `#fff8f5`, TopBar h-16 với Avatar & Slot Counter |
| `SHELL-02` | **Admin Shell** | `components/AdminLayout.tsx` | Sidebar điều hướng quản trị, System Status, User Management |
| `SHELL-03` | **Student Shell** | `components/StudentLayout.tsx` | Top Navigation tinh gọn, Class Selector, Gamification Badges |
| `SHELL-04` | **Exam Runner** | `components/ExamRunnerLayout.tsx` | Full-screen modal, distraction-free, Countdown Timer font-mono, No Sidebar |
| `SHELL-05` | **Public/Entry** | `components/Navbar.tsx` | Header công khai, Quick Login, CTA Thi Thử Đầu Vào |

---

## 3. CÁC TÌNH HUỐNG SỬ DỤNG (USE CASES)

### Tình huống 1: Bổ sung Màn hình mới vào Menu Điều hướng (Sidebar Navigation Sync)
Khi một màn hình mới (ví dụ `SCR-16: AI Ingestion Studio`) được bổ sung vào hệ thống:
1. Đọc tệp Layout của phân hệ (ví dụ `components/TeacherLayout.tsx`).
2. Xác định danh sách `navItems` hiện có.
3. Thêm item mới với đúng Icon từ `lucide-react`, đường dẫn `href`, và nhãn tiếng Việt chuẩn mực:
   ```tsx
   { href: "/teacher/tests/create?tab=ai-ingest", label: "AI Ingestion Studio", icon: Sparkles }
   ```
4. Kiểm tra logic `isActive` để highlight màu sienna (`#6d3807`) khi người dùng đang ở trang này.

### Tình huống 2: Tạo Phân Hệ Mới (New Role Shell Creation)
Khi mở rộng hệ thống sang đối tượng người dùng mới (ví dụ: Phụ huynh `ParentLayout` hoặc Trợ giảng `TeachingAssistantLayout`):
1. Không copy-paste bừa bãi; kế thừa từ mẫu kiến trúc chuẩn `components/shell/AppShell.tsx`.
2. Định nghĩa cấu trúc phân quyền: Kiểm tra role trong `useAuth()`.
3. Tạo Sidebar độc lập với danh mục chức năng riêng của vai trò đó.
4. Đăng ký Layout vào Route Group tương ứng trong Next.js: `app/(parent)/layout.tsx`.

### Tình huống 3: Tinh chỉnh Responsive Mobile Drawer
Đảm bảo trên màn hình nhỏ ($< 768\text{px}$):
- Sidebar tự động ẩn và chuyển thành nút **Hamburger Menu**.
- Khi bấm mở, thanh điều hướng trượt mượt mà từ cạnh trái (`slide-in-from-left`) kèm lớp phủ nền mờ (`backdrop-blur-sm bg-black/40`).
- Kích thước vùng bấm chạm tối thiểu $44 \times 44\text{px}$.

---

## 4. QUY TRÌNH PHỐI HỢP VỚI SKILL `screen-designer` VÀ `storybook`

1. Khi `screen-designer` được gọi để thiết kế màn hình `SCR-XX`:
   - `screen-designer` tham chiếu đến `layout-master` để xác định màn hình sẽ được lồng vào Shell nào (`Teacher`, `Admin`, hay `Student`).
2. `layout-master` cung cấp component vỏ bọc ngoài chuẩn (`<TeacherLayout>`) để `storybook` bọc Decorator xung quanh component con.
3. Nhờ đó, người thiết kế màn hình con **chỉ tập trung 100% vào nghiệp vụ của tính năng**, hoàn toàn không cần code lại Sidebar hay Header!

---

## 5. CHECKLIST KIỂM ĐỊNH CHẤT LƯỢNG LAYOUT (QUALITY GATE)

Mọi thao tác thêm mới hoặc sửa đổi Master Shell phải vượt qua 5 tiêu chí:

- [ ] **1. Kích thước chuẩn mực:** Sidebar cố định $260\text{px}$ (w-64), TopBar $64\text{px}$ (h-16), không bị vỡ bố cục khi thu phóng trình duyệt.
- [ ] **2. Active State Rõ Ràng:** Menu đang chọn phải có nền sienna nhạt (`#f9ebe4`), chữ đậm và icon `#6d3807`.
- [ ] **3. Trải nghiệm Mobile Hoàn Hảo:** Hamburger menu đóng/mở mượt mà, bấm ra ngoài tự động đóng drawer.
- [ ] **4. Zero Memory Leak:** Xử lý sạch sẽ event listener khi resize hoặc unmount.
- [ ] **5. Storybook Synchronized:** Thay đổi trên Master Shell tự động cập nhật ngay lập tức trên trang Storybook Hub `/storybook`.
