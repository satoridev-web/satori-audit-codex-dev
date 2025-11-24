# PR Spec: Implement Automation Scheduling (WP-Cron) — SATORI Audit

**Target Branch:**  
`main`

**New Feature Branch:**  
`codex/automation-scheduling`

**Summary:**  
Implement the automation layer for SATORI Audit that uses the Automation settings to schedule recurring report generation and optional notifications.  
This includes registering/updating WP-Cron events when settings change, and providing a central cron callback that executes the automation run.

This PR should **not** introduce UI changes; it must strictly consume existing settings from `Screen_Settings::get_settings()`.

---

## Settings to Honour

- `automation_enabled`
- `automation_frequency` (`none`, `monthly`, `weekly`)
- `automation_day_of_month`
- `automation_time_of_day`
- `automation_include_attachments`
- `debug_mode`

---

## Changes Required

### 1. Automation Class
File: `includes/class-satori-audit-automation.php`

Responsibilities:
- Register/clear cron events
- Compute next run timestamp
- Hook cron callback
- Log actions when debug_mode enabled

---

### 2. Scheduling Logic
- Use `wp_next_scheduled()` / `wp_schedule_event()`
- Monthly: use day_of_month + time_of_day
- Weekly: consistent weekly time
- Parse HH:MM safely

---

### 3. Cron Callback
`Automation::run_cron()` should:
- Exit if disabled
- Log settings snapshot
- Stub for future PDF + notifications

---

### 4. Integration with Settings Save
Hook: `update_option_{OPTION_KEY}`
- Reschedule/clear cron when settings change

---

### 5. Logging
Log when debug_mode enabled:
- Reschedule activity
- Next run time
- Cron execution timestamp

---

## Acceptance Criteria
- Correct scheduling for monthly/weekly
- Clearing of old cron events
- `run_cron` executes without errors
- Logging reflects actions
