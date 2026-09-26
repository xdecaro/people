# People 1.7.28 Information, Backup, Restore and Trash Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn People Information into an operational maintenance center with real diagnostics, logical People-only backups, validated restore, recoverable trash, permanent purge protection, and maintenance audit logs.

**Architecture:** Keep People the sole owner of its data. Introduce focused services for maintenance logging, trash lifecycle, backup creation/storage, restore/preview, and integrity checks; expose actions through authenticated Joomla administrator controllers and render the resulting read models in the existing Information view. Cross-component status remains capability-based only; no SQL against private tables owned by other components.

**Tech Stack:** Joomla 6.1.3, PHP 8.3+, MySQL/InnoDB, Joomla MVC/DI/ACL/CSRF, PHP `ZipArchive` and JSON, existing plain-PHP contract/runtime test style.

**Spec:** `docs/superpowers/specs/2026-09-26-people-information-backup-restore-trash-design.md`

## Global Constraints

- Joomla target is exactly **6.1.3**; do not add Joomla 4/5 or other 6.x compatibility work.
- PHP minimum is **8.3**.
- Canonical package is `pkg_people`; do not reintroduce `pkg_xdecaropeople` except existing migration compatibility.
- Namespace remains lowercase `xdecaro\...`; visible product label remains `People`.
- People may read/write only its own tables; external components are accessed only through public capabilities/services.
- Sensitive person values must never be emitted by diagnostics, maintenance logs, backup list summaries, or generic error messages.
- All destructive or state-changing administrator actions are POST + Joomla CSRF, except authenticated backup download.
- Backup payload whitelist is exactly the functional People tables: `#__xdecaropeople_people`, `#__xdecaropeople_history`, `#__xdecaropeople_duplicate_ignores`, `#__xdecaropeople_merges`.
- Backup metadata and maintenance-log tables are local operational state and are **not** part of restore payloads.
- Restore full must always create a safety backup first and use a database transaction for the actual replacement.
- Trash uses `state = -2`; ordinary People lists/providers must continue excluding trashed rows unless explicitly filtered.
- ID/UUID preservation takes priority during full restore; single-person restore preserves UUID and reuses ID only when free.
- Existing Dashboard, Duplicates, merge, Import, Export and public People provider contracts must remain non-regressive.

## Review Focus

1. **Hostile/corrupt ZIP** — reject path traversal, unexpected files, bad checksum and unsupported schema without modifying DB; pinned in Task 4 preview tests.
2. **Restore failure after mutation begins** — rollback leaves current People data intact and still keeps the pre-restore safety backup; pinned in Task 5 runtime tests.
3. **Unsafe/unwritable backup storage** — fail closed with a clear diagnostic and never fall back to a public/cache path; pinned in Task 3 tests.
4. **Permanent purge of a referenced person** — do not silently assume external safety; show capability-based reference warning when available and require explicit purge confirmation; pinned in Task 2 controller/service tests.
5. **Single-person UUID/ID collision** — preserve UUID, reuse free original ID, otherwise allocate a new ID, and require explicit overwrite when UUID already exists outside trash; pinned in Task 6 tests.

---

### Task 1: Version, schema, ACL and configuration foundation

**Files:**
- Create: `component/admin/sql/updates/mysql/1.7.28.sql`
- Create: `tests/people-1.7.28-information-maintenance-contract.php`
- Create: `.github/workflows/people-1.7.28-information-maintenance.yml`
- Modify: `component/admin/sql/install.mysql.utf8mb4.sql`
- Modify: `component/admin/access.xml`
- Modify: `component/admin/config.xml`
- Modify: `component/xdecaropeople.xml`
- Modify: `package/pkg_people.xml`
- Modify: `component/media/joomla.asset.json`
- Modify: `VERSION`

**Interfaces:**
- Produces DB tables `#__xdecaropeople_backups` and `#__xdecaropeople_maintenance_log`.
- Produces ACL actions `people.backup` and `people.restore`.
- Produces component params `backup_storage_path` (string, optional) and `backup_max_upload_mb` (integer, default `64`).
- All later tasks consume the schema and ACL defined here.

