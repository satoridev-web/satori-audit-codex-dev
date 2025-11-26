# SATORI Commit Message Standard
*Version 0.1 — Draft*

---

## 1. Purpose

This document defines the **standard commit message format** for all SATORI plugins and related repos.

Goals:

- Make `git log` readable and useful.
- Provide enough context for future you (and Codex).
- Simplify release notes and changelog generation.
- Keep a consistent style across all SATORI projects.

---

## 2. Format Overview

Each non-trivial commit must follow this structure:

```text
<Type>: Short summary in sentence case (max ~60 chars)

Optional longer explanation of what changed and why.

Includes:
- Bullet for key change
- Bullet for another key change
- Bullet referencing spec or issue if relevant
```

### 2.1 First Line (Title)

- Format: `<Type>: <Short summary>`
- Max recommended length: **60 characters** (hard limit ~72).
- Use **sentence case** (only first word capitalised, plus proper nouns).

Examples:

```text
Fix: PDF CSS wrapping and DOMPDF styling for Template v2
Feature: Add automation scheduling UI for monthly reports
Refactor: Extract settings helper into dedicated class
Docs: Update SATORI Audit project status document
Spec: Add PR-SPEC for archive delete/regenerate
```

### 2.2 Types

Use one of these **capitalised types**:

- `Feature` — new features or significant behaviour.
- `Fix` — bug fixes.
- `Refactor` — internal code changes, no behaviour change.
- `Docs` — documentation only.
- `Spec` — PR specs, project specs, R3P briefs.
- `Chore` — maintenance, tooling, config, CI, etc.
- `Release` — version bumps, tagged releases, changelog only.

---

## 3. Body

The body is **required** for any commit that is:

- A feature
- A fix
- A refactor
- Anything touching multiple files or modules

It is **optional** for tiny changes (typo, spacing, one-line doc tweak).

### 3.1 Structure

```text
<Type>: Short summary in sentence case

Short explanation of what changed and why. 1–3 short paragraphs.

Includes:
- Bullet of key change
- Bullet of impacted area
- Bullet referencing spec, PR-SPEC, or issue (if applicable)
```

Guidelines:

- Wrap lines around **72 characters** where possible.
- Use present tense: “Fix…” / “Add…” / “Refactor…”.
- Be specific, not vague (“Improve PDF CSS handling” > “Changes”).

### 3.2 Examples

```text
Fix: PDF CSS wrapping and DOMPDF styling for Template v2

Resolves raw CSS printing in PDF output and improves DOMPDF compatibility.
CSS is now safely injected into <style> tags via a centralised method.

Includes:
- Added PDF CSS builder method in the PDF class
- Introduced optional PDF-specific CSS file
- Removed old inline CSS concatenation
- Kept HTML preview behaviour unchanged
```

```text
Feature: Add automation scheduling UI for monthly reports

Implements the first version of the automation scheduling interface. This
exposes scheduling controls but does not yet activate cron-based execution.

Includes:
- New automation tab in SATORI Audit settings
- Basic schedule selector (monthly cadence)
- Settings stored under the existing options array
- No changes to cron or background processing yet
```

---

## 4. Small / Trivial Commits

For very small changes, a one-line message is acceptable:

```text
Docs: Fix typo in SATORI Audit README
Chore: Update .gitignore for local tooling
```

Use your judgement. If future you might wonder “why did I do this?”, write a body.

---

## 5. Relation to Specs and PRs

Where relevant, reference:

- `docs/PR-SPEC-*.md` files.
- `docs/SATORI--SPEC.md` / `SATORI-AUDIT-SPEC.md`.
- GitHub issues or PR numbers.

Example:

```text
Spec: Add PR-SPEC for PDF CSS wrapping

Defines the scope and acceptance criteria for fixing the PDF CSS and DOMPDF
styling issues described in v0.3.0 release notes.

Includes:
- docs/PR-SPEC-pdf-template-css-wrapping.md
- Cross-link to SATORI-AUDIT-PROJECT-STATUS.md
```

---

## 6. Enforced Rules (Commit Hook)

The following rules may be enforced by a `commit-msg` hook:

1. First line must match:  
   `^(Feature|Fix|Refactor|Docs|Spec|Chore|Release): `
2. Second line must be **blank** (unless there is no body).
3. Long lines in the title should be avoided (>72 chars).

See `tools/git-hooks/commit-msg` (or `.git/hooks/commit-msg`) for implementation.

---

*End of Document*
