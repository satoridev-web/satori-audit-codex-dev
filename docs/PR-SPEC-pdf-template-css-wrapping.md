# PR Spec: PDF Template CSS Wrapping & Layout — SATORI Audit

**Target Branch:**  
`main`

**New Feature Branch:**  
`codex/pdf-template-css-wrapping`

---

## Summary

After the previous PDF engine + settings binding work, PDF export now:

- Successfully renders the **Template v2** report structure into PDF.
- Includes service metadata, summary, plugin updates, and diagnostics.

However, the current PDF output:

- Shows raw CSS text at the top of the page (e.g. `.satori-pdf__header{display:flex;…}`).
- Renders the rest of the report content as unstyled text further down the page.

This PR must:

- Ensure CSS is **embedded correctly** for DOMPDF (wrapped in a `<style>` block).
- Prevent raw CSS from appearing as printable text.
- Maintain (and slightly refine) the **layout** so the PDF looks like a clean, styled report.

No changes are required to the **data** in the report; this is a **template/CSS wiring** and layout polish PR only.

---

## Current Problem (Observed Behaviour)

1. The first line of the PDF page is a CSS rule, e.g.:

   ```text
   .satori-pdf__header{display:flex;align-items:center;justify-content:space-between;margin-b…
   ```

2. The rest of the page contains:

   - “SATORI Audit Report” heading,
   - Site metadata (Site Name, URL, Client, Managed By, Service Start Date),
   - Summary,
   - Plugin Updates,
   - Diagnostics.

   But this content is effectively unstyled or poorly styled because the CSS is not being applied; it’s being printed as plain text before the HTML.

This strongly suggests that the CSS is concatenated as plain text with the HTML, rather than being wrapped inside a `<style>` tag in the `<head>` section of the HTML passed to DOMPDF.

---

## Requirements

### 1. Wrap CSS in `<style>` Tag for DOMPDF

Locate the code that constructs the HTML passed to the PDF engine, most likely in:

- `includes/class-satori-audit-pdf.php`:

  - Any method that builds `$html` or `$pdf_html` for DOMPDF.
  - Any place where CSS is concatenated as a string before/after the HTML.

Change this so that:

- CSS is **embedded inside `<style>` tags in the document `<head>`**, not printed as plain text.

Example structure (illustrative, not prescriptive):

```php
$html = '<!DOCTYPE html><html><head>';
$html .= '<meta charset="utf-8" />';
$html .= '<style>' . $css . '</style>';
$html .= '</head><body>';
$html .= $report_html; // existing Template v2 HTML from Reports::get_report_html()
$html .= '</body></html>';
```

Key points:

- `$css` must contain only valid CSS, without stray HTML.
- `$report_html` should be the **existing HTML** from the report renderer (Template v2).

Avoid:

- Prepending `$css` directly to `$report_html` as raw text.
- Echoing CSS outside of `<style>` tags.

### 2. Use the Same HTML Markup as the Admin Preview

Ensure the PDF engine uses the **same HTML markup** used by the report preview template:

- Continue to call the existing render method, e.g.:

  ```php
  $report_html = Reports::get_report_html( $report_id );
  ```

- Do **not** strip tags or flatten the HTML.
- Do **not** alter the report content structure; only adjust *how* it is wrapped for DOMPDF.

The goal is for the PDF to visually match the admin preview as closely as DOMPDF allows.

### 3. Basic Layout / Margin Sanity

While not a full design pass, make minor adjustments to ensure the PDF looks clean:

- Set sensible default margins via CSS or DOMPDF config so that:

  - Header is not touching the top edge.
  - Content blocks (summary, plugin cards, diagnostics) have consistent spacing.

Examples (illustrative):

```css
body.satori-audit-pdf {
    margin: 20mm;
    font-family: Helvetica, Arial, sans-serif;
    font-size: 11px;
}

.satori-pdf__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12mm;
}
```

Constraints:

- Keep any new CSS **scoped** to SATORI Audit classes (e.g., `.satori-audit-pdf`, `.satori-pdf__*`) to avoid conflicts.
- Do not introduce page-size logic here (paper size/orientation is handled via settings and DOMPDF config from the previous PR).

### 4. Preserve Existing Functionality

This PR must **not**:

- Change how settings are stored or retrieved.
- Change which data appears in the report.
- Change the export URLs, nonces, or handlers.
- Change notifications, automation, access control, or REST API logic.

It should only alter:

- How CSS and HTML are combined into the final DOMPDF-ready document.
- Minor CSS trimming / scoping required for PDF layout.

---

## Files to Inspect / Touch

Codex should inspect and likely modify:

- `includes/class-satori-audit-pdf.php`
  - The HTML assembly for DOMPDF.
  - The way CSS is injected into that HTML.

- Potentially any dedicated CSS or inline CSS helper used for PDF:
  - For example, if a helper method returns the PDF CSS string, ensure it is used inside `<style>…</style>`.

No changes should be necessary to:

- `templates/admin/report-preview.php`
- `includes/class-satori-audit-reports.php`
- Settings classes

unless a bug is discovered that directly affects PDF HTML.

---

## Constraints

- Keep the HTML structure valid (`<!DOCTYPE html>`, `<html>`, `<head>`, `<body>`).
- Ensure the resulting HTML is compatible with DOMPDF 2.x expectations.
- Do not add external dependencies or require remote CSS; the CSS should be inline within the document.

---

## Acceptance Criteria

- When exporting any report to PDF:
  - The top of the PDF page **no longer shows raw CSS text**.
  - The report content is styled according to the SATORI Audit PDF CSS (header layout, cards, headings, etc.).
- Report content in the PDF still includes:
  - SATORI Audit header (site metadata, client, managed by, service start).
  - Summary section.
  - Plugin Updates section.
  - Diagnostics section respecting the display toggle.
- No PHP warnings/notices/fatals are produced during PDF export.
- No regressions in settings persistence, report content, or earlier PDF engine work.
