# Repository Guidelines — People by xdecaro

People is a separate Joomla product in the xdecaro ecosystem.

## Domain boundary

People owns reusable person master records and basic identity/contact data. Joomla Users remain authentication identities; Membership owns memberships; Courses, Events and other products own their workflows. Avoid unnecessary sensitive data.

Core by xdecaro provides only shared infrastructure: Web Asset Manager assets, design tokens, compatibility helpers, diagnostics and public cross-product reference contracts. Do not move People-specific business rules into Core.

Dependency direction is `People -> Core`, never `Core -> People`.

Cross-product integration must use stable public APIs or `Xdecaro\Core\Integration\EntityReference` / `RelationReference`. Never read or write another product's private database tables as an integration mechanism.

## Joomla/security

Use modern Joomla APIs, server-side ACL, CSRF for state-changing operations, filtered/validated input, escaped output, bound database queries and `#__` table prefixes. Minimize personal data, never expose sensitive person data in diagnostics, and do not claim Joomla versions that have not been runtime-tested.

## Releases

Version 0.2.0 establishes the first technical baseline. Keep stable IDs `com_xdecaropeople` and `pkg_xdecaropeople`. Normal updates must preserve data and configuration. ZIPs must be directly installable in Joomla.
