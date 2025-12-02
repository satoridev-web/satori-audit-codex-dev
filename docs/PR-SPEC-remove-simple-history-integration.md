# PR-SPEC: Remove Simple History integration and harden PDF export

**Status:** Ready  
**Target:** `main` (satori-audit-codex-dev)  
**Plugin:** SATORI Audit

---

## 1. Background

The SATORI Audit plugin previously used **Simple History** as a fallback to retrieve historic plugin update events.  
This was only intended as a *temporary bootstrap mechanism* to allow one initial month of data to be captured.

However:

- The Simple History schema changed (`context` field no longer exists).
- Many sites do not use Simple History.
- The plugin now throws DB errors on archive pages.
- These DB warnings leak into the **PDF output stream**, corrupting the resulting PDF.
- Our new PDF transport pipeline (PR #37) is now correct, but the *input HTML* is still polluted.

The decision:  
We do **NOT** want Simple History integration anymore.  
Instead, SATORI Audit will maintain its **own internal plugin update records** and allow the user to manually input the missing ~4 weeks of historical data.

---

## 2. Goals

### ✔ Remove Simple History dependency entirely
- Delete all SQL queries referencing `wp_simple_history`.
- Delete any conditionals related to Simple History.
- Delete helper classes related to that feature.

### ✔ Replace plugin update source with internal SATORI Audit data
Add a lightweight internal system:
- Custom table: `wp_satori_audit_plugin_updates`
- Or use postmeta option `satori_audit_plugin_updates` (initial approach preferred by Codex).
- Store:
  - plugin slug
  - previous version
  - new version
  - updated date (timestamp)
  - optional notes

### ✔ Support manual backfill for one month
Inside **Settings → Display & Output** add:
- “Manual Plugin History Backfill” panel
- Allow user to enter:
  - Plugin name
  - Previous version
  - Updated version
  - Updated on (date)
- Entries are appended to the internal list.

### ✔ Keep PDF generation robust
- No errors must be echoed before PDF headers (done in PR #37).
- No DB warnings may appear in output stream.
- PDF must render using full HTML layout.

---

## 3. Non‑Goals

- No need to import from Simple History anymore.
- No need to detect Simple History presence.
- No need to modify the report visual style.
- No need to track future plugin updates automatically (phase 2).
- No need to rewrite the data model fully yet.

---

## 4. Implementation Details

### 4.1 Remove Simple History Query

Delete all logic resembling:

```php
SELECT date, context FROM wp_simple_history ...
```

Remove:
- `class-satori-audit-plugin-update-source.php`
- Any Simple-History-related conditional blocks in:
  - `class-satori-audit-reports.php`
  - `class-satori-audit-data.php`
  - `class-satori-audit-archive.php`

Replace with:

```php
$updates = Satori_Audit\Data\Plugin_Updates::get_updates_for_period( $start, $end );
```

---

### 4.2 Create Internal Storage Class

Create file:

`includes/data/class-satori-audit-plugin-updates.php`

Responsibilities:
- Store / retrieve plugin update entries.
- Validate version strings.
- Enforce strict data schema.
- Provide retrieval by month.

Structure example (stored in single option):

```php
[
  [
    'plugin' => 'lite-speed-cache',
    'previous' => '7.5',
    'current'  => '7.6.2',
    'updated'  => '2025-11-28',
  ],
]
```

Provide functions:

```php
add_update( $plugin, $previous, $current, $date )
get_updates_for_period( $start, $end )
delete_update( $id )
list_updates()
```

---

### 4.3 Update the Reports Builder

In `class-satori-audit-reports.php`:

Replace Simple History lookup with:

```php
$updates = Plugin_Updates::get_updates_for_period( $start, $end );
```

Then inject into HTML.

---

### 4.4 Update Settings UI

A new section under **Display & Output**:

**Manual Plugin Update Entry**

Fields:
- Plugin Name (text)
- Previous Version (text)
- Updated Version (text)
- Updated On (date)

Actions:
- “Add Entry”
- “Delete Entry”
- “Export JSON”
- “Import JSON”

---

## 5. Internal Data Design

We will start with the simplest correct structure:

### Option name:
```
satori_audit_plugin_updates
```

### Option structure:

```php
[
  'version' => 1,
  'updates' => [
     [
        'p' => 'plugin-slug',
        'v_prev' => '1.0.0',
        'v_new'  => '1.2.0',
        'date'   => '2025-11-25'
     ],
  ]
]
```

We keep it compact for efficiency.

---

## 6. PDF Output Hardening

Already implemented in PR #37:

- Clean output buffers before rendering.
- Stream-safe writing to PDF engines.
- Remove stub content in TCPDF layer.
- Prevent DOMPDF fallback corruption.

This PR ensures **no DB errors appear in the stream** by removing the Simple History query.

---

## 7. Files Modified in This PR

### Removed
```
includes/simple-history/*
includes/data/class-satori-audit-simple-history.php
```

### Added
```
includes/data/class-satori-audit-plugin-updates.php
templates/settings/plugin-updates-panel.php
```

### Updated
```
class-satori-audit-reports.php
class-satori-audit-archive.php
class-satori-audit-settings.php
class-satori-audit-pdf.php
assets/css/admin-settings.css
```

---

## 8. Testing Plan

### 8.1 Before applying PR
- DB error visible in “Archive” screen.
- PDF corruption seen (“%PDF stub…”).
- PDF only renders one line.

### 8.2 After applying PR

#### 1. Archive Screen
- No DB errors.
- Plugin Update section displays entries from internal storage.

#### 2. Manual Backfill
- Add test entry in settings → Display & Output.
- Confirm it appears in monthly archive.

#### 3. PDF Export
- PDF includes all sections.
- No partial content.
- No blank pages.
- No output corruption.

#### 4. Switching Engines
Test:
- Automatic
- DOMPDF
- TCPDF

All must fully render the HTML included in `satori-audit-pdf-last.html`.

---

## 9. Future Extension (Not part of this PR)

- Automatic background tracking of plugin updates.
- User-exportable audit history.
- Multi-site support.

---

## 10. Codex Action Summary (for automation)

Implement the following work:

1. **Delete all Simple History references**.
2. **Add internal plugin update storage system**.
3. **Update report builder to use internal data**.
4. **Add manual backfill UI to settings**.
5. **Ensure PDF generation uses the full HTML template**.
6. **Ensure no warnings leak into PDF output**.
7. **Add logging for missing/invalid update entries**.

---

## 11. Ready for Codex PR Creation

Paste the following block into Codex:

```
/codex plan
Implement removal of Simple History integration and replace with internal plugin update storage.
Refactor archive and PDF builder to use internal data model.
Add manual backfill UI and internal data structure.
Ensure PDF output is clean and free of warnings.
Add tests for storage retrieval and PDF HTML hydration.
```

---

## END OF SPEC
