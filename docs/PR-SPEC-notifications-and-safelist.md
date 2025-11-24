# PR Spec: Implement Notifications + Safelist + Settings Integration — SATORI Audit

**Target Branch:**  
`main`

**New Feature Branch:**  
`codex/notifications-and-safelist`

**Summary:**  
Implement the full email Notification system for SATORI Audit including send-on-publish, send-on-update, safelist enforcement (emails + domains), subject prefixing, and logging.  
This uses settings defined in the Notifications, Safelist, and Diagnostics tabs of the existing Settings UI.

---

## Changes Required

### 1. New Notifications Class

**File:**  
`includes/class-satori-audit-notifications.php`

**Namespace:** `Satori_Audit`  
**declare:** `strict_types=1`

**Class:** `Notifications`

**Responsibilities:**

- Public API:
  - `public static function send( int $report_id, string $context ): void`
    - `$context` is `'publish'` or `'update'`.
- Internal helpers:
  - `private static function get_settings(): array`
  - `private static function get_recipients( array $settings ): array`
  - `private static function apply_safelist( array $emails, array $settings ): array`
  - `private static function build_subject( int $report_id, string $context, array $settings ): string`
  - `private static function log( string $message ): void`

**Behaviour:**

- Load all settings via `Screen_Settings::get_settings()`:
  - Notifications:
    - `notify_from_email`
    - `notify_recipients`
    - `notify_subject_prefix`
    - `notify_send_on_publish`
    - `notify_send_on_update`
  - Safelist:
    - `safelist_emails`
    - `safelist_domains`
  - Diagnostics:
    - `debug_mode`
- Decide whether to send based on:
  - `notify_send_on_publish` (`$context === 'publish'`)
  - `notify_send_on_update` (`$context === 'update'`)
- Build recipient list from `notify_recipients`:
  - Split on newlines/commas.
  - Trim and validate using `is_email()`.
- Apply safelist rules (see section 3).
- Build subject:
  - If `notify_subject_prefix` is non-empty:
    - Prefix subject with `[<prefix>] `
  - Append `<report title> — <service_site_name or get_bloginfo( 'name' )>`.
- Use `wp_mail()` to send:
  - From: `notify_from_email` (fallback to `get_bloginfo( 'admin_email' )` if invalid/empty).
  - To: filtered recipients.
- All behaviour should be silent unless `debug_mode` is enabled, in which case logging is used.

---

### 2. Hook into Report Lifecycle

Integrate notifications into the report publish/update lifecycle.

**Files to update:**

- `includes/class-satori-audit-reports.php`  
  or
- `includes/class-satori-audit-cpt.php`  
  (wherever the report post type is stored/registered)

**Behaviour:**

- On initial publish of a SATORI Audit report:
  - Call `Notifications::send( $post_id, 'publish' )`.
- On update of an existing report:
  - Call `Notifications::send( $post_id, 'update' )`.

Implementation details may use:

- `transition_post_status`
- `save_post_{cpt}`

Ensure:

- Autosaves and revisions are ignored.
- Only fire when the relevant CPT is our audit report type.

---

### 3. Implement Safelist Enforcement

Safelist rules are based on:

- `safelist_emails` — one email per line.
- `safelist_domains` — one domain per line (e.g. `ballaustralia.com`).

**Rules:**

- If both `safelist_emails` and `safelist_domains` are empty:
  - Do not filter; allow all validated recipients.
- If `safelist_emails` is non-empty:
  - Only keep recipients exactly matching these addresses.
- If `safelist_domains` is non-empty:
  - Only keep recipients whose domain (after `@`) matches one of these domains.
- If both are set:
  - Recipient is allowed if it matches **either**:
    - An exact safelist email, OR
    - A safelist domain.

Log, when `debug_mode` is enabled:

- Original recipient list.
- Final filtered list.
- Any addresses dropped by safelist rules.

---

### 4. Logging

Use existing logging mechanism (e.g. `satori_audit_log()` or logger class) with respect to `debug_mode`.

**Log when `debug_mode` is enabled:**

- When Notifications::send() is called:
  - Context (`publish`/`update`).
  - Report ID.
- Recipient lists:
  - Before safelist.
  - After safelist.
- Final send outcome:
  - Email sent / skipped (with reason: no recipients, setting disabled, etc.).

---

### 5. No UI Changes

- Do **not** modify the existing Settings screen structure or tabs.
- This PR must strictly consume existing settings from `Screen_Settings::get_settings()`.

---

### 6. No Automation/Cron in This PR

- Do **not** implement cron-based automation here.
- Automation (using `automation_*` settings) will be implemented in a separate PR.

---

## Acceptance Criteria

- When `notify_send_on_publish = 1` and a report is first published, an email is sent to the filtered recipient list.
- When `notify_send_on_update = 1` and a report is updated, an email is sent to the filtered recipient list.
- `notify_subject_prefix` is correctly prepended when non-empty.
- Safelist rules are correctly applied:
  - Empty safelist → all validated recipients allowed.
  - Emails/domains populated → only allowed recipients receive mail.
- With `debug_mode = 1`, logs include:
  - Trigger context.
  - Recipients before safelist.
  - Recipients after safelist.
  - Send result.
- With `debug_mode = 0`, no debug logs are written.
- No PHP notices/warnings/fatal errors are introduced.
