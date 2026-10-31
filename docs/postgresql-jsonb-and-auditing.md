# PostgreSQL, JSONB, and auditing: next phase

Status: implemented as an opt-in PostgreSQL migration path, all-context database audit triggers, field-history API/UI, shared locks, verified import, and backup/restore tooling. See [PostgreSQL operations](postgresql-operations.md) for activation and the rehearsal procedure. Existing MySQL installations keep their migration path and legacy journal; installing the implementation does not cut over a live database.

## Existing architecture and changes

The application uses bounded contexts, application services, repository interfaces, DBAL adapters, data mappers, optimistic record versions, an integration outbox, and a transaction boundary. Keep those patterns. Use batch read ports for list enrichment rather than loading one related aggregate per result. Do not introduce a generic service locator or a new inheritance hierarchy to enforce SOLID.

This pass introduces batch reads on `PersonDirectory` and `OccurrenceDirectory`, with validation, deduplication, keyed results, and chunks of 500 IDs. Empty batches perform no query. Application services continue depending on interfaces; SQL remains in infrastructure. Member booking summaries use a joined projection. Volunteer eligibility still delegates screening, availability, and qualification rules to the domain service.

Removed repeated reads in member bookings, ticket offers, event roster names, volunteer schedules and manager labels, children attendance and station rosters, giving donor labels, payment list details, group meeting rosters, report occurrence lookup, rule segment lookup, follow-up task names and assignee permissions, and service-plan assignment checks. Follow-up account queries now sit behind an application port implemented by a DBAL adapter. Collection snapshots live only for the operation; no shared cache can retain stale eligibility or permission decisions.

Some workflow repositories still load complete collections through `all(kind)` and filter in PHP. These have bounded query counts in the refactored reads, but unbounded memory and row counts. Replace them incrementally with paginated context-specific projections and indexed filtering. Mutation workflows that intentionally reload state after writes, recheck permissions, or process each aggregate transactionally require separate concurrency analysis; do not remove those reads mechanically. In particular, communications delivery, payment maintenance, follow-up automation, need workload-specific profiling. No claim of eliminating every runtime N+1 is made by static inspection alone.

## Storage decisions

Use PostgreSQL as the target database, with a rehearsal environment before production cutover. Keep identity, people, families, relationships, events, occurrences, memberships, attendance, payment amounts, queue ordering, and foreign keys relational. Store money as integer minor units. Use native UUIDs, dates, and `timestamptz` where appropriate; preserve a separate event timezone for local scheduling.

Use JSONB selectively for configurable form definitions and answers, versioned context document payloads, integration payloads, provider metadata after redaction, and audit field changes. Existing `*_records.payload` documents are migration candidates, not a reason to put all relationships inside JSON. Promote frequently filtered identifiers, status, dates, and ordering fields into typed columns with constraints and indexes. Keep a payload schema version separate from the optimistic aggregate version.

