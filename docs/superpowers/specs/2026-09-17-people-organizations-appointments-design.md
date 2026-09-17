# People ↔ Organizations appointment history design

Date: 2026-09-17
Status: approved
Scope: Joomla 6 only

## Goal

Show a person's Organizations appointments inside the People person edit screen without duplicating data and without allowing People to read Organizations private tables.

People exposes an **Organizzazioni** tab when Organizations is installed, authorised, and advertises `organizations.people_appointments` version `1`. The tab contains **In carica** and **Storico incarichi**.

## Release boundary

Core 2.1.0, People 1.3.1, and Organizations 1.0.26 remain frozen as already tested. This feature targets Organizations 1.0.27 and People 1.3.2.

## Architecture

Organizations remains the single owner of appointment data and exposes `getPersonAppointmentsService()->getAppointmentsByPersonUuid(string $personUuid): array` under Joomla ACL `organizations.view_appointments` or `core.admin`.

The contract returns only appointment/organization identifiers, organization name, role fields, dates, end reason, role label key, visual status, and `is_current`. It excludes notes, end notes, fiscal/address fields, and unrelated sensitive data.

People adds `OrganizationsIntegrationService` following the existing Competitions integration pattern: boot Organizations, verify the Core capability, obtain the public service, and query by person UUID. People never queries `#__xdecaroorganizations_*` directly and never persists appointment data.

If Organizations is missing, incompatible, unauthorised, or fails, People remains usable and omits the Organizzazioni tab.

## UI

**In carica** columns: Organizzazione, Carica, Inizio, Fine prevista, Stato.

**Storico incarichi** columns: Organizzazione, Carica, Mandato, Data cessazione, Motivo cessazione.

Organization names link to the Organizations edit screen. People loads Organizations language strings so role/status/end-reason labels remain consistent.

## Acceptance criteria

An authorised user opening an existing People record sees an **Organizzazioni** tab containing current and historical Organizations appointments with correct labels/dates/navigation. People remains functional without Organizations and has no direct Organizations-table dependency.