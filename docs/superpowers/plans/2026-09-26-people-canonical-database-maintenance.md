# People Canonical Database Maintenance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add safe canonical-schema inspection, repair, data emptying, and functional-table recreation to People → Informazioni without touching Joomla or other components.

**Architecture:** A pure PHP canonical schema definition becomes the runtime source of truth for the six People-owned tables. A schema inspector compares live MySQL/MariaDB metadata to that definition, while a maintenance orchestrator performs conservative repair and backup-first destructive operations. The existing Information page remains the only administrator UI; controllers stay thin and all destructive behavior is server-validated.

**Tech Stack:** Joomla 6.1.3, PHP 8.3+, Joomla DatabaseInterface, MySQL 8/MariaDB-compatible DDL, existing People Backup/Integrity/Maintenance services, vanilla JavaScript, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-26-people-canonical-database-maintenance-design.md`

## Global Constraints

- Target release: **People 1.7.29**; latest published release is currently 1.7.28, so 1.7.29 is unused at planning time.
- Joomla target remains exactly **6.1.3**.
- PHP minimum remains **8.3**.
- People may operate only on tables whose exact canonical names start with `#__xdecaropeople_` and are present in the definition.
- Functional tables: `#__xdecaropeople_people`, `#__xdecaropeople_history`, `#__xdecaropeople_duplicate_ignores`, `#__xdecaropeople_merges`.
- Maintenance tables: `#__xdecaropeople_backups`, `#__xdecaropeople_maintenance_log`.
- Unknown columns/indexes/tables are reported but never removed unless their exact identity is in the version-controlled legacy-removal allowlist.
- `Svuota` and `Ricrea` require a successfully verified automatic safety backup before any destructive database action.
- `Svuota` requires exact server-side confirmation `SVUOTA`; `Ricrea` requires exact server-side confirmation `RICREA`.
- `Svuota` and `Ricrea` preserve the backup and maintenance-log tables and their rows.
- Existing backup payload remains limited to the four functional People tables.
- No direct reads or writes to private tables owned by Organizations, Membership, Competitions, Photos, Documents, Notifications, Core, or Joomla.

## Review Focus

- **Unknown/custom schema objects:** inspector reports them; repair and recreate-support code must not silently drop them unless allowlisted. Covered in Tasks 2–3.
- **Safety backup failure or checksum mismatch:** empty/recreate abort before the first destructive query and return a recoverable error. Covered in Tasks 4–5.
- **Partial DDL failure during recreate:** operation reports the safety-backup UUID and leaves maintenance history available for manual restore. Covered in Task 5.
- **Maintenance history preservation:** pre-existing backup rows/log rows survive both empty and recreate. Covered in Tasks 4–5.
- **Table-scope escape:** no generated repair/recreate operation may target a table outside the six exact canonical People tables. Covered in Tasks 1–5.

---

### Task 1: Canonical People schema definition and installer parity

**Files:**
- Create: `component/admin/src/Service/DatabaseSchemaDefinition.php`
- Create: `tests/people-1.7.29-canonical-schema-contract.php`
- Modify: `component/admin/sql/install.mysql.utf8mb4.sql`

**Interfaces:**
- Produces: `DatabaseSchemaDefinition::tables(): array`
- Produces: `DatabaseSchemaDefinition::functionalTables(): array`
- Produces: `DatabaseSchemaDefinition::maintenanceTables(): array`
- Produces: `DatabaseSchemaDefinition::table(string $table): array`
- Produces: `DatabaseSchemaDefinition::createTableSql(string $table): string`
- Produces: `DatabaseSchemaDefinition::legacyRemovals(): array`

- [ ] **Step 1: Write the failing canonical-schema contract**

Create `tests/people-1.7.29-canonical-schema-contract.php` asserting that the definition exposes exactly six tables, classifies exactly four as `functional` and two as `maintenance`, rejects unknown table names, contains no foreign component prefixes, and generates canonical CREATE TABLE SQL for each table. Also normalize the generated SQL signatures and `install.mysql.utf8mb4.sql` signatures and assert matching table/column/index identities.

