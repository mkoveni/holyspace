CREATE TABLE children_programmes (
    id UUID PRIMARY KEY, name VARCHAR(120) NOT NULL, current_policy_id UUID,
    min_staff INTEGER NOT NULL CHECK (min_staff >= 1), children_per_staff INTEGER NOT NULL CHECK (children_per_staff >= 1),
    version BIGINT NOT NULL DEFAULT 0, created_at TIMESTAMPTZ NOT NULL
);
CREATE TABLE children_consent_policies (
    id UUID PRIMARY KEY, programme_id UUID NOT NULL REFERENCES children_programmes(id),
    label VARCHAR(40) NOT NULL, document_reference VARCHAR(255) NOT NULL,
    content_hash CHAR(64) NOT NULL, created_at TIMESTAMPTZ NOT NULL,
    UNIQUE (programme_id, label), UNIQUE (programme_id, id)
);
ALTER TABLE children_programmes ADD CONSTRAINT children_current_policy_fk
    FOREIGN KEY (id, current_policy_id) REFERENCES children_consent_policies(programme_id, id);
CREATE TABLE children_classes (
    id UUID PRIMARY KEY, programme_id UUID NOT NULL REFERENCES children_programmes(id), name VARCHAR(120) NOT NULL,
    min_age_months INTEGER NOT NULL CHECK (min_age_months >= 0), max_age_months INTEGER NOT NULL CHECK (max_age_months <= 216 AND max_age_months > min_age_months),
    UNIQUE (programme_id, id)
);
CREATE TABLE children_child_guards (child_id UUID PRIMARY KEY REFERENCES people_persons(id));
CREATE TABLE children_enrolments (
    id UUID PRIMARY KEY, programme_id UUID NOT NULL REFERENCES children_programmes(id), child_id UUID NOT NULL REFERENCES people_persons(id),
    valid_from DATE NOT NULL, valid_until DATE NOT NULL CHECK (valid_until > valid_from),
    ended_at TIMESTAMPTZ, version BIGINT NOT NULL DEFAULT 0, created_at TIMESTAMPTZ NOT NULL,
    UNIQUE (programme_id, child_id, id)
);
CREATE INDEX children_enrolment_child_idx ON children_enrolments(child_id, programme_id);
CREATE TABLE children_class_assignments (
    id UUID PRIMARY KEY, programme_id UUID NOT NULL, class_id UUID NOT NULL, child_id UUID NOT NULL, enrolment_id UUID NOT NULL,
    valid_from DATE NOT NULL, valid_until DATE NOT NULL CHECK (valid_until > valid_from), ended_at TIMESTAMPTZ,
    version BIGINT NOT NULL DEFAULT 0,
    FOREIGN KEY (programme_id, class_id) REFERENCES children_classes(programme_id, id),
    FOREIGN KEY (programme_id, child_id, enrolment_id) REFERENCES children_enrolments(programme_id, child_id, id)
);
CREATE INDEX children_assignment_child_idx ON children_class_assignments(child_id, class_id);
CREATE TABLE children_guardian_authorities (
    id UUID PRIMARY KEY, child_id UUID NOT NULL REFERENCES people_persons(id), guardian_id UUID NOT NULL REFERENCES people_persons(id),
    valid_from TIMESTAMPTZ NOT NULL, valid_until TIMESTAMPTZ NOT NULL CHECK (valid_until > valid_from),
    evidence_reference VARCHAR(255) NOT NULL, verified_by UUID NOT NULL,
    revoked_at TIMESTAMPTZ, revocation_reason VARCHAR(500), version BIGINT NOT NULL DEFAULT 0,
    CHECK (child_id <> guardian_id), UNIQUE (child_id, id)
);
CREATE INDEX children_authority_child_idx ON children_guardian_authorities(child_id);
CREATE TABLE children_consents (
    id UUID PRIMARY KEY, programme_id UUID NOT NULL, policy_id UUID NOT NULL, child_id UUID NOT NULL, authority_id UUID NOT NULL,
    valid_from TIMESTAMPTZ NOT NULL, valid_until TIMESTAMPTZ NOT NULL CHECK (valid_until > valid_from),
    accepted_at TIMESTAMPTZ NOT NULL, recorded_by UUID NOT NULL, evidence_reference VARCHAR(255) NOT NULL,
    revoked_at TIMESTAMPTZ, revocation_reason VARCHAR(500), version BIGINT NOT NULL DEFAULT 0,
    FOREIGN KEY (programme_id, policy_id) REFERENCES children_consent_policies(programme_id, id),
    FOREIGN KEY (child_id, authority_id) REFERENCES children_guardian_authorities(child_id, id)
);
CREATE INDEX children_consent_child_idx ON children_consents(child_id, programme_id, policy_id);
CREATE TABLE children_audit (
    id UUID PRIMARY KEY, actor_id UUID NOT NULL, action VARCHAR(80) NOT NULL, target_id UUID NOT NULL,
    reason VARCHAR(500) NOT NULL DEFAULT '', occurred_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX children_audit_target_idx ON children_audit(target_id, occurred_at);
-- [jooq ignore start]
ALTER TABLE children_enrolments ADD CONSTRAINT children_enrolment_no_overlap
    EXCLUDE USING gist (programme_id WITH =, child_id WITH =, daterange(valid_from, valid_until, '[)') WITH &&) WHERE (ended_at IS NULL);
ALTER TABLE children_class_assignments ADD CONSTRAINT children_assignment_no_overlap
    EXCLUDE USING gist (enrolment_id WITH =, daterange(valid_from, valid_until, '[)') WITH &&) WHERE (ended_at IS NULL);
-- [jooq ignore stop]
INSERT INTO permissions (id, code, description) VALUES
 ('00000000-0000-0000-0000-000000000010', 'children.read', 'Read children programme administration'),
 ('00000000-0000-0000-0000-000000000011', 'children.manage', 'Manage children programmes and participation'),
 ('00000000-0000-0000-0000-000000000012', 'children.safeguarding', 'Verify guardian authority, consent and pickup grants'),
 ('00000000-0000-0000-0000-000000000013', 'children.station', 'Operate assigned children sessions'),
 ('00000000-0000-0000-0000-000000000014', 'children.supervise', 'Supervise sessions, transfers and lost-label handovers'),
 ('00000000-0000-0000-0000-000000000015', 'children.support', 'Read and maintain restricted child support records');
INSERT INTO role_permissions(role_id, permission_id)
 SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name = 'administrator' AND p.code LIKE 'children.%';
