# PR Spec: Implement Report Rendering (HTML Report Engine) — SATORI Audit

**Target Branch:**  
`main`

**New Feature Branch:**  
`codex/report-rendering-engine`

**Summary:**  
Implement the full HTML report rendering engine for SATORI Audit.  
This system produces a complete, styled HTML report for both:
- **Admin Preview** (report-preview.php)
- **PDF Export Engine** (consumed by PDF class)
- **Email / Notification payloads** (future PR)
- **Automation (cron)** (future PR)

This PR must NOT introduce any Notifications, PDF logic, or Automation logic — it strictly creates the HTML rendering layer and retrieves data from settings + report metadata.

---

## Rendering Must Honour

### Service Settings
- `service_client`
- `service_site_name`
- `service_site_url`
- `service_managed_by`
- `service_start_date`

### Display Settings
- `display_date_format`
- `display_show_debug_section` (conditional rendering)

### Diagnostics
- `debug_mode` (controls embedded debug info)

---

## Changes Required

### 1. Create/Extend Report Rendering Class  
File:  
`includes/class-satori-audit-reports.php`  
(If file exists, extend it. If not, create it.)

Namespace: `Satori_Audit`  
Ensure: `declare(strict_types=1);`

Add methods:

#### Public API:
- `public static function get_report_html( int $report_id ): string`
- `public static function render( int $report_id ): void`  
  (prints HTML for admin preview templates)

#### Internal helpers:
- `private static function get_settings(): array`
- `private static function get_report_metadata( int $report_id ): array`
- `private static function render_header( array $settings ): string`
- `private static function render_summary_section( int $report_id, array $settings ): string`
- `private static function render_plugin_updates( int $report_id ): string`
- `private static function render_diagnostics( array $settings ): string`
- `private static function wrap_html( string $body ): string`
  (adds `<html>`, `<head>`, fonts, inline CSS, etc.)

---

### 2. HTML Structure Requirements

The generated HTML should have this hierarchy:

```
<html>
  <head>
    <style>...</style>
  </head>
  <body>

    <header>…</header>

    <section id="summary">…</section>

    <section id="plugin-updates">…</section>

    <section id="diagnostics">…</section>

  </body>
</html>
```

### Header Section Includes:
- Site Name
- Site URL (linked)
- Client Name
- Managed By
- Service Start Date (formatted)

### Summary Section Includes:
- Report Title (post title)
- Report Date
- Short summary text (placeholder for now)
- Optional metrics for future use

### Plugin Updates Section:
- Pull plugin version data from:
  - `class-satori-audit-plugins-service.php`
- Render as vertical list/table:
  - Plugin Name  
  - Old Version  
  - New Version  
  - Date updated (if available)

### Diagnostics Section:
Show only if `display_show_debug_section = 1`:

Content:
- Debug Mode status
- Timestamp of rendering
- WordPress version
- PHP version
- Active Theme
- Total Plugins Count

---

## 3. Admin Preview Integration

Update:

`templates/admin/report-preview.php`

Change to:

```php
echo \Satori_Audit\Reports::get_report_html( $report_id );
```

Ensure `$report_id` resolves correctly (via query param or context).

---

## 4. Date Formatting

Use:

`display_date_format`

Fallback to:

`Y-m-d`

Apply to:
- Service Start Date
- Report Date
- Any plugin update dates (if available)

---

## 5. Inline CSS Requirements

Provide a minimal, clean CSS block inside `<style>`.

Focus on:
- Legible typography
- Clean layout for PDF compatibility
- Avoid heavy animations, floats, JS

Future PRs may replace inline CSS with a dedicated stylesheet.

---

## 6. Logging

Only when `debug_mode = 1`:
- Log rendering start
- Log rendering completion
- Log header/summary/plugin-segment generation

Use `satori_audit_log()`.

---

## Acceptance Criteria

- `Reports::get_report_html()` returns valid HTML for any report ID.
- Admin preview screen displays the full report consistently.
- Plugin update list renders vertically.
- Diagnostics section respects settings toggle.
- No UI modifications outside the report preview.
- No Notifications, PDF, or Automation logic introduced.
- No PHP warnings, notices, or fatal errors.
