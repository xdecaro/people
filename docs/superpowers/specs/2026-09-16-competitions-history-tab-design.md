# People Competitions History Tab Design

## Goal

Add a read-only `Competitions` tab to the People person edit screen so an administrator can see the person's competition history without moving or duplicating competition-owned data into People.

## Ownership and dependency boundaries

People remains the owner of reusable person master data only. Competition participation, roster membership, sport role, shirt number, team, season, tournament, participation status, roster status and competition-specific photos remain owned by Competitions.

People must not query private Competitions tables directly, persist a copy of competition history, or boot/depend directly on `com_competitions`.

Cross-product discovery follows the existing People repository rule: Core capability registration and entity references are the boundary between products. People consumes a generic related-activity capability for its own person entity; Competitions registers a provider for that capability.

The person is identified exclusively through the existing People UUID stored by Competitions as `person_uuid`. People numeric IDs are not used as a cross-product identifier.

If Core does not expose the required capability-provider mechanism yet, implementation must first add the minimal domain-neutral extension point in Core. People must not invent a Competitions-specific fallback API.

If no Competitions provider is installed or registered, People continues to work normally and the Competitions tab is omitted.

## Generic related-activity contract

The preferred integration is a Core-owned, domain-neutral capability for related panels/activities attached to an entity reference.

Conceptually, People asks Core for providers registered for:

```text
entity_type: people.person
entity_id: <person UUID>
capability: related_activity
```

A provider returns normalized panel metadata and rows, for example:

```php
[
    'key' => 'competitions',
    'label' => 'Competitions',
    'title' => 'Storico partecipazioni',
    'source' => 'com_competitions',
    'rows' => [/* provider-owned normalized rows */],
]
```

People does not know Competitions classes, tables or service names. It knows only the Core public capability contract.

The exact Core API name must reuse an existing stable `CapabilityRegistry`/`EntityReference` surface if one already exists. If no suitable public API exists, the Core change must remain generic and reusable by Organizations, Courses, Memberships and other future products; it must not contain Competitions-specific names or fields.

## User interface

The tab is shown only for an existing saved person with a non-empty UUID and when a registered related-activity provider with key `competitions` returns a panel.

The tab label is `Competitions`.

The content is read-only and contains:

1. a compact summary with the number of linked competition-history rows;
2. a `Storico partecipazioni` table ordered from newest to oldest;
3. an empty state `Nessuna partecipazione collegata` when the Competitions provider is available but has no roster history for the person.

Each history row displays:

- Competizione;
- Stagione;
- Squadra;
- Ruolo;
- N. maglia;
- Stato.

The first implementation intentionally does not add statistics, goals, cards, match-by-match events, transfer history or editable competition fields.

## People view model

The People `Person` administrator view resolves related-activity panels only for an existing item with a UUID. It exposes presentation data to the template without performing product-specific queries.

A suitable presentation property is:

```php
public array $relatedActivityPanels = [];
```

The Competitions tab is derived from the `competitions` panel in that normalized collection.

No competition query belongs in the template.

## Error handling

Failure of an optional related-activity provider must not prevent editing a person in People.

If Core, the capability registry, or a provider is unavailable, incompatible or throws while resolving optional activity, People continues to render its normal form. A failed optional provider is omitted.

Diagnostics may identify the provider key/source but must not include sensitive People values and must not expose internal SQL or stack traces in the UI.

People's existing save, apply, save-and-new and cancel flows remain unchanged.

## ACL and security

People continues to enforce its existing person edit ACL before rendering the form.

Related-activity providers used here are read-only. They perform no state-changing operation and require no CSRF token for in-process resolution.

The Competitions provider response must contain only competition-domain values needed by this tab. It must not include birth date, disability, accessibility, tax, residence, contact data or other sensitive People fields.

People never reads Competitions tables directly.

## Compatibility and graceful degradation

People remains installable and usable without Competitions.

The new feature does not make Competitions a mandatory package dependency of People.

Existing People records and configuration are unchanged; no People database migration is required for this feature.

The tab is absent on a new unsaved person because no stable People UUID exists yet for a cross-product lookup.

## Testing

People tests must cover:

- existing person + registered Competitions related-activity provider => `Competitions` panel exposed to the template;
- existing person + provider returns no rows => tab available with empty history;
- new person => related-activity lookup is not performed and the tab is absent;
- Competitions/provider absent => People remains functional and tab is absent;
- provider runtime failure => People remains functional and the failed optional panel is absent;
- template contains the `Competitions` tab only when the normalized panel exists;
- no direct `#__xdecarocompetitions_*` table references or Competitions classes are introduced into People.

Core contract tests must cover provider registration/resolution if the capability extension point is new.

Competitions contract/runtime tests must cover its provider registration and returned history shape.

## UI conventions

Use the existing Joomla `uitab` pattern already used by the People person form. Use existing xdecaro/Core admin table/card primitives where already available; do not introduce a separate visual framework for this tab.

The output must remain usable in desktop, tablet, smartphone, light mode and dark mode.

## Out of scope

This feature does not edit Competition data from People, create players automatically, synchronize roster changes into People, add statistics, or change the existing People-to-Competitions player creation/autofill flow.
