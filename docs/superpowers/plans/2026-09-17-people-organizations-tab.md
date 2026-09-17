# People Organizations Tab Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a read-only **Organizzazioni** tab to People showing current and historical Organizations appointments for the current person.

**Architecture:** People consumes the versioned Organizations public capability only. `OrganizationsIntegrationService` mirrors the existing Competitions bridge: it boots Organizations, validates `organizations.people_appointments` version `1`, calls the public service by People UUID, and exposes normalized rows to the Person view. The edit page renders current/history sections but never stores or mutates Organizations data.

**Tech Stack:** Joomla 6.1.3, PHP 8.3 production target, Joomla tabs/UI, Joomla language system, xdecaro Core CapabilityRegistry, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-17-people-organizations-appointments-design.md`

## Global Constraints

- Joomla 6 only.
- Target release: People 1.3.2; do not alter the already manually tested People 1.3.1 scope.
- Canonical package remains `pkg_people`; component remains `com_xdecaropeople`.
- People must never query `#__xdecaroorganizations_*` in production code.
- Organizations is optional; People editing/saving must continue when Organizations is missing, incompatible, unauthorised, or failing.
- The tab is read-only. No create/edit/end/delete appointment action is added to People.
- People does not persist any Organizations appointment data.

---

### Task 1: Lock the Organizations integration contract with a failing test

**Files:**
- Create: `tests/organizations-history-tab-contract.php`
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: Organizations capability `organizations.people_appointments` version `1`.
- Produces: required bridge `OrganizationsIntegrationService` and conditional Person-view tab contract.

- [ ] **Step 1: Write the RED contract**

The test must require these exact markers:

```php
assertContains("organizations.people_appointments", $integration, 'Missing Organizations capability check.');
assertContains("getPersonAppointmentsService", $integration, 'People must use Organizations public service.');
assertContains("getAppointmentsByPersonUuid", $integration, 'People must query by person UUID.');
assertContains("organizationsHistoryAvailable", $view, 'Person view must expose integration availability.');
assertContains("organizationsCurrent", $view, 'Person view must expose current appointments.');
assertContains("organizationsHistory", $view, 'Person view must expose appointment history.');
assertContains("COM_XDECAROPEOPLE_ORGANIZATIONS_TAB", $template, 'Missing Organizzazioni tab.');
```

Scan production People PHP files and fail if any contains `#__xdecaroorganizations_`.

- [ ] **Step 2: Run and verify RED**

```bash
php tests/organizations-history-tab-contract.php
```

Expected: FAIL because the integration service and tab do not exist.

- [ ] **Step 3: Add the test to People CI**

Append:

```bash
php tests/organizations-history-tab-contract.php
```

to the current People contract sequence in `.github/workflows/ci.yml`.

- [ ] **Step 4: Commit the RED test**

```bash
git add tests/organizations-history-tab-contract.php .github/workflows/ci.yml
git commit -m "test: define People Organizations history tab contract"
```

### Task 2: Implement the optional Organizations integration bridge

**Files:**
- Create: `component/admin/src/Service/OrganizationsIntegrationService.php`
- Modify: `component/admin/src/Extension/PeopleComponent.php`
- Modify: `component/admin/services/provider.php`
- Test: `tests/organizations-history-tab-contract.php`

**Interfaces:**
- Produces: `OrganizationsIntegrationService::isHistoryAvailable(): bool`.
- Produces: `OrganizationsIntegrationService::getPersonAppointments(string $personUuid): array`.
- Consumes: `OrganizationsComponent::getPersonAppointmentsService()->getAppointmentsByPersonUuid(string): array`.

- [ ] **Step 1: Implement the bridge using the Competitions pattern**

Constants:

```php
private const COMPONENT = 'com_xdecaroorganizations';
private const CAPABILITY = 'organizations.people_appointments';
private const CAPABILITY_VERSION = '1';
```

`isHistoryAvailable()` boots Organizations inside `try/catch`, verifies Core `CapabilityRegistry`, confirms `getPersonAppointmentsService()` exists, and returns false on any failure.

`getPersonAppointments()` normalizes/validates a non-empty UUID, then:

```php
$component = $this->component();
if (!$this->supportsHistory($component) || !method_exists($component, 'getPersonAppointmentsService')) {
    throw new RuntimeException('Organizations person appointment capability is unavailable.');
}
$service = $component->getPersonAppointmentsService();
if (!is_object($service) || !method_exists($service, 'getAppointmentsByPersonUuid')) {
    throw new RuntimeException('Organizations person appointments service is incompatible.');
}
return array_values((array) $service->getAppointmentsByPersonUuid($personUuid));
```

Wrap provider failures in a generic integration-unavailable `RuntimeException` without leaking internal DB details into UI output.

- [ ] **Step 2: Wire the bridge through DI and PeopleComponent**

Add property/setter/getter mirroring `CompetitionsIntegrationService`:

```php
private ?OrganizationsIntegrationService $organizations = null;
```

Register it in `component/admin/services/provider.php` as a shared zero-argument service and inject it into `PeopleComponent`.

- [ ] **Step 3: Run the contract**

