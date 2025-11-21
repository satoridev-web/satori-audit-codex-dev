# PROJECT SPECIFICATION – SATORI Audit
*Version 0.1 — Draft*

---

## 1. Project Overview

**Plugin Name:**  
SATORI – Audit & Reports (SATORI Audit)

**Description:**  
A SATORI-branded WordPress plugin that generates a monthly site service log, capturing plugin inventory and changes, and outputting BALL-style reports (HTML, CSV, and PDF) for client maintenance and governance.

**Goals:**  
- [ ] Provide a reliable monthly service report for each managed site.  
- [ ] Automatically track plugin changes (NEW / UPDATED / DELETED).  
- [ ] Offer an editable, BALL-style HTML report with export to CSV/PDF.  
- [ ] Integrate cleanly into SATORI’s tooling stack (Events, Forms, etc.) via hooks and filters.  

**Non-Goals (v1):**  
- Multi-site dashboards or cross-site aggregation.  
- Full security scanner (beyond recording known issues).  
- Licensing and update server integration (future SATORI Core responsibility).  

**Packaging:**  
- [ ] GitHub repo: `satori-audit-codex-dev`.  
- [ ] CI/CD via GitHub Actions (build + PHPCS + zip).  
- [ ] `README.md`, `CHANGELOG.md`, `LICENSE`, `PROJECT_SPEC.md`, and `docs/SATORI-AUDIT-SPEC.md` included.  

**Distribution:**  
- Individual plugin, installed per-client site.  
- Future inclusion as part of a SATORI “Maintenance Bundle”.  
- Potential future listing on a marketplace once licensing framework is in place.  

---

## 2. Key Deliverables

- [ ] Working plugin scaffold with SATORI-standard file/folder structure.  
- [ ] Custom post type and tables for audit reports and plugin/security rows.  
- [ ] Admin Dashboard, Archive, and Settings pages.  
- [ ] Report generation engine (on-demand + optional cron).  
- [ ] HTML report preview matching BALL-style service log layout.  
- [ ] CSV export for plugin inventory.  
- [ ] PDF export (engine + diagnostics or fallback).  
- [ ] Inline/spreadsheet-style editor for report data.  

---

## 3. Technical Constraints

- WordPress 6.1+ and PHP 8.0+ (no support for older versions).  
- Must comply with SATORI Standards (naming, structure, logging, options).  
- No fatal errors when `WP_DEBUG` and `WP_DEBUG_LOG` are enabled.  
- PHPCS clean under WordPress coding standards (with local phpcs.xml).  

---

## 4. Timeline / Phases (High Level)

1. **Phase 1 – Scaffolding & Data Model**  
   - Set up repo, plugin structure, CPT, tables, and settings skeleton.

2. **Phase 2 – Report Engine & UI**  
   - Implement report generation, HTML preview, and basic editor.

3. **Phase 3 – Exports & Automation**  
   - CSV/PDF exports, cron-based monthly email, and PDF diagnostics.

4. **Phase 4 – Polish & Standards**  
   - PHPCS cleanup, UX polish, documentation, and CI/CD integration.

---

*End of Document*
