# People Competitions History Tab Design

## Goal

Add a read-only `Competitions` tab to the People person edit screen so an administrator can see the person's competition history without moving or duplicating competition-owned data into People.

## Ownership and dependency boundaries

People remains the owner of reusable person master data only. Competition participation, roster membership, sport role, shirt number, team, season, tournament, participation status, roster status and competition-specific photos remain owned by Competitions.

People must not query private Competitions tables directly and must not persist a copy of competition history. The integration is provider-owned: People boots `com_competitions` and consumes a stable public history service exposed by Competitions.

The person is identified exclusively through the existing People UUID stored by Competitions as `person_uuid`. People numeric IDs are not used as a cross-product identifier.

If Competitions is not installed, cannot boot, is incompatible, or does not expose the required public method, People continues to work normally and the Competitions tab is omitted.

## User interface

The tab is shown only for an existing saved person with a non-empty UUID and when the Competitions public history provider is available.

The tab label is `Competitions`.

The content is read-only and contains:

1. a compact summary with the number of linked competition-history rows;
2. a `Storico partecipazioni` table ordered from newest to oldest;
3. an empty state `Nessuna partecipazione collegata` when the person is linked to Competitions but has no roster history.

Each history row displays:

- Competizione;
- Stagione;
- Squadra;
- Ruolo;
- N. maglia;
- Stato.

The first implementation intentionally does not add statistics, goals, cards, match-by-match events, transfer history or editable competition fields. Those can be added later through the same provider boundary if needed.

## Public contract consumed by People

Competitions exposes a public component method:

```php
public function getPersonHistoryService(): PersonHistoryService;
```

The service exposes:

```php
public function getHistoryByPersonUuid(string $personUuid): array;
```

The returned value is an ordered list of associative arrays. Each row has this normalized shape:

```php
[
    'player_id' => 123,
    'roster_id' => 456,
    'participation_id' => 789,
    'team_id' => 10,
    'team_name' => 'Example Team',
    'season_id' => 20,
    'season_name' => '2026',
    'season_year' => 2026,
    'tournament_id' => 30,
    'competition_name' => 'Example Cup',
    'role' => 'player',
    'shirt_number' => 7,
    'status' => 'approved',
    'start_date' => '2026-09-01',
    'end_date' => '2026-09-07',
]
```

Nullable source values remain `null`; the provider does not invent display values. `competition_name`, `season_name` and `team_name` are human-readable snapshot values assembled at query time from Competitions-owned entities.

The service normalizes the UUID to lowercase and trim before querying. An empty UUID returns an empty array.

## People integration service

People adds a focused integration service, for example:

```php
final class CompetitionsIntegrationService
{
    public function isHistoryAvailable(): bool;
    public function getPersonHistory(string $personUuid): array;
}
```

The service is responsible only for booting Competitions, validating its public surface and translating provider failures into a controlled optional-integration fallback.

The People `Person` administrator view obtains history only for an existing item with a UUID. The view exposes two presentation properties:

```php
public bool $competitionsHistoryAvailable = false;
public array $competitionsHistory = [];
```

No competition query belongs in the template.

## Error handling

Competitions provider failures must not prevent editing a person in People.

If the Competitions component is absent or its history provider is unavailable, the tab is omitted.

If the provider exists but fails while retrieving history, People omits the tab and may log a non-sensitive diagnostic message. The diagnostic must not include sensitive People values and must not expose internal SQL or stack traces in the UI.

People's existing save, apply, save-and-new and cancel flows remain unchanged.

## ACL and security

People continues to enforce its existing person edit ACL before rendering the form.

The Competitions history provider is read-only. It performs no state-changing operation and requires no CSRF token.

The history response must contain only competition-domain values needed by this tab. It must not include birth date, disability, accessibility, tax, residence, contact data or other sensitive People fields.

All Competitions database queries use Joomla query builders/bound values and `#__` table prefixes. People never reads Competitions tables directly.

## Compatibility and graceful degradation

People remains installable and usable without Competitions.

The new integration does not make Competitions a mandatory package dependency of People.

Existing People records and configuration are unchanged; no People database migration is required for this feature.

The tab is absent on a new unsaved person because no stable People UUID exists yet for a cross-product lookup.

## Testing

People tests must cover:

- existing person + compatible Competitions provider => tab available and normalized history exposed to the template;
- existing person + provider returns no rows => tab available with empty history;
- new person => provider is not called and tab is absent;
- Competitions absent/incompatible => People remains functional and tab is absent;
- provider runtime failure => People remains functional and tab is absent;
- template contains the `Competitions` tab only when `competitionsHistoryAvailable` is true;
- no direct `#__xdecarocompetitions_*` table references are introduced into People.

Competitions contract/runtime tests must cover the public service surface and returned history shape.

## UI conventions

Use the existing Joomla `uitab` pattern already used by the People person form. Use existing xdecaro/Core admin table/card primitives where already available; do not introduce a separate visual framework for this tab.

The output must remain usable in desktop, tablet, smartphone, light mode and dark mode.

## Out of scope

This feature does not edit Competition data from People, create players automatically, synchronize roster changes into People, add statistics, or change the existing People-to-Competitions player creation/autofill flow.
