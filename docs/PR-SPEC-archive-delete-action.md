# PR Spec: Archive Delete Action — SATORI Audit

**Target Branch:**  
`main`

**New Feature Branch:**  
`codex/archive-delete-action`

---

## Summary

Add a safe, capability-checked way to **delete/archive individual SATORI Audit reports** directly from the SATORI Audit **Archive** screen.

This PR must:

- Provide a **per-row Delete action** in the Archive table.
- Optionally support a **checkbox + bulk-delete** action if convenient.
- Use nonces and capabilities for security.
- Move reports to the WordPress **Trash** (do not hard-delete).
- Show appropriate success/error admin notices.

No changes to report content, rendering, PDF, notifications, automation, or REST API.

---

## Behaviour

### 1. Per-row Delete Action

On the **Audit → Archive** screen:

- Each report row should include a **“Delete”** action (link or button) in an **Actions** column.
- Clicking “Delete” should:
  - Confirm via standard `wp_die()`-style confirm or JavaScript `confirm()` prompt (optional).
  - Send a request including:
    - Report ID
    - Action identifier (e.g. `satori_audit_delete_report`)
    - Nonce

On successful processing:

- The underlying `satori_audit_report` post is moved to the **Trash** using `wp_trash_post( $report_id )`.
- User is redirected back to the Archive screen with a query arg like `deleted=1`.

On failure (invalid ID, capability, or nonce):

- Redirect back with an error flag, or display `wp_die()` message, as appropriate.

### 2. Capability & Permissions

- Only users with the **manage** capability from Access Control settings (e.g. `capability_manage`) may delete reports.

If a user lacks the capability and attempts to access the delete action:

- Access must be denied and a clear error message returned:
  - `"You do not have permission to delete SATORI Audit reports."`

### 3. Nonces & Security

Implement a nonce for the delete action, for example:

- Action: `satori_audit_delete_report`
- Nonce name: `_satori_audit_nonce`

The delete URL might look like:

`admin.php?page=satori-audit-archive&action=satori_audit_delete_report&report_id=123&_satori_audit_nonce=XYZ`

The handler must:

- Verify the nonce.
- Verify the capability.
- Verify that the report belongs to the SATORI Audit CPT.

### 4. Optional Bulk Delete (Nice-to-have)

If convenient and safe, add:

- A checkbox column in the Archive table.
- A **Bulk actions** dropdown with:
  - `Delete selected`

Bulk behaviour:

- Apply the same rules (capability + nonce + trash, not hard-delete).
- Show admin notice with number of reports trashed.

If bulk delete is too intrusive for this PR, Codex may skip this and implement **only per-row delete**. The priority is the per-row delete action.

### 5. Admin Notices

On the Archive screen:

- When one or more reports are deleted, show a success notice:

  - “SATORI Audit report deleted.”  
  - Or “X SATORI Audit reports moved to Trash.”

- On error (invalid request, no permission), show an error notice.

Implement notices using standard WP admin notices (e.g. `add_settings_error` or custom notice markup hooked to `admin_notices`).

---

## Files to Touch

Likely files (Codex should confirm):

- `admin/screens/class-satori-audit-screen-archive.php`
- `assets/css/admin.css` (for minor styling of the Delete link/button, if needed)
- Optional helper or handler in:
  - `includes/class-satori-audit-reports.php`
  - or a small dedicated handler method on the Archive screen class

Changes must be minimal and focused on the Archive behaviour.

---

## Constraints

- Do **not** introduce hard deletion (no `wp_delete_post( $id, true )`).
- Do **not** change any existing settings or capabilities names.
- Do **not** alter how reports are generated, rendered, or exported.
- Do **not** touch Notifications, Automation, PDF, or REST API features.
- Do **not** change CPT registration.

---

## Logging (Optional, debug_mode only)

When `debug_mode = 1`, log:

- Attempted deletes (with user ID and report ID).
- Successful deletes.
- Failed deletes (permission or nonce issues).

Use `satori_audit_log()`.

---

## Acceptance Criteria

- A per-row **Delete** action exists on the Archive table.
- Only users with the manage capability can delete reports.
- Deleting moves the report to Trash, not permanent delete.
- Archive screen shows appropriate success/error notices.
- No changes to non-archive screens.
- No PHP warnings, notices, or fatal errors are introduced.
