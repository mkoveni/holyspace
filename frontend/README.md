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

```bash
npm install
npm run dev       # http://localhost:3000
```

Configure the backend URL in `.env.development` / `.env.production`:

```bash
# Empty = use the Vite dev proxy below (recommended; avoids CORS since no
# CORS bundle is configured on the backend yet).
VITE_API_BASE_URL=

# Or point directly at a backend that has CORS enabled:
# VITE_API_BASE_URL=http://localhost:8000
```

During development, `vite.config.mts` proxies `/api` and `/health` to
`http://localhost:8000` so the browser only ever talks to the Vite dev server
(same-origin), sidestepping CORS entirely. For production, either serve the
built `dist/` behind the same origin as the API, enable CORS on the backend,
or configure a reverse-proxy equivalent to the dev proxy.

## Scripts

- `npm run dev` — start the Vite dev server
- `npm run build` — type-check and build for production
- `npm run preview` — preview the production build
- `npm run type-check` — run `vue-tsc`
- `npm run lint` / `npm run lint:fix` — ESLint

## Authentication

- `LoginView` posts credentials to `POST /api/login_check` and stores the returned JWT in
  `localStorage` (via the `auth` Pinia store).
- The `useApi()` composable attaches `Authorization: Bearer <token>` to every request and
  redirects to `/login` automatically on a `401` response.
- `router`'s `beforeEach` guard calls `GET /api/me` to bootstrap the session on load and
  protects every route under the default layout with `meta.requiresAuth`.

## Branding

The Vuetify theme (`src/plugins/vuetify.ts`) uses the project palette:

| Role | Hex | Usage |
| --- | --- | --- |
| Primary | `#96cdf9` | Buttons, links, highlights |
| Primary (darken) | `#2c4d94` | App bar accents, active nav state, gradients |
| Secondary | `#252426` | Navigation drawer, dark surfaces |
| Accent | `#fce9b9` | Brand mark background |

## Known backend gaps

The admin panel is built against `backend/openapi.yaml` as the source of truth. A few
IdentityAccess endpoints are write/lookup-only today (no list endpoints exist yet for
`GET /api/useraccounts`, `GET /api/roles`, or `GET /api/permissions`), and there is no endpoint
to list a family's members. The UI handles this by:

- Caching user accounts, roles, and permissions created/looked-up during the session
  (`stores/userAccounts.ts`, `stores/roles.ts`, `stores/permissions.ts`).
- Offering a "Look up by ID" control on the User Accounts page.
- Tracking family members added during the session locally (`stores/families.ts`).

Once the corresponding list endpoints are added to the backend, swap the local caches for
real `fetchAll()` calls in those stores.
