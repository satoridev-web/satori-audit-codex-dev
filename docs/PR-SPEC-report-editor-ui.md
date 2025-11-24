# PR Spec: Implement Report Editor UI (Template v2) — SATORI Audit

**Target Branch:**  
`main`

**New Feature Branch:**  
`codex/report-editor-ui`

**Summary:**  
Implement the SATORI Audit **Report Editor UI** (Template v2), providing a clean, structured interface for editing report metadata and a new version of the report template.  
This PR introduces UI enhancements for report editing but **does not** implement Notifications, PDF output, Automation, or history retrieval.  

The purpose is to give users a streamlined, modern editor layout.

---

## Goals

- Introduce **new Report Editor screen** for the SATORI Audit CPT.
- Provide a clear form for editing:
  - Report title
  - Report date
  - Summary text
  - Additional metadata
- Introduce Template v2:
  - Cleaner header
  - New typography
  - Better spacing
  - Prepared for future PDF improvements
- Keep full backward compatibility with Template v1.

No settings are altered. No PDF logic added.

---

## Changes Required

### 1. Create New Editor Screen  
File:  
`admin/screens/class-satori-audit-screen-editor.php` (if not present)  

Should include:

- `render()` method
- Capability enforcement:
  - Users must have `capability_manage`
- Form sections:
  - Title field
  - Report date (default = post_date)
  - Summary textarea
  - Optional metadata blocks (stubbed)

Form submission handled via:
- `POST` with nonce
- Save metadata into postmeta using:
  - `_satori_audit_summary`
  - `_satori_audit_report_date`

---

### 2. Update CPT to Use Custom Editor  
Modify CPT registration in:  
`includes/class-satori-audit-cpt.php`

- Disable WordPress default editor with:
  ```php
  'supports' => [ 'title' ]
  ```
- Add custom meta box or link that opens the new Editor UI.

---

### 3. Template v2 Storage  
Create new folder:

```
templates/report-v2/
```

Add:

- `header.php`
- `summary.php`
- `plugin-updates.php`
- `footer.php`

These should be modular and only contain HTML fragments (no logic).

Reports class will assemble these in later PRs.

---

### 4. Admin UI Styling  
Add new stylesheet:  
`assets/css/report-editor.css`

Basic layout:
- Two-column layout (main content + sidebar)
- Styled metadata fields
- Clean typography
- SATORI-branded header/title in top bar

---

### 5. Add New Menu Item  
Under Audit → Reports Archive, add a submenu:

```
Add New Report (Template v2)
```

Link to:

```
admin.php?page=satori-audit-report-editor&template=v2
```

---

### 6. Logging (debug_mode only)
Log events:
- Opening editor
- Saving summary
- Switching template
- Rendering preview (for v2 only)

Use `satori_audit_log()`.

---

## Acceptance Criteria

- New Report Editor screen is accessible only to users with `capability_manage`.
- Editor UI is clean and functional.
- Summary + report date save correctly.
- Template v2 files exist and load without PHP warnings.
- No notifications, automation, or PDF logic in this PR.
- No fatal errors or UI defects.