- [ ] **Step 1: Write the failing contract test**

Create `tests/people-1.7.28-information-maintenance-contract.php` asserting:
- `VERSION`, component manifest, canonical package manifest and web-asset version are `1.7.28`;
- install SQL and `1.7.28.sql` define `#__xdecaropeople_backups` and `#__xdecaropeople_maintenance_log`;
- `access.xml` contains `people.backup` and `people.restore`;
- `config.xml` contains `backup_storage_path` and `backup_max_upload_mb`;
- the install SQL still defines the four existing functional People tables unchanged in ownership;
- no SQL/table name from Organizations, Membership, Competitions, Photos, Documents or Notifications appears in the new maintenance SQL.

- [ ] **Step 2: Run it and verify RED**

Run: `php tests/people-1.7.28-information-maintenance-contract.php`
Expected: FAIL because version/schema/ACL/config are not yet present.

- [ ] **Step 3: Add the schema and ACL/config**

Define `#__xdecaropeople_backups` with: `id`, `uuid`, `filename`, `storage_path`, `sha256`, `size_bytes`, `people_count`, `component_version`, `schema_version`, `created`, `created_by`, `status`; unique UUID and useful created/status indexes.

Define `#__xdecaropeople_maintenance_log` with: `id`, `action`, nullable `subject_uuid`, `actor_user_id`, `created`, nullable `metadata`; indexes on action/date and subject/date.

Add the same CREATE TABLE definitions to install SQL and idempotent update SQL. Add ACL/config entries exactly as specified in Interfaces.

- [ ] **Step 4: Bump canonical version metadata to 1.7.28**

Update `VERSION`, `component/xdecaropeople.xml`, `package/pkg_people.xml` and every asset version in `component/media/joomla.asset.json` to `1.7.28`. Keep Joomla target `6.1.3` and author metadata unchanged.

- [ ] **Step 5: Run contract and build smoke**

Run:
- `php tests/people-1.7.28-information-maintenance-contract.php`
- `bash build/build.sh`
Expected: PASS; deterministic package build succeeds.

- [ ] **Step 6: Commit**

```bash
git add VERSION component package tests .github/workflows/people-1.7.28-information-maintenance.yml
git commit -m "feat: add People maintenance schema and permissions"
```

---

### Task 2: Maintenance log and recoverable trash lifecycle

**Files:**
- Create: `component/admin/src/Service/MaintenanceLogService.php`
- Create: `component/admin/src/Service/PersonTrashService.php`
- Modify: `component/admin/services/provider.php`
- Modify: `component/admin/src/Extension/PeopleComponent.php`
- Modify: `component/admin/src/Controller/PeopleController.php`
- Modify: `component/admin/src/Model/PeopleModel.php`
- Modify: `component/admin/src/Model/PersonModel.php`
- Modify: `tests/people-1.7.28-information-maintenance-contract.php`
- Create: `tests/people-1.7.28-trash-runtime.php`

**Interfaces:**
- `MaintenanceLogService::log(string $action, ?string $subjectUuid, int $actorUserId, array $metadata = []): void`
- `MaintenanceLogService::recent(int $limit = 20): array`
- `MaintenanceLogService::latestForSubject(string $subjectUuid, string $action): ?array`
- `PersonTrashService::trash(array $ids, int $actorUserId): int`
- `PersonTrashService::restore(array $ids, int $actorUserId): int`
- `PersonTrashService::purge(array $ids, int $actorUserId): int`
- `PersonTrashService::getRecentTrashed(int $limit = 10): array`

- [ ] **Step 1: Extend the contract and write runtime assertions first**

Contract assertions require both new services to exist, be registered in DI and exposed by `PeopleComponent`.

