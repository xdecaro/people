# People Canonical Database Maintenance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add safe canonical-schema inspection, repair, functional-data emptying, and functional-table recreation to People → Informazioni without touching Joomla or other components.

**Architecture:** A pure PHP canonical schema definition becomes the runtime source of truth for the six People-owned tables. A schema inspector compares live MySQL/MariaDB metadata against that definition; a maintenance service performs conservative repair and backup-first destructive operations. The existing Information page remains the administrator entry point and the controller stays thin.

**Tech Stack:** Joomla 6.1.3, PHP 8.3+, Joomla `DatabaseInterface`, MySQL 8/MariaDB-compatible DDL, existing People Backup/Integrity/Maintenance services, vanilla JavaScript, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-26-people-canonical-database-maintenance-design.md`

## Global Constraints

- Target release: **People 1.7.29**. Latest published release at planning time is 1.7.28.
- Joomla target: exactly **6.1.3**. PHP minimum: **8.3**.
- Canonical functional tables: `#__xdecaropeople_people`, `#__xdecaropeople_history`, `#__xdecaropeople_duplicate_ignores`, `#__xdecaropeople_merges`.
- Canonical maintenance tables: `#__xdecaropeople_backups`, `#__xdecaropeople_maintenance_log`.
- Unknown tables/columns/indexes are reported but never removed unless their exact identity is version-controlled in the legacy-removal allowlist.
- `Svuota` and `Ricrea` require a successfully created and verified automatic safety backup before the first destructive data/schema query.
- Exact typed confirmations are server-authoritative: `SVUOTA` and `RICREA`.
- `Svuota` and `Ricrea` preserve backup metadata/files and maintenance-log rows.
- Backup payload remains limited to the four functional People tables.
- No operation may read or write private tables owned by Joomla, Core, Organizations, Membership, Competitions, Photos, Documents, Notifications, or any other component.

## Review Focus

- **Custom/unknown schema objects:** they remain untouched even when repair runs; only exact allowlisted legacy objects may be removed. Tasks 2–3.
- **Safety-backup failure:** an unwritable backup destination or invalid backup verification aborts before deletes/drops. Tasks 4–5.
- **Partial DDL failure:** recreate must surface the safety-backup UUID and keep maintenance history available for recovery. Task 5.
- **Maintenance preservation:** existing backup rows/files and audit rows survive empty/recreate. Tasks 4–5.
- **Scope escape:** generated SQL is restricted to the six exact canonical logical table names and uses Joomla prefix replacement only after validation. Tasks 1–5.

---

### Task 1: Canonical schema definition and installer parity

**Files:**
- Create: `component/admin/src/Service/DatabaseSchemaDefinition.php`
- Create: `tests/people-1.7.29-canonical-schema-contract.php`
- Modify only if parity test proves drift: `component/admin/sql/install.mysql.utf8mb4.sql`

**Interfaces:**
- `DatabaseSchemaDefinition::tables(): array`
- `DatabaseSchemaDefinition::functionalTables(): array`
- `DatabaseSchemaDefinition::maintenanceTables(): array`
- `DatabaseSchemaDefinition::table(string $table): array`
- `DatabaseSchemaDefinition::createTableSql(string $table): string`
- `DatabaseSchemaDefinition::legacyRemovals(): array`

- [ ] **Step 1: Write the failing contract**

Assert exactly six table definitions, exactly four functional and two maintenance roles, exact known names only, no foreign prefixes, deterministic CREATE TABLE output, and rejection of unknown table names. Extract table/column/index signatures from generated DDL and from `install.mysql.utf8mb4.sql` and assert parity.

- [ ] **Step 2: Verify RED**

Run: `php tests/people-1.7.29-canonical-schema-contract.php`

Expected: FAIL because `DatabaseSchemaDefinition` does not exist.

- [ ] **Step 3: Implement the canonical definition**

