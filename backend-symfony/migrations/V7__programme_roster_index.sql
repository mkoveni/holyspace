CREATE INDEX children_enrolment_programme_roster_idx
    ON children_enrolments(programme_id, created_at DESC, id);
