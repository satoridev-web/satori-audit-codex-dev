# PR-SPEC – Simple History integration toggle & safety guard
*Slug: simple-history-integration-toggle*  
*Plugin: SATORI Audit*  

---

## 1. Background

SATORI Audit currently attempts to query the Simple History plugin table
(`wp_simple_history`) to backfill plugin update information for reports.

On some installs the `wp_simple_history` schema does **not** include a
`context` column. The hard-coded query:

```sql
SELECT date, context FROM wp_simple_history
WHERE action IN ('plugin_updated', 'updated', 'plugin_update')
  AND date BETWEEN '...' AND '...'
ORDER BY date DESC
```

causes a WordPress database error:

> Unknown column 'context' in 'field list'

This error is emitted as HTML before the PDF bytes, corrupting the PDF
output and making it unreadable.

Important notes:

- Simple History was only intended as a **one-off bootstrap/backfill**
  mechanism to avoid losing a month of plugin-version history when
  SATORI Audit was first installed.
- Long-term, SATORI Audit should primarily rely on its **own** stored
  version history, not a live dependency on Simple History.

Goal of this PR:

- Remove the **hard dependency** on Simple History from the normal report
  pipeline, and make any Simple History usage **explicitly optional and
  schema-safe**.

---

## 2. Goals

1. Add a settings-level toggle for Simple History integration so it can
   be fully **disabled** (default), or **enabled (safe)** if the admin
   wishes to pull from Simple History.
2. Ensure that no Simple History query can emit a WordPress DB error
   into the PDF output under any circumstances.
3. Keep existing SATORI Audit data model intact (no schema changes).
4. Maintain backwards compatibility with existing installs:
   - If Simple History is present and the admin wants to use it, they
     can enable it with a clear label and behaviour.
5. Lay a foundation for a future one-off “Import from Simple History”
   tool without keeping a hard runtime dependency in the report
   pipeline.

---

## 3. Scope

### 3.1 In Scope

- Settings UI additions for a Simple History option.
- Changes to any helper methods that query `wp_simple_history`.
- Guarding those queries with:
  - Existence checks for the table and required columns.
  - Early return when disabled or schema is incompatible.
- Logging meaningful messages to SATORI’s debug log instead of throwing
  visible errors.

### 3.2 Out of Scope

- Changes to Simple History itself.
- Any new database tables in SATORI Audit.
- A full “one-click backfill wizard” (can be a future PR).
- Visual styling of the report (handled in other PRs).

---

## 4. Implementation Details

### 4.1 Settings: Simple History integration toggle

Add a new setting on the **PDF Engine & Diagnostics** tab (or another
sensible diagnostics-related tab):

**Label:**  
> Plugin Update Source (Simple History)

**Control type:**  
`<select>` with the following options:

- `Disabled` (value: `none`)  
  > Do not query Simple History. Use SATORI’s own data only.

- `Simple History (safe)` (value: `simple_history_safe`)  
  > Attempt to merge updates from Simple History, but only if the table
    and required columns exist. Otherwise, log a message and skip.

Internal option key (in settings array):

```php
'plugin_update_source' => 'none' | 'simple_history_safe'
```

Default value: **`none`**.

The settings class must:

- Register this field with a default.
- Sanitize input so only known values are stored (fallback to `none`).

### 4.2 Data layer: Simple History helper

Locate the helper method that currently runs the Simple History query
(e.g. `get_updates_from_simple_history()`).

Refactor it as follows:

- Accept the settings array or the `plugin_update_source` value as an
  argument, or retrieve from `Settings::get_settings()` internally.
- Immediately short-circuit when the setting is `none`:

  ```php
  if ( 'simple_history_safe' !== $settings['plugin_update_source'] ) {
      return array();
  }
  ```

- Before running any query, validate that:

  1. The `wp_simple_history` table exists.
  2. The columns you intend to use exist. At minimum, check `date` and
     whatever other columns are actually used in the mapping (e.g.
     `message` or `context`, depending on the final design).

  Example:

  ```php
  global $wpdb;

  $table = $wpdb->prefix . 'simple_history';

  // Table exists?
  $has_table = $wpdb->get_var(
      $wpdb->prepare(
          "SHOW TABLES LIKE %s",
          $table
      )
  );

  if ( $has_table !== $table ) {
      self::log_simple_history(
          'Simple History integration skipped: table not found: ' . $table
      );
      return array();
  }

  // Column checks (example using `date` and `message`).
  $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );

  foreach ( array( 'date', 'message' ) as $required ) {
      if ( ! in_array( $required, $columns, true ) ) {
          self::log_simple_history(
              'Simple History integration skipped: missing column "' . $required . '" on ' . $table
          );
          return array();
      }
  }
  ```

