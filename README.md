# SATORI Audit

## Overview
SATORI Audit is a proprietary WordPress plugin developed by **Satori Graphics Pty Ltd (trading as SATORI)**.  
It provides a structured, client-ready audit/reporting framework for monthly service logs, plugin change history, diagnostics, PDF export, automation, and more.

This plugin is part of the broader **SATORI Pro Tools Suite**, including SATORI Core, Events, Forms, Search, and Cache.

---

## Features
### Core Features
- Tabbed Settings UI (Service, Notifications, Safelist, Access Control, Automation, Display, PDF Engine, Diagnostics)
- Monthly service log generation
- Change tracking and plugin version summary
- Export-ready HTML report templates
- PDF generation engine (DOMPDF/TCPDF support)
- Notification system with safelist enforcement
- Automation scheduling (monthly/weekly)
- Access control and capability-based visibility

### Developer Features
- PSR-4 autoloading
- Modular class architecture
- SATORI coding standards + WPCS
- Debug logging subsystem
- Highly extensible for client-specific needs

---

## Installation

### Option A — Standard Plugin Installation
1. Copy the plugin folder `satori-audit` into `wp-content/plugins/`
2. Activate via WP Admin → Plugins

### Option B — Must-Use Plugin (recommended for production retainers)
1. Place `satori-audit.php` in `/wp-content/mu-plugins/`
2. Place the plugin directory inside `/wp-content/mu-plugins/satori-audit/`
3. Ensure autoload paths remain correct

---

## Directory Structure
```
satori-audit/
│
├── admin/
│   ├── class-satori-audit-admin.php
│   └── screens/
│       ├── class-satori-audit-screen-dashboard.php
│       ├── class-satori-audit-screen-archive.php
│       └── class-satori-audit-screen-settings.php
│
├── includes/
│   ├── class-satori-audit-plugin.php
│   ├── class-satori-audit-reports.php
│   ├── class-satori-audit-automation.php
│   ├── class-satori-audit-logger.php
│   ├── class-satori-audit-tables.php
│   ├── class-satori-audit-pdf.php
│   └── class-satori-audit-plugins-service.php
│
├── templates/
│   └── admin/
│       └── report-preview.php
│
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
│
├── CHANGELOG.md
├── README.md
├── LICENSE
└── satori-audit.php
```

---

## Development Workflow

### Coding Standards
- **SATORI Coding Style**
- **WPCS (WordPress Coding Standards)** enforced via PHPCS
- Tabs for indentation, SATORI block comments

### Useful Commands
Run PHPCS:
```
vendor/bin/phpcs
```

Autofix (where safe):
```
vendor/bin/phpcbf
```

### Branching Model
- `main` — stable, production-ready
- feature branches — `feature/...`
- codex branches — auto-generated AI-assisted development branches

---

## Roadmap
### Near-term
- Notification engine wiring (send on publish/update)
- Safelist enforcement
- Automation scheduling system (WP-Cron)
- PDF export templates (header/footer)
- HTML → PDF consistency improvements

### Mid-term
- Multi-site compatibility
- Export archives
- Optional REST API endpoints

### Long-term
- Cloud-based SATORI Insights dashboard
- Client portal integration
- Unified SATORI maintenance suite (Audit + Monitor + Cache)

---

## Legal
This plugin is **proprietary software** owned by:

**Satori Graphics Pty Ltd (trading as “SATORI”)**  
ABN 66 078 916 391  
See LICENSE for full terms.

Unauthorized distribution or reuse is prohibited.

---

## Changelog
See [`CHANGELOG.md`](CHANGELOG.md) for full version history.