Represent each table with ordered column SQL fragments, PK, unique indexes, indexes, engine, charset, collation and role. `createTableSql()` builds DDL only for exact known logical names. Start `legacyRemovals()` empty unless repository evidence identifies an exact retired object; never invent legacy names to satisfy tests.

- [ ] **Step 4: Align installer SQL only if needed**

Fresh installer SQL must represent the same current schema. Historical `updates/mysql/*` files are not replayed or parsed by runtime maintenance.

- [ ] **Step 5: Verify GREEN**

Run the canonical contract. Expected: `PASS`.

- [ ] **Step 6: Commit**

`feat: define canonical People database schema`

---

### Task 2: Live schema inspector

**Files:**
- Create: `component/admin/src/Service/DatabaseSchemaInspector.php`
- Create: `tests/people-1.7.29-database-schema-runtime.php`
- Modify: `component/admin/services/provider.php`
- Modify: `component/admin/src/Extension/PeopleComponent.php`

**Interfaces:**
- Consumes `DatabaseSchemaDefinition`.
- `DatabaseSchemaInspector::inspect(): array`
- Result keys: `status`, `ok`, `tables`, `missing_tables`, `unexpected_tables`, `missing_columns`, `incompatible_columns`, `unknown_columns`, `missing_indexes`, `incompatible_indexes`, `unknown_indexes`, `engine_differences`, `collation_differences`.
- `PeopleComponent::getDatabaseSchemaDefinition(): DatabaseSchemaDefinition`
- `PeopleComponent::getDatabaseSchemaInspector(): DatabaseSchemaInspector`

- [ ] **Step 1: Write the runtime probe**

On Joomla 6.1.3/MySQL: assert a clean install is clean; then independently create and detect a missing table, missing column, missing index, custom column, custom index, and unexpected `#__xdecaropeople_*` table. Each mutation must be restored before the next case so the probe exits with the canonical schema intact. Assert inspection itself never mutates rows/schema.

- [ ] **Step 2: Verify RED**

Run in the Joomla harness: `php ../tests/people-1.7.29-database-schema-runtime.php`

Expected: FAIL because inspector/getters are missing.

- [ ] **Step 3: Implement inspector normalization**

Read live metadata through `INFORMATION_SCHEMA` or deterministic `SHOW` queries. Normalize column type/null/default/extra, indexes, engine and collation before comparison. Unknown objects are findings only, never executable instructions.

- [ ] **Step 4: Register definition + inspector**

Wire both through `component/admin/services/provider.php` and typed setters/getters in `PeopleComponent`.

- [ ] **Step 5: Verify GREEN**

Run canonical contract + schema runtime. Expected: both PASS and final live schema clean.

- [ ] **Step 6: Commit**

`feat: inspect People schema against canonical definition`

---

### Task 3: Conservative schema repair and ACL

**Files:**
- Create: `component/admin/src/Service/DatabaseMaintenanceService.php`
- Create: `tests/people-1.7.29-database-repair-runtime.php`
- Modify: `component/admin/access.xml`
- Modify: `component/admin/services/provider.php`
- Modify: `component/admin/src/Extension/PeopleComponent.php`
- Modify: `component/admin/language/it-IT/com_xdecaropeople.ini`
- Modify: `component/admin/language/en-GB/com_xdecaropeople.ini`

**Interfaces:**
- Constructor dependencies: `DatabaseInterface`, `DatabaseSchemaDefinition`, `DatabaseSchemaInspector`, `BackupService`, `IntegrityService`, `MaintenanceLogService`.
- `DatabaseMaintenanceService::check(int $actorUserId, bool $writeLog = true): array`
- `DatabaseMaintenanceService::repair(int $actorUserId): array`
- `PeopleComponent::getDatabaseMaintenanceService(): DatabaseMaintenanceService`
- ACL actions: `people.database_repair`, `people.database_destructive`.

- [ ] **Step 1: Write failing repair/scope tests**

