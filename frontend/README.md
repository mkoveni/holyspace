# Holyspace Admin Panel

A Vue 3 + Vuetify 3 administration panel for the Church Management Portal. It authenticates
against the Symfony backend and gives administrators a single place to manage the bounded
contexts that are currently implemented on the backend: **People**, **Families**,
**Relationships**, **Life Events**, **User Accounts**, **Roles** and **Permissions**.

## Stack

- Vue 3 (Composition API, `<script setup>`)
- Vuetify 3 (Material Design components, theming, defaults)
- Pinia (setup-style stores, used as the composable layer for all API state)
- Vue Router (nested layouts + navigation guards)
- Axios (HTTP client with JWT bearer auth interceptor)
- TypeScript + ESLint (`eslint-config-vuetify`)

## Project structure

```text
src/
├── components/common/   # AppSnackbar, ConfirmDialog, PageHeader, StatusChip
├── composables/         # useApi() — the shared Axios client + error helper
├── layouts/             # AuthLayout (login) and DefaultLayout (app shell: nav + app bar)
├── plugins/             # Vuetify instance + theme, plugin registration
├── router/              # Route table + auth guard
├── stores/               # Pinia stores: auth, ui, people, families, lifeEvents,
│                          # userAccounts, roles, permissions
├── types/                # TypeScript interfaces mirroring backend/openapi.yaml schemas
└── views/
    ├── auth/              # LoginView
    ├── people/            # People list/detail + Person & Relationship form dialogs
    ├── families/          # Families list/detail + Family & Member form dialogs
    ├── life-events/       # Life Events list/detail + form dialog
    ├── identity/          # User Accounts, Roles, Permissions
    ├── settings/          # Placeholder for future Email/SMS provider configuration
    └── errors/            # 404 view
```

## Getting started

Use Node.js 22.22.2+ (22.x) or 24.15.0+. The frontend Docker image uses Node 24.

```bash
npm install
npm run dev       # http://localhost:5173
```

Configure the backend URL in `.env.development` / `.env.production`:

```bash
# Empty = use the Vite dev proxy below.
VITE_API_BASE_URL=

# Or point directly at a backend that has CORS enabled:
# VITE_API_BASE_URL=http://localhost:8081
```

During development, `vite.config.mts` proxies `/api` and `/health` to
`http://localhost:8081` (or `VITE_API_PROXY_TARGET` when set; Docker uses
`http://webserver`) so the browser only ever talks to the Vite dev server
(same-origin), sidestepping CORS entirely. For production, either serve the
built `dist/` behind the same origin as the API, enable CORS on the backend,
or configure a reverse-proxy equivalent to the dev proxy. The backend currently allows
`http://localhost:5173` on `/api` routes, with JSON and Authorization headers.
Override `CORS_ALLOW_ORIGIN` in the backend environment for another deployment origin.

## Scripts

- `npm run dev` — start the Vite dev server
- `npm run build` — type-check and build for production
- `npm run preview` — preview the production build
- `npm run type-check` — run `vue-tsc`
- `npm run lint` / `npm run lint:fix` — ESLint
- `npm run test` — API integration and session regression tests

## Authentication

- `LoginView` posts credentials to `POST /api/login_check` and stores the returned JWT in
  `localStorage` (via the `auth` Pinia store).
- The `useApi()` composable attaches `Authorization: Bearer <token>` to every request and
  redirects to `/login` automatically on a `401` response. Logout and session changes
  clear cached domain records; responses from a previous token are discarded.
- Temporary network/server failures preserve the token and show a retry action.
- `router`'s `beforeEach` guard calls `GET /api/me` to bootstrap the session on load and
  protects every route under the default layout with `meta.requiresAuth`.

## Branding

The Vuetify theme (`src/plugins/vuetify.ts`) uses the project palette:

| Role | Hex | Usage |
| --- | --- | --- |
| Primary | `#2c4d94` | Navigation drawer, buttons, links, active state |
| Primary (lighten) | `#96cdf9` | Avatars, soft highlights |
| Secondary | `#252426` | Dark surfaces |
| Accent | `#fce9b9` | Brand mark background |

## Person profiles

People now have a full profile beyond the core record, surfaced on the Person detail page:

