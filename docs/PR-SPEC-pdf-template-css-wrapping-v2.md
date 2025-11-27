# PR-SPEC – PDF Template CSS Wrapping v2 (Remove Raw CSS Output)
*Slug: pdf-template-css-wrapping-v2*  
*Plugin: SATORI Audit*  

---

## 1. Background

PR #32 implemented an initial PDF CSS pipeline for DOMPDF, but the generated PDFs still display the CSS rules as **visible text** at the top of the PDF (e.g. `@page { margin: ... }`, `body.satori-audit-pdf { ... }`) before the “SATORI Audit Report…” heading.

This confirms that CSS is still being concatenated into the **body content** instead of being strictly confined to a `<style>` block in the `<head>`.

HTML admin preview remains acceptable, but the **“no raw CSS in the PDF body”** requirement is not yet met.

This PR is a focused follow-up to finalise CSS handling so that **no visible CSS** appears in the PDF output.

---

## 2. Goals

1. **Eliminate raw CSS text from PDF body**
   - No printed CSS rules should appear at the top or anywhere inside the PDF.

2. **Centralise CSS injection**
   - All CSS must be injected via a `<style>` tag inside the `<head>` element only.

3. **Maintain DOMPDF compatibility**
   - Keep margins, fonts, layout correct for Template v2.

4. **Do not affect HTML admin preview**
   - No regressions in preview rendering.

---

## 3. Scope

### 3.1 In Scope
- `includes/class-satori-audit-pdf.php`
- Any PDF helper methods added by PR #32
- Any existing PDF-specific CSS files

### 3.2 Out of Scope
- Editor UI
- Archive logic
- Automation/scheduling
- Diagnostics
- Template changes not related to CSS injection

---

## 4. Implementation Requirements

### 4.1 Build a proper PDF HTML shell

Introduce or refine a single method (e.g. `build_pdf_html( string $report_html ): string`) that:

1. Accepts `$report_html` with **no CSS included**.
2. Wraps it in a full DOMPDF-ready HTML shell:

   ```php
   $html  = '<!DOCTYPE html><html><head>';
   $html .= '<meta charset="utf-8">';
   $html .= '<style>' . $this->get_pdf_styles() . '</style>';
   $html .= '</head><body class="satori-audit-pdf">';
   $html .= '<div class="satori-audit-report-pdf">';
   $html .= $report_html;
   $html .= '</div></body></html>';
   ```

3. Returns `$html` to the DOMPDF loading function.

### 4.2 Ensure `get_pdf_styles()` returns CSS only

- It must return only the **pure CSS rules**, no `<style>` tag.
- If using an external CSS asset (e.g. `assets/css/report-pdf.css`), read via filesystem path.
- Must not echo or print.

### 4.3 Remove CSS concatenation into content

Locate and remove any patterns where CSS is prepended to `$report_html`, such as:

```php
$report_html = $css . $report_html;
```

Or any concatenation injecting CSS text into body content.

### 4.4 Avoid double-injection

Ensure there is **one and only one** `<style>` block in the `<head>` containing PDF CSS.

---

## 5. Behaviour & UX Requirements

### 5.1 PDF Output
- Styled PDF layout  
- No visible CSS text  
- First visible text must be report heading/content  

### 5.2 Admin Preview
- Must remain unchanged  

---

## 6. Testing & Acceptance Criteria

### 6.1 Manual Tests
1. Export from preview  
   - No CSS text printed  
   - Layout acceptable  

2. Export from archive  
   - Same as above  

3. Long report  
   - Footer spacing correct  
   - No CSS text  

### 6.2 Acceptance Criteria
- [ ] No visible CSS  
- [ ] DOMPDF styling correct  
- [ ] Preview unchanged  
- [ ] PHPCS passes  
- [ ] CI passes  

---

## 7. Notes

- No new settings  
- New methods should be protected/private  
- Use SATORI comment headers  

  ```php
  /* -------------------------------------------------
   * Section: PDF HTML assembly and CSS injection
   * -------------------------------------------------*/
  ```

---
