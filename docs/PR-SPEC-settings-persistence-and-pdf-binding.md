# PR Spec: Fix Settings Persistence & Bind Settings to Reports/PDF — SATORI Audit

**Target Branch:**  
`main`

**New Feature Branch:**  
`codex/fix-settings-persistence-and-pdf-binding`

---

## Summary

Two issues have been observed in the current SATORI Audit build:

1. **Settings are not “sticky” across tabs.**  
   - Filling in fields on any Settings tab and clicking **Save Changes** appears to work, but when navigating away to another tab and then back again, the fields are **blank**.  
   - This suggests that settings are **not being saved or loaded correctly**.

2. **PDF exports are missing settings-driven data.**  
   - The generated PDF now renders the Template v2 layout and plugin updates, but fields such as **Client** and other service metadata are empty.  
   - This is likely a consequence of the same settings persistence issue and/or missing bindings from settings → report HTML → PDF.

This PR must:

- Fix the **settings registration, saving, and retrieval** logic so that all tabs persist values correctly.
- Ensure settings values are correctly wired into **Report Preview (HTML)** and **PDF output**.
- Add minimal “Template v2 enrichment” using existing settings (client, service notes, diagnostics toggle, date format).

It must **not** change Notifications behaviour, Automation schedules, Access Control semantics, or REST API endpoints beyond what is required to read settings.

---

## Problems to Fix

1. **Settings forms do not persist user input**
   - After entering values and clicking “Save Changes”:
     - No obvious error is shown.
     - When returning to the same tab, all fields are empty.
   - All tabs (Service Details, Notifications, Recipient Safelist, Access Control, Automation, Display & Output, PDF Engine & Diagnostics) appear affected.

2. **Report header fields and diagnostics sections are incomplete**
   - In HTML and PDF:
     - `Client` is blank, even when entered in Settings.
     - Other service-level fields (service notes, managed by, etc.) may not reflect the current settings.
     - Diagnostics section visibility may not honour “Show diagnostics section” or related toggles.

---

## Design & Approach

### 1. Settings Storage Strategy

Codex should inspect the existing settings implementation in:

- `admin/screens/class-satori-audit-screen-settings.php`
- Any related helper classes (e.g. utility methods for `get_option` or per-section settings)
- The main plugin class where settings are registered (e.g. `includes/class-satori-audit-plugin.php` or similar).

The goal is to ensure a **coherent, minimal** settings model such as:

- A **single option array** (preferred), e.g. `satori_audit_settings`, containing keys for all settings, OR
- A small number of logically grouped options (`satori_audit_service`, `satori_audit_notifications`, etc.)

Codex may choose the model that best matches the current code, but it must:

- Be consistent across all tabs.
- Be read via a thin helper such as:

  ```php
  public static function get_setting( string $key, $default = '' )
  ```

  which looks up the correct option (or array key) in one place.

### 2. Fix Settings Registration & Form Handling

For each Settings tab:

- Ensure **`register_setting()`** is called with:
  - A **group name** (e.g. `satori_audit_service`, `satori_audit_notifications`, etc.)
  - A valid **option name** (e.g. `satori_audit_settings` or specific option strings)
  - A sanitization callback if needed.

- Ensure the Settings form for the active tab outputs:

  ```php
  <form method="post" action="options.php">
      <?php
          settings_fields( $group ); // matches register_setting group
          do_settings_sections( $page ); // where $page matches add_options_page/add_menu_page
      ?>
      <?php submit_button(); ?>
  </form>
  ```

  Where:
  - `$group` is the correct group name for the active tab.
  - `$page` is the screen slug (e.g. `satori-audit-settings`).

- If the code uses a custom form rather than `do_settings_sections()`, ensure:
  - Hidden fields `option_page` and `action` and nonce(s) are correctly set.
  - The tab identifier (e.g. `tab=service-details`) is preserved when redirecting back after save.

- After saving, WordPress should redirect back to the same page with `settings-updated=1`.  
  Codex should ensure that **the active tab is preserved** via a `tab` query arg or hidden input to avoid confusing the user.