- **Addresses** — `GET/POST /api/people/{personId}/addresses`
- **Communication options** — `POST /api/people/{personId}/communication-options`
- **Education history** — `POST /api/people/{personId}/education`
- **Employment history** — `POST /api/people/{personId}/employment`
- **Families** — `GET /api/people/{personId}/families`

All of the above are fetched together via `GET /api/people/{personId}/profile` (addresses,
communication options, education, employment) and `GET /api/people/{personId}/families`, and
managed through `stores/people.ts`. Note that the `/profile` endpoint returns raw database rows
(snake_case keys), unlike the rest of the API; see `src/types/profile.ts` for the exact shapes.

The family directory and detail pages now use `GET /api/family-overviews` and
`GET /api/families/{id}/overview` to show every member and role, all addresses,
the selected primary contact, and upcoming active life events involving family members.
Select a member’s star to save them as the primary contact through
`PUT /api/families/{id}/primary-contact`; clear the selection with the contact’s close button.
Events include today and later, exclude completed/cancelled events, and are deduplicated
when multiple household members participate.

Families also expose their member list via `GET /api/families/{familyId}/people`
(`stores/families.ts`), and a family can have addresses added via
`POST /api/families/{familyId}/addresses` (the overview returns all stored addresses).

## Known backend gaps

The admin panel is built against `backend/openapi.yaml` as the source of truth. IdentityAccess
endpoints remain write/lookup-only (no list endpoints exist yet for `GET /api/users`,
`GET /api/roles`, or `GET /api/permissions`). The UI handles this by:

- Caching user accounts, roles, and permissions created/looked-up during the session
  (`stores/userAccounts.ts`, `stores/roles.ts`, `stores/permissions.ts`).
- Offering a "Look up by ID" control on the User Accounts page.

Once the corresponding list endpoints are added to the backend, swap the local caches for
real `fetchAll()` calls in those stores. Family member listing and person profiles (addresses,
communication, education, employment, families) are no longer gaps — the backend now exposes
real endpoints for these, and the UI consumes them directly (see "Person profiles" above).

## API alignment

Account operations use `/api/users`. Membership status, gender, and life-event choices
match the backend enums. Person PUT requests send all editable fields, and life-event
PUT requests send type, participants, and event date while preserving existing details.
The people and event lists show retryable load errors; dashboard counts distinguish
unavailable data from zero and recent records are sorted by creation date.

## Visual system

The application uses the family detail view as its visual reference: warm white
surfaces, navy typography, muted blue accents, fine borders, and rounded cards.
Shared design tokens live in `src/styles/main.scss`; Vuetify component defaults
live in `src/plugins/vuetify.ts`. PageHeader and StatStrip provide consistent
page introductions and compact summaries across screens.

The family directory is a sortable, paginated table (25 households per page by
default) with member counts, primary contacts, addresses, and the next upcoming
event. Open a household for all members and roles, every address, contact
selection, and the complete upcoming-event list.

## Operational contexts

The portal includes Volunteering, Children's Ministry, Giving, Attendance, Ministries/small groups, and Communications. The backend enforces the corresponding `<context>.manage` permission. Giving forms accept ordinary currency amounts and submit integer minor units. Communications queues campaigns; provider configuration and worker setup are documented in [the implementation guide](../docs/bounded-context-roadmap.md).

### Shared person search

`PeopleSearch` is registered globally and can be used in any form. It searches the backend
by name, email address, or phone after at least two characters, with a 300 ms debounce.
It displays contact details to distinguish people and supports multi-word matching.

```vue
<PeopleSearch v-model="personId" label="Person" :rules="[required]" />
<PeopleSearch v-model="recipientIds" label="Audience" multiple />
<PeopleSearch v-model="relatedPersonId" :exclude-ids="[personId]" label="Related person" />
```

The model contains a person ID (or `null` when cleared), or an array of IDs when `multiple`
is enabled. Standard Vuetify form attributes such as `clearable`, `rules`, and `density`
are forwarded; `disabled` preserves read-only selections. Failed directory loads show a
retry action. Suggestions use `GET /api/people/search?q=...&limit=20`; `hasMore` prompts
users to narrow their query. Saved selections are hydrated by ID in batches of at most 50.
Outdated requests are cancelled and ignored. The picker does not fetch the full directory.
Use this component for person selection instead of building a separate person dropdown.


## Java People workspace

The Java directory has its own deployment configuration. Run the Java backend
at `http://localhost:8082`, then start the frontend:

```sh
npm install
npm run dev:java -- --host 127.0.0.1
```

Open **http://localhost:5175** and sign in with a Java account. The existing
administrator has the People permissions. Java sessions use a separate storage
key; PHP tokens are not accepted. The default `npm run dev` continues to use the
PHP configuration, with its existing screens and workflows.

The Java workspace includes:

- A redesigned People directory with server-side name, membership, age-date,
  archival filters, cancellation and pagination.
- Person profiles with optional catalogue titles, the four membership types,
  independent life status and age classification. No birth date means unknown age.
- Canonical contact methods, addresses and preserved history. Preferred contacts
  are set per method; family members may share contact details.
- Household directories, multiple memberships, one primary household, membership
  history and primary contacts selected by current membership identity.
- Directed relationships from the current person's perspective and inverse read
  views. Relationships never confer child pickup rights.
- Read/manage controls, explicit end/archive confirmations, inline validation,
  loading/retry states and revision conflicts that require review before retrying.

Routes/navigation in Java mode expose only supported Java capabilities. Legacy
education/employment, media, life events, ministry and member-portal operations
remain in the PHP workspace. Existing PHP data is not copied or modified.

The development configuration uses Vite's same-origin relay to the Java API.
Only an Origin matching the frontend host is rewritten to the upstream origin;
foreign origins are preserved for API rejection. `VITE_API_PROXY_TARGET` can
point a Java dev server at an isolated API. Production `npm run build:java`
produces assets that expect `/api` to be reverse-proxied to Java on the same
origin; set `VITE_API_BASE_URL` explicitly for a cross-origin deployment and
configure that origin in the API's CORS settings.

### Verification

```sh
npm run type-check
npm test
npm run build:java
npm run test:directory
```

Directory browser tests require a running Java-mode frontend. Set
`DIRECTORY_E2E_BASE_URL` to its address (the browser test configuration defaults
to the isolated test port, 5176). Presentation tests mock the API and verify
read-only desktop/mobile layouts and expected requests.

Real API scenarios run only when `DIRECTORY_E2E_PASSWORD_FILE` points to a file
containing an isolated test administrator's password; optionally set
`DIRECTORY_E2E_USERNAME` (default `directory-admin`). Run these against a
dedicated Java API/database and Java-mode dev server, not shared church data.
They create test people/households and verify contacts, addresses, directed
relationships, primary contacts, stale-edit recovery, archival and history.
The tests save desktop/mobile screenshots under ignored `test-results/`.

## Java Children Ministry

Run `npm run dev:java` and open `/children`. The Java deployment includes:

- Programme cards, classes and versioned family consent policies.
- Child ministry profiles with enrolment, class placement, guardian verification,
  consent, collector permissions and restricted care information.
- Session planning, room capacity, staff assignments and presence.
- A station workspace for arrivals, printable one-time QR pickup labels,
  identity-verified collection, supervised exceptions and room transfers.
- Spaces, rooms, team clearance and expired care-record cleanup.

The module reuses the People directory heading, surfaces, typography, badges,
forms and person picker. Its typed client lives in `src/children/api.ts` and
uses the Java contracts. PHP Children Ministry screens remain on the PHP deployment.
Programme/session context carries through related forms. Guardian authority and
pickup rights remain explicit; family relationships do not confer them.

Navigation and actions follow the account's permissions. Station-only operators
use scoped discovery and named attendance endpoints without People directory
access. Administrative person/account searches require their corresponding
People/Identity permissions. Care is fetched only on explicit access, and pickup
codes remain in the active label dialog rather than application stores/storage.

Run `npm test`, `npm run build:java`, and `npm run test:children`. The browser
suite checks desktop/mobile layouts, read-only access, labels and collection using
isolated contracts. Real API scenarios additionally run when
`CHILDREN_E2E_PASSWORD_FILE` points to a password file for a `children-admin`
account on an isolated Java database; set `CHILDREN_E2E_BASE_URL` to its frontend.
Those scenarios create test records and should target a disposable environment.
They disable tracing to keep test credentials and pickup codes out of traces.

## Java production release

The Java production frontend is built with `npm run build:java` using
`docker/java-production/web.Dockerfile`, with same-origin `/api` requests.
See [the Java production runbook](../docs/java-production-runbook.md) and
[launch tracker](../docs/production-readiness.md); the PHP production image uses
a different build and must not be used for the Java staff workspace.
