# PR Spec: Implement Plugin Update History Integration — SATORI Audit

**Target Branch:**  
`main`

**New Feature Branch:**  
`codex/plugin-update-history`

**Summary:**  
Integrate plugin update history into SATORI Audit reports using data from Simple History (if installed) and/or WordPress core update records.  
This PR extracts plugin update events and stores or renders them for use in:
- Report Rendering Engine (vertical plugin update list)
- Notifications (future)
- Automation (future)
- PDF export (future)

This PR **must not** implement notifications, automation, or PDF output.  
It strictly focuses on retrieving and structuring plugin update history data.

---

## Data Sources (to support)

### 1. Simple History Plugin
If plugin **Simple History** is active:
- Read update events from its database tables
- Extract:
  - Plugin name
  - Version before
  - Version after
  - Update date/time

### 2. WordPress Core (fallback)
If Simple History is NOT installed:
- Use WP's stored plugin data:
  - `get_option( 'update_plugins' )`
  - Plugin version from `get_plugins()`
- Change detection fallback:
  - Compare version changes over time (best-effort)
  - Only show latest known version if history unavailable

---

## Changes Required

### 1. Extend Plugin Service Class
File:  
`includes/class-satori-audit-plugins-service.php`

Add:

#### Public API:
- `public static function get_plugin_update_history( int $report_id = 0 ): array`

#### Return Format:
```
[
  [
    'plugin'      => 'Plugin Name',
    'old_version' => '1.2.3',
    'new_version' => '1.3.0',
    'date'        => '2025-11-10 14:33:22'
  ],
  ...
]
```

### 2. Simple History Integration

If Simple History active:

```php
$wpdb->prefix . "simple_history" // events table
```

Filter by:
- Action: plugin updated
- Date range:
  - For a given report → since previous report
  - For preview → last 60 days (fallback)

Extract plugin slug and versions from event context JSON.

### 3. WordPress Fallback (No Simple History)

When Simple History is NOT active:

- Use `get_plugins()` to identify installed plugins
- Pull version from plugin headers
- Compare to previous snapshot (if available)
- If no history: return an array containing the plugin + current version only

### 4. Integrate with Reports Class

Modify:
`includes/class-satori-audit-reports.php`

Add call:

```php
$updates = Plugins_Service::get_plugin_update_history( $report_id );
```

The Rendering Engine PR will consume this.

This PR should:
- Provide structured data ONLY
- NOT render HTML

### 5. Logging (debug_mode only)

Log:
- Whether Simple History was detected
- Number of update records loaded
- Fallback usage
- Any SQL errors

Use: `satori_audit_log()`

---

## Acceptance Criteria

- When Simple History installed:
  - Update events correctly retrieved
  - Correct versions and timestamps parsed
  - No SQL errors

- When Simple History NOT installed:
  - Fallback version extraction works
  - Output is still well-structured

- `Plugins_Service::get_plugin_update_history()` returns consistent arrays

- No rendering logic included in this PR  
- No UI modifications  
- No notifications/PDF/automation  
- No PHP warnings/errors/fatals