`tests/people-1.7.28-trash-runtime.php` must prove on a Joomla test DB:
- trash changes only `state` to `-2`, preserving ID and UUID;
- default `PeopleModel` query excludes the trashed row;
- explicit `filter_state=-2` can list it;
- restore returns the previous state recorded in maintenance metadata, defaulting safely to `1` if unavailable;
- purge refuses non-trashed rows;
- purge removes a trashed row only after the caller has `core.delete` and explicit purge endpoint path is used;
- `trash_person`, `restore_trash_person`, `purge_person` are logged without copying full sensitive row data.

- [ ] **Step 2: Run tests and verify RED**

Run:
- `php tests/people-1.7.28-information-maintenance-contract.php`
- runtime script through the existing Joomla runtime harness used by `build.yml`.
Expected: FAIL on missing services/actions.

- [ ] **Step 3: Implement `MaintenanceLogService`**

Use bound queries only. Encode `metadata` as JSON with `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`. Never accept a complete person row as metadata; callers pass only IDs/UUIDs/state/counts/non-sensitive operational values.

- [ ] **Step 4: Implement `PersonTrashService`**

For each trash operation load current `id`, `uuid`, `display_name`, `state`; write previous state in log metadata, then set `state=-2`. Restore uses latest `trash_person` metadata for prior state. Purge only rows currently `-2` and logs the minimal audit identity before deletion.

- [ ] **Step 5: Route administrator actions through PeopleController**

Override ordinary delete behavior so UI delete means trash, not hard delete. Add explicit POST tasks `people.restoreTrash` and `people.purge`, both with token and ACL checks. Keep publish/unpublish behavior for non-trash states unchanged.

- [ ] **Step 6: Verify provider/list exclusion and reference warning hook**

Do not change external provider visibility: trashed rows remain excluded by normal provider queries. Before purge, query only existing public Core/CapabilityRegistry reference checks if available; if unavailable, return `reference_status=unknown` rather than querying external tables.

- [ ] **Step 7: Run tests**

Expected: contract + trash runtime PASS and existing duplicate runtime still PASS.

- [ ] **Step 8: Commit**

```bash
git add component/admin/src component/admin/services tests
git commit -m "feat: add recoverable People trash lifecycle"
```

---

### Task 3: Backup creation, private storage, listing and download

**Files:**
- Create: `component/admin/src/Service/BackupStorageService.php`
- Create: `component/admin/src/Service/BackupService.php`
- Create: `tests/people-1.7.28-backup-runtime.php`
- Modify: `component/admin/services/provider.php`
- Modify: `component/admin/src/Extension/PeopleComponent.php`
- Modify: `tests/people-1.7.28-information-maintenance-contract.php`

**Interfaces:**
- `BackupStorageService::resolvePrivateDirectory(): string`
- `BackupStorageService::pathFor(string $backupUuid): string`
- `BackupStorageService::isHealthy(): array` returning `['ok'=>bool,'message'=>string]`
- `BackupService::create(int $actorUserId, string $reason = 'manual'): array`
- `BackupService::list(): array`
- `BackupService::resolveDownload(string $backupUuid): array`
- `BackupService::delete(string $backupUuid, int $actorUserId): void`
- Canonical backup ZIP entries: `manifest.json`, `data.json`, `SHA256SUMS.txt` only.

- [ ] **Step 1: Write backup runtime tests**

Use a known fixture with one person, one history row, one duplicate-ignore row and one merge row. Assert:
- `data.json` contains exactly the four whitelist tables;
- table rows preserve IDs/UUIDs/state/metadata values;
- canonical JSON checksum is stable for identical dataset/order;
- ZIP manifest includes People version, schema version, Joomla version, UTC timestamp, actor ID, table counts, people count and payload SHA256;
- DB metadata row is created with file size/hash/count/status;
- maintenance log receives `backup_create`, `backup_download`, `backup_delete`;
- configured unwritable or public-web storage fails closed;
- no Joomla cache directory fallback is used.

- [ ] **Step 2: Run and verify RED**

Expected: missing backup/storage services.

- [ ] **Step 3: Implement `BackupStorageService`**

