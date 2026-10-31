-- Supports member-scoped guardian reads without granting authority through households.
CREATE INDEX children_authority_guardian_scope_idx
    ON children_guardian_authorities (guardian_id, child_id, valid_from, valid_until)
    WHERE revoked_at IS NULL;

-- Keep the session's local calendar date explicit for dated enrolment and class placement.
CREATE VIEW children_member_session_dates AS
SELECT session.id AS session_id,
       CAST(pg_catalog.timezone(location.time_zone, session.starts_at) AS DATE) AS on_date
FROM children_sessions session
JOIN children_rooms room ON room.id = session.room_id
JOIN children_locations location ON location.id = room.location_id;
