CREATE TABLE event_venues (
    id UUID PRIMARY KEY, name VARCHAR(150) NOT NULL, address VARCHAR(1000) NOT NULL,
    exclusive BOOLEAN NOT NULL, active BOOLEAN NOT NULL, version BIGINT NOT NULL CHECK(version>=0)
);
CREATE TABLE event_definitions (
    id UUID PRIMARY KEY, title VARCHAR(200) NOT NULL, type VARCHAR(30) NOT NULL CHECK(type IN ('SERVICE','MEETING','CONFERENCE','OUTREACH','CLASS','OTHER')),
    description VARCHAR(5000) NOT NULL DEFAULT '', active BOOLEAN NOT NULL, version BIGINT NOT NULL CHECK(version>=0)
);
CREATE INDEX event_definition_title_idx ON event_definitions(title,id);
CREATE TABLE event_series (
    id UUID PRIMARY KEY, event_id UUID NOT NULL REFERENCES event_definitions(id), time_zone VARCHAR(80) NOT NULL,
    frequency VARCHAR(20) NOT NULL CHECK(frequency IN ('ONCE','DAILY','WEEKLY','MONTHLY')),
    repeat_interval INTEGER NOT NULL CHECK(repeat_interval BETWEEN 1 AND 52), occurrence_count INTEGER NOT NULL CHECK(occurrence_count BETWEEN 1 AND 104),
    first_date DATE NOT NULL, starts_at TIME NOT NULL, ends_at TIME NOT NULL, end_day_offset INTEGER NOT NULL CHECK(end_day_offset BETWEEN 0 AND 7),
    venue_id UUID NOT NULL REFERENCES event_venues(id), version BIGINT NOT NULL CHECK(version>=0), UNIQUE(event_id,id)
);
CREATE TABLE event_occurrences (
    id UUID PRIMARY KEY, event_id UUID NOT NULL REFERENCES event_definitions(id), series_id UUID NOT NULL,
    origin_date DATE NOT NULL, time_zone VARCHAR(80) NOT NULL, starts_at TIMESTAMPTZ NOT NULL, ends_at TIMESTAMPTZ NOT NULL,
    venue_id UUID NOT NULL REFERENCES event_venues(id), venue_name VARCHAR(150) NOT NULL, venue_address VARCHAR(1000) NOT NULL,
    exclusive BOOLEAN NOT NULL, status VARCHAR(20) NOT NULL CHECK(status IN ('SCHEDULED','CANCELLED')), overridden BOOLEAN NOT NULL,
    version BIGINT NOT NULL CHECK(version>=0), schedule_revision BIGINT NOT NULL CHECK(schedule_revision>=0),
    FOREIGN KEY(event_id,series_id) REFERENCES event_series(event_id,id), UNIQUE(series_id,origin_date), CHECK(ends_at>starts_at)
);
CREATE INDEX event_occurrence_calendar_idx ON event_occurrences(starts_at,id);
CREATE INDEX event_occurrence_event_idx ON event_occurrences(event_id,starts_at,id);
CREATE INDEX event_occurrence_venue_idx ON event_occurrences(venue_id,starts_at,ends_at);
-- [jooq ignore start]
ALTER TABLE event_occurrences ADD CONSTRAINT event_venue_schedule_no_overlap
    EXCLUDE USING gist (venue_id WITH =,tstzrange(starts_at,ends_at,'[)') WITH &&)
    WHERE (status='SCHEDULED' AND exclusive) DEFERRABLE INITIALLY DEFERRED;
-- [jooq ignore stop]
CREATE TABLE event_registration_policies (
    occurrence_id UUID PRIMARY KEY REFERENCES event_occurrences(id), open BOOLEAN NOT NULL DEFAULT FALSE,
    capacity INTEGER CHECK(capacity BETWEEN 1 AND 10000), waitlist_enabled BOOLEAN NOT NULL,
    next_position BIGINT NOT NULL CHECK(next_position>=0), version BIGINT NOT NULL CHECK(version>=0)
);
CREATE TABLE event_registrations (
    id UUID PRIMARY KEY, occurrence_id UUID NOT NULL REFERENCES event_occurrences(id), person_id UUID NOT NULL,
    status VARCHAR(20) NOT NULL CHECK(status IN ('CONFIRMED','WAITLISTED','CANCELLED')), queue_position BIGINT NOT NULL CHECK(queue_position>0),
    version BIGINT NOT NULL CHECK(version>=0),created_at TIMESTAMPTZ NOT NULL,updated_at TIMESTAMPTZ NOT NULL,
    UNIQUE(occurrence_id,person_id),UNIQUE(occurrence_id,queue_position)
);
CREATE INDEX event_registration_queue_idx ON event_registrations(occurrence_id,status,queue_position);
CREATE TABLE event_registration_history (
    id UUID PRIMARY KEY,registration_id UUID NOT NULL REFERENCES event_registrations(id),actor_id UUID NOT NULL,
    action VARCHAR(40) NOT NULL,reason VARCHAR(1000) NOT NULL,occurred_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX event_registration_history_idx ON event_registration_history(registration_id,occurred_at,id);
CREATE TABLE event_occurrence_history (
    id UUID PRIMARY KEY,occurrence_id UUID NOT NULL REFERENCES event_occurrences(id),actor_id UUID NOT NULL,
    action VARCHAR(40) NOT NULL,reason VARCHAR(1000) NOT NULL,starts_at TIMESTAMPTZ NOT NULL,ends_at TIMESTAMPTZ NOT NULL,
    venue_id UUID NOT NULL REFERENCES event_venues(id),venue_name VARCHAR(150) NOT NULL,venue_address VARCHAR(1000) NOT NULL,
    status VARCHAR(20) NOT NULL,version BIGINT NOT NULL,schedule_revision BIGINT NOT NULL,occurred_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX event_occurrence_history_idx ON event_occurrence_history(occurrence_id,occurred_at,id);
CREATE TABLE event_requests (
    id UUID PRIMARY KEY,kind VARCHAR(40) NOT NULL,request_hash CHAR(64) NOT NULL,target_id UUID NOT NULL,actor_id UUID NOT NULL,created_at TIMESTAMPTZ NOT NULL
);
CREATE TABLE event_audit (
    id UUID PRIMARY KEY,actor_id UUID NOT NULL,action VARCHAR(60) NOT NULL,target_id UUID NOT NULL,reason VARCHAR(1000) NOT NULL,occurred_at TIMESTAMPTZ NOT NULL
);
INSERT INTO permissions(id,code,description) VALUES
 ('00000000-0000-0000-0000-000000000027','events.read','Read events, venues and calendar'),
 ('00000000-0000-0000-0000-000000000028','events.manage','Manage event scheduling and venues'),
 ('00000000-0000-0000-0000-000000000029','events.registrations.read','Read event registrations'),
 ('00000000-0000-0000-0000-000000000030','events.registrations.manage','Manage event registrations');
INSERT INTO role_permissions(role_id,permission_id) SELECT '00000000-0000-0000-0000-000000000001',id FROM permissions WHERE code IN ('events.read','events.manage','events.registrations.read','events.registrations.manage');

CREATE INDEX serving_shifts_source_idx ON serving_shifts(source_kind,source_id,starts_at,id);
