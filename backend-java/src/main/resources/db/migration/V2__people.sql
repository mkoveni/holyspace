CREATE TABLE people_titles (
    code VARCHAR(20) PRIMARY KEY CHECK (code = upper(code)),
    label VARCHAR(40) NOT NULL CHECK (length(trim(label)) > 0)
);
INSERT INTO people_titles (code, label) VALUES
    ('DR', 'Dr'), ('MR', 'Mr'), ('MISS', 'Miss'), ('MRS', 'Mrs'),
    ('MS', 'Ms'), ('PROF', 'Prof'), ('REV', 'Rev'), ('PS', 'Ps'),
    ('APOST', 'Apost');

CREATE TABLE people_persons (
    id UUID PRIMARY KEY,
    title_code VARCHAR(20) REFERENCES people_titles(code),
    first_name VARCHAR(100) NOT NULL CHECK (length(trim(first_name)) > 0),
    last_name VARCHAR(100) NOT NULL CHECK (length(trim(last_name)) > 0),
    date_of_birth DATE,
    gender VARCHAR(20) NOT NULL CHECK (gender IN ('MALE', 'FEMALE', 'UNKNOWN')),
    membership_type VARCHAR(30) NOT NULL DEFAULT 'VISITOR'
        CHECK (membership_type IN ('ACTIVE_MEMBER', 'REGULAR_VISITOR', 'VISITOR', 'TRANSFERRED')),
    life_status VARCHAR(20) NOT NULL DEFAULT 'UNKNOWN' CHECK (life_status IN ('LIVING', 'DECEASED', 'UNKNOWN')),
    date_of_death DATE,
    archived_at TIMESTAMPTZ,
    version BIGINT NOT NULL DEFAULT 0 CHECK (version >= 0),
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL,
    CHECK (date_of_death IS NULL OR life_status = 'DECEASED'),
    CHECK (date_of_death IS NULL OR date_of_birth IS NULL OR date_of_death >= date_of_birth)
);
CREATE INDEX people_persons_name_idx ON people_persons (last_name, first_name, id);
CREATE INDEX people_persons_birth_idx ON people_persons (date_of_birth);
CREATE TABLE people_contact_methods (
    id UUID PRIMARY KEY,
    person_id UUID NOT NULL REFERENCES people_persons(id),
    kind VARCHAR(10) NOT NULL CHECK (kind IN ('EMAIL', 'PHONE')),
    value VARCHAR(254) NOT NULL CHECK (length(trim(value)) > 0),
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL,
    ended_at TIMESTAMPTZ,
    CHECK (ended_at IS NULL OR ended_at >= created_at)
);
CREATE INDEX people_contact_person_idx ON people_contact_methods(person_id);
CREATE TABLE people_addresses (
    id UUID PRIMARY KEY,
    person_id UUID NOT NULL REFERENCES people_persons(id),
    line1 VARCHAR(200) NOT NULL CHECK (length(trim(line1)) > 0),
    line2 VARCHAR(200) NOT NULL DEFAULT '',
    city VARCHAR(100) NOT NULL,
    region VARCHAR(100) NOT NULL DEFAULT '',
    postal_code VARCHAR(20) NOT NULL DEFAULT '',
    country_code VARCHAR(2) NOT NULL CHECK (length(country_code) = 2),
    created_at TIMESTAMPTZ NOT NULL,
    ended_at TIMESTAMPTZ,
    CHECK (ended_at IS NULL OR ended_at >= created_at)
);
CREATE INDEX people_addresses_person_idx ON people_addresses(person_id);
CREATE TABLE people_households (
    id UUID PRIMARY KEY,
    name VARCHAR(160) NOT NULL CHECK (length(trim(name)) > 0),
    primary_contact_membership_id UUID,
    version BIGINT NOT NULL DEFAULT 0 CHECK (version >= 0),
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL
);
CREATE TABLE people_household_memberships (
    id UUID PRIMARY KEY,
    household_id UUID NOT NULL REFERENCES people_households(id),
    person_id UUID NOT NULL REFERENCES people_persons(id),
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    valid_from TIMESTAMPTZ NOT NULL,
    valid_to TIMESTAMPTZ,
    UNIQUE (household_id, id),
    CHECK (valid_to IS NULL OR valid_to > valid_from)
);
ALTER TABLE people_households ADD CONSTRAINT people_primary_contact_fk
    FOREIGN KEY (id, primary_contact_membership_id) REFERENCES people_household_memberships(household_id, id);
CREATE INDEX people_membership_person_idx ON people_household_memberships(person_id);
CREATE TABLE people_relationships (
    id UUID PRIMARY KEY,
    source_id UUID NOT NULL REFERENCES people_persons(id),
    target_id UUID NOT NULL REFERENCES people_persons(id),
    type VARCHAR(20) NOT NULL CHECK (type IN ('PARENT_OF', 'GUARDIAN_OF', 'SPOUSE_OF', 'SIBLING_OF', 'OTHER')),
    valid_from TIMESTAMPTZ NOT NULL,
    valid_to TIMESTAMPTZ,
    version BIGINT NOT NULL DEFAULT 0 CHECK (version >= 0),
    CHECK (source_id <> target_id),
    CHECK (valid_to IS NULL OR valid_to > valid_from),
    CHECK (type NOT IN ('SPOUSE_OF', 'SIBLING_OF', 'OTHER') OR source_id < target_id)
);
CREATE INDEX people_relationship_target_idx ON people_relationships(target_id);
CREATE TABLE people_relationship_guard (id SMALLINT PRIMARY KEY CHECK (id = 1));
INSERT INTO people_relationship_guard (id) VALUES (1);
CREATE TABLE people_audit (
    id UUID PRIMARY KEY,
    actor_id UUID NOT NULL,
    action VARCHAR(80) NOT NULL,
    target_reference VARCHAR(100) NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL
);

-- PostgreSQL temporal and partial constraints are executed by Flyway, not the H2 codegen interpreter.
-- [jooq ignore start]
CREATE EXTENSION IF NOT EXISTS btree_gist;
CREATE UNIQUE INDEX people_contact_value_unique ON people_contact_methods(person_id, kind, value) WHERE ended_at IS NULL;
CREATE UNIQUE INDEX people_contact_primary_unique ON people_contact_methods(person_id, kind) WHERE is_primary AND ended_at IS NULL;
ALTER TABLE people_household_memberships ADD CONSTRAINT people_membership_no_overlap
    EXCLUDE USING gist (household_id WITH =, person_id WITH =, tstzrange(valid_from, valid_to, '[)') WITH &&);
ALTER TABLE people_household_memberships ADD CONSTRAINT people_primary_household_no_overlap
    EXCLUDE USING gist (person_id WITH =, tstzrange(valid_from, valid_to, '[)') WITH &&) WHERE (is_primary);
ALTER TABLE people_relationships ADD CONSTRAINT people_relationship_no_overlap
    EXCLUDE USING gist (source_id WITH =, target_id WITH =, type WITH =, tstzrange(valid_from, valid_to, '[)') WITH &&);
-- [jooq ignore stop]

INSERT INTO permissions (id, code, description) VALUES
    ('00000000-0000-0000-0000-000000000003', 'people.read', 'Read people, households and relationships'),
    ('00000000-0000-0000-0000-000000000004', 'people.manage', 'Manage people, households and relationships');
INSERT INTO role_permissions (role_id, permission_id)
    SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
    WHERE r.name = 'administrator' AND p.code IN ('people.read', 'people.manage');
