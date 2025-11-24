# Changelog

## [0.2.0] – 2025-11-24
### Added
- Full tabbed Settings UI implemented under SATORI Audit → Settings.
- All settings consolidated into the `satori_audit_settings` array option.
- New sections: Service, Notifications, Safelist, Access Control, Automation, Display, PDF Engine, Diagnostics.
- Full sanitization and defaults for all settings.

### Changed
- Refactored Admin menu callbacks and settings bootstrap.
- Updated Admin screen loading and capability resolution.

### Fixed
- Tabs previously showing empty content are now fully functional.
- Fatal errors in Dashboard/Archive due to missing render_page() resolved.

---

## [0.1.0] – 2025-11-01
### Added
- Initial plugin scaffolding.
- Base admin menus (Dashboard, Archive).
- Template loader, class autoloader, PDF/Report stubs.
