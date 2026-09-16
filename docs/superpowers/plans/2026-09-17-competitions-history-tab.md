# Competitions History Tab Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show a read-only Competitions history tab on a saved People person using the existing `person_uuid` link, without duplicating Competitions-owned data.

**Architecture:** Competitions owns and exposes a `PersonHistoryService` and advertises `competitions.people_history` through the existing Core capability registry. People discovers that optional capability at runtime, calls only the public Competitions service, and renders the normalized history in the existing Joomla person tabs.

**Tech Stack:** Joomla 6 component MVC/DI, PHP 8.3, xdecaro Core `CapabilityRegistry`, GitHub Actions contract/runtime tests.

**Spec:** `docs/superpowers/specs/2026-09-16-competitions-history-tab-design.md` plus Competitions `docs/superpowers/specs/2026-09-16-people-history-provider-design.md`.

## Global Constraints

- People must not query `#__xdecarocompetitions_*` tables.
- People must not import Competitions implementation namespaces.
- Competitions remains the sole owner of competition history.
- Cross-product identity is People `person_uuid` only.
- Integration is optional and must degrade safely when Competitions is unavailable.
- UI is read-only and uses existing Joomla/xdecaro admin primitives.

---

### Task 1: Competitions public history provider

**Files:**
- Create: `component/admin/src/Service/PersonHistoryService.php`
- Modify: `component/admin/src/Service/CoreIntegrationService.php`
- Modify: `component/admin/src/Extension/CompetitionsComponent.php`
- Modify: `component/admin/services/provider.php`
- Test: `tests/people-history-contract.php`

**Interfaces:**
- Produces: `PersonHistoryService::getHistoryByPersonUuid(string $personUuid): array`
- Produces capability: `competitions.people_history` v1

- [ ] Write the contract test that requires the service, capability, DI registration and normalized fields.
- [ ] Run it in CI and verify RED because `PersonHistoryService` is absent.
- [ ] Implement one joined query from players -> rosters -> participations -> teams -> seasons -> tournaments using bound UUID input.
- [ ] Normalize IDs/nullable fields and order newest first.
- [ ] Register/expose the service and capability.
- [ ] Re-run CI and verify GREEN.

### Task 2: People optional integration and view model

**Files:**
- Create: `component/admin/src/Service/CompetitionsIntegrationService.php`
- Modify: `component/admin/src/Extension/PeopleComponent.php`
- Modify: `component/admin/services/provider.php`
- Modify: `component/admin/src/View/Person/HtmlView.php`
- Test: `tests/competitions-history-tab-contract.php`

**Interfaces:**
- Consumes: Competitions capability `competitions.people_history` v1
- Consumes: public `getPersonHistoryService()->getHistoryByPersonUuid()`
- Produces: `isHistoryAvailable(): bool`, `getPersonHistory(string $personUuid): array`
- Produces view properties: `$competitionsHistoryAvailable`, `$competitionsHistory`

- [ ] Write the contract test requiring capability discovery and the two view properties.
- [ ] Run it in CI and verify RED because the integration service is absent.
- [ ] Implement optional runtime discovery through Core `CapabilityRegistry` and `bootComponent('com_competitions')`.
- [ ] Register the service in People DI and expose it on `PeopleComponent`.
- [ ] Load history only for an existing person with a non-empty UUID; swallow optional-integration failures and omit the tab.
- [ ] Re-run CI and verify service/view contract GREEN.

### Task 3: People Competitions tab UI

**Files:**
- Modify: `component/admin/tmpl/person/edit.php`
- Modify: `component/admin/language/it-IT/com_xdecaropeople.ini`
- Modify: `component/admin/language/en-GB/com_xdecaropeople.ini`
- Test: `tests/competitions-history-tab-contract.php`

**Interfaces:**
- Consumes: `$this->competitionsHistoryAvailable`, `$this->competitionsHistory`
- Produces: conditional read-only `Competitions` tab

- [ ] Add translated tab/table/empty-state labels.
- [ ] Render the tab only when integration is available.
- [ ] Render count plus `Competizione / Stagione / Squadra / Ruolo / N. maglia / Stato` columns, or the empty state.
- [ ] Escape every external value and keep existing person save flows unchanged.
- [ ] Run People CI and existing contracts.
- [ ] Review both PR diffs for direct-table access, compile-time cross-product imports and unrelated changes.
