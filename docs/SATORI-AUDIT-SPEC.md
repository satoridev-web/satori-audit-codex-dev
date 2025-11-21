# SATORI Audit – R3P Plugin Spec
*Version 0.1 — Draft*

---

## 1. Overview

**Plugin Name:**  
SATORI – Audit & Reports (short name: SATORI Audit)

**Slug / Folder Name (local plugin):**  
`satori-audit`

**Repository Name (GitHub):**  
`satori-audit-codex-dev`

**Namespace / Prefix:**  
- PHP namespace root (option A): `Satori_Audit`  
- Or class prefix (option B): `Satori_Audit_`  
(Final choice to match SATORI Standards and autoloader pattern.)

**Text Domain:**  
`satori-audit`

**Minimum Versions:**  
- WordPress: 6.1+  
- PHP: 8.0+

**Primary Purpose:**  
Provide a lean, repeatable monthly site service log for client sites, focused on:

- Automatically capturing plugin inventory and version changes each month.  
- Producing a BALL-style service log (HTML preview + CSV/PDF export).  
- Allowing manual editing and commentary prior to finalising a report.  
- Maintaining a filterable archive of past reports.

**Target Users:**  
- Agencies and freelancers providing monthly maintenance for WordPress sites.  
- Internal SATORI team for managed service clients.

---

## 2. Scope

### 2.1 Goals (v1)

- [ ] Automatically create and maintain a monthly service report for the current site.
- [ ] Capture plugin inventory and highlight NEW / UPDATED / REMOVED plugins.
- [ ] Provide an HTML report preview using a BALL-style service log layout.
- [ ] Support CSV export for plugin inventory.
- [ ] Support PDF export for the full report (with basic PDF diagnostics).
- [ ] Provide a spreadsheet-style editor for report data (plugin rows, security rows).
- [ ] Store reports in an archive with filters by month/year.
- [ ] Provide a Settings screen (service details, notifications, access control, automation, PDF engine tools).
- [ ] Use SATORI Standards for structure, naming, logging, and CI/CD.

### 2.2 Non-Goals (v1)

- Multi-site network management (single-site focus only).
- Deep security scanning (may integrate with security plugins in future).
- Licensing/update server integration (ensure compatibility but do not implement).
- Complex dashboards or charts (keep UI pragmatic and text/table driven).

---

## 3. Core Features

### Feature A – Monthly Service Log Engine

**Description:**  
Create and manage monthly service reports. Each report is tied to a period key (`YYYY-MM`) and stores service- and technician-level metadata.

**Key Requirements:**

- One “main” report per month per site (period key `YYYY-MM`).
- Manual controls:
  - Generate/Update current month report.
  - Lock/Unlock report (locking prevents edits and regeneration without confirmation).
- Each report stores:
  - Service details: site name, URL, managed by, start date, service date, end date.
  - Client details: client name, contact notes.
  - Technician details: name, email, phone.
  - Legend / threat-level explanation.
  - Overview summary (security, general, misc, comments).

**Behaviour:**

- On “Generate report”:
  - Create report if none exists for the current month.
  - If report exists and is unlocked:
    - Refresh plugin inventory (Feature B).
    - Preserve any existing manual notes where possible.
- On “Lock report”:
  - Mark report as final; editing requires explicit unlock action.

---

### Feature B – Plugin Inventory & Change Tracking

**Description:**  
Capture plugin metadata for each report and identify changes since the previous report.

**Data captured per plugin row:**

- `plugin_slug`  
- `plugin_name`  
- `plugin_description` (shortened for layout)  
- `plugin_type` (FREE / FREEMIUM / PREMIUM – editable)  
- `version_from` (previous version, if applicable)  
- `version_to` (current version, if applicable)  
- `version_current` (current version)  
- `is_active` (active/inactive)  
- `status_flag` (NEW / UPDATED / DELETED / UNCHANGED)  
- `price_notes` (free text)  
- `comments` (free text)  
- `last_checked` (datetime UTC)

**Behaviour:**

- Compare current plugin list from WordPress against the last available report:
  - New plugins → flagged as NEW.
  - Version changes → flagged as UPDATED; `version_from` and `version_to` populated.
  - Missing plugins (previously present, now gone) → flagged as DELETED.
  - Unchanged plugins → flagged as UNCHANGED.
- DELETED rows appear in the period where removal occurs and remain in that report’s archive.

**Edge Cases:**

- If plugin slug changes or plugin is renamed:
  - Treated as NEW by default (future enhancement may allow mapping).
- When regenerating for a month:
  - Preserve manual columns (`price_notes`, `comments`, `plugin_type`) for matching slugs.

