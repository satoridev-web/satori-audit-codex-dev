# 🧪 SATORI Audit — Full Audit & Test Run Checklist  
**Version:** 2025-11-26  
**Author:** SATORI / Andy Garard  

This checklist ensures the plugin is fully functional end-to-end.  
Use this after each Codex PR merge, before tagging a release.

---

# 1. Environment Setup

- [ ] WordPress loads without PHP warnings/errors  
- [ ] Plugin activates without errors  
- [ ] CPT `satori_audit_report` registered  
- [ ] Admin menu visible and correctly titled  
- [ ] Capabilities work with alternate users  

---

# 2. Settings System

### Across ALL tabs:
- [ ] Values persist after clicking **Save Changes**  
- [ ] Selected tab remains active after save  
- [ ] Tabs switch cleanly with no layout jump  
- [ ] Sanitisation works (email fields, dates, textareas)  

### Service Details
- [ ] Client name saved  
- [ ] Managed By saved  
- [ ] Service Start Date saved  
- [ ] Service Notes saved  

### Notifications
- [ ] From Name  
- [ ] Reply-To  
- [ ] Email template preview works  

### Recipient Safelist
- [ ] Table entries persist  
- [ ] “Add recipient” works  
- [ ] Removal works  

### Access Control
- [ ] Only admin/authorised roles see plugin  
- [ ] Non-authorised roles blocked  

### Automation
- [ ] Monthly schedule values persist  
- [ ] Checkbox “Enable Automation” persists  

### Display & Output
- [ ] Date format changes reflected in UI  
- [ ] Diagnostics toggle works  

### PDF Engine
- [ ] Engine selection persists (DOMPDF/TCPDF)  
- [ ] Paper size/orientation settings persist  

---

# 3. Report Lifecycle

### Generate a report
- [ ] Create new report entry  
- [ ] Title auto-generated or entered  
- [ ] Report date correct  
- [ ] Plugin updates stored  

### Report Archive
- [ ] Archive index lists created reports  
- [ ] Date, plugin counts, version details correct  
- [ ] Links:
  - [ ] Preview  
  - [ ] Download PDF  
- [ ] No styling glitches

### Single Report View
- [ ] Template v2 header correct  
- [ ] Summary correct  
- [ ] Diagnostics displayed if enabled  
- [ ] Plugin updates visible  
- [ ] PDF Download button appears  

---

# 4. HTML Rendering Test

- [ ] Template v2 matches designed structure  
- [ ] Header layout correct  
- [ ] Cards display correctly  
- [ ] Typography consistent  
- [ ] Diagnostics tested ON / OFF  
- [ ] CSS loads in admin  

---

# 5. PDF Rendering Test

### With DOMPDF:
- [ ] PDF generates without errors  
- [ ] No raw CSS printed  
- [ ] Styles applied (header layout, cards, spacing)  
- [ ] All report sections appear  
- [ ] Margins correct  
- [ ] File saved to uploads path  

### With TCPDF:
- [ ] PDF generates without errors  
- [ ] No raw CSS printed  
- [ ] Content formatted acceptably  
- [ ] Text not overlapping margins  

---

# 6. Notifications Test

*(Sending not yet implemented)*  
- [ ] Settings load correctly  
- [ ] Safelist respected  
- [ ] Template saved  

---

# 7. Automation Test

*(Engine not implemented — UI only)*  
- [ ] Schedule settings load  
- [ ] Next run calculation (future)  

---

# 8. Access Control Test

- [ ] Admin sees full plugin  
- [ ] Editor/Author blocked  
- [ ] Custom role with capability allowed  

---

# 9. REST API / Export Tests

*(Future PR)*  
- [ ] Endpoint loads  
- [ ] JSON contains expected report data  

---

# 10. Final Regression Pass

- [ ] No PHP notices in debug.log  
- [ ] No console errors  
- [ ] UI alignment correct  
- [ ] No untranslated strings  
- [ ] No orphaned CSS or JS  

---

# End of Checklist
