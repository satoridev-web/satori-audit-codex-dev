# PR-SPEC – Fix PDF output transport for SATORI Audit
*Slug: pdf-output-transport-fix*  
*Plugin: SATORI Audit*  

---

## 1. Background

SATORI Audit now has a centralised `PDF` class that:

- Builds report HTML for export.
- Writes a debug HTML snapshot as `satori-audit-pdf-last.html`.
- Uses DOMPDF or TCPDF to generate a binary PDF.

Current behaviour from testing:

- **Automatic / TCPDF modes**
  - Browser “hangs” on loading the PDF in a new window.
  - A file (e.g. `satori-audit-report-19-3.pdf`) is downloaded and a copy
    is written under `uploads/satori-audit/reports/{ID}/`.
  - When opened in a text editor, the file begins with:

    ```text
    %PDF-1.4
    % Stub TCPDF output
    <!DOCTYPE html><html>...
    ```

    i.e. a short “stub” PDF header followed by raw HTML.

  - This is **not** a valid PDF file: mixing plaintext HTML content
    directly into the PDF bytes corrupts the document and causes readers
    to fail.

- **DOMPDF mode**
  - Browser again appears to “hang” while loading the PDF in a new
    window.
  - A file like `satori-audit-report-19-2.pdf` is written.
  - When inspected, the file contains a valid PDF structure with a text
    stream that includes:
    - All CSS rules for `.satori-audit-pdf`, headings, sections etc.
    - The full audit content (summary, plugin updates, diagnostics).
  - In the viewer, only a single line of text is visible by default, but
    this is a **layout/rendering concern**, not a missing content issue.

- **Additional corruption path: DB errors**
  - When Simple History queries fail, WordPress emits an error block:

    ```html
    <div id="error"><p class="wpdberror"><strong>WordPress database error:</strong> ...</p></div>
    ```

    before any PDF output.
  - This HTML ends up **ahead of** the `%PDF-1.4` header in the output
    file, making the resulting file invalid as a PDF.

Summary:

- The engines (especially DOMPDF) are capable of producing correct PDF
  content.
- The primary issues are:
  1. **Transport / file-writing**: HTML and stub strings are being
     appended or prepended to the PDF binary.
  2. **Unexpected output** (e.g. database errors) is leaking into the
     same response and/or file.

This PR focuses on fixing the **PDF transport pipeline** so that:

- Only real PDF bytes are ever written into `.pdf` files.
- Debug HTML remains available as `satori-audit-pdf-last.html`.
- Any unexpected output is caught and handled, not merged into the PDF.

Interaction with the Simple History integration will be handled by a
separate PR (see the Simple History integration toggle spec). This PR
must still be robust in the presence of unexpected output.

---

## 2. Goals

1. Ensure that **all generated `.pdf` files are valid PDFs**:
   - No HTML, stubs, or error markup concatenated into the binary.
   - Files open cleanly in standard readers (Preview, Acrobat, etc.).
2. Preserve the existing HTML debug snapshot system
   (`satori-audit-pdf-last.html`) for diagnostics, but keep it strictly
   separate from the PDF bytes.
3. Ensure that **unexpected output** (e.g. PHP notices, DB error blocks)
   never corrupts the PDF:
   - Any such output must be captured and logged, not written into the
     file.
4. Keep the public behaviour of “Download PDF” the same:
   - Admins can still download a PDF from the Archive screen.
   - Existing settings (Automatic / DOMPDF / TCPDF) remain in place.
5. Maintain compatibility with existing SATORI Audit settings and data.

---

## 3. Scope

### 3.1 In Scope

- Changes within the `PDF` class (and any closely related helpers) to:
  - Clean up engine output handling.
  - Isolate HTML debug files from PDF binaries.
  - Add simple output buffering around PDF generation and serving.
- Changes to the controller/handler that:
  - Triggers PDF generation.
  - Streams the file to the browser.

### 3.2 Out of Scope

- Changes to the visual layout or CSS of the report template.
- Changes to the Simple History integration logic itself (handled by
  another PR).