---

### Feature C – HTML Preview, CSV & PDF Export

**Description:**  
Provide a clear HTML preview of each report and allow exporting to CSV and PDF.

**HTML Preview:**

- Layout modelled on BALL Service Log style:
  - Header (SATORI contact details, client details, logo).
  - Service details & technician details.
  - Legend and threat-level explanation.
  - Plugin list table.
  - Security/known issues (if applicable).
  - Overview section (high-level notes).

**Exports:**

- CSV:
  - Exports plugin inventory table for the selected report.
  - Includes all plugin row fields, suitable for spreadsheets.

- PDF:
  - Full report output (all sections) in a PDF-ready layout.
  - Use one consistent PDF engine (e.g., DOMPDF or similar) or browser-based print-to-PDF as fallback.
  - Provide a PDF diagnostics panel in Settings:
    - Show engine status and required PHP extensions.

---

### Feature D – Report Archive & Filters

**Description:**  
Archive and view historical reports.

**Requirements:**

- Use a custom post type for reports (e.g. `satori_audit_report`).
- Admin “Archive” screen with:
  - Filter by month/year.
  - List of reports showing:
    - Period (YYYY-MM).
    - Site name.
    - Status (Draft / Finalised / Locked).
    - Summary counts (e.g., `3 updated, 1 new, 0 deleted`).
- Clicking a report:
  - Opens HTML preview plus an “Edit” toggle.

---

### Feature E – Spreadsheet-style Report Editor

**Description:**  
Allow inline editing of report content via table-style UI.

**Requirements:**

- For each report:
  - Tabs: Overview, Plugin List, Security, Known Issues.
- Plugin List tab:
  - Grid/table-based editor (WP List Table or small JS grid).
  - Inline editing for:
    - `plugin_type`
    - `price_notes`
    - `comments`
  - Ability to:
    - Manually add a row (custom tools or services).
    - Delete a row.
- Security and Known Issues tabs:
  - Add/edit rows for:
    - Vulnerability type.
    - Description.
    - CVSS score.
    - Severity.
    - Attack report / notes.
    - Solution.
    - Action required flag.

**Behaviour:**

- Save changes via AJAX or standard form save.
- Reflect edits instantly in HTML preview.

---

### Feature F – Settings, Notifications, Access Control & Automation

**Description:**  
Central settings screen with tabbed UI.

**Tabs & Fields:**

1. **Service Details**
   - Client / Organisation.
   - Site Name & URL.
   - Managed By (company/team).
   - Start Date, Service date default behaviour.
   - PDF Header Logo (media upload).

2. **Notifications**
   - From Email.
   - Default recipients for monthly report.
   - Optional webhook URL for external logging.
   - Option to suppress WP core/plugin auto-update emails (where practical).

3. **Recipient Safelist**
   - Enforce safelist (boolean).
   - List of allowed email addresses/domains.

4. **Access Control**
   - Capability required to:
     - Manage reports.
     - Change settings.
     - Export CSV/PDF.
   - Main Administrator Email for critical notices.

5. **Automation**
   - Enable/disable Monthly PDF Email.
   - Day-of-month and time for sending.
   - History retention window (months; 0 = keep all).
   - Placeholders for future daily watch/alerts (can be disabled for v1).

6. **Display & Output**
   - Toggle visibility of specific sections in the report.
   - PDF page size and orientation.
   - Optional branding tweaks (footer text, etc.).

7. **PDF Engine & Diagnostics**
   - Show whether the chosen PDF engine is:
     - Installed.
     - Correctly configured.
   - “Check path” / “Probe engine” actions with admin notices.

**Automation Behaviour:**

- If Monthly PDF Email is enabled:
  - Schedule a `wp_cron` job.
  - On run:
    - Ensure current period report exists (create if missing).
    - Lock report (if configured).
    - Generate PDF and email to configured recipients.

---

## 4. Data Model

### 4.1 Custom Post Type: `satori_audit_report`

- Supports: title, editor (optional), custom fields.
- Not public-facing by default.
- Stores high-level report metadata via post meta, including:
  - Period (`_satori_audit_period` – `YYYY-MM`).
  - Site/client information.
  - Technician information.
  - Threat level and overview sections.
  - Lock/final status flags.

### 4.2 Custom Tables

Use custom tables for structured rows:

1. **Plugins Table** (e.g. `{$wpdb->prefix}satori_audit_plugins`)
   - `id` (PK)
   - `report_id` (FK → CPT ID)
   - `plugin_slug`
   - `plugin_name`
   - `plugin_description`
   - `plugin_type`
   - `version_from`
   - `version_to`
   - `version_current`
   - `is_active`
   - `status_flag`
   - `price_notes`
   - `comments`
   - `last_checked`

