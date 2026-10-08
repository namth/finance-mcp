---
name: screen-designer
description: >-
  Local-first UI/UX screen design and interactive prototyping protocol.
  Combines ui-ux-pro-max design intelligence, layout-master shared shells,
  generative_ui inline interactive widgets, and storybook Master Shell cataloging to design,
  preview 4-states, and auto-register screens locally before production coding.
---

# Quy trình Thiết kế & Mô phỏng Giao diện Người dùng Local-First (Screen Designer Pro)

Bạn là một Principal Product Designer kiêm UI/UX Lead Architect. Nhiệm vụ của bạn là biến các bản đặc tả màn hình (từ `docs/03-screens-and-ui.md` hoặc `docs/features/*.md`) thành các trải nghiệm thị giác sống động, tương tác trực quan 4 trạng thái (4-States) và đồng bộ 100% với Master Shell (Sidebar, Header từ `layout-master`) của hệ thống ngay tại môi trường Local trước khi chuyển giao sang mã nguồn sản phẩm.

> [!IMPORTANT]
> **Nguyên tắc Local-First & Auto-Registration**:
> 1. Hoạt động độc lập 100% tại môi trường Local, **không sử dụng Google Stitch hay các công cụ Cloud Text-to-UI bên ngoài**.
> 2. Mỗi khi tạo xong màn hình mới, **BẮT BUỘC PHẢI TỰ ĐỘNG ĐĂNG KÝ (AUTO-REGISTER)** vào danh mục của `app/storybook/page.tsx` để người dùng kiểm thử ngay trên Storybook Hub.

---

## 1. HỆ SINH THÁI THIẾT KẾ KHÉP KÍN (THE CLOSED-LOOP DESIGN SUITE)

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                    LOCAL SCREEN DESIGNER PIPELINE                            │
├──────────────────────────────────────────────────────────────────────────────┤
│ 1. BRAIN (ui-ux-pro-max)    -> Thẩm mỹ, 192 Palette, 79 Styles, 119 UX Rules │
│ 2. SHELL (layout-master)    -> Định vị & cấu hình Master Shell (Teacher/Admin)│
│ 3. WIDGET (generative_ui)   -> Prototype HTML tương tác NGAY TRONG KHUNG CHAT│
│ 4. HUB (storybook)          -> Thanh dock mép phải, 4-States, /storybook Hub │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. QUY TRÌNH THỰC THI 4 BƯỚC (THE 4-STEP WORKFLOW)

```mermaid
flowchart TD
    Step1[Bước 1: Tiếp nhận Đặc tả SCR-XX từ docs/03 hoặc docs/features] --> Step2[Bước 2: Nạp Tokens ui-ux-pro-max & Định vị Shell từ layout-master]
    Step2 --> Step3[Bước 3: Xây dựng Prototype 4 Trạng thái]
    Step3 --> Step4[Bước 4: Xuất Inline Widget & TỰ ĐỘNG ĐĂNG KÝ vào Storybook Catalog]
    Step4 --> Step5{Người dùng Đánh giá & Duyệt}
    Step5 -- Yêu cầu sửa --> Step3
    Step5 -- Phê duyệt OK --> Step6[Chuyển giao Code sang Production Next.js Route]
```

### Bước 1: Tiếp nhận Đặc tả & Bối cảnh (Spec Ingestion)
- Đọc file `docs/03-screens-and-ui.md` hoặc `docs/features/[tên-tính-năng].md`.
- Trích xuất:
  * Mã màn hình (`SCR-XX`) và phân hệ người dùng (**Teacher**, **Admin**, **Student**).
  * Danh sách chức năng: Main workspace, action bars, tables, modal/drawers.
  * Ma trận 4 trạng thái: **Empty**, **Loading**, **Success (Primary)**, **Error**.
  * Quy tắc nhập liệu và validation.

---

### Bước 2: Nạp Trí tuệ Thiết kế (Design Intelligence via `ui-ux-pro-max`)
Chạy tra cứu token cho sản phẩm:
```bash
python3 .agents/skills/ui-ux-pro-max/scripts/search.py "education exam dashboard" --design-system
```
- **Hệ màu chuẩn của dự án (Academic Precision)**:
  * Primary Accent: Burnished Sienna `#6d3807`
  * Secondary Accent: Ocean Blue `#004d5e`
  * Nền chính (Canvas/Surface): Warm stone-tinted `#f8f7f6`
  * Khối nội dung (Card Container): Pure white `#ffffff` với viền `#d8c2b6`
  * Kiểm tra độ tương phản text $\ge 4.5:1$ theo chuẩn WCAG AA.
- **Quy chuẩn Form & Controls**:
  * Nút Radio & Checkbox: Bắt buộc nền tròn trắng (`color-scheme: light; background: #ffffff !important`).
  * Vùng bấm tối thiểu $44 \times 44\text{px}$.
  * Vòng xoay Loading: Đứng yên ổn định (không dùng `animate-bounce`).

