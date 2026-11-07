CREATE INDEX children_attendance_visit_reporting_idx
    ON children_attendance_segments(arrived_at DESC, id);
CREATE INDEX children_attendance_session_visits_idx
    ON children_attendance_segments(session_id, arrived_at, id);