Use configured `backup_storage_path` when valid; otherwise use a Joomla private non-web path suitable for persistent component data. Reject empty/unwritable/unsafe resolved paths. Generated filenames are UUID-based and not user-controlled.

- [ ] **Step 4: Implement canonical payload generation in `BackupService`**

Fetch whitelist tables with deterministic row ordering (`id ASC` where applicable), build canonical JSON, calculate SHA256, then create ZIP. Metadata/maintenance tables are never included in payload.

- [ ] **Step 5: Implement metadata/list/download/delete**

`resolveDownload()` must return metadata + server path only after DB metadata/hash/file existence checks; controller-level ACL is added later. Delete removes file and metadata row and logs action; data tables remain untouched.

- [ ] **Step 6: Register services and run tests**

Expected: backup runtime PASS, storage failure tests PASS, build PASS.

- [ ] **Step 7: Commit**

```bash
git add component/admin/src/Service component/admin/services component/admin/src/Extension tests
git commit -m "feat: add People backup service"
```

---

### Task 4: Restore upload and safe preview validation

**Files:**
- Create: `component/admin/src/Service/RestoreService.php`
- Create: `tests/people-1.7.28-restore-preview-runtime.php`
- Modify: `component/admin/services/provider.php`
- Modify: `component/admin/src/Extension/PeopleComponent.php`
- Modify: `tests/people-1.7.28-information-maintenance-contract.php`

**Interfaces:**
- `RestoreService::preview(string $zipPath, int $actorUserId): array`
- Preview result keys: `compatible`, `blocking_errors`, `warnings`, `manifest`, `counts`, `people_active`, `people_trashed`, `payload_sha256`.
- No preview method may modify functional People tables.

- [ ] **Step 1: Write preview tests**

Fixtures/assertions:
- valid backup previews successfully and functional table row counts remain unchanged;
- corrupt ZIP rejects;
- missing `manifest.json`, `data.json` or `SHA256SUMS.txt` rejects;
- checksum mismatch rejects;
- archive entry such as `../evil.php` rejects;
- extra executable/unexpected file rejects;
- data table outside whitelist rejects;
- unsupported backup format/schema is blocking;
- older compatible People version can produce warning but not block if schema format is supported;
- upload over configured `backup_max_upload_mb` is rejected before extraction.

- [ ] **Step 2: Run and verify RED**

Expected: RestoreService missing.

- [ ] **Step 3: Implement archive validation**

Inspect ZIP entries before extraction; do not extract to a web directory. Accept only the three canonical entry names. Decode JSON with exceptions. Recompute canonical payload hash and compare using `hash_equals`.

- [ ] **Step 4: Implement schema/data validation**

Require manifest format version supported by 1.7.28, whitelist table keys, array rows, valid UUID syntax for people/merge UUID fields, plausible counts, and no maintenance/backup metadata tables.

- [ ] **Step 5: Log preview and run tests**

Write only `restore_preview` operational metadata (backup UUID/version/counts/result), never full person data.

- [ ] **Step 6: Commit**

```bash
git add component/admin/src/Service component/admin/services component/admin/src/Extension tests
git commit -m "feat: add validated People restore preview"
```

---

### Task 5: Full transactional restore with automatic safety backup

**Files:**
- Modify: `component/admin/src/Service/RestoreService.php`
- Modify: `component/admin/src/Service/BackupService.php`
- Create: `tests/people-1.7.28-restore-full-runtime.php`

**Interfaces:**
- `RestoreService::restoreFull(string $zipPath, int $actorUserId): array`
- Return keys: `safety_backup_uuid`, `restored_counts`, `integrity_ok`, `warnings`.

- [ ] **Step 1: Write restore-full runtime tests**

Assert:
- valid restore first creates a backup with reason `pre_restore`;
- restore replaces only four functional People tables and reconstructs exact fixture IDs/UUIDs/rows;
- backup metadata and maintenance log rows from current server survive the restore;
- injected failure after first functional-table mutation rolls back all functional-table changes;
- safety backup still exists after rollback;
- tables owned by other components are byte/count unchanged before/after restore.

