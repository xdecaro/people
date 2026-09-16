# People Competitions History Tab Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a read-only `Competitions` tab to an existing People person record, backed by Competitions history via the public capability/service boundary.

**Architecture:** People does not store or query competition data. A small optional integration service discovers `competitions.people_history` through Core `CapabilityRegistry`, calls the Competitions public history service, and exposes normalized rows to the Person administrator view; missing/incompatible Competitions degrades safely by omitting the tab.

**Tech Stack:** PHP 8.3, Joomla 6.1.3, xdecaro Core 2.0.1+, Joomla uitab, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-16-competitions-history-tab-design.md`

## Global Constraints

- People remains owner only of reusable person master data.
- No `#__xdecarocompetitions_*` query or Competitions namespace import in People.
- Runtime integration remains optional; People installs and works without Competitions.
- Only existing saved people with a UUID can request history.
- Provider failures must not break the person edit screen.
- Existing ACL/save/apply/cancel behavior remains unchanged.

---

### Task 1: Add failing integration/UI contract

**Files:**
- Create: `tests/competitions-history-contract.php`
- Modify: `.github/workflows/build.yml`

**Interfaces:**
- Produces requirements for `CompetitionsIntegrationService`, People component wiring, Person view properties, conditional `Competitions` tab and language keys.

- [ ] **Step 1: Write the failing contract** checking optional service API, capability name/version, component wiring, view properties, conditional template branch, read-only columns, and no direct Competitions tables/namespaces.
- [ ] **Step 2: Add the contract to `Current People contracts`.**
- [ ] **Step 3: Run CI and confirm RED** because integration/UI files do not exist yet.

### Task 2: Implement optional Competitions consumer

**Files:**
- Create: `component/admin/src/Service/CompetitionsIntegrationService.php`
- Modify: `component/admin/services/provider.php`
- Modify: `component/admin/src/Extension/PeopleComponent.php`

**Interfaces:**
- Produces: `isHistoryAvailable(): bool`, `getPersonHistory(string): array`, and component getter.

- [ ] **Step 1: Implement runtime component loading without importing Competitions implementation classes.**
- [ ] **Step 2: Build an in-memory Core `CapabilityRegistry`; register provider capabilities and require `competitions.people_history` v1.**
- [ ] **Step 3: Call `getPersonHistoryService()->getHistoryByPersonUuid()` only after capability/service checks.**
- [ ] **Step 4: Catch optional-integration failures and return controlled unavailable/empty behavior without exposing sensitive values.**
- [ ] **Step 5: Wire the service through People DI/component.**

### Task 3: Render history in the Person view

**Files:**
- Modify: `component/admin/src/View/Person/HtmlView.php`
- Modify: `component/admin/tmpl/person/edit.php`
- Modify: `component/admin/language/it-IT/com_xdecaropeople.ini`
- Modify: `component/admin/language/en-GB/com_xdecaropeople.ini`

**Interfaces:**
- Consumes: optional integration service and person UUID.
- Produces view properties `competitionsHistoryAvailable` and `competitionsHistory` and conditional read-only tab.

- [ ] **Step 1: Initialize view properties to unavailable/empty.**
- [ ] **Step 2: For an existing person with UUID, call the optional service; never call it for a new person.**
- [ ] **Step 3: Add conditional Joomla uitab `Competitions`.**
- [ ] **Step 4: Render count summary and responsive read-only table with Competizione, Stagione, Squadra, Ruolo, N. maglia, Stato.**
- [ ] **Step 5: Render `Nessuna partecipazione collegata` when capability exists but history is empty.**
- [ ] **Step 6: Escape every provider-derived display value.**

### Task 4: Add testable graceful-degradation coverage

**Files:**
- Extend: `tests/competitions-history-contract.php`

**Interfaces:**
- Consumes optional service loading boundary.
- Produces evidence for compatible provider, empty history, absent capability/service and runtime failure behavior.

- [ ] **Step 1: Use lightweight stubs/test seam for the component loader and Core capability registry where needed.**
- [ ] **Step 2: Assert capability/service available returns rows.**
- [ ] **Step 3: Assert available provider returning `[]` remains available with empty rows.**
- [ ] **Step 4: Assert absent/incompatible/failing provider leaves People usable and tab unavailable.**
- [ ] **Step 5: Run contracts and confirm GREEN.**

### Task 5: Version and build coherence

**Files:**
- Modify version-bearing manifest/update/changelog files required by repository validation.
- Modify: `CHANGELOG.md`
- Modify: `VERSION`

**Interfaces:**
- Produces: People `1.3.0` installable package metadata.

- [ ] **Step 1: Bump feature release to 1.3.0 consistently.**
- [ ] **Step 2: Document the optional Competitions history tab and graceful fallback.**
- [ ] **Step 3: Run complete People CI and deterministic build.**
- [ ] **Step 4: Review diff against spec and ensure no direct cross-product table access.**
