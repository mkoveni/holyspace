CREATE TABLE children_locations (
    id UUID PRIMARY KEY, name VARCHAR(120) NOT NULL, time_zone VARCHAR(80) NOT NULL
);
CREATE TABLE children_rooms (
    id UUID PRIMARY KEY, location_id UUID NOT NULL REFERENCES children_locations(id), name VARCHAR(120) NOT NULL,
    capacity INTEGER NOT NULL CHECK (capacity > 0), UNIQUE (location_id, id)
);
CREATE TABLE children_workers (
    person_id UUID PRIMARY KEY REFERENCES people_persons(id), account_id UUID NOT NULL UNIQUE,
    verified_from TIMESTAMPTZ NOT NULL, screened_until TIMESTAMPTZ NOT NULL, trained_until TIMESTAMPTZ NOT NULL,
    evidence_reference VARCHAR(255) NOT NULL, verified_by UUID NOT NULL, revoked_at TIMESTAMPTZ,
    version BIGINT NOT NULL DEFAULT 0,
    CHECK (screened_until > verified_from AND trained_until > verified_from)
);
CREATE TABLE children_sessions (
    id UUID PRIMARY KEY, class_id UUID NOT NULL REFERENCES children_classes(id), room_id UUID NOT NULL REFERENCES children_rooms(id),
    starts_at TIMESTAMPTZ NOT NULL, ends_at TIMESTAMPTZ NOT NULL CHECK (ends_at > starts_at),
    seat_limit INTEGER NOT NULL CHECK (seat_limit > 0),
    status VARCHAR(20) NOT NULL DEFAULT 'PLANNED' CHECK (status IN ('PLANNED','OPEN','NEEDS_REVIEW','CLOSED','CANCELLED')),
    version BIGINT NOT NULL DEFAULT 0
);
CREATE INDEX children_session_schedule_idx ON children_sessions(starts_at, id);
CREATE TABLE children_session_staff (
    session_id UUID NOT NULL REFERENCES children_sessions(id), worker_id UUID NOT NULL REFERENCES children_workers(person_id),
    role VARCHAR(20) NOT NULL CHECK (role IN ('LEADER','ASSISTANT')), present BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (session_id, worker_id)
);
-- [jooq ignore start]
ALTER TABLE children_sessions ADD CONSTRAINT children_room_schedule_no_overlap
    EXCLUDE USING gist (room_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&) WHERE (status <> 'CANCELLED');
ALTER TABLE children_sessions ADD CONSTRAINT children_class_schedule_no_overlap
    EXCLUDE USING gist (class_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&) WHERE (status <> 'CANCELLED');
-- [jooq ignore stop]