- [ ] **Step 2: Run and verify RED**

- [ ] **Step 3: Implement `restoreFull()`**

Call `preview()` first and reject any blocking error. Create safety backup outside the replacement transaction. Start DB transaction, clear/replace only whitelist tables in FK-safe logical order, restore explicit IDs/UUIDs, reset AUTO_INCREMENT where needed, validate counts/UUID uniqueness, commit only on success; rollback on any throwable.

- [ ] **Step 4: Log successful/failed restore without sensitive payloads**

Use `restore_full` metadata with source backup UUID/hash, safety backup UUID, counts and outcome. Do not write full rows.

- [ ] **Step 5: Run runtime + existing regression tests**

Expected: restore-full PASS; duplicates/import/export/provider runtime remain PASS.

- [ ] **Step 6: Commit**

```bash
git add component/admin/src/Service tests
git commit -m "feat: add transactional People restore"
```

---

### Task 6: Single-person restore by UUID

**Files:**
- Modify: `component/admin/src/Service/RestoreService.php`
- Create: `tests/people-1.7.28-restore-person-runtime.php`

**Interfaces:**
- `RestoreService::restorePerson(string $zipPath, string $personUuid, int $actorUserId, bool $overwrite = false): array`
- Return keys: `uuid`, `id`, `mode`, `warnings` where `mode` is `inserted`, `restored_from_trash`, or `overwritten`.

- [ ] **Step 1: Write collision-focused tests**

Assert:
- UUID found in current trash uses trash restore path and keeps same ID/UUID;
- absent UUID + free original ID inserts using original ID;
- absent UUID + occupied original ID inserts with new local ID but same UUID;
- existing active UUID rejects when `overwrite=false`;
- `overwrite=true` updates the matching UUID only after preserving its current state through a safety backup;
- unrelated history/merge rows are not blindly imported for a single-person restore;
- invalid/missing UUID in backup rejects without mutation.

- [ ] **Step 2: Run and verify RED**

- [ ] **Step 3: Implement `restorePerson()`**

Reuse `preview()` and canonical payload parser. Prefer current trash restore. For fresh insert preserve UUID, choose ID according to collision rule, preserve safe person metadata, and let normal database uniqueness constraints reject conflicting `user_id` unless explicitly resolved by the administrator.

- [ ] **Step 4: Log and run tests**

Write `restore_person` metadata with UUID, resulting ID and mode only.

- [ ] **Step 5: Commit**

```bash
git add component/admin/src/Service/RestoreService.php tests/people-1.7.28-restore-person-runtime.php
git commit -m "feat: add single person restore"
```

---

### Task 7: Integrity diagnostics and complete Information read model

**Files:**
- Create: `component/admin/src/Service/IntegrityService.php`
- Modify: `component/admin/src/Model/InformationModel.php`
- Modify: `component/admin/src/View/Information/HtmlView.php`
- Modify: `component/admin/services/provider.php`
- Modify: `component/admin/src/Extension/PeopleComponent.php`
- Modify: `tests/people-1.7.28-information-maintenance-contract.php`
- Create: `tests/people-1.7.28-information-runtime.php`

**Interfaces:**
- `IntegrityService::run(int $actorUserId = 0, bool $writeLog = true): array`
- Integrity result entries use `key`, `status` (`ok|warning|error`), `count`, `message_key`.
- `InformationModel::getDiagnostics(): array` remains the public view-model entry point but returns grouped keys: `environment`, `database`, `connections`, `integrity`, `backups`, `trash`, `maintenance`, `permissions`.

- [ ] **Step 1: Write Information/integrity tests**

Assert diagnostics expose:
- People/package/schema/Joomla/PHP/DB driver+version/timezone/Core version;
- people totals by active/unpublished/trash;
- history/merge/ignore counts;
- backup count/latest backup/latest restore;
- UUID missing/duplicate checks, unresolved history/merge checks, storage health and ZIP/JSON capability status;
- connection status for Core, Organizations, Membership, Competitions, Photos, Documents, Notifications only through boot/public capability checks;
- unsupported external capability reports `unsupported`, not fabricated counts;
- diagnostics arrays contain no person email, phone, tax identifier, address or notes values.

