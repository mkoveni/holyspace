CREATE TABLE children_attendance (
    id UUID PRIMARY KEY, child_id UUID NOT NULL REFERENCES people_persons(id), session_id UUID NOT NULL REFERENCES children_sessions(id),
    arrived_at TIMESTAMPTZ NOT NULL, arrived_by UUID NOT NULL, arrival_request_id UUID NOT NULL,
    checked_out_at TIMESTAMPTZ, checked_out_by UUID, collector_id UUID REFERENCES people_persons(id),
    checkout_request_id UUID, authorization_id UUID REFERENCES children_pickup_authorizations(id), identity_verified BOOLEAN NOT NULL DEFAULT FALSE,
    exception_reason VARCHAR(500), label_hash CHAR(64), label_version INTEGER NOT NULL DEFAULT 0,
    label_expires_at TIMESTAMPTZ, failed_attempts INTEGER NOT NULL DEFAULT 0, attempts_reset_at TIMESTAMPTZ,
    version BIGINT NOT NULL DEFAULT 0,
    UNIQUE (arrived_by, arrival_request_id),
    CHECK (checked_out_at IS NULL OR checked_out_at >= arrived_at),
    CHECK (checked_out_at IS NULL OR (collector_id IS NOT NULL AND checked_out_by IS NOT NULL AND authorization_id IS NOT NULL AND identity_verified)),
    CHECK (failed_attempts >= 0 AND label_version >= 0 AND version >= 0)
);
CREATE INDEX children_attendance_session_idx ON children_attendance(session_id, checked_out_at, id);
CREATE INDEX children_attendance_history_idx ON children_attendance(child_id, arrived_at, id);
CREATE TABLE children_attendance_segments (
    id UUID PRIMARY KEY, attendance_id UUID NOT NULL REFERENCES children_attendance(id), session_id UUID NOT NULL REFERENCES children_sessions(id),
    enrolment_id UUID NOT NULL REFERENCES children_enrolments(id), assignment_id UUID NOT NULL REFERENCES children_class_assignments(id),
    consent_id UUID NOT NULL REFERENCES children_consents(id), arrived_at TIMESTAMPTZ NOT NULL, left_at TIMESTAMPTZ,
    CHECK (left_at IS NULL OR left_at >= arrived_at)
);
CREATE INDEX children_segments_attendance_idx ON children_attendance_segments(attendance_id, arrived_at);
CREATE TABLE children_attendance_events (
    id UUID PRIMARY KEY, attendance_id UUID NOT NULL REFERENCES children_attendance(id), actor_id UUID NOT NULL,
    type VARCHAR(40) NOT NULL, reason VARCHAR(500) NOT NULL DEFAULT '', occurred_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX children_events_attendance_idx ON children_attendance_events(attendance_id, occurred_at, id);
CREATE TABLE children_support_records (
    child_id UUID PRIMARY KEY REFERENCES people_persons(id), allergies VARCHAR(2000) NOT NULL DEFAULT '',
    care_instructions VARCHAR(2000) NOT NULL DEFAULT '', restricted_notes VARCHAR(2000) NOT NULL DEFAULT '',
    review_on DATE NOT NULL, retain_until DATE NOT NULL CHECK (retain_until >= review_on),
    version BIGINT NOT NULL DEFAULT 0, updated_at TIMESTAMPTZ NOT NULL
);
-- [jooq ignore start]
CREATE UNIQUE INDEX children_one_open_attendance ON children_attendance(child_id) WHERE checked_out_at IS NULL;
CREATE UNIQUE INDEX children_one_open_segment ON children_attendance_segments(attendance_id) WHERE left_at IS NULL;
-- [jooq ignore stop]