### 3. Fix Settings Loading (Sticky Values)

Wherever settings fields are rendered:

- Use the same helper (e.g. `Settings::get_setting( 'client' )`) to populate `value=""` or `checked="checked"` etc.
- Ensure **array keys/names match** the keys used in the form.

Example pattern:

```php
$client = Settings::get_setting( 'client', '' );
?>
<input type="text"
       name="satori_audit_settings[client]"
       value="<?php echo esc_attr( $client ); ?>" />
<?php
```

Codex should align the **form field names**, the **option structure**, and the **get_setting()** logic so that:

- After saving, each field re-renders with the stored value.
- Each tab correctly reflects its persisted settings.

### 4. Bind Settings to Report HTML & PDF

Once settings persistence is fixed, update the report rendering to **pull these settings**:

Files to inspect:

- `includes/class-satori-audit-reports.php` (or equivalent report rendering class)
- `templates/admin/report-preview.php`
- `includes/class-satori-audit-pdf.php` (if it needs access to settings; typically it calls the HTML renderer so settings flow through indirectly).

Requirements:

- **Header block** should use:
  - `client` setting for the Client value.
  - `site_name` and `site_url` settings if present, or fallback to `get_bloginfo()` if empty.
  - `managed_by` setting.
  - `service_start_date` setting, rendered using the configured date format.

- **Summary / Service notes**:
  - If there is a “Service Notes” or equivalent setting, include it in the Summary section or a dedicated “Service Notes” block in both HTML and PDF.

- **Diagnostics section toggle**:
  - Respect the Display/Diagnostics setting that indicates whether a Diagnostics section should be shown.
  - If “Show diagnostics” is OFF:
    - Hide the diagnostics block in HTML and PDF.
  - If ON:
    - Show diagnostics, using existing diagnostics data (no new diagnostics computation is required).

- **Date formatting**:
  - Use the **Display Settings** date format (e.g. `d/m/Y`, `j F Y`, etc.) when rendering:
    - Report Date
    - Plugin update dates
    - Service start date

  If no custom format is set, fallback to `j F Y`.

### 5. Keep the Settings UI and Semantics Stable

- Do not rename options in the database if it can be avoided. If restructuring is necessary, include a migration path that:
  - Reads existing values.
  - Writes them into the new structure.
  - Avoids data loss for any existing installs.

- Do not remove settings fields; only fix their persistence and usage.

---

## Files to Touch

Codex will need to inspect and likely modify:

- `admin/screens/class-satori-audit-screen-settings.php`
- `includes/class-satori-audit-plugin.php` (or wherever `register_setting` is called)
- `includes/class-satori-audit-reports.php`
- `templates/admin/report-preview.php`
- `includes/class-satori-audit-pdf.php` (only as needed to ensure HTML-based rendering carries settings through)
- Any helper or utility file used for reading settings (if such a class exists; if not, Codex may introduce a lightweight static helper under `includes/`.

---

## Constraints

- Do **not** change the public APIs used by other parts of the plugin (e.g. REST endpoints, notifications) beyond reading settings correctly.
- Do **not** introduce new admin pages.
- Do **not** add new settings unless strictly necessary; focus on making existing ones work.
- Keep any new helper classes namespaced under `Satori_Audit\` and autoloaded consistently with the existing plugin.

---

## Acceptance Criteria

- After filling in **any** Settings tab and clicking **Save Changes**:
  - Values persist when returning to that tab.
  - Values persist across page reloads and browser sessions.
- Service Details, Notifications, Safelist, Access Control, Automation, Display & Output, and PDF Engine & Diagnostics all exhibit sticky behaviour.
- HTML Report Preview:
  - Shows Client, Service Start Date, Managed By, and other configured settings correctly.
  - Applies the chosen date format.
  - Shows or hides Diagnostics according to the Display setting.

- PDF Export:
  - Produces a valid PDF that mirrors the HTML report header fields and key sections (including Service metadata and Diagnostics toggle).
  - No PHP warnings, notices, or fatal errors are logged during settings save or PDF export.