- [ ] **Step 2: Run the contract and verify RED**

Run: `php tests/people-1.7.29-canonical-schema-contract.php`

Expected: FAIL because `DatabaseSchemaDefinition.php` does not exist.

- [ ] **Step 3: Implement `DatabaseSchemaDefinition`**

Use one structured definition per table containing role, ordered column SQL fragments, primary key, unique indexes, normal indexes, engine, charset and collation. `createTableSql()` must only accept an exact defined table name and must build deterministic DDL from that structure. Start `legacyRemovals()` empty unless an exact retired object is proven from repository history during implementation; do not invent legacy names.

- [ ] **Step 4: Align installer SQL with the canonical definition**

Update `component/admin/sql/install.mysql.utf8mb4.sql` only where the contract proves drift. Fresh installation SQL must represent the same six-table current schema, without replaying historical update files.

- [ ] **Step 5: Run the contract and verify GREEN**

Run: `php tests/people-1.7.29-canonical-schema-contract.php`

Expected: `People 1.7.29 canonical schema contract PASS`.

- [ ] **Step 6: Commit**

Commit message: `feat: define canonical People database schema`

---

### Task 2: Live schema inspector

**Files:**
- Create: `component/admin/src/Service/DatabaseSchemaInspector.php`
- Create: `tests/people-1.7.29-database-schema-runtime.php`
- Modify: `component/admin/services/provider.php`
- Modify: `component/admin/src/Extension/PeopleComponent.php`

**Interfaces:**
- Consumes: Task 1 `DatabaseSchemaDefinition`.
- Produces: `DatabaseSchemaInspector::inspect(): array`
- Produces: `PeopleComponent::getDatabaseSchemaDefinition(): DatabaseSchemaDefinition`
- Produces: `PeopleComponent::getDatabaseSchemaInspector(): DatabaseSchemaInspector`
- `inspect()` result keys: `status`, `ok`, `tables`, `missing_tables`, `unexpected_tables`, `missing_columns`, `incompatible_columns`, `unknown_columns`, `missing_indexes`, `incompatible_indexes`, `unknown_indexes`, `engine_differences`, `collation_differences`.

- [ ] **Step 1: Write runtime tests for clean and drifted schemas**

In `tests/people-1.7.29-database-schema-runtime.php`, on Joomla 6.1.3 assert: a freshly installed schema is `OK`; removing one test index is reported as missing; adding a custom column is reported under `unknown_columns`; creating a fake `#__xdecaropeople_custom_test` table is reported under `unexpected_tables`; none of these read-only checks mutates schema or rows.

- [ ] **Step 2: Run the runtime probe and verify RED**

Run inside the existing Joomla CI harness: `php ../tests/people-1.7.29-database-schema-runtime.php`

Expected: FAIL because the inspector service/getters are missing.

- [ ] **Step 3: Implement `DatabaseSchemaInspector`**

Use MySQL/MariaDB metadata (`INFORMATION_SCHEMA` or deterministic `SHOW` queries) to normalize live table, column and index metadata. Compare only against `DatabaseSchemaDefinition`; do not derive expectations from historical migrations. Unknown objects are findings, never repair instructions.

- [ ] **Step 4: Register definition and inspector in DI/component**

Add shared services in `component/admin/services/provider.php`; add typed setters/getters in `PeopleComponent` following existing service patterns.

- [ ] **Step 5: Run canonical contract plus runtime probe**

Run:
`php tests/people-1.7.29-canonical-schema-contract.php`
`php ../tests/people-1.7.29-database-schema-runtime.php`

Expected: both PASS.

- [ ] **Step 6: Commit**

Commit message: `feat: inspect People schema against canonical definition`

---

### Task 3: Conservative database repair and ACL