2. **Security Table** (e.g. `{$wpdb->prefix}satori_audit_security`)
   - `id` (PK)
   - `report_id` (FK)
   - `vulnerability_type`
   - `description`
   - `cvss_score`
   - `severity`
   - `attack_report`
   - `solution`
   - `comments`
   - `action_required`

### 4.3 Settings Storage

- Single grouped option: `satori_audit_settings` (associative array).
- Handles all fields from the Settings tabs.

### 4.4 Logging

- Helper function `satori_audit_log( $message, $context = [] )`.
- Writes to a log file in a plugin-/SATORI-specific log directory.
- Honour global SATORI debug flags/constants if present.

---

## 5. Architecture & Structure

### 5.1 Files & Folders (initial)

- `satori-audit.php` (main plugin bootstrap file)
- `README.md`
- `CHANGELOG.md`
- `LICENSE`
- `PROJECT_SPEC.md`
- `docs/`
  - `SATORI-AUDIT-SPEC.md`
- `admin/`
  - `class-satori-audit-admin.php`
  - `class-satori-audit-screen-dashboard.php`
  - `class-satori-audit-screen-archive.php`
  - `class-satori-audit-screen-settings.php`
- `includes/`
  - `class-satori-audit-plugin.php` (core bootstrap)
  - `class-satori-audit-cpt.php`
  - `class-satori-audit-tables.php` (DB schema/migrations)
  - `class-satori-audit-reports.php`
  - `class-satori-audit-plugins-service.php`
  - `class-satori-audit-pdf.php`
  - `class-satori-audit-logger.php`
  - `class-satori-audit-automation.php`
- `assets/css/`
- `assets/js/`
- `templates/`
  - Admin HTML preview templates.
  - (Optional) Front-end templates.
- `languages/`
  - `.pot` file for translation.

### 5.2 Autoloader

- Single autoloader registered in main plugin file.
- Map namespace/prefix to `admin/` and `includes/` directories.
- Follow SATORI naming patterns (lowercase file names, hyphen/underscore conventions as required).

---

## 6. Admin UX

- Menu entry: **SATORI Audit** (positioned near other SATORI tools).
- Sub-pages:
  - Dashboard (current month, quick controls).
  - Archive (list of all reports).
  - Settings (tabs).

**Dashboard:**

- Period selector (dropdown for month/year).
- Buttons:
  - Generate/Update Current Month.
  - Lock/Unlock report.
  - Export CSV.
  - Export PDF.
- HTML preview of selected report.

**Archive:**

- List table with filters and actions for each report.
- Columns for period, status, summary counts.

**Report Editor:**

- For a selected report:
  - Tabs for Overview, Plugin List, Security, Known Issues.
  - Grid-based editing within each tab.

**Settings:**

- Tabbed layout for all configuration.
- Use WordPress Settings API and SATORI-standard admin notices.

---

## 7. Hooks & Filters (Initial)

Define key extension points:

- `do_action( 'satori_audit_before_report_generate', $period )`
- `do_action( 'satori_audit_after_report_generate', $report_id, $period )`
- `apply_filters( 'satori_audit_plugin_rows', $rows, $report_id )`
- `apply_filters( 'satori_audit_security_rows', $rows, $report_id )`
- `apply_filters( 'satori_audit_pdf_filename', $filename, $report_id )`

---

## 8. CI/CD & Standards

- Conform to SATORI Standards for structure, naming, and logging.
- Provide:
  - `README.md`
  - `CHANGELOG.md`
  - `LICENSE`
  - `PROJECT_SPEC.md`
  - `docs/SATORI-AUDIT-SPEC.md`
- Integrate with GitHub Actions (build/lint/zip workflow) once code is in place.

---

## 9. Acceptance Criteria (Summary)

- Plugin activates without errors on PHP 8.0+ / WP 6.1+.
- DB tables are created on activation and versioned for future migrations.
- Admin menu “SATORI Audit” appears with Dashboard, Archive, and Settings.
- “Generate Current Month” creates or updates the report and plugin rows correctly.
- HTML preview resembles BALL-style service log layout.
- CSV export works and contains correct plugin data.
- PDF export works or provides a clear diagnostic/fallback when engine unavailable.
- Spreadsheet editor allows inline editing and persists values.
- Settings page stores all configuration in `satori_audit_settings`.
- Monthly automation (when enabled) generates and emails the PDF report for the current period.
- Plugin passes PHPCS (WordPress standards) with SATORI’s CI/CD workflow once added.

---

*End of Spec*
