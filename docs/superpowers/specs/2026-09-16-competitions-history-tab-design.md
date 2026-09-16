# People Competitions History Tab Design

## Goal

Add a read-only `Competitions` tab to the People person edit screen so an administrator can see the person's competition history without moving or duplicating competition-owned data into People.

## Ownership and dependency boundaries

People remains the owner of reusable person master data only. Competition participation, roster membership, sport role, shirt number, team, season, tournament, participation status, roster status and competition-specific photos remain owned by Competitions.

People must not query private Competitions tables directly and must not persist a copy of competition history.

The integration follows the existing xdecaro Core contract: People discovers the optional Competitions capability through `CapabilityRegistry`, then calls Competitions through its public component service surface. Core remains domain-neutral and no Core change is required.

The person is identified exclusively through the existing People UUID stored by Competitions as `person_uuid`. People numeric IDs are not used as a cross-product identifier.

If Competitions is not installed, cannot boot, does not declare the required capability, is incompatible, or does not expose the required public service, People continues to work normally and the Competitions tab is omitted.

## Competitions capability consumed by People

Competitions declares the additive capability:

```text
competitions.people_history v1
```

owned by component `com_xdecarocompetitions`.

People does not assume the capability merely because the component is installed. Its optional integration service boots Competitions, obtains its public Core integration service, registers Competitions capabilities into an in-memory `CapabilityRegistry`, and verifies:

```php
$registry->supports('com_xdecarocompetitions', 'competitions.people_history', '1')
```

Only after this capability check may People call the Competitions public person-history service.

This is runtime optional integration, not a package-level mandatory dependency.

## Public Competitions contract consumed by People

Competitions exposes a public component method:

```php
public function getPersonHistoryService(): PersonHistoryService;
```

The service exposes:

```php
public function getHistoryByPersonUuid(string $personUuid): array;
```

The returned value is an ordered list of associative arrays with this normalized shape:

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

Nullable source values remain `null`; the provider does not invent display values. `competition_name`, `season_name` and `team_name` are resolved from Competitions-owned entities at query time.

## People optional integration service

People adds a focused optional runtime service:

```php
final class CompetitionsIntegrationService
{
    public function isHistoryAvailable(): bool;
    public function getPersonHistory(string $personUuid): array;
}
```

Responsibilities:

- boot `com_xdecarocompetitions` only at runtime;
- validate the public Competitions component surface using `method_exists` rather than compile-time Competitions classes;
- build an in-memory Core `CapabilityRegistry` and ask Competitions to register its capabilities;
- require `competitions.people_history` version `1`;
- call `getPersonHistoryService()->getHistoryByPersonUuid()` only when the capability and service are available;
- convert optional-integration failures into a controlled empty/unavailable result without breaking People.

People must not import Competitions PHP namespaces and must not access Competitions tables.

## User interface

The tab is shown only for an existing saved person with a non-empty UUID and when the Competitions history capability/service is available.

The tab label is `Competitions`.

The content is read-only and contains:

1. a compact summary with the number of linked competition-history rows;
2. a `Storico partecipazioni` table ordered from newest to oldest;
3. an empty state `Nessuna partecipazione collegata` when Competitions history is available but no roster rows exist for the person.

Each history row displays:

- Competizione;
- Stagione;
- Squadra;
- Ruolo;
- N. maglia;
- Stato.

The first implementation intentionally does not add statistics, goals, cards, match-by-match events, transfer history or editable competition fields.

## People view model

The People `Person` administrator view obtains history only for an existing item with a non-empty UUID. It exposes:

```php
public bool $competitionsHistoryAvailable = false;
public array $competitionsHistory = [];
```

No competition query or component boot belongs in the template.

## Error handling

Competitions integration failures must not prevent editing a person in People.

If Competitions is absent, the capability is not declared, the service is incompatible, or history retrieval fails, the tab is omitted.

A controlled warning may be written to Joomla logging. It must not include sensitive person values, SQL text or stack traces in the UI.

People's existing save, apply, save-and-new and cancel flows remain unchanged.

## ACL and security

People continues to enforce its existing person edit ACL before rendering the form.

The Competitions history provider is read-only. It performs no state-changing operation and requires no CSRF token for in-process use.

The history response contains only competition-domain values needed by this tab. It must not include birth date, disability, accessibility, tax, residence, contact data or other sensitive People fields.

All database access remains inside Competitions. People never reads Competitions tables directly.

## Compatibility and graceful degradation

People remains installable and usable without Competitions.

The new integration does not make Competitions a mandatory package dependency of People.

Existing People records and configuration are unchanged; no People database migration is required.

The tab is absent on a new unsaved person because no stable People UUID exists yet for the cross-product lookup.

## Testing

People tests must cover:

- existing person + Competitions capability/service => tab available and normalized history exposed to the template;
- existing person + provider returns no rows => tab available with empty history;
- new person => history lookup is not performed and tab is absent;
- Competitions absent => People remains functional and tab is absent;
- Competitions installed but capability absent => tab is absent;
- capability present but service incompatible => tab is absent;
- provider runtime failure => People remains functional and tab is absent;
- template contains the `Competitions` tab only when `competitionsHistoryAvailable` is true;
- no direct `#__xdecarocompetitions_*` table references or Competitions namespace imports are introduced into People.

Competitions contract/runtime tests cover its capability registration, public service and returned history shape.

## UI conventions

Use the existing Joomla `uitab` pattern already used by the People person form. Use existing xdecaro/Core admin table/card primitives where available; do not introduce a separate visual framework for this tab.

The output must remain usable on desktop, tablet and smartphone, in light and dark mode.

## Out of scope

This feature does not edit Competition data from People, create players automatically, synchronize roster changes into People, add statistics, or change the existing People-to-Competitions player creation/autofill flow.
