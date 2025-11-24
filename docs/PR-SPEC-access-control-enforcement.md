# PR Spec: Implement Access Control Enforcement — SATORI Audit

**Target Branch:**  
`main`

**New Feature Branch:**  
`codex/access-control-enforcement`

**Summary:**  
Implement full access-control enforcement across all SATORI Audit admin pages, actions, and components.  
This PR ensures capabilities and menu visibility strictly follow the Access Control settings from the Settings → Access tab.

No UI changes. No new settings.  
This PR must *consume* existing settings only.

---

## Settings to Honour

From the **Access Control** tab:

- `capability_manage`  
- `capability_view_reports`  
- `hide_menu_from_non_admin` (boolean)

---

## Requirements

### 1. Enforce Capability Checks on All Screens

Apply capability checks in:

**Files affected:**
- `admin/class-satori-audit-admin.php`
- `admin/screens/class-satori-audit-screen-dashboard.php`
- `admin/screens/class-satori-audit-screen-archive.php`
- `admin/screens/class-satori-audit-screen-settings.php`

Rules:

- Dashboard + Archive:
  - Require: `capability_view_reports`
- Settings:
  - Require: `capability_manage`

If current user lacks capability:
- Deny access
- Use `wp_die()` with proper message:  
  `"You do not have permission to access this page."`

---

### 2. Enforce Menu Visibility Based on Settings

Modify menu registration in:

`admin/class-satori-audit-admin.php`

Rules:

- If `hide_menu_from_non_admin = 1`:
  - Only users with `capability_manage` should see the SATORI Audit top-level menu and its submenu items.
- Otherwise:
  - Dashboard + Archive require `capability_view_reports`
  - Settings requires `capability_manage`

Ensure:
- Menu disappears entirely for users without required capabilities when hidden.

---

### 3. Harden Direct URL Access

Each screen class (`render()` method or controller) must independently check capabilities.

Even if the menu is hidden, URLs like:

```
/wp-admin/admin.php?page=satori-audit
/wp-admin/admin.php?page=satori-audit-settings
```

must be protected.

---

### 4. Centralize Capability Retrieval

Add helper in `Screen_Settings` or dedicated utility class:

```php
public static function get_capabilities(): array {
    return [
        'manage' => $settings['capability_manage'],
        'view'   => $settings['capability_view_reports'],
    ];
}
```

All menu logic and screen access checks should call this helper.

---

### 5. Logging (optional, only when debug_mode enabled)

When `debug_mode = 1`:
- Log resolved capabilities
- Log denied accesses
- Log menu-visibility behaviour

Use `satori_audit_log()`.

---

## Acceptance Criteria

- Users without `capability_view_reports`:
  - Cannot access Dashboard or Archive
- Users without `capability_manage`:
  - Cannot access Settings
  - Cannot see menus when `hide_menu_from_non_admin = 1`
- Direct URL access is blocked with proper error message
- No UI changes added
- No Notices / Warnings / Fatal errors
