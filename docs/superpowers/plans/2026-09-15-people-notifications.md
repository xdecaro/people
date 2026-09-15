# People Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Emit privacy-safe People events into the shared Notifications administrator bell for one configured Joomla user.

**Architecture:** Add a focused People notification integration service that owns recipient resolution and calls the public Notifications component services. PersonModel remains responsible for deciding when People business events occur; it delegates notification persistence/delivery to the integration service. Configuration uses Joomla component params, with no hard-coded user identity.

**Tech Stack:** Joomla 6.1.3, PHP 8.3+, People component services, Notifications 1.1.1 public component services, PHPUnit-free PHP contract tests used by the repository.

**Spec:** `docs/superpowers/specs/2026-09-15-people-notifications-design.md`

## Global Constraints

- Target Joomla 6.1.3 only.
- PHP 8.3+.
- No direct writes to Notifications tables.
- No People schema migration.
- Notification failures must never block People persistence/state actions.
- Do not include sensitive People values in notification titles/messages.
- Recipient must be configurable; no personal name or Joomla user id is hard-coded.

---

### Task 1: Notification integration contract

**Files:**
- Create: `tests/people-notifications-contract.php`
- Create: `component/admin/src/Service/NotificationIntegrationService.php`
- Modify: `component/admin/services/provider.php`
- Modify: `component/admin/src/Extension/PeopleComponent.php`

**Interfaces:**
- Produces `NotificationIntegrationService::notifyPersonCreated(array $person): void`
- Produces `NotificationIntegrationService::notifyPersonUpdated(array $person, array $changedFields): void`
- Produces `NotificationIntegrationService::notifyPersonStateChanged(array $person, int $previousState, int $newState): void`
- Produces `NotificationIntegrationService::notifyPossibleDuplicate(array $person): void`

- [ ] Write a failing contract test requiring the service, component wiring, configured recipient lookup, `bootComponent('com_xdecaronotifications')`, `getNotificationService()->create()`, `getDeliveryService()->queueForNotification(..., ['in_app'])`, privacy-safe event methods and exception logging.
- [ ] Run `php tests/people-notifications-contract.php` and verify failure before implementation.
- [ ] Implement the minimal integration service and DI/component wiring.
- [ ] Run the contract test again and verify PASS.

### Task 2: Component option and translated copy

**Files:**
- Modify: `component/admin/config.xml`
- Modify: `component/admin/language/it-IT/com_xdecaropeople.ini`
- Modify: `component/admin/language/en-GB/com_xdecaropeople.ini`

- [ ] Extend the contract test so it fails unless `notification_recipient_user_id` is a Joomla `user` field and all notification message keys exist in IT/EN.
- [ ] Add the component option and translations.
- [ ] Re-run the contract test and verify PASS.

### Task 3: Person save and duplicate events

**Files:**
- Modify: `component/admin/src/Model/PersonModel.php`
- Test: `tests/people-notifications-contract.php`

**Interfaces:**
- Consumes the integration service via `PeopleComponent::getNotificationIntegrationService()`.

- [ ] Extend the failing contract to require create notification after successful new-person save, update notification only when important fields changed, and duplicate notification when DuplicateService reports a match.
- [ ] Refactor duplicate detection to return a boolean while preserving the existing Joomla warning.
- [ ] After successful save, load the saved row, calculate changed fields, write history, then emit create/update and duplicate events without exposing field values.
- [ ] Re-run contracts and existing People tests.

### Task 4: Publication state events

**Files:**
- Modify: `component/admin/src/Model/PersonModel.php`
- Test: `tests/people-notifications-contract.php`

- [ ] Extend the contract to require `publish(&$pks, $value = 1)` override with before/after state snapshots.
- [ ] Implement state notifications for published, unpublished, trashed and restored transitions after `parent::publish()` succeeds.
- [ ] Ensure repeated no-op state actions do not emit a notification.
- [ ] Re-run contracts and existing People tests.

### Task 5: People 1.2.16 and Joomla 6.1.3-only release metadata

**Files:**
- Modify: `VERSION`
- Modify: `component/xdecaropeople.xml`
- Modify: `package/pkg_xdecaropeople.xml`
- Modify: `component/media/joomla.asset.json`
- Modify: `.github/workflows/build.yml`
- Modify: `.github/workflows/people-1.2.15-package.yml` (rename/rework logically to 1.2.16 in content)
- Modify: `.github/workflows/release.yml`
- Create: `tests/people-1.2.16-contract.php`

- [ ] Write a failing 1.2.16 release contract requiring version consistency, Joomla 6.1.3-only metadata and no `1.2.16.sql` migration.
- [ ] Bump source/package/assets to 1.2.16 and align CI runtime target to Joomla 6.1.3 only.
- [ ] Keep the update feed unchanged until release automation publishes the verified package checksum.
- [ ] Run all PHP contracts, XML/JSON parsing, JS syntax and deterministic package build checks available in CI.

### Task 6: PR and verification

- [ ] Open a PR from `feature/people-notifications-1.2.16` to `main` summarizing behavior and privacy guarantees.
- [ ] Wait for CI; inspect failures and fix only root causes.
- [ ] Verify all required checks are green before requesting merge/release.