Damage a disposable installed schema by removing a canonical table/column/index. Add custom column/index and a fake People-prefixed table plus an unrelated non-People probe table. Assert `repair()` recreates canonical missing structures and leaves all unknown/custom/non-People objects untouched. Add a static assertion that DROP-column/DROP-index planning can only be sourced from `legacyRemovals()`; with the current empty allowlist no unknown object may be dropped.

- [ ] **Step 2: Verify RED**

Run: `php ../tests/people-1.7.29-database-repair-runtime.php`

Expected: FAIL because the maintenance service is missing.

- [ ] **Step 3: Implement `check()` and `repair()`**

`check()` delegates to inspector and optionally logs `database_check`. `repair()` re-inspects, builds an explicit ordered plan, allows only canonical CREATE/ADD/MODIFY/index replacement plus exact allowlisted removals, validates every logical table against the canonical definition before converting `#__` with `$db->replacePrefix()`, executes, re-inspects, and logs `database_repair` with before/after status and operation names.

- [ ] **Step 4: Add ACL and labels**

Add `people.database_repair` and `people.database_destructive`; never grant/bypass them in PHP.

- [ ] **Step 5: Register maintenance service**

Wire DI + `PeopleComponent` getter/setter.

- [ ] **Step 6: Verify GREEN**

Run repair runtime + existing 1.7.28 maintenance contracts. Expected: PASS and schema restored clean.

- [ ] **Step 7: Commit**

`feat: repair canonical People database schema`

---

### Task 4: Verified safety backup and “Svuota dati People”

**Files:**
- Create: `component/admin/src/Exception/DatabaseMaintenanceException.php`
- Modify: `component/admin/src/Service/BackupService.php`
- Modify: `component/admin/src/Service/DatabaseMaintenanceService.php`
- Create: `tests/people-1.7.29-database-empty-runtime.php`

**Interfaces:**
- `BackupService::verify(string $backupUuid): array` validates existence + SHA256 without logging a download.
- `DatabaseMaintenanceException::getSafetyBackupUuid(): ?string`
- `DatabaseMaintenanceService::emptyFunctionalData(int $actorUserId, string $confirmation): array`
- Result: `safety_backup_uuid`, `removed_counts`, `schema_status`, `integrity`.

- [ ] **Step 1: Write failing backup/empty tests**

Assert `BackupService::verify()` rejects a deliberately tampered backup file. Seed all functional tables plus pre-existing backup/log rows. Assert wrong confirmation rejects with no backup/delete. Configure an invalid/unwritable backup storage path and assert empty aborts with functional rows untouched. Restore valid storage and assert success creates+verifies reason `before_empty_database`, deletes only the four functional tables in dependency-safe order, preserves all pre-existing maintenance rows plus the new backup/log, resets functional AUTO_INCREMENT where supported, and finishes with clean schema/integrity.

- [ ] **Step 2: Verify RED**

Run: `php ../tests/people-1.7.29-database-empty-runtime.php`

Expected: FAIL because `verify()` / exception / empty method are missing.

- [ ] **Step 3: Refactor backup verification**

Move file existence and SHA256 validation into `verify()`. `resolveDownload()` calls `verify()` and still logs `backup_download`; internal safety checks call `verify()` directly.

- [ ] **Step 4: Implement `emptyFunctionalData()`**

Validate exact `SVUOTA`; create backup with `before_empty_database`; verify it before deletes; count rows; delete `history`, `duplicate_ignores`, `merges`, `people` using transactional DML where available; reset AUTO_INCREMENT after successful deletes; run schema/integrity checks; log `database_empty`. Never delete maintenance tables. Expected operational errors use `DatabaseMaintenanceException` and include backup UUID when one already exists.

- [ ] **Step 5: Verify GREEN + regressions**

Run empty runtime and all existing backup/restore runtime probes. Expected: all PASS.

- [ ] **Step 6: Commit**

`feat: safely empty People functional data`

---

### Task 5: “Ricrea database People” from canonical schema

