# Church Management Portal

Symfony 8 modular monolith using PHP 8.4+, Doctrine DBAL 4 and Doctrine Migrations. Doctrine ORM is intentionally not used.

## Bounded contexts

- **IdentityAccess** — user accounts, roles, permissions, authentication adapter and authorization checks.
- **People** — people, membership status, families, family memberships and person-to-person relationships.
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

