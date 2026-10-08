---
name: storybook
description: >-
  Local UI Component & Screen Storybook Studio combining ui-ux-pro-max design intelligence,
  generative_ui 4-state prototyping, Master Shell synchronization, and a sleek right-edge
  floating collapsible icon dock. Use this skill to design, preview, test 4 states
  (Empty, Loading, Success, Error), and catalog interactive screens locally before committing to production code.
---

# Local Storybook Studio Protocol (Quy trình Studio Bản mẫu Local)

Bạn là một Principal Frontend Architect & Design Systems Lead. Kỹ năng này cung cấp quy trình khép kín để thiết kế, quản lý, kiểm thử và catalog hóa các màn hình giao diện người dùng (Screens & Components) trực tiếp tại môi trường Local thông qua route Next.js `app/storybook/page.tsx` và widget `generative_ui`.

---

## 1. TỔNG QUAN KIẾN TRÚC STUDIO (ARCHITECTURE OVERVIEW)

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                    LOCAL STORYBOOK STUDIO ARCHITECTURE                       │
├──────────────────────────────────────────────────────────────────────────────┤
│ 1. BRAIN: ui-ux-pro-max      -> Cung cấp Palette, Style, Typography & Spacing│
│ 2. ENGINE: generative_ui     -> Sinh mã React/Tailwind 4 trạng thái tương tác│
│ 3. SHELL: layout-master      -> Đồng bộ 100% Sidebar, Header, Breadcrumbs    │
│ 4. DOCK: Floating Icon Menu  -> Thanh điều khiển 4-State thu gọn mép phải    │
│ 5. HUB: Next.js /storybook   -> Trình duyệt tập trung SCR-01..16 tại local   │
│ 6. QA: Playwright MCP        -> Tự động chụp ảnh 2K kiểm định giao diện      │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. CHUẨN THIẾT KẾ: THANH ĐIỀU KHIỂN DOCK ICON MÉP PHẢI (RIGHT-EDGE FLOATING DOCK)

Để giải phóng 100% không gian màn hình, không bị các thanh TopBar cồng kềnh che khuất nội dung, mọi dự án áp dụng kỹ năng `storybook` bắt buộc phải sử dụng **mẫu thiết kế Thanh Dock Icon bám mép phải màn hình** (`fixed right-4 top-20`):

### Đặc điểm thiết kế cốt lõi:
1. **Chế độ Mở (Expanded Dock)**:
   - Dạng thanh dọc siêu gọn (`w-12`), nền tối kính mờ (`bg-[#211a16]/95 backdrop-blur-md`), bo tròn góc lớn (`rounded-2xl`).
   - **Chỉ hiển thị Icon**, không hiển thị text thừa trên thanh dock.
   - **Tooltip thông minh (Hover-to-Reveal)**: Khi rê chuột vào bất kỳ icon nào, nhãn chữ giải thích chi tiết sẽ bay ra ở **bên trái icon** (`absolute right-full mr-3 top-1/2 -translate-y-1/2`).
   - **Nút bấm 4 Trạng thái**:
     * `Inbox`: 1. Empty State (Dropzone rỗng)
     * `Clock`: 2. Loading Stepper (Đang xử lý)
     * `CheckCircle2`: 3. Success View (Duyệt dự thảo)
     * `AlertTriangle`: 4. Error State (Báo lỗi tệp)
   - **Công cụ bổ trợ**: Nút chọn Màn hình (`FileCode`), Nút Bật/Tắt Master Shell (`Layout`), Nút chuyển đổi Viewport đa thiết bị (`Monitor`, `Laptop`, `Tablet`, `Smartphone`).
2. **Chế độ Thu gọn (Collapsed Pill)**:
   - Khi bấm icon thu gọn (`ChevronRight`), thanh dock biến mất và chỉ còn lại duy nhất một nút dẹt ghim sát mép màn hình: **`[Sliders] 4-States`**.
   - Giúp người dùng hoặc khách hàng xem trọn vẹn 100% giao diện thực tế mà không bị vướng mắt.

---

## 3. MẪU MÃ NGUỒN CHUẨN: `StorybookShell.tsx` DÙNG CHUNG CHO MỌI DỰ ÁN

Khi khởi tạo Storybook trong bất kỳ dự án nào, hãy triển khai tệp `components/storybook/StorybookShell.tsx` theo mẫu chuẩn sau:

```tsx
"use client";

import React, { useState } from "react";
import {
  Monitor, Laptop, Tablet, Smartphone,
  ChevronRight, Sliders, CheckCircle2,
  AlertTriangle, Clock, Inbox, Layout, FileCode
} from "lucide-react";

export type ScreenState = "empty" | "loading" | "success" | "error";

export interface ScreenMeta {
  id: string;
  code: string;
  name: string;
  role: string;
  route: string;
  component: React.ComponentType<{ forcedState?: ScreenState; onStateChange?: (st: ScreenState) => void }>;
}

export default function StorybookShell({ screens, defaultScreenId }: { screens: ScreenMeta[]; defaultScreenId?: string }) {
  const [selectedScreenId, setSelectedScreenId] = useState(defaultScreenId || screens[0]?.id);
  const [activeState, setActiveState] = useState<ScreenState>("success");
  const [viewport, setViewport] = useState<"desktop" | "laptop" | "tablet" | "mobile">("desktop");
  const [withShell, setWithShell] = useState<boolean>(true);
  const [isDockOpen, setIsDockOpen] = useState<boolean>(true);
  const [showScreenDropdown, setShowScreenDropdown] = useState<boolean>(false);

  const activeScreen = screens.find((s) => s.id === selectedScreenId) || screens[0];
  const ActiveComponent = activeScreen?.component;

  const getViewportWidth = () => {
    switch (viewport) {
      case "laptop": return "max-w-[1280px]";
      case "tablet": return "max-w-[768px]";
      case "mobile": return "max-w-[390px]";
      default: return "w-full";
    }
  };

  return (
    <div className="min-h-screen bg-[#f8f7f6] flex flex-col font-sans text-[#211a16] relative">
      {/* 1. Collapsed Pill Button */}
      {!isDockOpen && (
        <button
          onClick={() => setIsDockOpen(true)}
          className="fixed right-0 top-24 z-50 bg-[#211a16] hover:bg-[#362f2a] text-[#ffb782] p-2.5 rounded-l-2xl shadow-2xl border-l border-y border-[#52443a] transition-all flex flex-col items-center gap-1 cursor-pointer group hover:pr-3.5"
          title="Mở thanh điều khiển Storybook"
        >
          <Sliders className="w-5 h-5 text-[#ffb782] group-hover:scale-110 transition-transform" />
          <span className="text-[10px] font-bold text-white uppercase tracking-wider [writing-mode:vertical-lr] rotate-180 py-1">
            4-States
          </span>
        </button>
      )}

      {/* 2. Expanded Vertical Icon Dock */}
      {isDockOpen && (
        <aside className="fixed right-4 top-20 z-50 bg-[#211a16]/95 backdrop-blur-md text-[#fff8f5] p-2 rounded-2xl shadow-2xl border border-[#362f2a] flex flex-col items-center gap-2 transition-all">
          <div className="flex items-center justify-between w-full px-1 pb-1 border-b border-[#362f2a]/60">
            <span className="text-[10px] font-bold uppercase text-[#ffb782] px-1 font-mono">SB Hub</span>
            <button onClick={() => setIsDockOpen(false)} className="p-1 rounded-lg text-[#857469] hover:text-white transition">
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>

          {/* Screen Picker with Hover Tooltip & Popover */}
          <div className="relative group">
            <button onClick={() => setShowScreenDropdown(!showScreenDropdown)} className="p-2 rounded-xl text-[#d8c2b6] hover:bg-[#362f2a] hover:text-white">
              <FileCode className="w-5 h-5" />
            </button>
            <div className="absolute right-full mr-3 top-1/2 -translate-y-1/2 px-2.5 py-1.5 rounded-xl bg-[#211a16] text-[#fff8f5] border border-[#52443a] text-xs font-semibold whitespace-nowrap shadow-xl opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
              Chọn Màn Hình ({activeScreen.code})
            </div>
            {showScreenDropdown && (
              <div className="absolute right-full mr-3 top-0 w-64 bg-[#211a16] border border-[#52443a] rounded-2xl shadow-2xl p-2 z-50">
                {screens.map((s) => (
                  <button key={s.id} onClick={() => { setSelectedScreenId(s.id); setShowScreenDropdown(false); }} className={`w-full text-left px-3 py-2 rounded-xl text-xs ${selectedScreenId === s.id ? "bg-[#6d3807] text-[#ffb782] font-bold" : "text-[#d8c2b6] hover:bg-[#362f2a]"}`}>
                    {s.code}: {s.name}
                  </button>
                ))}
              </div>
            )}
          </div>

          <div className="w-6 h-px bg-[#362f2a]" />

          {/* 4-State Icons */}
          {[
            { id: "empty", icon: Inbox, label: "1. Empty State (Dropzone rỗng)" },
            { id: "loading", icon: Clock, label: "2. Loading Stepper (Đang xử lý)" },
            { id: "success", icon: CheckCircle2, label: "3. Success View (Duyệt dự thảo)" },
            { id: "error", icon: AlertTriangle, label: "4. Error State (Báo lỗi tệp)" }
          ].map(({ id, icon: Icon, label }) => (
            <div key={id} className="relative group">
              <button onClick={() => setActiveState(id as ScreenState)} className={`p-2 rounded-xl transition ${activeState === id ? (id === "error" ? "bg-[#ba1a1a] text-white ring-2 ring-rose-400" : "bg-[#6d3807] text-[#ffb782] ring-2 ring-[#ffb782]/40") : "text-[#d8c2b6] hover:bg-[#362f2a] hover:text-white"}`}>
                <Icon className="w-5 h-5" />
              </button>
              <div className="absolute right-full mr-3 top-1/2 -translate-y-1/2 px-2.5 py-1.5 rounded-xl bg-[#211a16] text-[#fff8f5] border border-[#52443a] text-xs font-semibold whitespace-nowrap shadow-xl opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                {label}
              </div>
            </div>
          ))}

          <div className="w-6 h-px bg-[#362f2a]" />

          {/* Master Shell Toggle */}
          <div className="relative group">
            <button onClick={() => setWithShell(!withShell)} className={`p-2 rounded-xl transition ${withShell ? "text-[#ffb782] bg-[#362f2a]" : "text-[#857469] hover:bg-[#362f2a]"}`}>
              <Layout className="w-5 h-5" />
            </button>
            <div className="absolute right-full mr-3 top-1/2 -translate-y-1/2 px-2.5 py-1.5 rounded-xl bg-[#211a16] text-[#fff8f5] border border-[#52443a] text-xs font-semibold whitespace-nowrap shadow-xl opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
              {withShell ? "Đang bật Master Shell (Click để tắt)" : "Đang tắt Master Shell (Click để bật)"}
            </div>
          </div>

          <div className="w-6 h-px bg-[#362f2a]" />

          {/* Viewports */}
          {[
            { id: "desktop", icon: Monitor, label: "Desktop (100%)" },
            { id: "laptop", icon: Laptop, label: "Laptop (1280px)" },
            { id: "tablet", icon: Tablet, label: "Tablet (768px)" },
            { id: "mobile", icon: Smartphone, label: "Mobile (390px)" }
          ].map(({ id, icon: Icon, label }) => (
            <div key={id} className="relative group">
              <button onClick={() => setViewport(id as any)} className={`p-2 rounded-xl transition ${viewport === id ? "bg-[#6d3807] text-[#ffb782]" : "text-[#857469] hover:bg-[#362f2a]"}`}>
                <Icon className="w-4 h-4" />
              </button>
              <div className="absolute right-full mr-3 top-1/2 -translate-y-1/2 px-2.5 py-1.5 rounded-xl bg-[#211a16] text-[#fff8f5] border border-[#52443a] text-xs font-semibold whitespace-nowrap shadow-xl opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                {label}
              </div>
            </div>
          ))}
        </aside>
      )}

      {/* 3. Main Workspace */}
      <main className="flex-1 flex justify-center items-start overflow-x-auto w-full">
        <div className={`${getViewportWidth()} transition-all duration-300 ${viewport !== "desktop" ? "border-2 border-dashed border-[#857469]/50 p-2 rounded-3xl bg-white shadow-xl my-6" : ""}`}>
          {ActiveComponent && <ActiveComponent forcedState={activeState} onStateChange={(st) => setActiveState(st)} />}
        </div>
      </main>
    </div>
  );
}
```