**Files:**
- Modify: `component/admin/src/Service/DatabaseMaintenanceService.php`
- Create: `tests/people-1.7.29-database-recreate-runtime.php`

**Interfaces:**
- `DatabaseMaintenanceService::recreateFunctionalDatabase(int $actorUserId, string $confirmation): array`
- Result: `safety_backup_uuid`, `schema_status`, `integrity`, `recreated_tables`.

- [ ] **Step 1: Write failing recreate tests**

Seed functional + maintenance rows and drift one functional table. Assert wrong confirmation does nothing. Assert invalid backup storage aborts before DDL. On success assert reason `before_recreate_database`, verified backup first, exactly four functional tables dropped/recreated through `DatabaseSchemaDefinition::createTableSql()` + `$db->replacePrefix()`, zero functional rows, canonical schema, and all prior maintenance rows/files preserved. Create an unrelated probe table and verify untouched.

For the partial-DDL failure case, create an external test table with a foreign key referencing People so dropping the referenced functional table fails after the safety backup exists; assert `DatabaseMaintenanceException::getSafetyBackupUuid()` returns that backup UUID and the external test table remains untouched. Run this failure case last in the probe.

- [ ] **Step 2: Verify RED**

Run: `php ../tests/people-1.7.29-database-recreate-runtime.php`

Expected: FAIL because recreate is missing.

- [ ] **Step 3: Implement recreate orchestration**

Validate exact `RICREA`; create+verify backup; drop only names returned by `functionalTables()`; recreate them deterministically from canonical DDL; inspect/repair preserved maintenance tables if safe; perform post-checks; log `database_recreate`. Do not claim transaction atomicity for DDL. If DDL fails after backup, throw `DatabaseMaintenanceException` carrying the safety-backup UUID.

- [ ] **Step 4: Verify GREEN + regressions**

Run recreate, empty, repair, and existing restore-full runtimes. Expected: PASS except the deliberately caught failure case, which must assert the expected recoverable exception.

- [ ] **Step 5: Commit**

`feat: recreate People database from canonical schema`

---

### Task 6: Information-page controls, thin controller, typed-confirmation UX

**Files:**
- Modify: `component/admin/src/Controller/MaintenanceController.php`
- Modify: `component/admin/src/Model/InformationModel.php`
- Modify: `component/admin/src/View/Information/HtmlView.php`
- Modify: `component/admin/tmpl/information/default.php`
- Modify: `component/media/css/information.css`
- Create: `component/media/js/database-maintenance.js`
- Modify: `component/media/joomla.asset.json`
- Create: `tests/people-1.7.29-database-maintenance-ui-contract.php`

**Interfaces:**
- Controller: `checkDatabase()`, `repairDatabase()`, `emptyDatabase()`, `recreateDatabase()`.
- Model: `getDatabaseSchemaStatus(): array`.
- View properties: `databaseSchemaStatus`, `canDatabaseRepair`, `canDatabaseDestructive`.

- [ ] **Step 1: Write failing UI/controller/ACL contract**

Assert all four actions exist; `checkDatabase` requires `core.manage`; repair requires both `core.manage` and `people.database_repair`; empty/recreate require both `core.manage` and `people.database_destructive`; every POST checks CSRF; destructive actions pass the typed string to the service. Assert Information contains schema status, maintenance history summary, external UUID-reference warning, exact `SVUOTA`/`RICREA` inputs, and registered JS asset. Assert expected maintenance exceptions are converted to queued Information-page messages rather than uncaught call stacks.

- [ ] **Step 2: Verify RED**

Run: `php tests/people-1.7.29-database-maintenance-ui-contract.php`

Expected: FAIL.

- [ ] **Step 3: Implement thin controller actions**

Token → required ACL(s) → input → service call → localized message → redirect to Information. Catch `DatabaseMaintenanceException`/expected `RuntimeException`; include safety-backup UUID in the message when available.

