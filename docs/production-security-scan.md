# Production security scan record

Latest backend assessment: 1 November 2026. This records local release-candidate checks,
not approval to deploy or an exploitability assessment. Re-run the gates for
the exact release images and lockfiles before deployment.

## Backend fix verified on 1 November 2026

The failed image gate was reproduced locally: the former Debian database image
had 88 high/critical package findings (43 distinct advisories). API and backup
tools passed. Of the database findings, 26 affected the old Go runtime inside
`gosu`; the other 62 had no vendor fix listed for the installed Debian packages.

For this fresh production database, the runtime now uses the official PostgreSQL
18 Alpine 3.24 variant with OS updates. The same upstream gosu 1.19 source commit
is built with patched Go 1.27.3, preserving supplementary-group initialization for
mounted secrets. The compiler is excluded from the runtime image. No advisory is
ignored and the high/critical threshold is unchanged.

The final API, database and backup-tool images each have **zero high/critical
findings** in the current scan. This is a point-in-time result, not a waiver of
future findings. This backend result does not certify the unused bundled Caddy
image or the VPS's Nginx/Ubuntu packages.

This database runtime is for fresh compatible volumes. It writes a runtime marker
at initialization and refuses old unmarked/incompatible data directories.
Deployment checks an existing volume read-only before replacing its container.
Never switch an existing Debian cluster to Alpine in place; use a reviewed logical
backup/restore migration. Restore drills now use the deployment's database image.

Scanner failures and package/advisory/fixed-version details are now written to the
workflow log and GitHub step summary. Complete JSON remains in `image-security`.
The isolated production rehearsal passed fresh/repeat migrations, database restart,
old-runtime refusal, role/secret access, browser authentication, encrypted backup,
restore using the same database runtime, and database-outage probes. All 21
deployment/installer tests passed. No production data or VPS was modified.

The following earlier results are retained as the 31 October baseline.

## Runtime image baseline on 31 October

The production image gate scans OS packages and application dependencies for
high and critical findings with Trivy
`0.76.1@sha256:790fd66d1697395d8693d1acaa1ea702406a20f109b6fc7bac8db623eca6c06c`.
It does not automatically ignore findings without vendor fixes.

| Image | High/critical package findings | Unique advisory IDs |
| --- | ---: | ---: |
| Java API | 0 | 0 |
| Vue web server | 0 | 0 |
| Backup tools | 0 | 0 |
| PostgreSQL | 88 | 43 |
| HTTPS edge | 7 | 5 |

Package findings can repeat an advisory across installed packages. These counts
do not establish whether a vulnerability is reachable in this deployment.
PostgreSQL findings include vendor OS packages and the bundled `gosu` binary;
edge findings include Go runtime and module dependencies. Some matches have no
vendor fix available. At that baseline, backend CI and deployment were blocked by database
findings. The bundled deployment also gates its edge image; the host-Nginx target
omits that unused image and requires host security review separately.
Upstream patches or a documented assessment are required;
this work introduces no waivers.

The API uses jsoup 1.23.2 and excludes Temurin's unused `pebble` helper binary.
Java runs directly as the container process. Runtime OS updates are included
in the images; the web server uses nginx 1.30. The API result above is from
the final image after removing the helper, superseding its earlier scan.

## Frontend and mobile packages

| Lockfile audit | Low | Moderate | High | Critical |
| --- | ---: | ---: | ---: | ---: |
| Vue staff workspace | 0 | 25 | 0 | 0 |
| Holyspace mobile | 4 | 25 | 0 | 0 |

Compatible Vite/Vitest and `follow-redirects` updates were applied. Mobile uses
Expo SDK 58.0.22 with React Native 0.88.1, aligned through Expo's installer.
Transitive Zod 4.6.7 satisfies the ESLint dependencies' declared ranges; no
override or SDK downgrade is used. Lower-severity findings still require review.
Package gates reject high/critical findings independently of functional tests.

The updated mobile app passed type checking, lint, 29 unit tests, 35 browser
workflows, all three platform JavaScript exports and all 20 Expo Doctor checks.
An ARM64 Android debug build also passed. Rebuild and reinstall the development
app to pick up native changes. A debug compilation does not validate signed
releases, iOS native compilation or physical-device behavior.

## Release limits

The local production rehearsal passed migration, private database-role,
browser authentication, forwarding/CORS, encrypted backup/isolated restore and
outage checks. It did not provision production hosting, activate external
alerts or off-site backup schedules, issue real certificates, send provider
messages or charge donors.

Privileged MFA, verified recovery/invitations, operational recovery acceptance,
privacy and load review, provider/signing setup and physical-device testing
remain release requirements. See the [readiness tracker](production-readiness.md)
and [operations runbook](java-production-runbook.md).
