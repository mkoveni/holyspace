# Porting Communities to Symfony

Status: **not started**. This plan tracks the complete port of the Java
`communities` bounded context. Java remains the behavioral and API source of
truth; the existing V18 migration already defines its initial schema.

## Scope

Port ministry and group definitions, memberships, leadership, directory and
member views, meeting scheduling and calendar integration, frozen rosters and
attendance, follow-ups, history, reviewed notices and deliveries, and all
published contracts. Preserve the Java HTTP routes and request/response shapes.
Do not edit the applied V18 migration; add a new migration only if comparison
shows schema drift.

## Sessions

- [ ] C1: Domain rules and records; compare all Java domain types and tests.
- [ ] C2: Community management and membership commands, audit/history, locking,
  optimistic versions, repositories and persistence.
- [ ] C3: Directory, contacts, leadership, history, follow-up and member queries;
  published `CommunityParticipation` and member reader contracts.
- [ ] C4: Group meeting scheduling, calendar/event movement contracts, roster
  preparation, guests, attendance and member meeting queries.
- [ ] C5: Notice review/approval/delivery, Communications eligibility and
  template policy integration.
- [ ] C6: HTTP controllers, routes, API/error behavior and complete API/security
  coverage; wire services and close the port with `make check`.

## Status board

No sessions complete. Next: **C1**.

## Decision log

- Communities is its own Symfony bounded context at `src/Communities/` (not a
  Children Ministry module).
- Preserve the existing V18 schema and its compatibility with Java. Confirm
  cross-context contracts against Symfony Events, People, IdentityAccess and
  Communications before implementing their adapters.

## Next session notes

Read the Communities section of `backend-java/CONTEXT-MAP.md`, then inspect the
Java domain classes, `CommunityRules`, and their tests. The Java API endpoints
are exposed by the five Communities controllers; keep route compatibility.
