# PR Spec: PDF Engine Hardening & DOMPDF Integration — SATORI Audit

**Target Branch:**  
`main`

**New Feature Branch:**  
`codex/pdf-engine-hardening-dompdf`

---

## Summary

PDF export currently fails with the admin notice:

> “Unable to generate PDF for this report. Please check the PDF settings or logs.”

The HTML report preview works correctly, but `PDF::generate_pdf()` returns an empty/false value, and no actual PDF is produced.

This PR must:

- Implement a **fully working PDF engine** using **DOMPDF**, bundled via Composer.
- Ensure the existing **PDF Engine** settings tab continues to function (Engine, Paper size, Orientation, Font).
- Use the existing HTML output from `Reports::get_report_html( $report_id )`.
- Write PDFs to the existing SATORI Audit uploads path.
- Improve logging and error handling so failures are diagnosable.

It must **not** change Notifications, Automation, Access Control, REST API, or report rendering logic beyond what is necessary to render HTML into PDF.

---

## Problems to Fix

1. **PDF generation always fails**  
   - `PDF::generate_pdf( $report_id )` returns empty/false, causing the UI to show the “Unable to generate PDF” notice.
   - No PDFs are created under the SATORI uploads directory.

2. **PDF engine libraries are not present / not wired**  
   - The Settings screen offers “DOMPDF” and “TCPDF” engines, but the plugin does not currently bundle or autoload a PDF library.
   - Attempting to instantiate DOMPDF or TCPDF classes fails or is wrapped in a stub.

3. **Error handling is opaque**  
   - Failures always look the same from the UI.
   - Logs may not clearly indicate which error occurred.

---

## Requirements

### 1. Bundle DOMPDF via Composer

Update the repository to include DOMPDF via Composer:

- Modify `composer.json` to add:

  ```json
  "require": {
      "dompdf/dompdf": "^2.0"
  }
  ```

  (Adjust version as appropriate; use a stable current major.)

- Ensure `composer install` produces a `vendor/` directory with DOMPDF and autoloader.
- This PR must commit the necessary Composer changes (updated `composer.json`, `composer.lock`) and any required `vendor/` files, **following the project’s existing conventions** for committing vendor code (if the repo already uses vendor, be consistent; if not, follow WordPress plugin norms and commit the vendor tree required for DOMPDF).

### 2. Load Composer Autoloader Conditionally

In the main plugin bootstrap file (`satori-audit.php`) or a central bootstrap location:

- Add conditional logic to load Composer’s autoloader if present:

  ```php
  $autoloader = __DIR__ . '/vendor/autoload.php';
  if ( file_exists( $autoloader ) ) {
      require_once $autoloader;
  }
  ```

- This must occur **before** the PDF engine is used.

### 3. Implement `PDF::generate_pdf()` with DOMPDF

In `includes/class-satori-audit-pdf.php`:

- Implement `PDF::generate_pdf( $report_id )` using DOMPDF as the **primary engine**.
- Behaviour:

  1. Retrieve the report HTML via existing rendering:

     ```php
     $html = Reports::get_report_html( $report_id );
     ```

  2. If HTML is empty:
     - Log an error (respecting debug settings).
     - Return an empty string or false so the caller can show the error notice.

  3. Initialise DOMPDF:

     ```php
     $dompdf = new \Dompdf\Dompdf( $options );
     ```

     - Configure options as needed (e.g. enable remote resources if required).
     - Apply the selected **paper size** and **orientation** from settings.
     - Apply the selected **font family** by setting a default font in DOMPDF options if supported; otherwise note in comments that font selection is best-effort.

  4. Load HTML and render:

     ```php
     $dompdf->loadHtml( $html );
     $dompdf->setPaper( $paper_size, $orientation );
     $dompdf->render();
     ```

  5. Generate a binary string:

     ```php
     $output = $dompdf->output();
     ```

  6. Determine the output path:

     - Use a helper (existing or new) to compute an uploads path like:

       `wp-content/uploads/satori-audit/reports/{report-id}/`

     - Ensure directories are created with appropriate permissions.
     - File name suggestion:

       `satori-audit-report-{report-id}.pdf`

  7. Write the file:

     ```php
     file_put_contents( $file_path, $output );
     ```

     or use `WP_Filesystem` if that is already being used in the project.

  8. On success:

     - Return the **absolute file path** for the PDF.

  9. On any failure:

     - Log the exception or error message via `satori_audit_log()` when debug mode is enabled.
     - Return an empty string/false so the caller can show the error banner.

- Engine selection:

  - If the Settings “Engine” value is set to DOMPDF, use DOMPDF as above.
  - If it is set to TCPDF (and TCPDF is **not** installed), log a clear message and **fallback to DOMPDF**, so that PDF export works out of the box as long as DOMPDF is bundled.

### 4. Respect Existing Settings

Ensure the engine uses:

- **Paper size**:
  - Map A4, Letter, etc. to DOMPDF-supported sizes.
- **Orientation**:
  - Map Portrait/Landscape to DOMPDF orientation.
- **Font family**:
  - Where supported, configure DOMPDF’s default font to match the selected family (fallback gracefully if a direct mapping is not available).

If any setting is invalid, fallback to a sensible default (A4, Portrait, Helvetica/Arial).

### 5. Improve Error Handling & Logging

- In `PDF::generate_pdf()`:

  - Wrap engine usage in a `try/catch`.
  - On exception:

    - Log:

      - Report ID
      - Engine type
      - Exception message

      via `satori_audit_log()` (only when debug mode is ON).

    - Return an empty value to signal failure.

- In the export handler (already implemented as part of the Export PDF buttons PR):

  - When `generate_pdf()` returns an empty value:
    - Redirect back with `pdf_error=1` as currently implemented.
    - Do **not** expose raw engine errors to the user — keep them in logs.

### 6. Do Not Break Existing Behaviour

- Do not change the public method signature of `PDF::generate_pdf()`.
- Do not change the existing export URLs, handlers, or nonces.
- Do not alter Notifications, Automation, or REST API code.
- Do not remove support for a future TCPDF engine; instead, treat DOMPDF as the **current default** and leave room for future expansion.

---

## Files to Touch

Codex should expect to modify at least:

- `composer.json`
- `composer.lock` (generated)
- `vendor/` (DOMPDF + autoloader, committed per project norms)
- `satori-audit.php` (or equivalent bootstrap) to load `vendor/autoload.php`
- `includes/class-satori-audit-pdf.php` to implement the DOMPDF-based engine
- Optionally:
  - Any helper functions related to determining the uploads directory for PDFs.

---

## Constraints

- Must work on a standard WordPress environment with no extra system packages required.
- Must not require shell access at runtime (Composer is for development only; end-users just ship the committed vendor files).
- Must be backwards compatible with the current database schema and settings.

---

## Acceptance Criteria

- From the **Archive** screen:
  - Clicking **Export PDF** for a report generates and downloads a valid PDF.
- From the **Report Preview** screen:
  - Clicking **Download PDF** generates and downloads a valid PDF.
- PDF content:
  - Displays header information, summary, plugin updates, diagnostics, and footer as rendered in the HTML.
- After an export:
  - A `.pdf` file exists in the SATORI Audit uploads subdirectory for that report.
- When the engine fails (e.g. corrupt HTML or filesystem permissions):
  - The user sees the existing “Unable to generate PDF” notice.
  - Detailed error information is available in the SATORI Audit logs when debug mode is enabled.
- No regressions are introduced in:
  - HTML rendering
  - Notifications / Automation
  - Access Control
  - REST API
  - Settings UI.