- [ ] **Step 2: Run and verify RED**

- [ ] **Step 3: Implement `IntegrityService`**

Use only People tables plus runtime/platform checks. Keep each check isolated so one warning does not prevent the rest of diagnostics.

- [ ] **Step 4: Expand `InformationModel`**

Compose existing Core integration, People DB aggregates, BackupService, MaintenanceLogService, PersonTrashService, IntegrityService and public component/capability availability. Do not query external tables.

- [ ] **Step 5: Update HtmlView**

Keep `core.manage` gate. Load the information CSS asset introduced in Task 8 and expose capability booleans for action buttons based on `people.backup`, `people.restore`, `core.edit.state`, `core.delete` or `core.admin`.

- [ ] **Step 6: Run tests and commit**

```bash
git add component/admin/src tests
git commit -m "feat: expand People maintenance diagnostics"
```

---

### Task 8: Administrator maintenance controllers and Information UI

**Files:**
- Create: `component/admin/src/Controller/MaintenanceController.php`
- Create: `component/media/css/information.css`
- Modify: `component/media/joomla.asset.json`
- Modify: `component/admin/tmpl/information/default.php`
- Modify: `component/admin/language/it-IT/com_xdecaropeople.ini`
- Modify: `component/admin/language/en-GB/com_xdecaropeople.ini`
- Modify: `tests/people-1.7.28-information-maintenance-contract.php`
- Create: `tests/people-1.7.28-maintenance-controller-contract.php`

**Interfaces:**
- Controller tasks: `maintenance.createBackup`, `maintenance.downloadBackup`, `maintenance.deleteBackup`, `maintenance.uploadPreview`, `maintenance.restoreFull`, `maintenance.restorePerson`, `maintenance.runIntegrity`.
- POST required for every state-changing task; download is GET with ACL + unpredictable UUID + DB/file/hash verification.
- Restore full UI requires a preview token/session fingerprint matching the same uploaded/stored backup hash before POST restore is accepted.

- [ ] **Step 1: Write controller/UI contract tests**

Assert:
- every POST action calls token validation and correct ACL;
- restore requires `people.restore|core.admin`; backup create/download/delete requires `people.backup|core.admin`;
- uploaded file size is checked against `backup_max_upload_mb`;
- full restore cannot be called without a prior matching preview fingerprint;
- template contains sections Product + Ambiente, Database People, Componenti collegati, Diagnostica e integrità, Gestione database, Backup disponibili, Cancellati, Attività manutenzione;
- dangerous actions use POST forms and explicit confirmation copy;
- trash links open People with `filter_state=-2`;
- no filesystem path is rendered.

- [ ] **Step 2: Run and verify RED**

- [ ] **Step 3: Implement `MaintenanceController`**

Keep controller thin: input/ACL/CSRF/upload checks, invoke services, enqueue translated result messages, redirect back to Information. Stream downloads with safe headers and no physical path disclosure.

- [ ] **Step 4: Build the Information template**

Use compact responsive Joomla cards, no decorative charts. Surface real values only. Backup rows show date/version/people/size/short checksum/creator/status plus allowed actions. Cancellati shows count + recent items + Ripristina + Apri cestino. Maintenance activity shows action/date/user, not sensitive payload.

- [ ] **Step 5: Add `information.css` and asset registration**

Follow dashboard visual language: compact cards, responsive grid, no horizontal overflow, clear warning/danger separation. Register `com_xdecaropeople.information` depending on `com_xdecaropeople.admin`.

- [ ] **Step 6: Add full Italian and English language strings**

All user-visible labels/messages/confirmations must be translatable. Italian is the primary acceptance screenshot language; English must remain complete.

- [ ] **Step 7: Run contracts + build and commit**

