# PR Spec: Implement PDF Export Engine — SATORI Audit

**Target Branch:**  
`main`

**New Feature Branch:**  
`codex/pdf-export-engine`

**Summary:**  
Implement the PDF export engine for SATORI Audit, enabling HTML reports to be converted to PDF using DOMPDF or TCPDF based on user settings.  
This PR consumes existing settings from the **Display**, **PDF Engine**, and **Diagnostics** tabs.  
No UI changes. No Automation/Notifications logic here.

---

## Settings to Honour

### PDF Engine
- `pdf_engine` (none, dompdf, tcpdf)
- `pdf_paper_size` (e.g., A4, Letter)
- `pdf_orientation` (portrait, landscape)
- `pdf_font_family`
- `pdf_logo_url`
- `pdf_footer_text`

### Display
- `display_date_format`

### Diagnostics
- `debug_mode`

---

## Changes Required

### 1. Create/Extend PDF Class  
File: `includes/class-satori-audit-pdf.php`

Class: `Satori_Audit\PDF`  
`declare(strict_types=1);`

Responsibilities:
- Public API:
  - `public static function generate_pdf( int $report_id ): string`
    - Returns full **file path** to generated PDF.
- Internal helpers:
  - `load_engine()`
  - `build_html()`
  - `apply_header_footer()`
  - `log()`

Engine handling:
- When `pdf_engine = 'dompdf'` → use DOMPDF.
- When `pdf_engine = 'tcpdf'` → use TCPDF.
- When `pdf_engine = 'none'` → return early or throw controlled exception.

PDF styling:
- Inject logo if `pdf_logo_url` set.
- Inject footer if `pdf_footer_text` set.
- Honour `display_date_format`.

Output:
- Save to tmp directory under `wp-content/uploads/satori-audit/`  
- Ensure directory is created if missing.

---

### 2. HTML Source for PDF  
The report HTML is generated elsewhere; PDF class should:

- Call a method like `Reports::get_report_html( $report_id )`.  
- If this method doesn’t exist, create a **read-only stub** in this PR.

HTML → PDF responsibilities include:
- Converting relative URLs to absolute.
- Ensuring inline CSS or linked stylesheet is loaded correctly.

---

### 3. Engine Loader  
Implement a private `load_engine()` that:

- Loads DOMPDF or TCPDF depending on `pdf_engine`.
- Handles missing libraries gracefully (log + fallback).
- Sets paper size + orientation.
- Sets base font family if engine supports it.

DOMPDF:  
- Use common options (`isRemoteEnabled = true`, etc.)

TCPDF:  
- Set page settings (orientation, margins).
- Set font (`SetFont()`).

---

### 4. Header + Footer  
Implement an internal method:

`private static function apply_header_footer( $html ): string`

Rules:
- If `pdf_logo_url` is set:
  - Render logo in header.
- If `pdf_footer_text` is set:
  - Inject footer into page bottom.
- Footer should appear on every page if engine supports it.

---

### 5. Logging  
If `debug_mode` = 1:
- Log selected engine.
- Log path of generated PDF.
- Log failures or fallback behaviour.

Log via `satori_audit_log()`.

---

## Acceptance Criteria

- PDF generation works when settings allow it.
- DOMPDF and TCPDF both supported (as installed).
- When engine = none → function exits safely.
- Headers/footers are applied when settings exist.
- Generated PDF is saved in `/wp-content/uploads/satori-audit/`.
- Errors do not break dashboard/admin.
- No UI changes introduced.
- No automation or notification features added here.
