# Church Management Portal

Single-church feature development: see the [feature roadmap](docs/feature-parity-roadmap.md).

Production deployment and staff pilot: see the [operations runbook](docs/production-runbook.md) and [pilot checklist](docs/staff-pilot-checklist.md).

New to the portal? See the [beginner's user guide](docs/user-guide.md) for everyday tasks, setup steps, and help with common problems.

Symfony 8 modular monolith using PHP 8.4+, Doctrine DBAL 4 and Doctrine Migrations. Doctrine ORM is intentionally not used. The default Docker Compose development stack uses PostgreSQL 18 and pgAdmin; see [database setup](docs/postgresql-operations.md#development-stack).

## Java API foundation

A separate Spring Boot, jOOQ, PostgreSQL and Flyway application is available in
[`backend-java`](backend-java/README.md). It uses its own development database and
implements authentication and identity management; the remaining business contexts
continue to use the Symfony backend.

## Admin panel (frontend)

A Vue 3 + Vuetify 3 administration panel lives in `./frontend`. It provides authenticated
management of the established bounded contexts (People, Families, Relationships, Life Events,
User Accounts, Roles and Permissions) against the backend's HTTP API described in
`backend/openapi.yaml`. See `frontend/README.md` for setup and architecture notes.
All backend contexts follow the [clean architecture and CQRS standard](docs/clean-architecture-cqrs.md), enforced by architecture tests in CI.

## Bounded contexts

- **IdentityAccess** — user accounts, roles, permissions, authentication adapter and authorization checks.
- **People** — people, membership status, families, family memberships and person-to-person relationships.
- **Events** — church gatherings, one-off/weekly schedules, timezone-aware occurrences, rescheduling, cancellation and restoration.
- **LifeEvents** — unified life-event history including marriages and engagements with typed details and participants.

## Architecture

Each context follows the same separation:

```text
Domain
Application
Infrastructure
Presentation
```

Repositories are domain interfaces implemented by explicit DBAL adapters. Database rows are converted through explicit mappers/hydrators. Symfony Messenger commands use explicit `AsMessageHandler` handlers, while HTTP controllers form the presentation adapter.

There are no Doctrine ORM entities and no shared Clock abstraction. Application/domain mutation timestamps use `DateTimeImmutable`; persisted audit timestamps are reconstituted from the database.

## People tables

- `people_persons`
- `people_families`
- `people_family_memberships`
- `people_relationships`

## IdentityAccess tables

- `user_accounts`
- `roles`
- `permissions`
- `user_roles`
- `role_permissions`

## LifeEvents tables

- `life_events`
- `life_event_participants`

## HTTP API

### People
This the bounded context that is responsible to managing people, families and, relationship within families in the church.

- `POST /api/people`
- `GET /api/people`
- `GET /api/people/{id}`
- `PUT /api/people/{id}`
- `POST /api/people/{id}/status`
- `POST /api/families`
- `GET /api/families`
- `GET /api/families/{id}`
- `POST /api/families/{familyId}/members`
- `GET /api/people/{personId}/relationships`
- `POST /api/people/{personId}/relationships`
- `POST /api/relationships/{id}/end`

### IdentityAccess

- `POST /api/users`
- `GET /api/users/{id}`
- `GET /api/users/{id}/permissions`
- `PUT /api/users/{id}/password`
- `POST /api/users/{id}/disable`
- `POST /api/users/{id}/enable`
- `POST /api/users/{id}/roles/{roleId}`
- `DELETE /api/users/{id}/roles/{roleId}`
- `POST /api/roles`
- `POST /api/roles/{roleId}/permissions/{permissionId}`
- `DELETE /api/roles/{roleId}/permissions/{permissionId}`
- `POST /api/permissions`

### LifeEvents

- `POST /api/life-events`
- `GET /api/life-events`
- `GET /api/life-events/{id}`
- `PUT /api/life-events/{id}`
- `DELETE /api/life-events/{id}`
- `POST /api/life-events/{id}/cancel`
- `POST /api/life-events/{id}/restore`
- `GET /api/people/{personId}/life-events`


## Backend

src/
│
├── Shared/
│   └── Domain/
│       ├── Event/
│       ├── Exception/
│       ├── Service/
│       └── ValueObject/
│           ├── Identifier.php
│           └── AuditTimestamps.php
│
├── IdentityAccess/
│
├── People/
│
└── LifeEvents/
    │
    ├── Domain/
    │   ├── Model/
    │   │   ├── LifeEvent.php
    │   │   └── LifeEventParticipant.php
    │   │
    │   ├── Details/
    │   │   ├── LifeEventDetails.php
    │   │   ├── MarriageDetails.php
    │   │   ├── EngagementDetails.php
    │   │   └── GenericLifeEventDetails.php
    │   │
    │   ├── Enum/
    │   │   ├── LifeEventType.php
    │   │   ├── LifeEventStatus.php
    │   │   └── ParticipantRole.php
    │   │
    │   ├── ValueObject/
    │   │   ├── LifeEventId.php
    │   │   └── PersonId.php
    │   │
    │   ├── Repository/
    │   │   └── LifeEventRepository.php
    │   │
    │   └── Event/
    │       ├── LifeEventRecorded.php
    │       ├── LifeEventUpdated.php
    │       └── LifeEventCancelled.php
    │
    ├── Application/
    │   ├── Command/
    │   ├── Query/
    │   ├── DTO/
    │   └── Port/
    │       └── PersonExistenceChecker.php
    │
    ├── Infrastructure/
    │   ├── Persistence/
    │   │   └── DBAL/
    │   │       ├── DbalLifeEventRepository.php
    │   │       ├── LifeEventMapper.php
    │   │       └── LifeEventParticipantMapper.php
    │   │
    │   └── People/
    │       └── PeoplePersonExistenceChecker.php
    │
    └── Presentation/
        └── Http/
            └── Controller/



## Events

Apply migrations with `docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction` (or the equivalent backend command). The Events screen is available at `/events`.

- `GET /api/events`
- `POST /api/events`
- `GET /api/events/{id}`
- `PUT /api/events/{id}` — title, type, description
- `PUT /api/events/{id}/occurrences/{occurrenceId}` — local start/end, location, scheduled/cancelled status

Creation accepts local `startsAt`/`endsAt` values (`YYYY-MM-DDTHH:mm`), an IANA `timezone`, `recurrence` (`once` or `weekly`), and `occurrenceCount` (1–52; exactly 1 for one-off events). Returned occurrences have stable IDs and timestamps with local offsets. Editing details preserves the schedule; changing one occurrence preserves the other occurrences.

Volunteering, Children's Ministry, Giving, Attendance, Ministries/small groups, and Communications now have portal workflows and permission-protected APIs. Apply the migrations before opening `/volunteering`, `/children`, `/giving`, `/attendance`, `/ministries`, or `/communications`.

The [bounded-context implementation guide](docs/bounded-context-roadmap.md) describes workflows, permissions, outbox processing, provider configuration, and remaining extensions. Communications requires configured email/SMS providers and its delivery worker; tests send no real messages. Giving currently records manual gifts and acknowledgement receipts.

Children’s station setup and operation: [paired labels and verified collection](docs/children-check-in-stations.md).

Member setup, authenticator MFA and recovery: [member portal guide](docs/member-portal.md).

Operational reports, saved segments and task automation: [reporting and follow-up guide](docs/reporting-segments-and-followups.md).
