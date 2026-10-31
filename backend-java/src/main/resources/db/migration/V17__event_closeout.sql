CREATE TABLE event_closeouts (
    occurrence_id UUID PRIMARY KEY REFERENCES event_occurrences(id),
    status VARCHAR(10) NOT NULL CHECK (status IN ('OPEN', 'CLOSED')),
    version BIGINT NOT NULL CHECK (version > 0),
    note VARCHAR(2000) NOT NULL,
    follow_up VARCHAR(2000) NOT NULL,
    review_hash CHAR(64) NOT NULL,
    updated_by UUID NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL
);
CREATE TABLE event_closeout_history (
    id UUID PRIMARY KEY,
    occurrence_id UUID NOT NULL REFERENCES event_occurrences(id),
    version BIGINT NOT NULL,
    action VARCHAR(20) NOT NULL,
    note VARCHAR(2000) NOT NULL,
    follow_up VARCHAR(2000) NOT NULL,
    review_hash CHAR(64) NOT NULL,
    attended BIGINT NOT NULL,
    absent BIGINT NOT NULL,
    waitlisted BIGINT NOT NULL,
    unresolved_notices BIGINT NOT NULL,
    actor_id UUID NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL,
    UNIQUE (occurrence_id, version)
);
CREATE INDEX event_closeout_history_idx ON event_closeout_history (occurrence_id, version DESC);