```bash
git add component/admin component/media tests
git commit -m "feat: add People maintenance center UI"
```

---

### Task 9: Joomla runtime, upgrade preservation and release readiness

**Files:**
- Modify: `.github/workflows/build.yml`
- Create: `.github/workflows/people-1.7.28-maintenance-runtime.yml`
- Modify: `.github/workflows/release.yml` only if its current version-detection/updater logic requires an explicit 1.7.28 change
- Modify: `README.md` only for administrator-facing backup/restore/trash documentation
- Verify generated: `updates/pkg_people.xml` through the existing release workflow, not by pre-emptive manual checksum invention

**Interfaces:**
- CI must exercise clean Joomla 6.1.3 install and supported upgrade baselines.
- Release asset remains `pkg_people_1.7.28.zip` with updater SHA256 matching the published asset.

- [ ] **Step 1: Add runtime workflow coverage**

Install Joomla 6.1.3 + current Core dependency + People package, then run:
- trash/restore/purge lifecycle;
- backup create/list/download verification;
- corrupt preview rejection;
- valid full restore + rollback injection case;
- single-person restore collision cases;
- Information diagnostics render/model probe;
- existing duplicate merge runtime and People provider probes.

- [ ] **Step 2: Add upgrade preservation checks**

From each baseline already supported by `build.yml`, verify upgrade creates both new tables and preserves existing People rows, UUIDs, config and duplicate/merge data. Existing records must not be auto-trashed or rewritten.

- [ ] **Step 3: Run the complete local/static suite**

Run:
- all `tests/people-1.7.28-*.php` contract tests that can run standalone;
- `php -l` over PHP sources;
- `bash build/build.sh` twice and compare output hashes;
- existing current People contracts referenced by `build.yml`.
Expected: all PASS and deterministic build hashes match.

- [ ] **Step 4: Run/observe GitHub CI to completion**

Required green checks:
- People CI validate;
- Joomla runtime clean install;
- all existing supported upgrade runtime jobs;
- People 1.7.28 contract workflow;
- People 1.7.28 maintenance runtime workflow;
- existing duplicate/dashboard/provider contracts.

- [ ] **Step 5: Perform final security/self-review**

Review specifically:
- no private cross-component SQL;
- no sensitive payload in logs/UI;
- no GET destructive action;
- no path traversal/public storage;
- restore whitelist cannot include backup/maintenance tables;
- every restore failure path rolls back;
- purge cannot target non-trash rows.

- [ ] **Step 6: Commit final CI/docs changes**

```bash
git add .github README.md
git commit -m "test: verify People maintenance runtime"
```

- [ ] **Step 7: Release verification after merge to main**

Confirm release workflow publishes `People 1.7.28`, `pkg_people_1.7.28.zip`, component ZIP and `SHA256SUMS.txt`; then confirm `updates/pkg_people.xml` reports version `1.7.28`, target Joomla `6.1.3`, and the exact SHA256 of the published canonical package.

---

## Plan Self-Review

- **Spec coverage:** environment/database/connections, backup create/list/download/delete, preview, full restore, single-person restore, trash/restore/purge, maintenance audit, integrity checks, ACL/CSRF, private storage, responsive Information UI, clean install, upgrades and release verification are each assigned to a task.
- **Boundaries:** maintenance logging, trash, storage, backup, restore and integrity are separate focused services; controller remains transport/ACL; InformationModel is composition/read-model only.
- **Type consistency:** Task 2 defines MaintenanceLogService used unchanged by Tasks 3–8; Task 3 defines BackupService used by Tasks 4–8; Task 4 defines RestoreService preview extended by Tasks 5–6; Task 7 consumes but does not redefine those interfaces.
- **Review Focus coverage:** hostile ZIP (Task 4), transactional rollback (Task 5), storage failure (Task 3), external-reference uncertainty before purge (Task 2), UUID/ID collision (Task 6).
- **YAGNI:** automatic scheduling, cloud sync, whole-site backups, other-component restore and advanced encryption remain out of scope for 1.7.28.