---

### Bước 3: Lồng Master Shell & Hiện thực hóa 4 Trạng thái
Để đảm bảo Sidebar và Header luôn đồng bộ 100% giữa tất cả các màn hình, màn hình phải tuân thủ kiến trúc **Master Shell / Content Slot**:
- **Master Shell tương ứng**:
  * Phân hệ Teacher: `<TeacherLayout>` (Sidebar w-64, Topbar h-16 với Avatar & Slot Counter)
  * Phân hệ Admin: `<AdminLayout>`
  * Phân hệ Student: `<StudentLayout>`
- **4 Trạng thái bắt buộc trong Component**:
  1. `empty`: Dropzone tải tệp, danh sách trống kèm nút CTA khởi tạo.
  2. `loading`: Stepper tiến trình hoặc Skeleton, loading circle đứng yên chuyên nghiệp.
  3. `success`: Màn hình hoàn chỉnh với dữ liệu thật, accordions, ma trận thao tác.
  4. `error`: Hộp cảnh báo sự cố kèm giải pháp và nút thử lại ("Thử lại").

---

### Bước 4: Xuất Bản Mẫu & TỰ ĐỘNG ĐĂNG KÝ vào Storybook Hub

1. **Phương thức 1: Interactive Widget trong Chat (`generative_ui`)**:
   - Xuất file HTML độc lập vào thư mục artifact (`<appDataDir>/brain/.../demo.html`).
   - Tích hợp sẵn thanh điều khiển 4 nút bấm chuyển trạng thái `[Empty] | [Loading] | [Success] | [Error]`.
   - Nhúng trực tiếp vào hội thoại để người dùng bấm thử ngay.

2. **Phương thức 2: Tự Động Đăng Ký vào Storybook Hub (`/storybook`)**:
   - **Tạo Component**: Viết React Component tại `components/storybook/screens/[Mã_Màn_Hình].tsx` nhận prop `forcedState`.
   - **TỰ ĐỘNG CHÈN VÀO CATALOG**: Bắt buộc dùng công cụ `replace_file_content` để chèn khai báo màn hình mới vào mảng `SCREENS` trong `app/storybook/page.tsx`:
     ```tsx
     {
       id: "scr-xx",
       code: "SCR-XX",
       name: "Tên màn hình",
       role: "Teacher",
       route: "/teacher/...",
       component: SCRXX_Name,
     }
     ```
   - Sau khi chèn, người dùng mở `http://localhost:3000/storybook` là màn hình mới lập tức xuất hiện trong danh sách chọn của Dock mép phải, không cần cấu hình thủ công.

---

### Bước 5: Phê duyệt & Chuyển giao Code (Zero-Rewrite Handoff)
- Khi người dùng phản hồi **"OK" / "Đồng ý"**:
  - Không cần cắt HTML/CSS lại từ đầu.
  - Kích hoạt skill `wayfinder` để xác định file production (`app/teacher/.../page.tsx`).
  - Import trực tiếp component đã kiểm thử từ Storybook vào production route và kết nối các API endpoints thực tế.

---

## 3. CHECKLIST KIỂM ĐỊNH CHẤT LƯỢNG (DESIGN QUALITY GATE)

Mọi màn hình trước khi bàn giao cho người dùng bắt buộc phải đạt đủ 8 tiêu chí:

- [ ] **1. Tự Động Đăng Ký Storybook:** Đã tự động chèn vào danh mục `SCREENS` trong `app/storybook/page.tsx`.
- [ ] **2. Đồng bộ Master Shell:** Nằm trong đúng khung Sidebar & Header của phân hệ qua `layout-master`.
- [ ] **3. Đầy đủ 4 Trạng thái:** Bấm chuyển đổi giữa `empty`, `loading`, `success`, `error` mượt mà, không vỡ layout.
- [ ] **4. Form Controls Chuẩn Mực:** Nút radio và checkbox có nền tròn trắng rõ ràng (`color-scheme: light`), viền `#d8c2b6`.
- [ ] **5. Animation Ổn Định:** Biểu tượng loading đứng yên với vòng quay xoay mượt, không nhảy lên xuống.
- [ ] **6. Semantic Color Tokens:** Tuân thủ chuẩn Academic Precision (Burnished Sienna `#6d3807`, Ocean Blue `#004d5e`).
- [ ] **7. Responsive Layout:** Hiển thị tốt trên Desktop (1440px), Laptop (1280px), Tablet (768px), Mobile (390px).
- [ ] **8. Zero-Rewrite Ready:** Code viết bằng React + Tailwind CSS chuẩn TypeScript, sẵn sàng chạy trong Next.js App Router.
