CREATE TABLE event_member_offers (
    event_id UUID PRIMARY KEY REFERENCES event_definitions(id),
    enabled BOOLEAN NOT NULL,
    version BIGINT NOT NULL CHECK (version > 0)
);
CREATE TABLE event_admission_passes (
    token_hash VARCHAR(64) PRIMARY KEY,
    registration_id UUID NOT NULL REFERENCES event_registrations(id),
    account_id UUID NOT NULL REFERENCES user_accounts(id),
    session_id UUID NOT NULL,
    member_version BIGINT NOT NULL,
    registration_version BIGINT NOT NULL,
    schedule_revision BIGINT NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    used_at TIMESTAMPTZ
);
CREATE INDEX event_admission_pass_expiry ON event_admission_passes(expires_at);
