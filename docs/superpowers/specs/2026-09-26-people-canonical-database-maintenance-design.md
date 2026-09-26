# People canonical database maintenance — design

Date: 2026-09-26
Target: People 1.7.29
Platform: Joomla 6.1.3, PHP 8.3+

## Goal

Give administrators a safe way, from **People → Informazioni → Gestione database**, to keep the People database clean, current and easy to reset for testing without depending on the full historical migration chain.

The feature must operate only on People-owned tables. It must never modify Joomla core tables or private tables owned by Organizations, Membership, Competitions, Photos, Documents, Notifications or any other component.

## Current People-owned tables

Functional tables:

- `#__xdecaropeople_people`
- `#__xdecaropeople_history`
- `#__xdecaropeople_duplicate_ignores`
- `#__xdecaropeople_merges`

Maintenance tables:

- `#__xdecaropeople_backups`
- `#__xdecaropeople_maintenance_log`

The functional tables are data that may be emptied or rebuilt. The maintenance tables are safety/audit infrastructure and must survive destructive functional-data operations.

## Core design decision: canonical schema

People will have one **canonical schema definition in PHP** as the runtime source of truth for the current database structure.

The canonical schema must describe, for every People-owned table:

- table name;
- table role: `functional` or `maintenance`;
- columns and their expected definitions;
- primary key;
- unique indexes;
- normal indexes;
- engine, charset and collation expectations where relevant;
- whether a legacy object is explicitly safe to remove.

The schema inspector, repair service and recreate service all consume this canonical definition.

`install.mysql.utf8mb4.sql` remains the Joomla installer SQL, but CI must verify that it represents the same current schema. Historical files in `admin/sql/updates/mysql/` remain only for upgrades from older installed versions; they are not the source used to build a fresh People database during maintenance.

This avoids reconstructing a new database by replaying every historical migration.

## Database maintenance section

The existing **Gestione database** area gains four separate actions with different risk levels.

### 1. Controlla database

Read-only operation.

It compares the live People schema with the canonical schema and reports:

- missing tables;
- unexpected People tables;
- missing columns;
- explicitly known legacy columns;
- incompatible column definitions;
- missing indexes;
- incompatible indexes;
- unexpected indexes when they are explicitly classified as obsolete;
- engine/charset/collation differences where relevant;
- overall status: `OK`, `Da aggiornare`, or `Errore`.

It must not mutate data or schema.

The UI should show a compact summary first and expandable technical details underneath.

### 2. Ripara database

Conservative schema repair.

It may:

- create a missing People table;
- add a missing column;
- adjust a column to the canonical compatible definition;
- add a missing index;
- replace an incompatible index;
- remove a column or index only when that object is listed in an explicit legacy-removal allowlist.

It must not:

- delete people records;
- truncate tables;
- remove an unknown column automatically;
- touch external component tables;
- infer destructive changes from naming alone.

Before applying repair, it performs a fresh inspection and generates a repair plan. The executed operations are limited to that plan.

A repair operation is logged in `#__xdecaropeople_maintenance_log` with the before/after status and performed actions.

### 3. Svuota dati People

Purpose: quickly reset People data for import/testing while keeping the current schema and maintenance history.

Mandatory flow:

1. require `core.manage` plus `people.database_destructive`;
2. require CSRF token;
3. require the administrator to type exactly `SVUOTA`;
4. create a full People safety backup automatically;
5. verify that the backup was created successfully;
6. clear only the four functional tables;
7. preserve `#__xdecaropeople_backups` and `#__xdecaropeople_maintenance_log`;
8. reset functional-table auto-increment counters when supported;
9. run a post-operation integrity/schema check;
10. log the operation, including safety backup UUID and row counts removed.

Functional tables are cleared in dependency-safe order:

1. history;
2. duplicate ignores;
3. merges;
4. people.

The implementation should prefer `DELETE` plus explicit auto-increment reset rather than `TRUNCATE`, so transactional/error handling remains under application control and repository safety rules are not weakened.

### 4. Ricrea database People

Purpose: rebuild the functional People database from the **current canonical schema**, removing structural residue from old releases.

Mandatory flow:

1. require `core.manage` plus `people.database_destructive`;
2. require CSRF token;
3. require the administrator to type exactly `RICREA`;
4. create a full People safety backup automatically;
5. verify the backup successfully exists and has a valid checksum;
6. drop only the four functional People tables;
7. recreate those four tables directly from the canonical current schema;
8. preserve the two maintenance tables;
9. inspect/repair the maintenance tables against the canonical schema;
10. run a full post-recreate schema/integrity check;
11. log the operation with safety backup UUID and result.

`Ricrea database` must not restore the backed-up data automatically. Its successful result is a clean, empty current People functional database with the safety backup still available for manual restore.

## Maintenance tables

`#__xdecaropeople_backups` and `#__xdecaropeople_maintenance_log` are preserved by both `Svuota` and `Ricrea`.

They are still part of the canonical schema and can be checked/repaired by `Controlla` and `Ripara`.

If a maintenance table is missing, `Ripara` may create it. If a maintenance table is damaged, repair must be conservative and preserve recoverable rows whenever possible.

`Ricrea` must not drop these two tables.

## Backup behavior

The current backup payload remains limited to the four functional People tables:

- people;
- history;
- duplicate ignores;
- merges.

This is intentional. Backup metadata and maintenance audit history belong to the current server and are not restored as functional data.

Safety backups created by `Svuota` and `Ricrea` use distinct reasons, e.g.:

- `before_empty_database`;
- `before_recreate_database`.

A destructive operation must abort if its safety backup cannot be created and validated.

## Permissions

Existing backup/restore permissions remain unchanged.

Add two explicit database-maintenance permissions:

- `people.database_repair` — may inspect a repair plan and apply conservative canonical-schema repair;
- `people.database_destructive` — may empty or recreate the functional People database.

Rules:

- `Controlla`: `core.manage`;
- `Ripara`: `core.manage` + `people.database_repair`;
- `Svuota`: `core.manage` + `people.database_destructive`;
- `Ricrea`: `core.manage` + `people.database_destructive`.

The default administrator role may receive these permissions through normal Joomla ACL inheritance; they must never be bypassed in code.

## Confirmation UX

Do not rely on browser `confirm()` alone for destructive operations.

The UI must require typed confirmation:

- `SVUOTA` for emptying functional data;
- `RICREA` for rebuilding functional tables.

The submit button remains disabled until the exact confirmation text matches.

Server-side code independently validates the confirmation string; JavaScript is only a usability aid.

The panel must clearly state what is preserved and what is removed before submission.

## Canonical schema service

Introduce a dedicated service layer, conceptually:

- `DatabaseSchemaDefinition` — current expected People schema;
- `DatabaseSchemaInspector` — reads actual MySQL/MariaDB metadata and computes differences;
- `DatabaseMaintenanceService` — repair, empty and recreate orchestration.

These names may be adjusted during planning to fit repository conventions, but responsibilities must remain separated.

The controller must remain thin: authentication/ACL, input validation, service invocation and redirect/message only.

## Repair policy and legacy cleanup

The important rule is **known legacy only**.

An unexpected column or index is reported by `Controlla`, but is not automatically deleted by `Ripara` unless its exact table/object identity is present in a version-controlled legacy-removal allowlist.

This prevents accidental deletion of administrator/custom data while still allowing People to deliberately clean structures known to have been retired by earlier versions.

When People intentionally retires a schema object in a future release, that release adds it to the legacy-removal allowlist and a regression test documents the decision.

## Transactions and failure handling

Schema DDL on MySQL can implicitly commit, so `Ricrea` cannot pretend to be fully transaction-atomic.

Safety therefore comes from:

- mandatory validated safety backup before destructive DDL;
- deterministic operation order;
- canonical schema recreation;
- step-by-step error checking;
- post-operation schema verification;
- audit logging;
- clear recovery path through the existing Restore feature.

`Svuota` should use transactional DML where the database driver permits it.

If any destructive operation fails, the UI must return to People → Informazioni with a clear error message and the safety backup UUID where available. It must never expose a raw Joomla call stack for expected operational failures.

## UI layout

Inside **Informazioni → Gestione database**, show a dedicated **Manutenzione database** panel with four actions ordered by risk:

- `Controlla database` — neutral/primary;
- `Ripara database` — warning but non-destructive to records;
- `Svuota dati People` — danger;
- `Ricrea database People` — strongest danger treatment.

The panel also shows:

- current schema status;
- last database check;
- latest repair result;
- latest destructive maintenance result;
- latest safety backup UUID/date when relevant.

After each operation the page refreshes and displays the new schema/data state.

## Audit log actions

Add maintenance actions such as:

- `database_check`;
- `database_repair`;
- `database_empty`;
- `database_recreate`.

Metadata should include useful counts and results but never secrets or complete backup payloads.

## External integrations

Emptying or recreating People can temporarily leave UUID references in other components without a current People record. People must not modify those external tables directly.

The UI must warn that Organizations, Membership, Competitions and other components may retain references until people are reimported/restored/relinked.

People continues to interact with external components only through their public integration surfaces.

## Versioning

Target release: **People 1.7.29** unless that version is consumed before implementation begins; in that case use the next unused SemVer patch and never reuse a published version.

Joomla target remains exactly 6.1.3. PHP minimum remains 8.3.

## Testing requirements

TDD is mandatory.

Tests must cover at least:

1. canonical definition contains exactly the six intended People tables and correct functional/maintenance classification;
2. inspector reports clean schema as clean;
3. inspector detects missing table/column/index;
4. inspector reports unknown schema objects without deleting them;
5. repair creates known missing structures;
6. repair removes only allowlisted legacy structures;
7. `Svuota` refuses without exact `SVUOTA` confirmation;
8. `Svuota` aborts when safety backup fails;
9. `Svuota` clears all four functional tables while preserving backup/log tables;
10. `Ricrea` refuses without exact `RICREA` confirmation;
11. `Ricrea` creates and validates a safety backup first;
12. `Ricrea` rebuilds exactly the four functional tables from canonical schema;
13. `Ricrea` preserves maintenance tables and their prior rows;
14. no operation touches any non-People table;
15. Joomla 6.1.3 clean install still succeeds;
16. upgrade from supported legacy People baselines still succeeds;
17. existing backup/restore runtime tests remain green;
18. information-page contract verifies all four maintenance actions, typed confirmations and external-reference warning;
19. ACL tests verify repair and destructive operations cannot be invoked without their dedicated permissions.

CI must also compare the canonical schema with `install.mysql.utf8mb4.sql` sufficiently to prevent the two definitions drifting apart.

## Non-goals

This feature does not:

- manage the whole Joomla database;
- optimize or repair arbitrary MySQL tables outside People;
- delete Organizations/Membership/Competitions data;
- automatically restore external references;
- replace normal Joomla extension migrations for upgrades;
- remove unknown custom schema objects automatically.

## Success criteria

The feature is complete when an administrator can open People → Informazioni and:

1. see whether the People schema exactly matches the current canonical definition;
2. repair known schema drift without losing people records;
3. empty all functional People data after an automatic verified backup;
4. rebuild the functional People tables from the current schema after an automatic verified backup;
5. retain backup history and maintenance audit history;
6. restore from the safety backup using the existing Restore feature if necessary;
7. perform all of this without People touching any table owned by Joomla or another component.
