# PR-SPEC – PDF Template CSS Wrapping & Styling Fix
*Slug: pdf-template-css-wrapping*  
*Plugin: SATORI Audit*  
*Related docs:*  
- docs/SATORI-AUDIT-PROJECT-STATUS.md  
- docs/SATORI-AUDIT-TEST-RUN-CHECKLIST.md  
- docs/SATORI-AUDIT-SPEC.md (overall spec)  

---

## 1. Overview

**Problem:**  
When exporting a report to PDF using **Template v2**, CSS is currently being output as **raw text in the PDF** and/or is not being correctly applied by the DOMPDF engine. The HTML admin preview is fine, but the PDF output is visually broken.

Per Master Status + v0.3.0 release notes:  

- The PDF engine uses **DOMPDF** (via Composer) tied to Template v2.   
- Known issue: *“PDF CSS still printed as raw text (fix in progress)”* and *“DOMPDF not applying styles”*.   

**Goal of this PR:**  
Fix the PDF rendering pipeline so that:

1. **No raw CSS** is rendered as visible text in the PDF.
2. Template v2’s report layout is **properly styled** in PDF output via DOMPDF.
3. The HTML admin preview behaviour is **unchanged**.

This PR is **tightly scoped** to the PDF CSS wrapping and loading logic.

---

## 2. Current Behaviour (As Understood)

- Template v2 HTML for reports is assembled by:   
  - `includes/class-satori-audit-reports.php`  
  - `templates/admin/report-preview.php`  
  - `templates/report-v2/header.php`  
  - `templates/report-v2/summary.php`  
  - `templates/report-v2/plugin-updates.php`  
  - `templates/report-v2/footer.php`  

- The **same HTML** (or very similar) is passed to the **PDF engine** in:  
  - `includes/class-satori-audit-pdf.php`  

- DOMPDF is loaded via `composer.json` and used to generate PDFs saved in uploads.   

- Known issues:
  - CSS for Template v2 is currently being injected or concatenated in a way that causes **raw CSS text** to appear in the PDF body.
  - DOMPDF does **not consistently apply styles** (e.g., fonts, margins, layout).

---

## 3. Objectives & Non-Goals

### 3.1 Objectives

1. **Correct CSS Injection for DOMPDF**
   - Ensure CSS is **loaded as proper styles** (inline `<style>` or external CSS that DOMPDF can read) and not printed as content.
   - Ensure CSS is scoped so it does not break other potential PDF layouts.

2. **Stabilise Template v2 Styling in PDF**
   - Matching the HTML preview layout as closely as DOMPDF allows (within reason).
   - Respect existing Template v2 structure (header, summary, plugin updates, footer).

3. **Preserve Existing HTML Preview**
   - Do not break `templates/admin/report-preview.php` rendering.
   - Any changes to Template v2 should be **backwards compatible** with the preview.

4. **Follow SATORI Standards**
   - Namespacing, file naming, and commenting per SATORI Standards + Integration Guide.   

### 3.2 Non-Goals

- No new features to the report editor, archive, or diagnostics.
- No changes to automation or notifications.
- No refactor of the entire PDF engine—only what is necessary to fix CSS loading and raw text.

---

## 4. Implementation Requirements

### 4.1 PDF Engine Integration

**Target class:**

- `includes/class-satori-audit-pdf.php` (exact name/path per Project Status).   

**Requirements:**

1. **Centralised CSS Handling**
   - Introduce a **single, clearly named method** in the PDF class (e.g. `get_pdf_styles()` or similar) that:
     - Assembles CSS required for Template v2 PDFs.
     - Returns this as a string ready for `<style> … </style>` injection into the document `<head>` for DOMPDF.
   - The method must:
     - Avoid echoing/printing CSS directly.
     - Be used only within the PDF generation process.

2. **No Raw CSS in Output**
   - Confirm that any CSS previously being concatenated into the HTML body is now:
     - Inserted into `<head><style>…</style></head>` for DOMPDF; or
     - Loaded via an external CSS file in a way DOMPDF supports (e.g. `@page` rules, absolute path handling).
   - Ensure **no CSS text** appears as content in the generated PDF.

