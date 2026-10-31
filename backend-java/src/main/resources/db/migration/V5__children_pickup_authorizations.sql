CREATE TABLE children_pickup_authorizations (
    id UUID PRIMARY KEY, programme_id UUID NOT NULL REFERENCES children_programmes(id), child_id UUID NOT NULL,
    collector_id UUID NOT NULL REFERENCES people_persons(id), authority_id UUID NOT NULL,
    valid_from TIMESTAMPTZ NOT NULL, valid_until TIMESTAMPTZ NOT NULL CHECK (valid_until > valid_from),
    evidence_reference VARCHAR(255) NOT NULL, recorded_by UUID NOT NULL,
    revoked_at TIMESTAMPTZ, revocation_reason VARCHAR(500), version BIGINT NOT NULL DEFAULT 0,
    CHECK (child_id <> collector_id),
    FOREIGN KEY (child_id, authority_id) REFERENCES children_guardian_authorities(child_id, id)
);
CREATE INDEX children_pickup_lookup_idx ON children_pickup_authorizations(child_id, programme_id, collector_id);
-- [jooq ignore start]
ALTER TABLE children_pickup_authorizations ADD CONSTRAINT children_pickup_no_overlap
    EXCLUDE USING gist (child_id WITH =, collector_id WITH =, programme_id WITH =, tstzrange(valid_from, valid_until, '[)') WITH &&) WHERE (revoked_at IS NULL);
-- [jooq ignore stop]
