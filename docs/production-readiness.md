# Production readiness tracker

Scope: Java API, PostgreSQL, Vue staff workspace and Holyspace member application.
A completed development feature or test suite is not production approval. PHP
migration and legacy feature parity are outside this release.

## Foundation implemented

- Separate Java production Compose topology with HTTPS edge and built Java-mode Vue.
- Private database/API, non-root API/web, read-only app filesystems and bounded logs.
- Separate administrator, migration and runtime DB roles; mounted file secrets.
- One-shot Flyway entry point; operator-only initial administrator setup.
- Production documentation disabled; public generic readiness/liveness boundaries.
- Deployment preflight, existing-volume backup requirement and controlled migration.
- Encrypted PostgreSQL backups, isolated restore drill and runtime privilege checks.
- Aggregate service/email/push/payment checks and isolated release rehearsal.
- Release workflow for Java, Vue Java mode and mobile; explicit dependency gates.
- Ubuntu host-Nginx backend target with a loopback-only API and separate frontend origin.
- Opt-in GitHub-to-VPS backend deployment with tested image archives, pinned SSH,
  serialized activation, required backups and public HTTPS readiness checks.

See [the Java runbook](java-production-runbook.md) for commands and limits. These
files do not provision an external production environment or install host schedules.

## Required before a staff pilot

- [ ] Assign production host/domain ownership; configure DNS, HTTPS and firewall.
- [ ] Provision protected, recoverable secrets and first administrator credentials.
- [ ] Run CI and production rehearsal for the exact release; retain versioned images.
- [ ] Install off-site backup transfer/retention and alert on failed or missing backups.
- [ ] Complete a restored-application exercise and agree recovery point/time targets.
- [ ] Connect uptime, host capacity and queue checks to external alerts and a responder.
- [ ] Review staff privileges and verified identity/guardian/donor links.
- [ ] Add privileged-account MFA and a tested recovery procedure.
- [ ] Review retention, privacy notices, access reviews and incident/support procedures.
- [ ] Test concurrent bookings/check-ins, larger datasets and failure/retry behavior.
- [ ] Complete supervised staff acceptance for giving and child collection.

## Required before broad member release

- [ ] Verified invitations and secure account recovery; office-assisted recovery is current.
- [ ] Decide and implement the mobile session/renewal policy. Default: eight hours,
      no indefinite refresh token, explicit push enablement again after expiry.
- [ ] Configure Expo/EAS, Firebase FCM, APNs where applicable and platform signing.
- [ ] Test real background/cold-start push, denied permissions, sign-out/account switching.
- [ ] Test signed release builds on representative Android/iOS physical devices.
- [ ] Configure production email and complete a controlled live payment/refund/reconciliation test.
- [ ] Resolve or formally assess unresolved dependency advisories; CI does not waive them.
- [ ] Complete store assets, privacy declarations, support contact and release distribution.

## Verification on 31 October 2026

- Java: all 1,038 tests passed on jsoup 1.23.2 across the interrupted and resumed
  verification; the remaining Maven verification and packaging completed successfully.
- Vue: 97 unit tests, type checking and the Java-mode production build passed.
- Mobile: 29 unit tests, type checking, lint, all 35 browser workflows and Android,
  iOS and web JavaScript exports passed on the supported SDK 58 patch. Expo Doctor
  passed all 20 checks. The ARM64 Android debug APK compiled successfully after
  native regeneration; signing and physical-device acceptance remain outstanding.
- Production rehearsal: fresh/repeat migrations, restricted runtime credentials,
  real browser sign-in/person creation/sign-out, CORS/forwarding protections,
  encrypted backup/isolated restore and outage probes passed.
- API, frontend and backup-tool images have no high/critical scanner findings.
- Package audits have no high/critical findings after the compatible frontend
  follow-redirects patch, Expo SDK 58.0.22/React Native 0.88.1 alignment and the
  permitted transitive Zod 4.6.7 update. Lower-severity findings remain for review.
- Database and bundled HTTPS proxy images retain vendor/Go findings, including entries
  without vendor fixes. These are raw scanner matches, not a completed
  exploitability assessment. Backend CI and deployment block on the database;
  bundled deployments also block on the proxy. Host Nginx needs separate review.

See [the security scan record](production-security-scan.md) for scope and counts.
No production host, off-site schedule or external alert destination was activated.

## Sequencing

Finish and rehearse the production foundation, then implement privileged MFA and
verified recovery/invitations. Follow with mobile session design, provider/signing
activation and physical-device acceptance. Run a limited staff/member pilot before
opening broad access. Central monitoring integration, real restore acceptance,
load testing and operational sign-off remain required even when CI is green.
