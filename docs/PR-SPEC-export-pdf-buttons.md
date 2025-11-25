# PR Spec: Export PDF Buttons (Archive + Preview) — SATORI Audit

**Target Branch:**  
`main`

**New Feature Branch:**  
`codex/export-pdf-buttons`

---

## Summary

Add UI controls to allow users to **export/download a PDF version** of a SATORI Audit report from:

1. The **Audit → Archive** table (per-report action).
2. The **single Report Preview** screen.

This PR must:

- Reuse the existing PDF engine (`Satori_Audit\PDF` class and settings).
- Respect capabilities and access control.
- Use nonces and secure URLs.
- Provide clear admin feedback (success/error notices).

It must **not** change the PDF engine internals, add new settings, or alter Notifications/Automation/REST API behaviour.

---

## Behaviour

### 1. Archive Screen — “Export PDF” Action

On the **Audit → Archive** screen:

- Add a per-row action labelled **“Export PDF”** (ideally placed next to View/Delete).
- Clicking “Export PDF” should:
  - Trigger a secure admin action.
  - Generate a PDF for that specific report.
  - Initiate a download for the user (or open PDF in browser, depending on headers).

Implementation options (Codex may choose either):

1. **`admin-post.php` handler**:
   - URL like:  
     `admin-post.php?action=satori_audit_export_pdf&report_id=123&_wpnonce=XYZ`
   - Handler hooked to:
     - `admin_post_satori_audit_export_pdf` (for logged-in users).

2. **Custom page parameter on existing Audit screens**:
   - URL like:  
     `admin.php?page=satori-audit-archive&sa_action=export_pdf&report_id=123&_satori_audit_nonce=XYZ`
   - Handler logic implemented in the Archive screen controller.

Preference: **Use `admin-post.php`** for a clean separation of concerns.

### 2. Report Preview Screen — “Download PDF” Button

On the **single Report Preview** screen:

- Add a primary button near the top (e.g. top-right, within the `.wrap`):

  - Label: **“Download PDF”**
  - Behaviour identical to the archive action:
    - Same handler (`satori_audit_export_pdf`).
    - Includes the current report ID and nonce.

Example button:

```php
<a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary">
    <?php esc_html_e( 'Download PDF', 'satori-audit' ); ?>
</a>
```

---

## Permissions & Security

### Capability Check

- Only users with the **view** capability (from Access Control settings, e.g. `capability_view_reports`) should be able to:

  - See the “Export PDF” action/button.
  - Trigger the export.

If a user lacks capability but manually hits the URL:

- The handler must check capability and:
  - Return a `wp_die()` message:  
    `"You do not have permission to export SATORI Audit reports."`

### Nonces

- Include a nonce in all export URLs.
- Suggested nonce action: `satori_audit_export_pdf`
- Suggested nonce param: `_satori_audit_nonce` or `_wpnonce`

In the handler:

- Verify nonce.
- Verify capability.
- Verify that `report_id` refers to a valid SATORI Audit report.

---

## PDF Generation & Response

Use the existing PDF class:

```php
$pdf_path = \Satori_Audit\PDF::generate_pdf( $report_id );
```

- If `generate_pdf()` returns a valid path:
  - Send appropriate headers:
    - `Content-Type: application/pdf`
    - `Content-Disposition: attachment; filename="satori-audit-report-<id>.pdf"`
    - `Content-Length: ...` (if convenient)
  - Read the file and exit.

- If `generate_pdf()` fails or returns an empty value:
  - Redirect back to the referring screen (Archive or Preview).
  - Append a query arg, e.g. `pdf_error=1`.

---

## Admin Notices

On Archive and Preview screens:

- If `pdf_error=1` is present in the query string:
  - Show an error notice:
    - “Unable to generate PDF for this report. Please check the PDF settings or logs.”

Optionally, if there is a successful case that returns via redirect (e.g. `pdf_exported=1`, though typically the file download will not redirect back), Codex may show a success notice, but this is not required.

---

## UI / Styling

- For Archive per-row action:
  - Use a simple text link or small button-style link in the Actions column.
  - Match existing styling conventions for actions (e.g. “View”, “Delete”).

- For Preview screen button:
  - Use a standard `button button-primary` class.
  - Ensure it sits within the `.wrap` container and does not affect the report preview layout.

No new CSS is required unless minor spacing tweaks are needed; any new CSS must be scoped to SATORI Audit.

---

## Files to Touch

Codex should likely modify:

- `admin/screens/class-satori-audit-screen-archive.php`
  - Add “Export PDF” action URL generation and markup.
  - Handle notices for `pdf_error`.

- `templates/admin/report-preview.php`
  - Inject “Download PDF” button with correct export URL.

- A new or existing handler location:
  - Either:
    - `includes/class-satori-audit-reports.php` (static download/export handler), or
    - A new small controller class, or
    - Logic within `satori-audit.php` using `admin_post_satori_audit_export_pdf`.

Changes must be minimal and focused on wiring up existing PDF functionality.

---

## Constraints

- MUST reuse `Satori_Audit\PDF::generate_pdf()` (no new engines).
- MUST respect existing Access Control capabilities.
- MUST use nonces and verify report ID.
- MUST not alter Settings structure, Notifications, Automation, or REST API.
- MUST not change existing report content rendering.

---

## Logging (Optional, debug_mode only)

When `debug_mode = 1`, log:

- Export attempts:
  - User ID
  - Report ID
  - Success/failure
- Paths of generated PDFs (if available).

Use `satori_audit_log()`.

---

## Acceptance Criteria

- Each report row in the Archive table has an **Export PDF** action visible to authorised users.
- The Report Preview screen has a **Download PDF** button visible to authorised users.
- Clicking Export/Download generates a PDF using the existing engine and offers it for download.
- Unauthorized users cannot trigger exports.
- Nonce and capability checks are enforced.
- On failure, a clear error notice is shown on the relevant screen.
- No regressions to existing PDF, rendering, or settings behaviour.