- New settings or UI elements (unless strictly necessary for this fix).

---

## 4. Implementation Details

### 4.1 PDF generation: engine output handling

In the `Satori_Audit\PDF` class, refactor `generate_pdf( int $report_id )`
so that engine handling is unambiguous:

1. The method must only ever write **binary PDF bytes** to disk:

   ```php
   $output = '';

   if ( 'dompdf' === $engine['type'] ) {
       /** @var \Dompdf\Dompdf $dompdf */
       $dompdf = $engine['instance'];
       $dompdf->setPaper( $paper, $orientation );
       $dompdf->loadHtml( $html );
       $dompdf->render();

       $output = $dompdf->output();
   } elseif ( 'tcpdf' === $engine['type'] ) {
       /** @var \TCPDF $tcpdf */
       $tcpdf = $engine['instance'];
       $tcpdf_orient = ( 'landscape' === $orientation ) ? 'L' : 'P';

       $tcpdf->SetFont( $font_family, '', 10 );
       $tcpdf->AddPage( $tcpdf_orient, $paper );
       $tcpdf->writeHTML( $html, true, false, true, false, '' );

       $output = $tcpdf->Output( '', 'S' ); // return as string
   }
   ```

2. **Remove any stub or placeholder output** that concatenates HTML into
   `$output`, such as:

   ```php
   $output = "%PDF-1.4\n% Stub TCPDF output\n" . $html;
   ```

   or similar constructs. This behaviour must be completely removed.

3. Retain the early-return checks:

   - If `$output` is empty after engine rendering, log and return `''`.
   - If `file_put_contents()` fails, log the path and return `''`.

4. Factor out a small helper if needed for clarity:

   ```php
   /**
    * Write a PDF file to disk.
    *
    * @param string $path   Target path.
    * @param string $bytes  PDF binary data.
    * @return bool
    */
   private static function write_pdf_file( string $path, string $bytes ): bool {
       return false !== file_put_contents( $path, $bytes );
   }
   ```

   This is optional but may help centralise error handling.

### 4.2 HTML debug snapshot: keep it separate

The plugin already writes a debug HTML snapshot to something like:

- `wp-content/uploads/satori-audit-pdf-last.html`
- (and numbered variants: `...-2`, `...-3`, etc.)

Clarify and enforce the contract:

1. The HTML debug file is for **diagnostics only** and must:

   - Contain the full `<!DOCTYPE html>...` document used for PDF
     generation.
   - Never be read back and appended/prepended to the `.pdf` file.

2. Ensure that any function that writes `satori-audit-pdf-last.html`
   runs **before** calling the PDF engines, and is clearly separated
   from the file-write logic for the PDF itself.

3. If there is a central helper that writes this debug file, make sure
   it is called with explicit intent, e.g.:

   ```php
   self::write_debug_html_snapshot( $html );
   ```

   and that it only ever writes `.html` files.

### 4.3 Output buffering and unexpected output

To prevent DB errors or PHP notices from being mixed into the `.pdf`
file or streamed response:

1. In the public-facing method/controller that handles:

   - “Download PDF” from the Archive screen, and/or
   - Inline PDF view (new window/tab),

   wrap the generation and streaming in **output buffering**.

2. Recommended pattern:

   ```php
   ob_start();

   $path = PDF::generate_pdf( $report_id );

   $buffer = ob_get_clean();

   if ( ! empty( $buffer ) && function_exists( 'satori_audit_log' ) ) {
       // Log the first ~300 chars of unexpected output for diagnostics.
       satori_audit_log(
           '[PDF] Unexpected output during PDF generation: ' . substr( trim( $buffer ), 0, 300 )
       );
   }
   ```

3. After cleaning the buffer:

   - If `$path` is empty or the file does not exist, redirect back to
     the Archive with an admin notice or error message (implementation
     detail left to the plugin).
   - If `$path` is valid, stream the file to the browser using headers
     only, without echoing any additional HTML.

### 4.4 Streaming the PDF to the browser

The handler that sends the PDF to the browser should:

1. Confirm the file exists and is readable:

   ```php
   if ( ! $path || ! is_readable( $path ) ) {
       // Handle error: log and redirect, or show admin notice.
   }
   ```

2. Clear any previous buffering (safety net):

   ```php
   if ( ob_get_length() ) {
       ob_end_clean();
   }
   ```

3. Send appropriate headers:

   ```php
   header( 'Content-Type: application/pdf' );
   header( 'Content-Disposition: inline; filename="' . basename( $path ) . '"' );
   header( 'Content-Length: ' . (string) filesize( $path ) );
   ```

4. Read and output the file:

   ```php
   readfile( $path );
   exit;
   ```

There should be **no HTML** or WordPress admin chrome output in this
response; only headers and the PDF bytes.

If the plugin currently uses `wp_send_file()` or similar helpers, it may
reuse those as long as they honour the same constraints (no HTML, no
unexpected body output).

---

## 5. Testing & Acceptance Criteria

### 5.1 Manual tests

Use a local SATORI Audit install with the latest code:

1. **DOMPDF mode, no errors**
   - Set engine to **DOMPDF**.
   - Generate a report for a month with updates.
   - Click “Download PDF”.
   - Confirm:
     - Browser opens a new tab or download dialog.
     - File opens correctly in Acrobat/Preview.
     - When viewed in a text editor, the file begins with `%PDF-1.` and
       contains no raw HTML or “Stub” text.
     - `satori-audit-pdf-last.html` is present and contains the HTML
       report (but is not concatenated into the PDF).

2. **TCPDF mode, no errors**
   - Set engine to **TCPDF**.
   - Generate and download a report PDF.
   - Confirm:
     - No “Stub TCPDF output” or HTML is concatenated into the file.
     - The file opens in standard PDF readers.
     - The contents of the PDF correspond to the report HTML.

3. **Automatic mode**
   - Set engine to **Automatic**.
   - Repeat the test.
   - Confirm:
     - The chosen engine generates a valid PDF as above.
     - No hanging forever in the browser; the request completes with a
       valid file or a logged error.

4. **Unexpected output (simulated)**
   - Intentionally trigger a warning or notice during PDF generation
     (for example, temporarily adding a `trigger_error()` call in the
     generation path or reusing the Simple History DB error scenario
     before it is fully fixed).
   - Generate a PDF.
   - Confirm:
     - The generated `.pdf` does **not** contain the HTML error block.
     - The unexpected output is captured in the SATORI log via
       `satori_audit_log` (first ~300 characters).
     - Depending on implementation, the plugin either:
       - still produces a valid PDF, **or**
       - gracefully aborts and returns an error/notice, but in either
         case does not output half-PDF/half-HTML.

5. **Multiple engines over time**
   - Generate several reports back-to-back:
     - Switch engines between Automatic, DOMPDF, TCPDF.
   - Confirm:
     - The correct engine’s output is always a clean, valid PDF.
     - Debug HTML files are updated, but no “PDF + HTML” hybrids are
       created.

### 5.2 Acceptance criteria

- [ ] No `.pdf` file generated by SATORI Audit contains raw HTML or
      “Stub TCPDF output” in front of or after the PDF bytes.
- [ ] DOMPDF and TCPDF modes both generate valid PDFs that open
      successfully in standard readers.
- [ ] The `satori-audit-pdf-last.html` debug file is maintained as a
      separate HTML snapshot and is never concatenated into `.pdf`
      files.
- [ ] Unexpected output during PDF generation is captured in the SATORI
      log and **never** written into the `.pdf` file or streamed
      response.
- [ ] Automatic mode behaves consistently, selecting an engine and
      producing a valid PDF where possible.
- [ ] PHPCS/CI passes.

---

## 6. Follow-up Work (separate PRs)

- Combine this transport fix with the Simple History integration
  improvements (integration toggle + schema checks), so that:
  - The PDF pipeline is robust against both engine issues and data
    layer issues.
- Once both PRs are merged and shipped, consider adding a small
  diagnostics section to the UI that can display the last few PDF
  generation log entries for debugging client environments.
