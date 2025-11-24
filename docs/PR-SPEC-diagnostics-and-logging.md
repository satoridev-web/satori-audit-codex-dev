# PR Spec: Implement Diagnostics & Logging Enhancements — SATORI Audit

**Target Branch:**  
`main`

**New Feature Branch:**  
`codex/diagnostics-and-logging`

**Summary:**  
Enhance the Diagnostics & Logging subsystem in SATORI Audit to fully honour the Diagnostics settings screen, including debug mode, file logging, and log retention.  
This PR must NOT modify UI, notifications, PDF, automation, or report rendering.  
It strictly improves internal logging behaviour.

---

## Settings to Honour

From the **Diagnostics** tab:

- `debug_mode` (on/off)
- `log_to_file` (boolean)
- `log_retention_days` (integer)

Additional behaviour to support future features, but not implemented here.

---

## Changes Required

### 1. Extend/Replace Logger Class  
File: `includes/class-satori-audit-logger.php`

Namespace: `Satori_Audit`  
Ensure: `declare(strict_types=1);`

Provide:

- `public static function log( string $message ): void`
- `private static function write_to_file( string $line ): void`
- `private static function should_log(): bool`
- `private static function prune_old_logs(): void` (basic implementation)

Behaviour:

- `debug_mode = 0`  
  → Only log critical/internal errors (for now treat as “log nothing unless explicitly required”)

- `debug_mode = 1`  
  → Log all messages passed to `log()`

If `log_to_file = 1`:

- Write logs to directory:
  `wp-content/uploads/satori-audit/logs/`
- Ensure directory exists  
- Filename: `audit.log`  
- Format lines as:  
  `[YYYY-MM-DD HH:MM:SS] message`

---

### 2. Implement Log Retention  
Retention can be **basic** in this PR:

- On each new log write:
  - If `log_retention_days` > 0:
    - Delete any log files older than that number of days  
      (glob for `*.log` in the logs folder)
- If retention = 0 or invalid → skip pruning

A more advanced rotation system is for a later PR.

---

### 3. Provide Global Helper  
Add a global wrapper in `satori-audit.php`:

```php
function satori_audit_log( string $message ): void {
    \Satori_Audit\Logger::log( $message );
}
```

Verify it is declared **only once** and wrapped in an `if ( ! function_exists() )`.

This provides a unified logger API for the entire plugin.

---

### 4. Add Logging Calls to Key Systems  
Without introducing new features, add silent logging to:

- Settings save events  
- Admin init  
- Cron registration/de-registration (if automation PR already exists)  
- Report loads (e.g., in Reports::render)  
- File generation operations (PDF will plug in later)

Only log when `debug_mode = 1`.

Examples:

```php
satori_audit_log( 'Settings saved: automation toggled.' );
satori_audit_log( 'PDF generation requested for report #' . $report_id );
satori_audit_log( 'Diagnostics: logger initialised.' );
```

This PR should **not** add new behaviour; only observable logs.

---

## Directory Structure Additions

```
wp-content/
  uploads/
    satori-audit/
      logs/
        audit.log
```

Ensure directory creation:

```php
wp_mkdir_p( $upload_dir . '/satori-audit/logs' );
```

---

## Acceptance Criteria

- `debug_mode = 0`  
  → logger remains mostly silent (no verbose debug output)

- `debug_mode = 1`  
  → logger records all calls to `log()` or `satori_audit_log()`

- When `log_to_file = 1`:
  - `audit.log` is created if missing  
  - New entries appended with proper timestamps  
  - Old logs pruned according to `log_retention_days`

- When `log_to_file = 0`:
  - No file written
  - In-memory logging allowed only if used elsewhere (not required here)

- No PHP warnings or notices
- No UI changes
- No PDF, automation, or notification features introduced