```bash
php tests/organizations-history-tab-contract.php
```

At this stage failures may remain for the view/template markers, but service/capability/no-table checks must pass.

- [ ] **Step 4: Commit**

```bash
git add component/admin/src/Service/OrganizationsIntegrationService.php component/admin/src/Extension/PeopleComponent.php component/admin/services/provider.php
git commit -m "feat: add Organizations appointments integration bridge"
```

### Task 3: Load and classify Organizations appointments in the Person view

**Files:**
- Modify: `component/admin/src/View/Person/HtmlView.php`
- Test: `tests/organizations-history-tab-contract.php`

**Interfaces:**
- Produces public view properties:
  - `bool $organizationsHistoryAvailable = false`
  - `array $organizationsCurrent = []`
  - `array $organizationsHistory = []`

- [ ] **Step 1: Add view properties**

```php
public bool $organizationsHistoryAvailable = false;
public array $organizationsCurrent = [];
public array $organizationsHistory = [];
```

- [ ] **Step 2: Load the optional integration for saved people only**

Inside the existing `PeopleComponent` block, after computing `$personUuid`, load Organizations administrator language:

```php
$language->load('com_xdecaroorganizations', JPATH_ADMINISTRATOR . '/components/com_xdecaroorganizations');
```

Then call the bridge only when `!$isNew && $personUuid !== ''`.

- [ ] **Step 3: Split rows using Organizations-owned `is_current`**

Do not recalculate status in People:

```php
foreach ($rows as $row) {
    if (!empty($row['is_current'])) {
        $this->organizationsCurrent[] = $row;
    } else {
        $this->organizationsHistory[] = $row;
    }
}
$this->organizationsHistoryAvailable = true;
```

If the bridge reports unavailable or throws, log a warning under `com_xdecaropeople`, reset both arrays, and keep `$organizationsHistoryAvailable = false`. Never throw from this optional integration into the person edit lifecycle.

- [ ] **Step 4: Run the contract and existing Competitions tab regression**

```bash
php tests/organizations-history-tab-contract.php
php tests/competitions-history-tab-contract.php
```

Expected: Organizations view-state checks and existing Competitions integration both pass.

- [ ] **Step 5: Commit**

```bash
git add component/admin/src/View/Person/HtmlView.php
git commit -m "feat: load Organizations appointments in People person view"
```

### Task 4: Render the Organizzazioni tab and translations

**Files:**
- Modify: `component/admin/tmpl/person/edit.php`
- Modify: `component/admin/language/it-IT/com_xdecaropeople.ini`
- Modify: `component/admin/language/en-GB/com_xdecaropeople.ini`
- Test: `tests/organizations-history-tab-contract.php`

**Interfaces:**
- Consumes: `$this->organizationsCurrent`, `$this->organizationsHistory`, `$this->organizationsHistoryAvailable`.

- [ ] **Step 1: Add translations**

Italian keys:

```ini
COM_XDECAROPEOPLE_ORGANIZATIONS_TAB="Organizzazioni"
COM_XDECAROPEOPLE_ORGANIZATIONS_CURRENT="In carica"
COM_XDECAROPEOPLE_ORGANIZATIONS_HISTORY="Storico incarichi"
COM_XDECAROPEOPLE_ORGANIZATIONS_ORGANIZATION="Organizzazione"
COM_XDECAROPEOPLE_ORGANIZATIONS_ROLE="Carica"
COM_XDECAROPEOPLE_ORGANIZATIONS_START="Inizio"
COM_XDECAROPEOPLE_ORGANIZATIONS_PLANNED_END="Fine prevista"
COM_XDECAROPEOPLE_ORGANIZATIONS_STATUS="Stato"
COM_XDECAROPEOPLE_ORGANIZATIONS_MANDATE="Mandato"
COM_XDECAROPEOPLE_ORGANIZATIONS_ENDED_ON="Data cessazione"
COM_XDECAROPEOPLE_ORGANIZATIONS_END_REASON="Motivo cessazione"
COM_XDECAROPEOPLE_ORGANIZATIONS_CURRENT_EMPTY="Nessun incarico attualmente in carica."
COM_XDECAROPEOPLE_ORGANIZATIONS_HISTORY_EMPTY="Nessun incarico nello storico."
```

Add equivalent English strings.

- [ ] **Step 2: Add the conditional tab after Relations and before Competitions**

Render only when `$this->organizationsHistoryAvailable` is true.

For role label:

```php
$role = trim((string) ($row['role_custom'] ?? ''));
if ($role === '') {
    $key = trim((string) ($row['role_label_key'] ?? ''));
    $role = $key !== '' ? Text::_($key) : '—';
}
```

For status use `COM_XDECAROORGANIZATIONS_STATUS_` plus the uppercase `visual_status` value after mapping known values; for end reasons map `term_end`, `resignation`, `revocation`, `forfeiture`, `other` to existing Organizations translation keys.

- [ ] **Step 3: Render Current table**

Columns exactly: Organizzazione, Carica, Inizio, Fine prevista, Stato.

Organization link:

```php
Route::_('index.php?option=com_xdecaroorganizations&task=organization.edit&id=' . (int) $row['organization_id'])
```