- After passing checks, build a **safe** query using `wpdb::prepare`:

  ```php
  $sql = $wpdb->prepare(
      "SELECT date, message
       FROM {$table}
       WHERE action IN ('plugin_updated', 'updated', 'plugin_update')
         AND date BETWEEN %s AND %s
       ORDER BY date DESC",
      $start_date,
      $end_date
  );

  $rows = $wpdb->get_results( $sql );
  ```

- Map `$rows` into a **normalised internal structure** that the rest of
  the report code already understands (e.g. plugin slug, previous
  version, new version, date). If you cannot reliably derive those
  details from Simple History `message` content, return an empty array
  rather than partially broken data.

- Introduce a small private helper for logging:

  ```php
  private static function log_simple_history( string $message ): void {
      if ( function_exists( 'satori_audit_log' ) ) {
          satori_audit_log( '[Simple History] ' . $message );
      }
  }
  ```

### 4.3 Report pipeline integration

Wherever the Simple History data is merged into the plugin updates for
the report, ensure the code:

- Calls the refactored helper, which already respects the new setting
  and schema checks.
- Treats the helper’s return value as optional, e.g.:

  ```php
  $updates = self::get_core_plugin_updates( $report_id );

  $sh_updates = self::get_updates_from_simple_history(
      $window_start,
      $window_end,
      $settings
  );

  if ( ! empty( $sh_updates ) ) {
      $updates = array_merge( $updates, $sh_updates );
  }
  ```

Critically: **no errors or warnings** from Simple History should ever be
echoed to the browser or PDF stream. If Simple History is misconfigured
or missing, it should simply log and return an empty array.

### 4.4 PDF generation and error safety

The existing PDF refactor already centralises PDF generation in a single
method (e.g. `PDF::generate_pdf()`).

To harden against future unexpected output, optionally wrap the
generation in an output buffer:

- Start an output buffer at the beginning of `generate_pdf()`.
- After engine rendering and before writing the file, call
  `ob_get_clean()` and log the first 200–300 characters of any
  unexpected output via `satori_audit_log()`.
- Ensure buffers are cleaned up on exceptions.

This is not a substitute for fixing Simple History, but an extra guard.

---

## 5. Testing & Acceptance Criteria

### 5.1 Manual tests

Use a local install with SATORI Audit and Simple History enabled.

1. **Disabled (default)**
   - Set **Plugin Update Source (Simple History)** to `Disabled`.
   - Generate a report and PDF for a month with known plugin updates.
   - Confirm:
     - No queries to `wp_simple_history` are executed.
     - No WordPress DB error is shown on the Archive screen.
     - The PDF file starts directly with `%PDF-1.x` and opens normally.
     - Plugin updates section still shows entries based on SATORI’s own
       data.

2. **Enabled, valid schema**
   - Ensure `wp_simple_history` has at least the required columns (e.g.
     `date`, `message`).
   - Set Plugin Update Source to `Simple History (safe)`.
   - Generate a report and PDF.
   - Confirm:
     - No DB errors.
     - Optional: SATORI updates are enriched with extra entries from
       Simple History where appropriate.
     - PDF opens and layout remains consistent.

3. **Enabled, invalid schema (simulate missing column)**
   - Temporarily rename a column in `wp_simple_history` or test on an
     environment that lacks the required column.
   - Generate a report and PDF.
   - Confirm:
     - No visible WordPress errors on the Archive page.
     - The SATORI Debug log contains a message like:
       > `[Simple History] Simple History integration skipped: missing column "message" on wp_simple_history`
     - PDF opens correctly.

4. **No Simple History plugin**
   - Deactivate Simple History or test on a site without it.
   - With Plugin Update Source set to `Simple History (safe)`:
     - Report generation should log that the table was not found and
       gracefully continue.
     - No PHP notices or DB error HTML is emitted.
     - PDF opens correctly.

### 5.2 Acceptance criteria

- [ ] When Simple History is disabled (default), SATORI Audit does **not**
      touch `wp_simple_history` at all.
- [ ] When enabled, Simple History integration **only** runs if the
      table and required columns exist.
- [ ] No Simple History-related DB errors ever appear on the Archive
      screen or in generated PDFs.
- [ ] Existing SATORI Audit plugin-update tracking continues to work as
      before.
- [ ] PHPCS/CI pass.

---

## 6. Follow-up Work (separate PRs)

- Add a one-off “Import from Simple History” tool under SATORI Audit →
  Tools, which:
  - Reads historical plugin updates from Simple History.
  - Writes them into SATORI’s own version-history store.
  - Allows Simple History to be safely disabled afterwards.
- Enhance the report UI to mark entries sourced from Simple History as
  “imported” vs “native”.