---

## 4. QUY TẮC TỰ ĐỘNG ĐĂNG KÝ VÀO CATALOG (AUTO-REGISTRATION RULE)

> [!IMPORTANT]
> **Quy tắc bắt buộc khi tạo màn hình mới (`screen-designer`)**:
> Mỗi khi `screen-designer` tạo xong một component màn hình con (ví dụ `components/storybook/screens/SCRXX_Name.tsx`), nó **BẮT BUỘC PHẢI TỰ ĐỘNG CHÈN (INSERT)** định nghĩa màn hình đó vào danh mục `SCREENS` trong file `app/storybook/page.tsx`. Người dùng không cần phải copy hay import thủ công.

---

## 5. CHECKLIST KIỂM ĐỊNH CHẤT LƯỢNG STORYBOOK (QUALITY GATE)

- [ ] **1. Thanh Dock Mép Phải Chuẩn:** Dạng icon-only, tooltip bay sang trái mượt mà, thu gọn thành công thành viên thuốc nhỏ.
- [ ] **2. Đồng bộ Master Shell:** Màn hình lồng trong đúng layout (Teacher, Admin, Student) mà không bị lệch layout.
- [ ] **3. 4 Trạng thái Hoàn Chỉnh:** Chuyển đổi giữa `empty`, `loading`, `success`, `error` không bị crash DOM.
- [ ] **4. Responsive Test:** Kiểm tra đủ 4 nấc Desktop, Laptop, Tablet, Mobile.
- [ ] **5. Form Controls Chuẩn Mực:** Nút radio và checkbox có nền trắng rõ ràng (`color-scheme: light`).
- [ ] **6. Auto-Registered:** Đã xuất hiện ngay trong danh sách chọn của Hub `/storybook`.