JSONB supports indexing and containment queries. Add expression or GIN indexes only for demonstrated queries; regular indexes should serve typed filters. Check malformed JSON, duplicate keys, Unicode, numeric precision, and missing versus explicit null semantics before converting existing JSON/text. See [PostgreSQL JSON types](https://www.postgresql.org/docs/current/datatype-json.html).

## Migration sequence

1. Add `pdo_pgsql` to development, test, and production images. Add a disposable PostgreSQL integration environment and run repository, migration, HTTP, locking, and worker tests there. Keep SQLite unit coverage, but do not treat it as proof of PostgreSQL compatibility.
2. Inventory every table, constraint, index, JSON document kind, migration, and SQL statement. Existing migrations contain MySQL-specific `ENGINE=InnoDB`, inline indexes, `CHAR(36)`, and `AUTO_INCREMENT`; the operations command uses `JSON_EXTRACT`. Build a PostgreSQL baseline and explicit data-transfer migrations rather than editing deployed migration history.
3. Convert timestamps, booleans, UUIDs, JSONB, identity sequences, collation-sensitive searches, and uniqueness rules explicitly. Adapt all mappers to the DBAL values actually returned. Preserve FIFO queue positions and outbox sequence ordering independently of UUID sort order.
4. Replace local-file workflow locking with a shared Symfony Lock store suitable for every web and worker instance. Test capacity reservations, duplicate webhooks, outbox delivery, optimistic conflicts, retries, and transaction isolation across two independent processes.
5. Rehearse export/import and compare row counts, foreign keys, per-context totals, financial balances, versions, and payload checksums using normalized JSON. Establish restore points and a tested rollback process before cutover. Avoid an improvised dual-write period.
6. Put writes and workers into maintenance for a controlled final transfer, reset sequences, validate again, then switch connections and resume workers. Monitor query latency, lock contention, outbox lag, and audit completeness.

## Auditing across every context

Current `workflow_audit` records actor, context, kind, record ID, action, version, and timestamp. It has no field changes and is not a complete record of identity, people, family, relationship, life-event, security, or every event write. Historical before-values cannot be reconstructed reliably from those entries; label existing records as legacy metadata-only history.

The PostgreSQL implementation provides an append-only audit envelope with:

- Audit ID, transaction/correlation ID, request ID, context, aggregate type/ID, optional child entity type/ID, and operation (`created`, `updated`, `deleted`, or a named domain action).
- Aggregate version before/after, UTC occurrence time, actor type and ID, source (`http`, `worker`, `import`, `system`), and an explicit reason when supplied.
- JSONB `changes`, keyed by stable field path. Each change carries `from`, `to`, and presence flags so missing keys and null values differ. Arrays need an explicit policy: use whole-array values initially or stable item IDs, never positional paths that change meaning after reordering.
- Audit schema version and redaction policy version. Reference immutable IDs rather than depending on a surviving actor row.

Example change representation:

```json
{
  "/email": {"fromPresent": true, "from": "old@example.test", "toPresent": true, "to": "new@example.test"},
  "/preferences/sms": {"fromPresent": false, "from": null, "toPresent": true, "to": false}
}
```

The selected implementation uses PostgreSQL triggers as the single authoritative writer and an application `AuditHistory` read port. Dedicated database functions compare and redact fields independently of HTTP. Triggers capture actual OLD/NEW rows in the business transaction, so failed audit insertion rolls back the write. Deleted rows and associations retain their history. Stable association synchronization avoids delete-and-reinsert noise. No duplicate application `AuditWriter` is introduced alongside the trigger writer.

PostgreSQL row triggers enforce coverage for writes inside and outside repositories. Triggers can inspect OLD/NEW rows; they require transaction-local actor and correlation metadata, redaction rules, nested JSON comparison, and coverage of all business tables. Separate low-level row history from domain audit events to prevent double-counting. See [PostgreSQL trigger functions](https://www.postgresql.org/docs/current/plpgsql-trigger.html). Never let pooled connections leak actor metadata between requests.

Cover IdentityAccess, People, LifeEvents, Events/registration, Attendance, ChildrenMinistry, Ministries, Volunteering, ServicePlanning, Giving, Payments, Communications, Reporting/follow-ups, Forms, Media, and MemberPortal-originated mutations. A portal write should identify its source and audit the owning aggregate, rather than create an unrelated second history. Classify operational outbox and delivery tables separately from business audit history.

Never retain password hashes, reset tokens, MFA secrets/recovery codes, bearer tokens, payment credentials, raw gateway secrets, or unrestricted sensitive support notes in generic audit payloads. Record that a protected field changed with a redacted marker. Apply context-level audit read permissions, retention policies, restricted export access, and a database role that cannot update/delete audit records. Do not expose sensitive field history through an unrestricted generic audit endpoint.

## Acceptance criteria

- Every context has a write-path inventory and tests for create, update, deletion, association changes, imports, and worker writes.
- Tests assert exact typed before/after values, nested JSON changes, missing/null differences, actor attribution, redaction, transaction rollback, and optimistic conflict behavior.
- Two concurrent updates cannot both claim the same previous version. No audit entry survives a rolled-back write, and no committed write is missing its required entry.
- The history reader has deterministic cursor pagination and indexes on context/record/time and actor/time. Access controls apply to the old and new sensitive values as well as the current entity.
- PostgreSQL migrations run from empty and imported databases; load tests exercise large rosters/reports, validate bounded query counts, and verify index use with execution plans.
