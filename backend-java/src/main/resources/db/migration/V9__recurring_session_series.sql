CREATE TABLE children_session_series (
    id UUID PRIMARY KEY, name VARCHAR(120) NOT NULL,
    source_session_id UUID NOT NULL REFERENCES children_sessions(id),
    class_id UUID NOT NULL REFERENCES children_classes(id), room_id UUID NOT NULL REFERENCES children_rooms(id),
    seat_limit INTEGER NOT NULL CHECK (seat_limit > 0), time_zone VARCHAR(80) NOT NULL,
    first_date DATE NOT NULL, starts_at TIME NOT NULL, ends_at TIME NOT NULL CHECK (ends_at > starts_at),
    interval_weeks INTEGER NOT NULL CHECK (interval_weeks BETWEEN 1 AND 4),
    occurrences INTEGER NOT NULL CHECK (occurrences BETWEEN 1 AND 52),
    excluded_dates DATE[] NOT NULL, created_at TIMESTAMPTZ NOT NULL, created_by UUID NOT NULL,
    request_id UUID NOT NULL, request_fingerprint TEXT NOT NULL,
    stopped BOOLEAN NOT NULL DEFAULT FALSE, version BIGINT NOT NULL DEFAULT 0,
    UNIQUE (created_by, request_id)
);
CREATE INDEX children_session_series_created_idx ON children_session_series(created_at DESC, id);
CREATE TABLE children_series_sessions (
    series_id UUID NOT NULL REFERENCES children_session_series(id),
    session_id UUID NOT NULL UNIQUE REFERENCES children_sessions(id), scheduled_date DATE NOT NULL,
    PRIMARY KEY (series_id, scheduled_date)
);
CREATE TABLE children_scheduling_guard (id BOOLEAN PRIMARY KEY CHECK (id));
INSERT INTO children_scheduling_guard(id) VALUES (TRUE);
