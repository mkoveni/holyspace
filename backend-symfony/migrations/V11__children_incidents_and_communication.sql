CREATE TABLE children_incidents (
    id UUID PRIMARY KEY, child_id UUID NOT NULL REFERENCES people_persons(id),
    session_id UUID NOT NULL REFERENCES children_sessions(id), segment_id UUID NOT NULL REFERENCES children_attendance_segments(id),
    category VARCHAR(30) NOT NULL CHECK (category IN ('INJURY','ILLNESS','BEHAVIOUR','SAFEGUARDING','OTHER')),
    severity VARCHAR(20) NOT NULL CHECK (severity IN ('LOW','MODERATE','HIGH','CRITICAL')),
    occurred_at TIMESTAMPTZ NOT NULL, reported_by UUID NOT NULL, reported_at TIMESTAMPTZ NOT NULL,
    request_id UUID NOT NULL, request_hash CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL CHECK (status IN ('OPEN','IN_REVIEW','CLOSED')),
    owner_id UUID, review_on DATE NOT NULL, retain_until DATE NOT NULL,
    version BIGINT NOT NULL DEFAULT 0 CHECK (version >= 0),
    UNIQUE(reported_by,request_id), CHECK (occurred_at <= reported_at), CHECK (retain_until >= review_on),
    CHECK (status = 'OPEN' OR owner_id IS NOT NULL)
);
CREATE INDEX children_incident_session_idx ON children_incidents(session_id,status,id);
CREATE INDEX children_incident_followup_idx ON children_incidents(status,review_on,id);
CREATE INDEX children_incident_child_idx ON children_incidents(child_id,reported_at DESC,id);
CREATE TABLE children_incident_details (
    incident_id UUID PRIMARY KEY REFERENCES children_incidents(id),
    description VARCHAR(5000) NOT NULL, immediate_action VARCHAR(3000) NOT NULL
);
CREATE TABLE children_incident_events (
    id UUID PRIMARY KEY, incident_id UUID NOT NULL REFERENCES children_incidents(id),
    revision BIGINT NOT NULL, type VARCHAR(30) NOT NULL CHECK(type IN ('REPORTED','TRIAGED','ACTION','GUARDIAN_CONTACT','CLOSED','REOPENED')),
    actor_id UUID NOT NULL, occurred_at TIMESTAMPTZ NOT NULL,
    note VARCHAR(3000) NOT NULL, guardian_id UUID REFERENCES people_persons(id),
    UNIQUE(incident_id,revision)
);
CREATE INDEX children_incident_event_idx ON children_incident_events(incident_id,revision);
CREATE TABLE children_guardian_communications (
    id UUID PRIMARY KEY, child_id UUID NOT NULL REFERENCES people_persons(id), authority_id UUID NOT NULL REFERENCES children_guardian_authorities(id),
    guardian_id UUID NOT NULL REFERENCES people_persons(id), programme_id UUID NOT NULL REFERENCES children_programmes(id),
    policy_id UUID REFERENCES children_consent_policies(id), session_id UUID REFERENCES children_sessions(id), type VARCHAR(30) NOT NULL CHECK(type IN ('CONSENT_RENEWAL','SESSION_REMINDER')),
    message VARCHAR(3000) NOT NULL, due_on DATE NOT NULL, status VARCHAR(20) NOT NULL CHECK(status IN ('DRAFT','CONTACTED','CANCELLED')),
    created_by UUID NOT NULL, created_at TIMESTAMPTZ NOT NULL, request_id UUID NOT NULL, request_hash CHAR(64) NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL, updated_by UUID NOT NULL, contact_reference VARCHAR(500) NOT NULL DEFAULT '',
    version BIGINT NOT NULL DEFAULT 0 CHECK(version >= 0), UNIQUE(created_by,request_id),
    CHECK((type = 'SESSION_REMINDER') = (session_id IS NOT NULL))
);
CREATE INDEX children_communication_queue_idx ON children_guardian_communications(status,due_on,id);
INSERT INTO permissions(id,code,description) VALUES
 ('00000000-0000-0000-0000-000000000016','children.incidents.record','Report incidents within assigned sessions'),
 ('00000000-0000-0000-0000-000000000017','children.incidents.manage','Access restricted incidents and manage follow-up');
INSERT INTO role_permissions(role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
 WHERE r.name = 'administrator' AND p.code IN ('children.incidents.record','children.incidents.manage');