3. **Template v2 Awareness**
   - The PDF pipeline should take the **Template v2 HTML** (already built by the report rendering engine) and:
     - Wrap it in a minimal, PDF-specific HTML shell (doctype, `<html>`, `<head>`, `<body>`).
     - Inject the CSS from `get_pdf_styles()` into the `<head>`.

4. **DOMPDF Compatible HTML**
   - Make any small structural adjustments required for DOMPDF compatibility:
     - Avoid unsupported HTML/CSS features.
     - Ensure fonts, margins, and common elements render reliably.

5. **Config Constants / Settings**
   - Honour any existing settings that affect PDF output (location, filename pattern, orientation, paper size).
   - Do **not** introduce new options unless absolutely necessary. If new options are required:
     - They must be stored in `satori_audit_settings` or equivalent, per SATORI Integration Guide.   

### 4.2 CSS Source & Structure

**Goal:** Maintain SATORI Standards and avoid duplication.

1. **CSS Location**
   - If possible, reuse existing Template v2 CSS, or:
   - Add a **dedicated PDF CSS file**, e.g.:  
     - `assets/css/report-pdf.css`
   - If a new file is created:
     - Ensure it is properly enqueued/loaded for DOMPDF (not as a WP enqueue, but via filesystem path or inlined string).
     - Keep it **PDF-specific** (avoid admin UI styling in this file).

2. **SCSS / CSS Conventions**
   - Follow SATORI Standards for CSS/SCSS: modular, clean, minimal duplication.   

3. **Scoping**
   - Scope PDF styles to a top-level wrapper class or ID (e.g. `.satori-audit-report-pdf`) to avoid conflicts with other potential layouts.

---

## 5. UI / UX Considerations

- **Admin Preview (`report-preview.php`)**
  - Must look **unchanged** after this PR.
  - Any change to shared templates must be visually equivalent when rendered in the admin browser.

- **PDF Appearance**
  - The PDF does not have to be pixel-perfect identical to the browser preview, but:
    - Typography, spacing, and hierarchy must be clearly readable.
    - Section headings and summary/plugin updates layout should mirror the preview’s structure.

---

## 6. Testing & Acceptance Criteria

Use **docs/SATORI-AUDIT-TEST-RUN-CHECKLIST.md** as the baseline, focusing on the **Report Preview & PDF Export** sections.

### 6.1 Manual Test Cases

1. **Generate a Report & Preview**
   - Open any existing report or create a new one via the Editor.
   - Confirm template v2 preview renders correctly in admin (no changes expected).

2. **Export PDF from Preview**
   - Click the **PDF Export** button from the Preview screen.
   - **Expected:**
     - File downloads successfully.
     - No visible CSS code appears in the document.
     - Layout is styled (headings distinct, sections separated, margins present).

3. **Export PDF from Archive**
   - Go to Archive screen.
   - Export PDF from an existing report row.
   - **Expected:** Same as above.

4. **Regression: Wrong Template / Empty CSS**
   - Ensure that no errors occur if:
     - There is missing or malformed CSS file (defensive coding).
     - A different template is selected in future (graceful fallback).

### 6.2 Acceptance Criteria

This PR is **complete** when:

- [ ] No raw CSS is visible in any generated PDF.  
- [ ] Template v2 PDFs have readable, styled layout (header, summary, plugin updates, footer).  
- [ ] Admin HTML preview layout is unchanged.  
- [ ] DOMPDF successfully applies the CSS without throwing errors.  
- [ ] All changes conform to SATORI Standards (namespacing, comments, file naming).  
- [ ] PHPCS passes for modified PHP files.  
- [ ] CI / build workflow still passes (lint + zip).  

---

## 7. Technical Notes & Constraints

- Keep changes **focused** on:
  - `includes/class-satori-audit-pdf.php`
  - Template v2 wrapper logic needed for DOMPDF
  - Any new CSS file specifically for PDF rendering

- Ensure any new helper functions live in appropriate classes/namespaces and respect the “one class per file” guideline.   

- Follow SATORI commenting style, e.g.:

---

## 8. Out of Scope / Future Work

* Automation engine and scheduled PDF generation (v0.4.x).
* Archive delete/regenerate actions.
* Diagnostics content expansion.
* REST API export.

These are covered by separate PR specs and roadmap items.

---