- [ ] **Step 4: Expose read-only schema status**

`InformationModel::getDatabaseSchemaStatus()` calls `check(0, false)`. View sets dedicated ACL booleans; no UI visibility is treated as authorization.

- [ ] **Step 5: Build Manutenzione database panel**

Order: Controlla, Ripara, Svuota, Ricrea. State what is preserved/removed. Show current schema status, last check/repair/destructive action and latest safety-backup UUID/date when available. Warn that Organizations/Membership/Competitions/etc. may retain UUID references until reimport/restore/relink.

- [ ] **Step 6: Add JS confirmation gating**

`database-maintenance.js` enables destructive submit buttons only on exact text match. Server-side confirmation remains authoritative. Register/load via Web Asset Manager.

- [ ] **Step 7: Verify GREEN**

Run UI contract, existing Information runtime, `php -l` for touched PHP, and `node --check component/media/js/database-maintenance.js`.

- [ ] **Step 8: Commit**

`feat: add database maintenance controls to Information`

---

### Task 7: People 1.7.29 versioning, CI, packaging, and release readiness

**Files:**
- Modify: `VERSION`
- Modify: `component/xdecaropeople.xml`
- Modify: `package/pkg_people.xml`
- Modify: `component/media/joomla.asset.json`
- Create: `component/admin/sql/updates/mysql/1.7.29.sql`
- Modify: `tests/people-1.7.28-information-maintenance-contract.php`
- Create: `.github/workflows/people-1.7.29-database-maintenance.yml`
- Modify: `.github/workflows/release.yml`

**Interfaces:** no new runtime API; this task locks release metadata and evidence.

- [ ] **Step 1: Add failing 1.7.29 release-readiness assertions**

Require new service/exception/JS paths, two ACL actions, SQL marker, canonical/installer parity contract and all new runtime probes. Fix the existing 1.7.28 maintenance regression contract so it requires current `VERSION >= 1.7.28` and component/package/assets equal the current `VERSION` rather than permanently requiring exactly 1.7.28.

- [ ] **Step 2: Verify RED before bump**

Run new 1.7.29 contracts. Expected: FAIL on version/marker/package requirements.

- [ ] **Step 3: Bump to 1.7.29 everywhere**

Update `VERSION`, both manifests and every web-asset version. Keep Joomla `6.1.3` and PHP minimum `8.3.0`.

- [ ] **Step 4: Add 1.7.29 update marker**

If no persistent schema change is required beyond ACL/code, use a documented no-op SQL marker. Runtime recreate must never replay historical update SQL.

- [ ] **Step 5: Add dedicated workflow**

Contracts first; then Joomla 6.1.3/MySQL runtime for inspector, repair, empty, recreate, existing backup/restore and Information probes. Runtime probes must restore their intentional schema mutations before exit except the explicit partial-DDL failure case, which runs last in its isolated probe.

- [ ] **Step 6: Update release workflow**

Verify packaged new files, JS syntax, SQL marker, canonical/installer parity, 1.7.29 runtime contract, and release notes describing canonical check/repair + backup-first empty/recreate. Do not claim cross-component cleanup.

- [ ] **Step 7: Run full verification**

Evidence required before PR merge: PHP syntax; all People contracts; JS syntax; deterministic build; package ZIP inspection; Joomla 6.1.3 clean install; supported upgrade baselines; full 1.7.29 database-maintenance runtime; existing Organizations/Notifications/runtime integrations. Zero failures required.

- [ ] **Step 8: Spec/diff review**

Verify: exactly six canonical tables; no foreign-table access; unknown objects preserved; maintenance history preserved; safety backup verified first; server typed confirmations; installer/canonical parity; expected failures do not expose call stacks.

- [ ] **Step 9: Commit and open PR**

`release: prepare People 1.7.29 canonical database maintenance`

Open PR `people-1.7.29-empty-database` → `main`. Do not merge or publish until all required CI is freshly green.
