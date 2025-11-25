# PR Spec: UI Cleanup & Template Isolation — SATORI Audit

**Target Branch:**  
`main`

**New Feature Branch:**  
`codex/ui-cleanup-and-template-isolation`

---

## Summary

Clean up layout issues and isolate the SATORI Audit report templates so that:

- The **WordPress admin layout** (left admin menu, content area, background colours) is **not altered** by any SATORI Audit markup or CSS.
- The **Audit Archive** screen and the **single Report Preview** screen each render only their intended content.
- The **Report Archive table is *not* shown inside the single report preview**.
- All inline styles injected by the report rendering engine are **scoped** to the SATORI Audit preview container and do **not override global admin styles**.

This PR must NOT add new features (no new PDF buttons, no new settings, no new endpoints).  
It is purely a **UI/layout correctness** and **template isolation** fix.

---

## Problems to Fix

1. A **grey vertical band** appears to the left of the WordPress admin sidebar on SATORI Audit report screens.  
   - Likely caused by global `body`, `html`, or layout CSS injected by the report preview.

2. The **“Report Archive” table** appears at the bottom of the individual **Report Preview** page.  
   - The preview screen should only show the selected report; archive table belongs on the Archive screen.

3. The report preview markup may not be correctly wrapped or scoped, causing it to “bleed” into the surrounding admin layout.

---

## Files Likely to be Touched

Codex should inspect and modify as needed (but not limited to):

- `templates/admin/report-preview.php`
- `admin/screens/class-satori-audit-screen-archive.php`
- `admin/screens/class-satori-audit-screen-dashboard.php`
- `admin/screens/class-satori-audit-screen-settings.php`
- `includes/class-satori-audit-reports.php`
- `assets/css/admin.css`

The PR should only change files that are required to fix the layout, scoping, and screen responsibilities.

---

## Requirements

### 1. Isolate Report Preview Markup

- Ensure that the HTML output for a single report preview is wrapped inside a **dedicated container**, e.g.:

  ```html
  <div class="satori-audit-report-preview">
      <!-- all report content -->
  </div>
  ```

- This container must live **inside** the standard WordPress admin layout:

  ```php
  <div class="wrap">
      <h1>...</h1>
      <div class="satori-audit-report-preview">
          ...
      </div>
  </div>
  ```

- The preview should **not** output `<html>`, `<head>`, or `<body>` tags.  
  It should only output markup within the admin content area.

### 2. Scope Inline CSS / Styles

If the report rendering engine injects a `<style>` block (for inline CSS), update it so that:

- All SATORI Audit preview styles are **scoped to the `.satori-audit-report-preview` container** (or similar), for example:

  ```css
  .satori-audit-report-preview h1 { ... }
  .satori-audit-report-preview .satori-audit-section { ... }
  ```

- The CSS must **not** target:

  - `html`
  - `body`
  - `#wpcontent`
  - `#wpbody-content`
  - `#adminmenu`
  - `.wrap`
  - Any other generic WP admin selectors

- Remove or refactor any global CSS rules injected by the report renderer that affect the admin frame outside the preview container.

### 3. Ensure the Archive Table Appears Only on the Archive Screen

- **On the Archive screen** (`Satori_Audit\Screen_Archive`):

  - The “Report Archive” table SHOULD remain, listing all reports with “View” actions.

- **On the single Report Preview screen**:

  - The “Report Archive” table must **NOT** be rendered.
  - Only the selected report’s content (header, summary, plugin updates, diagnostics, etc.) should appear.

Implementation detail:

- If the Archive table template or logic is re-used in multiple places, update the screen classes so that:

  - Archive screen: calls the archive table renderer.
  - Preview screen: calls **only** the single-report renderer.

### 4. Verify Screen Responsibilities

Codex should verify that:

- `class-satori-audit-screen-archive.php`:
  - Responsible for listing reports and showing the “View” links.
  - Uses `Reports::get_report_html()` **only when appropriate**, and does not mix archive table + full report in a way that clutters the layout.

- `report-preview.php`:
  - Responsible solely for showing a single report.

- Any logic that renders both a single report and the archive table on the same screen should be refactored so that these are clearly separated.

### 5. Preserve Existing Features

While cleaning up UI, Codex must ensure:

- No change to core behaviour:
  - Report data
  - Plugin update history retrieval
  - PDF export engine
  - Notifications
  - Automation
  - Access control
  - REST API

- No change to:
  - Settings values
  - Option names
  - Capability checks

This PR must **not** add new menu items, new buttons, or new features.

### 6. Admin CSS Adjustments (if needed)

If `assets/css/admin.css` contains styles that affect:

- `html`, `body`, `#wpcontent`, `#wpbody-content`, `#adminmenu`, `.wrap`, or other global admin structures —

Then:

- Refactor those rules to be **scoped** under a SATORI container, e.g.:

  ```css
  .satori-audit-wrap { ... }
  .satori-audit-report-preview { ... }
  ```

- Apply the relevant wrapper class around the SATORI Audit screens in the PHP templates.

---

## Logging (Optional, debug_mode only)

If convenient, Codex may add light logging (using `satori_audit_log()`) when:

- A report preview is rendered.
- The archive screen is rendered.

Logging should respect `debug_mode` and remain minimal.

---

## Acceptance Criteria

- On **all SATORI Audit screens**:
  - The WordPress admin menu aligns correctly at the left without any extra grey bands or offsets.
  - No SATORI CSS overrides global admin layout or fonts outside the SATORI content area.

- On the **Report Preview** screen:
  - Only the selected report’s content is shown.
  - The “Report Archive” table is **not** rendered.
  - All report content is wrapped inside a dedicated container (e.g. `.satori-audit-report-preview`).

- On the **Archive** screen:
  - The “Report Archive” table is shown as expected.
  - Clicking “View” opens a clean single-report preview.

- No new features, buttons, or settings are introduced.
- No PHP notices, warnings, or fatal errors are introduced.
