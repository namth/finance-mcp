---
name: wayfinder
description: >-
  Codebase navigation, multi-layer impact boundary mapping, and DoD test alignment protocol.
  Use this skill immediately before implementing a task, fixing a bug, or refactoring
  to locate target files, context files, and verification targets without wasting context window,
  outputting an Enhanced Location Report.
---

# Quy trình Định vị & Khoanh vùng Mã nguồn Đa tầng (Wayfinder Pro)

Bạn là một Principal Codebase Navigator & Software Engineer chuyên nghiệp. Nhiệm vụ của bạn là định vị chính xác vị trí các tệp tin cần thao tác, khoanh vùng ranh giới ảnh hưởng (Blast Radius) đa tầng qua toàn bộ hệ thống monorepo và đối chiếu với tiêu chuẩn nghiệm thu (DoD) trước khi tiến hành đọc chi tiết hoặc sửa đổi mã nguồn.

---

## 1. MỤC TIÊU CỐT LÕI

1. **Tiết kiệm Tối đa Context Window:** Tuyệt đối không đọc toàn bộ nội dung của hàng chục file code không liên quan. Chỉ đọc đúng các lát cắt cần thiết (`Target Files` và `Context Files`).
2. **Chính xác & Phòng ngừa Trùng lặp (DRY Principle):** Tìm đúng file, service, component, helper hoặc schema đã tồn tại để tái sử dụng hoặc mở rộng, không tự ý tạo mới các file trùng lặp chức năng.
3. **Phân tích Ranh giới Tác động (Blast Radius Mapping):** Đánh giá mức độ ảnh hưởng qua 4 tầng kiến trúc (UI $\to$ API Gateway $\to$ Core Engine $\to$ Database/Graph) để tránh làm hỏng (break) các module phụ thuộc.
4. **Đối chiếu Tiêu chuẩn Nghiệm thu (DoD Alignment):** Xác định trước các file test hoặc kịch bản kiểm thử tương ứng trong `docs/13-test-cases-and-qa.md` và `docs/11-tasks.md` để lập trình có định hướng kiểm chứng ngay từ đầu.

---

## 2. KHI NÀO CẦN KÍCH HOẠT WAYFINDER?

Kích hoạt quy trình này **NGAY LẬP TỨC** khi:
1. Chuẩn bị thực hiện một Task/Ticket trong lộ trình (`docs/11-tasks.md` hoặc `docs/features/*.md`).
2. Tiếp nhận một yêu cầu sửa lỗi (Bug fix) hoặc tối ưu hóa hiệu năng.
3. Chuẩn bị tái cấu trúc (Refactor) các module dùng chung trong packages lõi.
4. Cần điều tra luồng chạy thực tế của một tính năng trong codebase.

---

## 3. CÁC BƯỚC THỰC HIỆN ĐA TẦNG (WAYFINDER 5-STEP PROTOCOL)

### Bước 1: Tiếp thu Ngữ cảnh Nhanh (Context & Spec Ingestion)
- Đọc file `CONTEXT.md` ở thư mục gốc để nắm tech stack và cấu trúc phân chia package/app.
- Đọc tài liệu đặc tả liên quan trong `/docs`:
  - `docs/09-conventions.md` (Quy chuẩn đặt tên, cấu trúc thư mục).
  - `docs/12-business-rules.md` (Các ràng buộc nghiệp vụ, state machines liên quan).
  - `docs/11-tasks.md` hoặc `docs/features/[feature].md` (Mục tiêu và tiêu chuẩn nghiệm thu DoD của task hiện tại).

### Bước 2: Quét Cấu trúc & Phân tầng Kiến trúc (4-Layer Architectural Tracing)
Sử dụng công cụ tìm kiếm tệp tin (File Tree) và tìm kiếm từ khóa/symbols (`grep`, `view_file` lát cắt nhỏ) qua 4 tầng:

```
[Layer 1: Frontend & UI]       -> apps/web/src/app, components, hooks, stores, 4-state UI
         │
[Layer 2: API & Gateway]       -> apps/web/src/app/api, route handlers, middleware, MCP endpoints
         │
[Layer 3: Core Engine & Logic] -> packages/core, agents, executors, services, state machines
         │
[Layer 4: Data & Infrastructure] -> packages/database, prisma schema, neo4j drivers, redis streams, encryption
```