Escape the organization name and all text output. Format ISO dates with Joomla `HTMLHelper::_('date', $value, Text::_('DATE_FORMAT_FILTER_DATE'))` or the existing repository date-display convention; empty dates render `—`.

- [ ] **Step 4: Render History table**

Columns exactly: Organizzazione, Carica, Mandato, Data cessazione, Motivo cessazione.

Mandato renders `starts_on → planned_ends_on`, using `senza scadenza`/`—` only when the provider value is empty. If the history array is empty, show the translated empty-state alert instead of an empty table.

- [ ] **Step 5: Run contract and PHP syntax**

```bash
php tests/organizations-history-tab-contract.php
php -l component/admin/tmpl/person/edit.php
php -l component/admin/src/View/Person/HtmlView.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add component/admin/tmpl/person/edit.php component/admin/language tests/organizations-history-tab-contract.php
git commit -m "feat: show Organizations current and historical appointments"
```

### Task 5: Add cross-repository Joomla runtime coverage

**Files:**
- Create: `.github/workflows/organizations-history-runtime.yml`

**Interfaces:**
- Consumes: Organizations 1.0.27 candidate public API.
- Produces: end-to-end proof that People can consume current/history rows while its edit component remains bootable.

- [ ] **Step 1: Build the candidate stack in CI**

Install Joomla 6.1.3 and verified Core. Build People from the current branch. Check out the Organizations feature branch/commit used by the PR and build `pkg_organizations_1.0.27.zip` rather than using a future unreleased download URL.

- [ ] **Step 2: Seed one person, one organization, one current appointment, and one ended appointment**

Use a deterministic UUID for the person and insert test fixture rows only inside the runtime test environment. Production People code must remain free of Organizations table names.

- [ ] **Step 3: Probe the People bridge**

Boot `com_xdecaropeople`, obtain `getOrganizationsIntegrationService()`, assert `isHistoryAvailable() === true`, then call `getPersonAppointments($personUuid)` and verify exactly two rows with one current and one history row.

Also boot/create the Person administrator view or otherwise exercise the same view-loading integration path and verify it does not throw.

- [ ] **Step 4: Verify optional degradation**

Add a second probe before Organizations is installed (or in an isolated clean job) asserting `isHistoryAvailable() === false` and that People itself still boots successfully.

- [ ] **Step 5: Run until GREEN and commit**

Expected workflow output includes `People Organizations history runtime OK`.

```bash
git add .github/workflows/organizations-history-runtime.yml
git commit -m "test: verify People Organizations history runtime"
```

### Task 6: Prepare People 1.3.2 and final verification

**Files:**
- Modify: `VERSION`
- Modify: `component/xdecaropeople.xml`
- Modify: `component/media/joomla.asset.json`
- Modify: `package/pkg_people.xml`
- Modify: `updates/pkg_people.xml`
- Modify: `updates/pkg_xdecaropeople.xml`
- Modify: `.github/workflows/release.yml`
- Modify: `README.md`

**Interfaces:**
- Keeps: `pkg_people`, `com_xdecaropeople`.
- Publishes: People 1.3.2 only after Organizations 1.0.27 contract is proven.

- [ ] **Step 1: Bump all People source metadata to `1.3.2`**

Keep the canonical-package migration logic from 1.3.1 unchanged.

- [ ] **Step 2: Update release validation and release notes**

Add `php tests/organizations-history-tab-contract.php` to the stable-source release validation. Release notes must say the new tab is optional/read-only and consumes Organizations public API only.

Both canonical and legacy updater feeds continue to deliver `pkg_people_1.3.2.zip`.

- [ ] **Step 3: Run deterministic build twice**

```bash
chmod +x build/build.sh
build/build.sh
cp dist/SHA256SUMS.txt /tmp/people-132-sums.txt
build/build.sh
diff -u /tmp/people-132-sums.txt dist/SHA256SUMS.txt
unzip -t dist/pkg_people_1.3.2.zip
```

Expected: no checksum diff and valid archive.

- [ ] **Step 4: Run full People regression suite**

Required green evidence:
- People source/metadata validation;
- existing sensitive provider contract;
- batch provider contract;
- Competitions history tab contract;
- Notifications runtime;
- clean Joomla install;
- People 1.3.0 → 1.3.2 canonical-package upgrade path;
- new Organizations history tab contract;
- new Organizations cross-repository runtime.

- [ ] **Step 5: Security/diff review**

Run:

```bash
! grep -Rni '#__xdecaroorganizations_' component
```

Confirm no People production code writes to Organizations, no appointment data is persisted into People tables, and all table output is escaped.

- [ ] **Step 6: Commit release preparation**

```bash
git add VERSION component package updates .github/workflows README.md
git commit -m "release: prepare People 1.3.2"
```

- [ ] **Step 7: Keep both feature PRs Draft for manual site verification**

Manual acceptance on the test site: open an existing person with one current and one historical appointment, verify the **Organizzazioni** tab, both sections, translated role/status/end reason, organization link, and unchanged Person save workflow. Only after that choose merge/release order: Organizations 1.0.27 first, then People 1.3.2.