**Files:**
- Create: `component/admin/src/Service/DatabaseMaintenanceService.php`
- Create: `tests/people-1.7.29-database-repair-runtime.php`
- Modify: `component/admin/access.xml`
- Modify: `component/admin/services/provider.php`
- Modify: `component/admin/src/Extension/PeopleComponent.php`
- Modify: `component/admin/language/it-IT/com_xdecaropeople.ini`
- Modify: `component/admin/language/en-GB/com_xdecaropeople.ini`

**Interfaces:**
- Consumes: `DatabaseSchemaDefinition`, `DatabaseSchemaInspector`, `MaintenanceLogService`, `BackupService`, `IntegrityService`.
- Produces: `DatabaseMaintenanceService::check(int $actorUserId, bool $writeLog = true): array`
- Produces: `DatabaseMaintenanceService::repair(int $actorUserId): array`
- Produces: `PeopleComponent::getDatabaseMaintenanceService(): DatabaseMaintenanceService`
- Adds ACL: `people.database_repair`, `people.database_destructive`.

- [ ] **Step 1: Write failing repair and scope tests**

Create a runtime probe that removes a canonical index/column from a disposable installed schema, adds one custom column/index and one fake People-prefixed table, runs `repair()`, and asserts canonical missing structures are recreated while the custom objects remain untouched. Add a legacy-removal test fixture only through `DatabaseSchemaDefinition::legacyRemovals()` and assert only that exact allowlisted object may be removed. Assert a non-People table with matching column names is unchanged.

- [ ] **Step 2: Verify RED**

Run: `php ../tests/people-1.7.29-database-repair-runtime.php`

Expected: FAIL because `DatabaseMaintenanceService` is missing.

- [ ] **Step 3: Implement `check()` and `repair()`**

`check()` delegates to the inspector and optionally logs `database_check`. `repair()` performs a fresh inspection, constructs an explicit ordered repair plan, permits CREATE/ADD/MODIFY/index replacement plus exact allowlisted removals, executes only canonical People-table operations, re-inspects, and logs `database_repair` with before/after status and operation names.

- [ ] **Step 4: Add ACL definitions and language labels**

Add `people.database_repair` and `people.database_destructive` to `access.xml` with Italian/English labels. Do not grant permissions in code.

- [ ] **Step 5: Register maintenance service**

Wire it through the DI provider and `PeopleComponent`.

- [ ] **Step 6: Verify GREEN**

Run the repair runtime plus existing People maintenance contracts. Expected: repair runtime PASS and no regression failures.

- [ ] **Step 7: Commit**

Commit message: `feat: repair canonical People database schema`

---

### Task 4: Verified safety backup and “Svuota dati People”

**Files:**
- Modify: `component/admin/src/Service/BackupService.php`
- Modify: `component/admin/src/Service/DatabaseMaintenanceService.php`
- Create: `tests/people-1.7.29-database-empty-runtime.php`

**Interfaces:**
- Produces: `BackupService::verify(string $backupUuid): array` — validates recorded file existence and SHA256 without logging a download.
- Produces: `DatabaseMaintenanceService::emptyFunctionalData(int $actorUserId, string $confirmation): array`
- Result includes: `safety_backup_uuid`, `removed_counts`, `schema_status`, `integrity`.

- [ ] **Step 1: Write failing empty-database tests**

Seed rows in all six People tables. Assert `emptyFunctionalData()` rejects any confirmation except exact `SVUOTA`; assert simulated backup creation/verification failure leaves all functional rows intact; assert success removes rows from all four functional tables, preserves pre-existing `backups` and `maintenance_log` rows, creates/logs a `before_empty_database` safety backup, resets functional auto-increments where supported, and leaves a clean canonical schema.

- [ ] **Step 2: Verify RED**

Run: `php ../tests/people-1.7.29-database-empty-runtime.php`

Expected: FAIL because `verify()` / `emptyFunctionalData()` are missing.

- [ ] **Step 3: Refactor backup verification into `BackupService::verify()`**

Move file existence + SHA256 validation out of download-only behavior. `resolveDownload()` must call `verify()` and continue to log `backup_download`; internal safety checks call `verify()` directly and do not create a false download audit event.

- [ ] **Step 4: Implement `emptyFunctionalData()`**

