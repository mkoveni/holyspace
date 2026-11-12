-- Visitor self check-in: a per-occurrence link switch, and what each visitor submitted at the door.
CREATE TABLE event_visit_links (
    occurrence_id UUID PRIMARY KEY REFERENCES event_occurrences(id),
    enabled BOOLEAN NOT NULL,
    generation INT NOT NULL CHECK (generation >= 1),
    updated_by UUID NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL
);
CREATE TABLE event_visitor_check_ins (
    registration_id UUID PRIMARY KEY REFERENCES event_registrations(id),
    occurrence_id UUID NOT NULL REFERENCES event_occurrences(id),
    full_name VARCHAR(201) NOT NULL,
    mobile VARCHAR(40),
    email VARCHAR(254),
    how_heard VARCHAR(40),
    party_adults SMALLINT CHECK (party_adults IS NULL OR party_adults BETWEEN 1 AND 20),
    first_time BOOLEAN NOT NULL,
    consent_version VARCHAR(40) NOT NULL,
    consented_at TIMESTAMPTZ NOT NULL,
    review_status VARCHAR(10) NOT NULL CHECK (review_status IN ('PENDING', 'CONFIRMED', 'REMOVED')),
    reviewed_by UUID,
    reviewed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL,
    CHECK (mobile IS NOT NULL OR email IS NOT NULL)
);
CREATE INDEX event_visitor_occurrence_idx ON event_visitor_check_ins(occurrence_id, created_at, registration_id);
INSERT INTO permissions(id, code, description) VALUES
    ('00000000-0000-0000-0000-000000000049', 'events.visitors.read', 'See the contact details of first-time visitors who checked themselves in');
INSERT INTO role_permissions(role_id, permission_id)
    SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name = 'administrator' AND p.code = 'events.visitors.read';