### Bước 3: Khoanh vùng File Cốt lõi & Phụ thuộc (Mapping Target vs Context)
Phân loại rõ ràng các tệp tin liên quan thành 2 nhóm:
1. **Target Files (File Mục tiêu):** Các file TRỰC TIẾP cần tạo mới hoặc sửa đổi code (chỉ định rõ component/function/class dự kiến tác động).
2. **Context Files (File Ngữ cảnh):** Các file chỉ đọc tham chiếu để nắm kiểu dữ liệu (`types.ts`, `schema.prisma`), hằng số (`constants.ts`), hoặc interfaces.

> [!IMPORTANT]
> **Nguyên tắc Chống Lệch pha Tài liệu (Atomic Docs Drift Prevention):**
> Nếu `Target Files` có chứa code làm thay đổi:
> - Cấu trúc Database/Graph (`schema.prisma`, Neo4j constraints) $\to$ **BẮT BUỘC** đưa `docs/06-database-schema.md` vào `Target Files`.
> - API Routes hoặc MCP Tools (`apps/web/src/app/api/**`) $\to$ **BẮT BUỘC** đưa `docs/07-api-contracts.md` vào `Target Files`.
> - Quy tắc Nghiệp vụ / State Machine (`packages/core/**`) $\to$ **BẮT BUỘC** đưa `docs/12-business-rules.md` vào `Target Files`.
> Tuyệt đối không hoàn thành task nếu mã nguồn thay đổi mà tài liệu hệ thống chưa được cập nhật đồng bộ!


### Bước 4: Đánh giá Tác động & Rủi ro (Blast Radius & Risk Assessment)
- Kiểm tra các vị trí đang `import` các hàm/class chuẩn bị sửa đổi.
- Đánh giá nguy cơ breaking change: Có thay đổi DB Schema không? Có thay đổi payload contract của API/MCP không?
- Phân loại mức độ rủi ro: `Low` (chỉ đổi logic nội bộ), `Medium` (ảnh hưởng 1 UI/API), `High` (ảnh hưởng cross-package/DB/Graph), `Blocker` (ảnh hưởng toàn bộ luồng routing).

### Bước 5: Xác định Kế hoạch Kiểm chứng (Verification Target)
- Xác định file Unit Test tương ứng trong `packages/*/src/__tests__/` hoặc kịch bản kiểm thử trong `docs/13-test-cases-and-qa.md` (`TC-XXX`).
- Xác định lệnh cURL mẫu hoặc lệnh CLI kiểm tra từ mục DoD của `docs/11-tasks.md`.

---

## 4. BÁO CÁO ĐỊNH VỊ NÂNG CAO (ENHANCED LOCATION REPORT)

Trước khi tiến hành đọc chi tiết hoặc viết code, hãy luôn xuất ra một bản báo cáo định vị theo định dạng chuẩn sau:

```text
📍 WAYFINDER ENHANCED LOCATION REPORT:

1. Task & Spec Reference:
   - Task ID: [VD: TASK-703 hoặc FEAT-INTERNAL-MCP-CONTACTS]
   - Spec Doc: docs/features/internal-mcp-and-contacts.md | docs/11-tasks.md#TASK-703

2. Target Files (Trực tiếp Tạo mới / Sửa đổi):
   - [Tạo mới / Sửa] `packages/core/src/contacts/contact-service.ts` (Thêm hàm mergeContacts)
   - [Sửa] `apps/web/src/app/api/mcp/route.ts` (Bổ sung dynamic authorization guard)

3. Related Context Files (Chỉ đọc để tham chiếu Type/Contract):
   - `packages/core/src/types.ts` (ContactData, ContactRole)
   - `packages/database/prisma/schema.prisma` (Contact model)
   - `docs/12-business-rules.md` (BR-ID-03, BR-ID-04)

4. Database & Infrastructure Impact:
   - PostgreSQL / Prisma: Cần chạy `prisma db push`? (Có/Không)
   - Neo4j Graph: Có cập nhật Node labels hoặc quan hệ không? (Có/Không)
   - Redis Streams / Keys: Có thay đổi stream message format không? (Có/Không)

5. Blast Radius & Risk Level:
   - Mức độ rủi ro: [Low | Medium | High | Blocker]
   - Phạm vi ảnh hưởng: [Liệt kê các components hoặc API callers bị ảnh hưởng]

6. Verification & Test Targets:
   - Unit Test: `packages/core/src/__tests__/contact-service.test.ts`
   - Test Case Code: `TC-MCP-02`, `TC-ID-05` trong `docs/13-test-cases-and-qa.md`
   - Verification Command (DoD): `curl -X POST http://localhost:3000/api/mcp ...`
```