Validate confirmation first; create backup with reason `before_empty_database`; verify it; count rows; delete in order `history`, `duplicate_ignores`, `merges`, `people` using transactional DML where available; reset functional AUTO_INCREMENT counters after successful deletes; run inspector/integrity checks; log `database_empty` including backup UUID and counts. Never clear maintenance tables.

- [ ] **Step 5: Verify GREEN**

Run empty runtime plus all existing backup/restore runtime probes. Expected: all PASS.

- [ ] **Step 6: Commit**

Commit message: `feat: safely empty People functional data`

---

### Task 5: “Ricrea database People” from canonical schema

**Files:**
- Modify: `component/admin/src/Service/DatabaseMaintenanceService.php`
- Create: `tests/people-1.7.29-database-recreate-runtime.php`

**Interfaces:**
- Consumes: `DatabaseSchemaDefinition::createTableSql()`, `BackupService::verify()`.
- Produces: `DatabaseMaintenanceService::recreateFunctionalDatabase(int $actorUserId, string $confirmation): array`
- Result includes: `safety_backup_uuid`, `schema_status`, `integrity`, `recreated_tables`.

- [ ] **Step 1: Write failing recreate tests**

Seed functional and maintenance rows and intentionally add structural drift to a functional table. Assert wrong confirmation rejects without backup or DDL; backup/verification failure performs no DROP; successful `RICREA` creates reason `before_recreate_database`, preserves all prior backup/log rows, drops/recreates exactly the four functional tables, produces zero functional rows, restores canonical columns/indexes, and leaves unrelated Joomla/external test tables unchanged. Add a forced DDL-failure path and assert the thrown/reported error retains the safety-backup UUID for recovery.

- [ ] **Step 2: Verify RED**

Run: `php ../tests/people-1.7.29-database-recreate-runtime.php`

Expected: FAIL because `recreateFunctionalDatabase()` is missing.

- [ ] **Step 3: Implement recreate orchestration**

Validate `RICREA`, create+verify safety backup, drop only names returned by `functionalTables()`, recreate each from `createTableSql()`, run `repair()`/inspection for the preserved maintenance tables, perform post-checks, and log `database_recreate`. Catch expected operational errors and attach the safety backup UUID to the service exception/result message; do not claim DDL atomicity.

- [ ] **Step 4: Verify GREEN**

Run recreate runtime, empty runtime, repair runtime, and existing restore-full runtime. Expected: all PASS.

- [ ] **Step 5: Commit**

Commit message: `feat: recreate People database from canonical schema`

---

### Task 6: Information-page controls, controller actions, and typed confirmation UX

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
- Controller actions: `checkDatabase()`, `repairDatabase()`, `emptyDatabase()`, `recreateDatabase()`.
- Model: `getDatabaseSchemaStatus(): array` plus latest database-maintenance audit summaries.
- View properties: `databaseSchemaStatus`, `canDatabaseRepair`, `canDatabaseDestructive`.

- [ ] **Step 1: Write failing UI/controller contract**

Assert the Information page exposes all four actions, shows current schema status and external-reference warning, includes typed `SVUOTA`/`RICREA` fields, loads `com_xdecaropeople.database-maintenance` JS, and has separate permission booleans. Assert controller methods enforce `core.manage` plus the dedicated ACL, CSRF, exact server-side confirmation, and catch expected runtime failures into Information-page messages rather than raw call stacks.

- [ ] **Step 2: Verify RED**

Run: `php tests/people-1.7.29-database-maintenance-ui-contract.php`

Expected: FAIL because the controls/actions/assets are absent.

- [ ] **Step 3: Add thin controller actions**

Each action performs token + ACL + input validation, calls `DatabaseMaintenanceService`, queues a concise success/warning/error message, and redirects to `index.php?option=com_xdecaropeople&view=information`. `emptyDatabase()` passes `confirmation`; `recreateDatabase()` passes `confirmation` exactly as typed.

- [ ] **Step 4: Expose schema state from model/view**

