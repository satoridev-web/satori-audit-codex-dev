# PR-SPEC – Bundle TCPDF and prefer it as PDF engine
*Slug: pdf-engine-tcpdf-vendor*  
*Plugin: SATORI Audit*  

## 1. Background
The current PDF export pipeline for SATORI Audit:
- Builds a complete HTML document for the report (`PDF::build_html()`).
- Injects PDF-specific CSS inside a `<style>` block in `<head>`.
- Passes this HTML to a PDF engine loaded by `PDF::load_engine()`.

DOMPDF is installed in LocalWP and is currently always used, even when TCPDF is selected, because `\TCPDF` is not available. DOMPDF produces PDFs containing raw CSS/text instead of rendered layout. HTML/CSS debug output confirms the pipeline is correct.

Goal: vendor TCPDF, prefer it by default, keep DOMPDF as fallback.

## 2. Goals
1. Bundle TCPDF into plugin (vendor or manual include).
2. Prefer TCPDF as default engine.
3. Preserve DOMPDF as fallback.
4. Improve engine selection logic.
5. Keep PDF Debug Mode unchanged.
6. Maintain backwards compatibility.

## 3. Scope
### In Scope
- Adding TCPDF dependency.
- Updating engine selection logic.
- Updating settings UI mapping/labels.
- Logging improvements.

### Out of Scope
- Changing report HTML/CSS.
- DOMPDF CSS simplification (future PR).

## 4. Implementation Details

### 4.1 Vendor TCPDF
If Composer is used:
```json
{
  "require": {
    "dompdf/dompdf": "^2.0",
    "tecnickcom/tcpdf": "^6.7"
  }
}
```
Include via:
```php
require __DIR__ . '/vendor/autoload.php';
```

If not using Composer:
- Place TCPDF under `includes/vendor/tcpdf/`.
- Conditionally load main file if class `\TCPDF` is missing.

### 4.2 Engine Setting Values
Internal values:
- `none`
- `automatic`
- `tcpdf`
- `dompdf`

### 4.3 Updated load_engine()
Rewritten logic:

- `automatic`: prefer TCPDF → DOMPDF fallback.
- `tcpdf`: TCPDF → DOMPDF fallback.
- `dompdf`: DOMPDF → TCPDF fallback.
- `none`: disable engine.
- Unknown: treat as automatic.

Add helpers:
- `init_tcpdf()`
- `init_dompdf()`

Both return:
```php
array{ type: string, instance: object }
```

### 4.4 Settings UI
Map UI values to internal ones. Update description:
> Automatic (recommended): TCPDF → fallback DOMPDF.

### 4.5 Logging
Log engine chosen, fallbacks, failures.

## 5. Compatibility
- Existing installs continue working.
- DOMPDF behaves same as before.
- Debug Mode unaffected.
- No schema changes.

## 6. Testing & Acceptance Criteria

### Tests
1. Automatic selection: uses TCPDF.
2. Explicit TCPDF: loads TCPDF.
3. Explicit DOMPDF: loads DOMPDF.
4. No engines: graceful fail, logged message.
5. Debug Mode: continues bypassing engines.

### Acceptance
- TCPDF always available.
- DOMPDF fallback works.
- Logging clear.

## 7. Follow-up Work
Separate PRs:
- DOMPDF compatibility CSS.
- PDF layout regression testing.

