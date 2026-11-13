-- Public and internal forms and quizzes (docs/public-events-and-forms-design.md section 7).
-- The privacy guarantees of anonymous forms are enforced here, not by convention.
CREATE TABLE forms (
    id UUID PRIMARY KEY,
    title VARCHAR(160) NOT NULL CHECK (btrim(title) <> ''),
    description VARCHAR(2000) NOT NULL DEFAULT '',
    kind VARCHAR(20) NOT NULL CHECK (kind IN ('DATA_COLLECTION', 'QUIZ')),
    audience VARCHAR(10) NOT NULL CHECK (audience IN ('PUBLIC', 'MEMBERS', 'STAFF', 'GROUPS')),
    identity_mode VARCHAR(10) NOT NULL CHECK (identity_mode IN ('ANONYMOUS', 'IDENTIFIED')),
    status VARCHAR(10) NOT NULL DEFAULT 'DRAFT' CHECK (status IN ('DRAFT', 'PUBLISHED', 'CLOSED')),
    public_token_hash CHAR(64) UNIQUE,
    opens_at TIMESTAMPTZ,
    closes_at TIMESTAMPTZ,
    max_responses INTEGER CHECK (max_responses IS NULL OR max_responses > 0),
    retention_months INTEGER NOT NULL DEFAULT 3 CHECK (retention_months BETWEEN 1 AND 3),
    leaderboard_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    leaderboard_size INTEGER NOT NULL DEFAULT 10 CHECK (leaderboard_size BETWEEN 1 AND 100),
    show_score BOOLEAN NOT NULL DEFAULT FALSE,
    show_answers BOOLEAN NOT NULL DEFAULT FALSE,
    current_version INTEGER NOT NULL DEFAULT 0 CHECK (current_version >= 0),
    draft_definition JSONB NOT NULL DEFAULT '{"questions": []}'::jsonb,
    owner_account_id UUID NOT NULL,
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL,
    version BIGINT NOT NULL DEFAULT 0,
    CHECK (audience <> 'PUBLIC' OR identity_mode = 'ANONYMOUS'),
    CHECK (closes_at IS NULL OR opens_at IS NULL OR closes_at > opens_at),
    CHECK (kind = 'QUIZ' OR (NOT leaderboard_enabled AND NOT show_score AND NOT show_answers)),
    CHECK (status = 'DRAFT' OR current_version >= 1),
    UNIQUE (id, identity_mode)
);
CREATE INDEX forms_status_idx ON forms (status, audience);

CREATE TABLE form_versions (
    form_id UUID NOT NULL REFERENCES forms (id),
    version INTEGER NOT NULL CHECK (version >= 1),
    definition JSONB NOT NULL,
    published_at TIMESTAMPTZ NOT NULL,
    PRIMARY KEY (form_id, version)
);

CREATE TABLE form_audience_groups (
    form_id UUID NOT NULL REFERENCES forms (id),
    community_id UUID NOT NULL,
    PRIMARY KEY (form_id, community_id)
);

CREATE TABLE form_responses (
    id UUID PRIMARY KEY,
    form_id UUID NOT NULL,
    form_version INTEGER NOT NULL,
    identity_mode VARCHAR(10) NOT NULL,
    submission_key CHAR(64) NOT NULL,
    answers JSONB NOT NULL,
    score INTEGER CHECK (score IS NULL OR score >= 0),
    max_score INTEGER CHECK (max_score IS NULL OR max_score >= 0),
    nickname VARCHAR(40),
    respondent_person_id UUID,
    respondent_account_id UUID,
    leaderboard_hidden BOOLEAN NOT NULL DEFAULT FALSE,
    submitted_on DATE NOT NULL,
    submitted_at TIMESTAMPTZ,
    expires_on DATE NOT NULL,
    FOREIGN KEY (form_id, identity_mode) REFERENCES forms (id, identity_mode),
    FOREIGN KEY (form_id, form_version) REFERENCES form_versions (form_id, version),
    UNIQUE (form_id, submission_key),
    -- An anonymous form cannot physically hold a respondent or a time of day.
    CHECK (identity_mode = 'IDENTIFIED' OR (respondent_person_id IS NULL AND respondent_account_id IS NULL AND submitted_at IS NULL)),
    CHECK (identity_mode = 'ANONYMOUS' OR submitted_at IS NOT NULL),
    CHECK (expires_on > submitted_on),
    CHECK (score IS NULL OR max_score IS NULL OR score <= max_score)
);
CREATE INDEX form_responses_form_idx ON form_responses (form_id, submitted_on);
-- One response per signed-in account on an identified form (assumption A6).
CREATE UNIQUE INDEX form_responses_account_uq ON form_responses (form_id, respondent_account_id) WHERE respondent_account_id IS NOT NULL;
CREATE INDEX form_responses_expiry_idx ON form_responses (expires_on);
CREATE INDEX form_responses_board_idx ON form_responses (form_id, score DESC, submitted_on, id) WHERE score IS NOT NULL AND NOT leaderboard_hidden;

-- HMAC(secret, form_id | account_id) for signed-in anonymous forms. It has no response id and no timestamp, so a mark
-- cannot be joined to the response it allowed.
CREATE TABLE form_participation_marks (
    form_id UUID NOT NULL REFERENCES forms (id),
    mark_hash CHAR(64) NOT NULL,
    PRIMARY KEY (form_id, mark_hash)
);

CREATE TABLE forms_audit (
    id UUID PRIMARY KEY,
    form_id UUID NOT NULL,
    action VARCHAR(40) NOT NULL,
    detail VARCHAR(500) NOT NULL DEFAULT '',
    actor_account_id UUID,
    created_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX forms_audit_form_idx ON forms_audit (form_id, created_at);

-- Audience and identity mode are what respondents were promised: they cannot change once a response exists.
CREATE FUNCTION forms_guard_promise() RETURNS trigger AS $$
BEGIN
    IF (NEW.audience <> OLD.audience OR NEW.identity_mode <> OLD.identity_mode OR NEW.kind <> OLD.kind)
       AND EXISTS (SELECT 1 FROM form_responses WHERE form_id = OLD.id) THEN
        RAISE EXCEPTION 'A form with responses cannot change its audience, kind or identity mode' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER forms_guard_promise BEFORE UPDATE ON forms FOR EACH ROW EXECUTE FUNCTION forms_guard_promise();

-- Published versions are immutable.
CREATE FUNCTION forms_reject_version_change() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'Published form versions cannot be changed' USING ERRCODE = '23514';
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER form_versions_immutable BEFORE UPDATE ON form_versions FOR EACH ROW EXECUTE FUNCTION forms_reject_version_change();

INSERT INTO permissions(id, code, description) VALUES
    ('00000000-0000-0000-0000-00000000004a', 'forms.read', 'See form definitions'),
    ('00000000-0000-0000-0000-00000000004b', 'forms.manage', 'Create, publish and close forms and quizzes'),
    ('00000000-0000-0000-0000-00000000004c', 'forms.responses.read', 'Read, summarise and export form responses'),
    ('00000000-0000-0000-0000-00000000004d', 'forms.responses.moderate', 'Hide leaderboard entries'),
    ('00000000-0000-0000-0000-00000000004e', 'forms.respond', 'Answer forms meant for staff');
INSERT INTO role_permissions(role_id, permission_id)
    SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
    WHERE r.name = 'administrator' AND p.code IN ('forms.read', 'forms.manage', 'forms.responses.read', 'forms.responses.moderate', 'forms.respond');