Use `getDatabaseMaintenanceService()->check(0, false)` for read-only page status. Add permission booleans without bypass logic.

- [ ] **Step 5: Build the Manutenzione database panel**

Order actions by risk: Controlla, Ripara, Svuota, Ricrea. State clearly that backup/log tables are preserved, while external components may retain UUID references after empty/recreate. Show last check/repair/destructive operation where audit data exists.

- [ ] **Step 6: Add typed-confirmation JavaScript**

`database-maintenance.js` only enables the Svuota/Ricrea submit buttons when input matches exact `SVUOTA`/`RICREA`; server validation remains authoritative. Register/load the asset through Joomla Web Asset Manager.

- [ ] **Step 7: Verify GREEN**

Run UI contract and Information runtime. Expected: PASS, plus PHP/JS syntax checks clean.

- [ ] **Step 8: Commit**

Commit message: `feat: add database maintenance controls to Information`

---

### Task 7: Version 1.7.29, regression compatibility, CI, packaging, and release readiness

**Files:**
- Modify: `VERSION`
- Modify: `component/xdecaropeople.xml`
- Modify: `package/pkg_people.xml`
- Modify: `component/media/joomla.asset.json`
- Create: `component/admin/sql/updates/mysql/1.7.29.sql`
- Modify: `tests/people-1.7.28-information-maintenance-contract.php`
- Create: `.github/workflows/people-1.7.29-database-maintenance.yml`
- Modify: `.github/workflows/release.yml`

**Interfaces:**
- No new runtime API; this task locks packaging/version/CI behavior.

- [ ] **Step 1: Add failing release-readiness assertions**

Extend the 1.7.29 contract/workflow to require the new schema/inspector/maintenance services, JS asset, ACL actions, SQL marker and runtime probes in the package. Make the existing 1.7.28 maintenance regression contract forward-compatible: require current `VERSION >= 1.7.28` and require component/package/assets to equal the current `VERSION`, rather than hard-coding 1.7.28 forever.

- [ ] **Step 2: Verify RED before version bump**

Run the new 1.7.29 contracts. Expected: FAIL on version/marker/release packaging requirements.

- [ ] **Step 3: Bump all version metadata to 1.7.29**

Update `VERSION`, component manifest, package manifest and every asset version to `1.7.29`. Keep Joomla target `6.1.3` and PHP minimum `8.3.0`.

- [ ] **Step 4: Add `1.7.29.sql` marker**

No historical replay is used for recreate. Add only schema/upgrade SQL genuinely required by this release; if no persistent table change is required, use a documented no-op marker so Joomla records the version cleanly.

- [ ] **Step 5: Add dedicated 1.7.29 workflow**

Run static contracts first, then one Joomla 6.1.3/MySQL runtime job containing schema-inspection, repair, empty, recreate, existing backup/restore and Information probes. Keep the general People CI unchanged except for new contract/package checks needed for this release.

- [ ] **Step 6: Update release workflow package assertions and notes**

Require all new service/JS/tested package paths, the 1.7.29 SQL marker, and the two new ACL actions. Release notes must describe canonical schema check/repair and backup-first empty/recreate; do not claim cross-component cleanup.

- [ ] **Step 7: Run complete verification**

Required evidence before merge/release:

`php -l` over all PHP files; all People contracts; `node --check component/media/js/database-maintenance.js`; deterministic package build; Joomla 6.1.3 clean install; supported upgrade baselines; People 1.7.29 maintenance runtime; existing Organizations/Notifications/runtime integrations; package ZIP inspection.

Expected: zero failures.

- [ ] **Step 8: Review diff against the spec**

Confirm line-by-line that: six exact tables only; no foreign table access; unknown schema objects are preserved; backup/log rows survive destructive actions; typed confirmations are server-validated; safety backup is verified first; installer SQL and canonical schema agree.

- [ ] **Step 9: Commit and prepare PR**

Commit message: `release: prepare People 1.7.29 canonical database maintenance`

Open a PR from `people-1.7.29-empty-database` to `main`. Do not merge or publish until all required CI is freshly green.
