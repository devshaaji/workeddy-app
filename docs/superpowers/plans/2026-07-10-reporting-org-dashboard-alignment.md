# Reporting Org Dashboard Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Align the reporting module with the documented org-report surface, tighten system-report permissions, and redesign org report pages to follow the Sneat analytics dashboard pattern.

**Architecture:** Keep the existing reporting module routes and page data services, but split system-only gating at the page-controller layer, expose org-report destinations more clearly in the sidebar and dashboard hub, and rebuild the org report views into compact Sneat-style cards, stats, and tables. Avoid new global assets and keep changes inside reporting views plus the sidebar partial.

**Tech Stack:** PHP 8, PHPUnit, server-rendered Sneat Bootstrap views, existing reporting snapshot/page-data services.

---

### Task 1: Lock system-report permission behavior

**Files:**
- Create: `v2/modules/Reporting/Tests/ReportingPageControllerTest.php`
- Modify: `v2/modules/Reporting/Presentation/ReportingPageController.php`

- [ ] Add a controller-level regression test that proves `dashboard`, `finance`, and `operations` require `reporting.system.view`, while org report pages keep using `reporting.view`.
- [ ] Run the new PHPUnit file and confirm it fails for the expected privilege mismatch.
- [ ] Update the controller methods to require the system-only permission for system pages.
- [ ] Re-run the PHPUnit file and confirm it passes.

### Task 2: Expose org reporting destinations coherently

**Files:**
- Modify: `v2/shared/Views/Partials/sidebar.php`
- Modify: `v2/modules/Reporting/Presentation/Views/Index/index.php`

- [ ] Rework the `Reports` dropdown so org-report destinations are visible separately from system-report destinations.
- [ ] Use only routes that actually exist or safe source-list entry points where a record-specific reporting page requires a UUID.
- [ ] Update the reporting hub so org reports and system reports are presented as separate surfaces.

### Task 3: Redesign org report pages to match Sneat analytics patterns

**Files:**
- Modify: `v2/modules/Reporting/Presentation/Views/Assessment/index.php`
- Modify: `v2/modules/Reporting/Presentation/Views/Corrective-action/index.php`
- Modify: `v2/modules/Reporting/Presentation/Views/Comparison/index.php`
- Modify: `v2/modules/Reporting/Presentation/Views/Audit-trial/index.php`

- [ ] Replace the hero/gradient treatment with standard page headers and compact action groups.
- [ ] Introduce stat strips, denser cards, and table/list sections using the Sneat analytics page structure as reference.
- [ ] Keep page copy short and operational, with no oversized explanatory sections.
- [ ] Preserve existing report data fields and export links.

### Task 4: Verify

**Files:**
- Modify: `v2/modules/Reporting/Tests/ReportingPageControllerTest.php`

- [ ] Run targeted PHPUnit coverage for reporting permission behavior.
- [ ] Run PHP syntax checks for the modified sidebar and reporting views.
- [ ] Inspect the final diff for accidental unrelated churn.
